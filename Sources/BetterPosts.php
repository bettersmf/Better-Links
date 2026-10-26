<?php

/**
 * Better Links
 *
 * Link preview enhancement for SMF 2.1.x.
 *
 * dev3 resets the project around one purpose: safe, cached link previews.
 * SMF's native mentions system is intentionally left entirely to SMF.
 *
 * @package BetterLinks
 * @version 1.0.4
 */

if (!defined('SMF'))
    die('No direct access...');

/**
 * Load Better Links language strings.
 */
function betterposts_load_language()
{
    loadLanguage('BetterPosts');
}

/**
 * Inspect the PHP cURL runtime before Better Links attempts any preview work.
 *
 * Link previews require:
 * - PHP cURL extension loaded
 * - curl_init / curl_version
 * - HTTP and HTTPS protocol support
 * - SSL/TLS support for HTTPS verification
 */
function betterposts_curl_capabilities()
{
    static $status = null;

    if ($status !== null)
        return $status;

    $status = array(
        'available' => false,
        'extension_loaded' => extension_loaded('curl'),
        'curl_init' => function_exists('curl_init'),
        'curl_version' => '',
        'ssl_version' => '',
        'http' => false,
        'https' => false,
        'reason' => '',
    );

    if (!$status['extension_loaded'])
    {
        $status['reason'] = 'The PHP cURL extension is not loaded.';
        return $status;
    }

    if (!$status['curl_init'] || !function_exists('curl_version'))
    {
        $status['reason'] = 'The PHP cURL functions required by Better Links are unavailable.';
        return $status;
    }

    $info = @curl_version();

    if (!is_array($info))
    {
        $status['reason'] = 'PHP cURL is present, but its capabilities could not be read.';
        return $status;
    }

    $status['curl_version'] = !empty($info['version']) ? (string)$info['version'] : '';
    $status['ssl_version'] = !empty($info['ssl_version']) ? (string)$info['ssl_version'] : '';

    $protocols = !empty($info['protocols']) && is_array($info['protocols'])
        ? array_map('strtolower', $info['protocols'])
        : array();

    $status['http'] = in_array('http', $protocols, true);
    $status['https'] = in_array('https', $protocols, true);

    if (!$status['http'] || !$status['https'])
    {
        $status['reason'] = 'PHP cURL must support both HTTP and HTTPS.';
        return $status;
    }

    if ($status['ssl_version'] === '')
    {
        $status['reason'] = 'PHP cURL does not report SSL/TLS support required for HTTPS validation.';
        return $status;
    }

    $status['available'] = true;
    $status['reason'] = 'cURL is available with HTTP, HTTPS and SSL/TLS support.';

    return $status;
}

/**
 * Read and clamp an integer Better Links setting.
 */
function betterposts_setting_int($key, $default, $min, $max)
{
    global $modSettings;

    $value = isset($modSettings[$key]) ? (int)$modSettings[$key] : (int)$default;

    if ($value < $min)
        $value = $min;
    elseif ($value > $max)
        $value = $max;

    return $value;
}

function betterposts_runtime_settings()
{
    $connect = betterposts_setting_int('betterposts_connect_timeout', 5, 1, 15);
    $total = betterposts_setting_int('betterposts_total_timeout', 15, 3, 45);

    if ($total < $connect)
        $total = $connect;

    return array(
        'cache_days' => betterposts_setting_int('betterposts_cache_days', 7, 1, 90),
        'connect_timeout' => $connect,
        'total_timeout' => $total,
        'max_html_kb' => betterposts_setting_int('betterposts_max_html_kb', 1536, 256, 4096),
        'max_image_kb' => betterposts_setting_int('betterposts_max_image_kb', 2048, 128, 8192),
    );
}

function betterposts_cache_stats()
{
    global $smcFunc, $boarddir;

    $stats = array(
        'total' => 0,
        'ready' => 0,
        'failed' => 0,
        'expired' => 0,
        'images' => 0,
        'image_bytes' => 0,
    );

    $request = $smcFunc['db_query']('', '
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = {string:ready} THEN 1 ELSE 0 END) AS ready,
            SUM(CASE WHEN status = {string:failed} THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN expires_at <= {int:now} THEN 1 ELSE 0 END) AS expired
        FROM {db_prefix}betterposts_link_previews',
        array(
            'ready' => 'ready',
            'failed' => 'failed',
            'now' => time(),
        )
    );

    if ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $stats['total'] = (int)$row['total'];
        $stats['ready'] = (int)$row['ready'];
        $stats['failed'] = (int)$row['failed'];
        $stats['expired'] = (int)$row['expired'];
    }

    $smcFunc['db_free_result']($request);

    $cache_dir = $boarddir . '/betterposts_cache';

    if (is_dir($cache_dir))
    {
        foreach (glob($cache_dir . '/*') as $file)
        {
            if (!is_file($file) || basename($file) === 'index.html')
                continue;

            $stats['images']++;
            $size = @filesize($file);

            if ($size !== false)
                $stats['image_bytes'] += (int)$size;
        }
    }

    return $stats;
}

function betterposts_expire_preview_cache()
{
    global $smcFunc;

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}betterposts_link_previews
        SET expires_at = {int:expired}',
        array('expired' => 0)
    );
}

function betterposts_clear_cached_images()
{
    global $smcFunc, $boarddir;

    $cache_dir = $boarddir . '/betterposts_cache';

    if (is_dir($cache_dir))
    {
        foreach (glob($cache_dir . '/*') as $file)
        {
            if (is_file($file) && basename($file) !== 'index.html')
                @unlink($file);
        }
    }

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}betterposts_link_previews
        SET image_path = {string:blank},
            expires_at = {int:expired}',
        array(
            'blank' => '',
            'expired' => 0,
        )
    );
}

function betterposts_format_bytes($bytes)
{
    $bytes = max(0, (int)$bytes);
    $units = array('B', 'KB', 'MB', 'GB');
    $value = (float)$bytes;
    $unit = 0;

    while ($value >= 1024 && $unit < count($units) - 1)
    {
        $value /= 1024;
        $unit++;
    }

    return ($unit === 0 ? (string)(int)$value : number_format($value, 1)) . ' ' . $units[$unit];
}

/**
 * Load one cached preview row by ID.
 */
function betterposts_get_preview_row($preview_id)
{
    global $smcFunc;

    $preview_id = (int)$preview_id;

    if ($preview_id <= 0)
        return false;

    $request = $smcFunc['db_query']('', '
        SELECT id_preview, url, final_url, domain, site_name, title, description,
            image_path, status, fetched_at, expires_at, error_text
        FROM {db_prefix}betterposts_link_previews
        WHERE id_preview = {int:id_preview}
        LIMIT 1',
        array('id_preview' => $preview_id)
    );

    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    return !empty($row) ? $row : false;
}

/**
 * Safely delete one Better Links cached-image file.
 */
function betterposts_delete_cached_image_path($image_path)
{
    global $boarddir;

    $image_path = trim((string)$image_path);

    if (
        $image_path === ''
        || !preg_match('~^betterposts_cache/[a-f0-9]{64}\.(?:jpg|png|gif|webp|ico)$~i', $image_path)
    )
        return false;

    $file = $boarddir . '/' . $image_path;

    return !is_file($file) ? true : @unlink($file);
}

/**
 * Mark one preview stale without deleting its row.
 *
 * Existing posts store preview IDs, so the manager deliberately never deletes
 * rows. Expiring preserves old posts and lets the next matching post refresh it.
 */
function betterposts_expire_preview($preview_id)
{
    global $smcFunc;

    $preview_id = (int)$preview_id;

    if ($preview_id <= 0)
        return false;

    $smcFunc['db_query']('', '
        UPDATE {db_prefix}betterposts_link_previews
        SET expires_at = {int:expired}
        WHERE id_preview = {int:id_preview}',
        array(
            'expired' => 0,
            'id_preview' => $preview_id,
        )
    );

    return true;
}

/**
 * Force-refresh one cached preview in place.
 */
function betterposts_refresh_preview($preview_id)
{
    $before = betterposts_get_preview_row($preview_id);

    if (empty($before) || empty($before['url']))
        return false;

    if (!betterposts_expire_preview($preview_id))
        return false;

    $returned_id = betterposts_get_or_fetch_preview($before['url']);

    if ((int)$returned_id !== (int)$preview_id)
        return false;

    $after = betterposts_get_preview_row($preview_id);

    if (
        !empty($before['image_path'])
        && (empty($after['image_path']) || $after['image_path'] !== $before['image_path'])
    )
        betterposts_delete_cached_image_path($before['image_path']);

    return !empty($after);
}

/**
 * Fetch the paged/filterable Preview Manager data.
 */
function betterposts_preview_manager_data($filter = 'all', $search = '', $start = 0, $per_page = 15)
{
    global $smcFunc, $boardurl;

    $allowed = array('all', 'ready', 'expired', 'failed');
    $filter = in_array($filter, $allowed, true) ? $filter : 'all';
    $search = trim((string)$search);
    $start = max(0, (int)$start);
    $per_page = max(5, min(50, (int)$per_page));
    $now = time();

    $where = array('1=1');
    $params = array(
        'now' => $now,
        'ready' => 'ready',
        'failed' => 'failed',
    );

    if ($filter === 'ready')
        $where[] = 'status = {string:ready} AND expires_at > {int:now}';
    elseif ($filter === 'expired')
        $where[] = 'expires_at <= {int:now}';
    elseif ($filter === 'failed')
        $where[] = 'status = {string:failed}';

    if ($search !== '')
    {
        $where[] = '(
            url LIKE {string:search}
            OR final_url LIKE {string:search}
            OR domain LIKE {string:search}
            OR site_name LIKE {string:search}
            OR title LIKE {string:search}
        )';
        $params['search'] = '%' . $search . '%';
    }

    $where_sql = implode(' AND ', $where);

    $request = $smcFunc['db_query']('', '
        SELECT COUNT(*) AS total
        FROM {db_prefix}betterposts_link_previews
        WHERE ' . $where_sql,
        $params
    );

    $count_row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    $total = !empty($count_row['total']) ? (int)$count_row['total'] : 0;

    if ($total > 0 && $start >= $total)
        $start = max(0, (int)(floor(($total - 1) / $per_page) * $per_page));

    $request = $smcFunc['db_query']('', '
        SELECT id_preview, url, final_url, domain, site_name, title, description,
            image_path, status, fetched_at, expires_at, error_text
        FROM {db_prefix}betterposts_link_previews
        WHERE ' . $where_sql . '
        ORDER BY fetched_at DESC, id_preview DESC
        LIMIT {int:start}, {int:limit}',
        $params + array(
            'start' => $start,
            'limit' => $per_page,
        )
    );

    $rows = array();

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $row['id_preview'] = (int)$row['id_preview'];
        $row['fetched_at'] = (int)$row['fetched_at'];
        $row['expires_at'] = (int)$row['expires_at'];
        $row['is_expired'] = $row['expires_at'] <= $now;

        if ($row['status'] === 'failed')
            $row['display_status'] = 'failed';
        elseif ($row['is_expired'])
            $row['display_status'] = 'expired';
        else
            $row['display_status'] = 'ready';

        $row['image_url'] = !empty($row['image_path'])
            ? rtrim($boardurl, '/') . '/' . ltrim($row['image_path'], '/')
            : '';

        $rows[] = $row;
    }

    $smcFunc['db_free_result']($request);

    return array(
        'filter' => $filter,
        'search' => $search,
        'start' => $start,
        'per_page' => $per_page,
        'total' => $total,
        'rows' => $rows,
    );
}

/**
 * Build compact previous/next manager navigation.
 */
function betterposts_preview_manager_pagination($manager, $scripturl)
{
    $start = (int)$manager['start'];
    $per_page = (int)$manager['per_page'];
    $total = (int)$manager['total'];

    $base = $scripturl . '?action=admin;area=betterposts'
        . ';bpfilter=' . rawurlencode($manager['filter']);

    if ($manager['search'] !== '')
        $base .= ';bpsearch=' . rawurlencode($manager['search']);

    return array(
        'previous' => $start > 0
            ? $base . ';bpstart=' . max(0, $start - $per_page)
            : '',
        'next' => $start + $per_page < $total
            ? $base . ';bpstart=' . ($start + $per_page)
            : '',
        'first_item' => $total > 0 ? $start + 1 : 0,
        'last_item' => min($total, $start + $per_page),
    );
}

/**
 * Gather every preview ID still referenced by a stored message.
 *
 * Better Links intentionally scans only messages containing our private BBC
 * marker, keeping the maintenance query narrow even on large forums.
 */
function betterposts_referenced_preview_ids()
{
    global $smcFunc;

    $referenced = array();

    $request = $smcFunc['db_query']('', '
        SELECT body
        FROM {db_prefix}messages
        WHERE body LIKE {string:needle}',
        array('needle' => '%[betterlink=%')
    );

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        if (
            !empty($row['body'])
            && preg_match_all('~\[betterlink=(\d+)\]~i', $row['body'], $matches)
        )
        {
            foreach ($matches[1] as $id)
                $referenced[(int)$id] = true;
        }
    }

    $smcFunc['db_free_result']($request);

    return $referenced;
}

/**
 * Return orphan-row and stray-image statistics without changing data.
 */
function betterposts_orphan_scan()
{
    global $smcFunc, $boarddir;

    $referenced = betterposts_referenced_preview_ids();
    $rows = array();
    $used_images = array();

    $request = $smcFunc['db_query']('', '
        SELECT id_preview, image_path
        FROM {db_prefix}betterposts_link_previews',
        array()
    );

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $id = (int)$row['id_preview'];
        $image_path = trim((string)$row['image_path']);

        if ($image_path !== '')
            $used_images[$image_path] = true;

        if (!isset($referenced[$id]))
            $rows[] = array(
                'id_preview' => $id,
                'image_path' => $image_path,
            );
    }

    $smcFunc['db_free_result']($request);

    $orphan_bytes = 0;

    foreach ($rows as $row)
    {
        if (
            $row['image_path'] !== ''
            && preg_match('~^betterposts_cache/[a-f0-9]{64}\.(?:jpg|png|gif|webp|ico)$~i', $row['image_path'])
        )
        {
            $file = $boarddir . '/' . $row['image_path'];

            if (is_file($file))
            {
                $size = @filesize($file);

                if ($size !== false)
                    $orphan_bytes += (int)$size;
            }
        }
    }

    $stray_files = array();
    $stray_bytes = 0;
    $cache_dir = $boarddir . '/betterposts_cache';

    if (is_dir($cache_dir))
    {
        foreach (glob($cache_dir . '/*') as $file)
        {
            if (!is_file($file) || basename($file) === 'index.html')
                continue;

            $relative = 'betterposts_cache/' . basename($file);

            if (!isset($used_images[$relative]))
            {
                $stray_files[] = $relative;
                $size = @filesize($file);

                if ($size !== false)
                    $stray_bytes += (int)$size;
            }
        }
    }

    return array(
        'orphan_rows' => $rows,
        'orphan_count' => count($rows),
        'orphan_bytes' => $orphan_bytes,
        'stray_files' => $stray_files,
        'stray_count' => count($stray_files),
        'stray_bytes' => $stray_bytes,
    );
}

/**
 * Remove only unreferenced preview rows and cache images.
 *
 * A fresh scan is always performed server-side immediately before deletion.
 * Referenced preview IDs are never deleted.
 */
function betterposts_cleanup_orphans()
{
    global $smcFunc;

    $scan = betterposts_orphan_scan();
    $ids = array();

    foreach ($scan['orphan_rows'] as $row)
    {
        $ids[] = (int)$row['id_preview'];

        if (!empty($row['image_path']))
            betterposts_delete_cached_image_path($row['image_path']);
    }

    if (!empty($ids))
    {
        $smcFunc['db_query']('', '
            DELETE FROM {db_prefix}betterposts_link_previews
            WHERE id_preview IN ({array_int:ids})',
            array('ids' => $ids)
        );
    }

    foreach ($scan['stray_files'] as $image_path)
        betterposts_delete_cached_image_path($image_path);

    return array(
        'rows_removed' => count($ids),
        'stray_files_removed' => count($scan['stray_files']),
        'bytes_reclaimed' => (int)$scan['orphan_bytes'] + (int)$scan['stray_bytes'],
    );
}

/**
 * Find a preserved cached preview for a normalized URL without fetching.
 */
function betterposts_find_cached_preview_id($url)
{
    global $smcFunc;

    $url = betterposts_normalize_url($url);

    if ($url === false)
        return 0;

    $request = $smcFunc['db_query']('', '
        SELECT id_preview
        FROM {db_prefix}betterposts_link_previews
        WHERE url_hash = {string:hash}
            AND status = {string:ready}
        LIMIT 1',
        array(
            'hash' => hash('sha256', $url),
            'ready' => 'ready',
        )
    );

    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    return !empty($row['id_preview']) ? (int)$row['id_preview'] : 0;
}

/**
 * Reconnect native self-labelled SMF links to preserved Better Links previews.
 *
 * Uninstall converts old preview links to [url=URL]URL[/url] and former
 * [nopreview] links to [url]URL[/url]. Recovery only touches the first form,
 * preserving the author's old No Preview choice after reinstall.
 *
 * No remote request occurs here.
 */
function betterposts_recover_preserved_previews()
{
    global $smcFunc;

    $result = array(
        'messages_scanned' => 0,
        'messages_changed' => 0,
        'links_recovered' => 0,
    );

    $request = $smcFunc['db_query']('', '
        SELECT id_msg, body
        FROM {db_prefix}messages
        WHERE body LIKE {string:url_tag}
            AND body LIKE {string:http}',
        array(
            'url_tag' => '%[url=%',
            'http' => '%http%',
        )
    );

    $updates = array();

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $result['messages_scanned']++;

        $body = (string)$row['body'];
        $parts = preg_split(
            '~(<br\s*/?>|\r\n|\r|\n)~i',
            $body,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        $changed = false;

        foreach ($parts as $i => $part)
        {
            if ($i % 2 === 1)
                continue;

            $trimmed = trim(str_replace('&nbsp;', ' ', $part));

            if (
                $trimmed === ''
                || !preg_match('~^\[(?:url|iurl)=~i', $trimmed)
            )
                continue;

            $url = betterposts_extract_standalone_url($trimmed);

            if ($url === false)
                continue;

            $preview_id = betterposts_find_cached_preview_id($url);

            if (empty($preview_id))
                continue;

            $parts[$i] = '[betterlink=' . (int)$preview_id . ']'
                . $url
                . '[/betterlink]';

            $result['links_recovered']++;
            $changed = true;
        }

        if ($changed)
        {
            $updates[] = array(
                'id_msg' => (int)$row['id_msg'],
                'body' => implode('', $parts),
            );
        }
    }

    $smcFunc['db_free_result']($request);

    foreach ($updates as $update)
    {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}messages
            SET body = {string:body}
            WHERE id_msg = {int:id_msg}',
            array(
                'body' => $update['body'],
                'id_msg' => $update['id_msg'],
            )
        );

        $result['messages_changed']++;
    }

    return $result;
}

/**
 * One authoritative gate for the entire link-preview feature.
 */
function betterposts_link_previews_available()
{
    global $modSettings;

    if (empty($modSettings['betterposts_link_previews_enabled']))
        return false;

    $curl = betterposts_curl_capabilities();

    return !empty($curl['available']);
}

/**
 * Load front-end CSS only while link previews are actually usable.
 */
function betterposts_load_theme()
{
    global $context, $settings;

    if (!betterposts_link_previews_available())
        return;

    if (!empty($context['betterposts_assets_loaded']))
        return;

    // Package Manager install/upgrade/uninstall requests can execute theme
    // integration hooks before SMF has populated the normal theme directory
    // settings used by loadCSSFile(). Better Links has nothing to render on
    // those screens, so skip asset registration until a normal themed request.
    if (
        empty($settings['theme_dir'])
        || empty($settings['default_theme_dir'])
        || (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'admin'
            && !empty($_REQUEST['area']) && $_REQUEST['area'] === 'packages')
    )
        return;

    $context['betterposts_assets_loaded'] = true;

    loadCSSFile(
        'better_posts.css',
        array(
            'minimize' => true,
            'order_pos' => 9000,
        ),
        'better_posts'
    );
}

/**
 * Move Better Links cards to the bottom of the message body.
 *
 * The private betterlink BBC renders the author's URL as a normal clickable
 * link plus a hidden marker. SMF calls integrate_prepare_display_context after
 * the message body has been parsed, so we can collect those markers and append
 * the rich cards after all message text. Attachments are rendered by SMF after
 * the message body, which keeps the final order:
 *
 *     message text / links
 *     Better Links previews
 *     SMF attachments
 */
function betterposts_prepare_display_context(&$output, &$message, $counter)
{
    if (empty($output['body']) || stripos($output['body'], 'betterposts-preview-marker') === false)
        return;

    $markers = array();

    if (preg_match_all(
        '~<span\s+class="betterposts-preview-marker"\s+data-preview-id="(\d+)"\s+data-preview-url="([^"]*)"\s*></span>~i',
        $output['body'],
        $matches,
        PREG_SET_ORDER
    ))
        $markers = $matches;

    // Markers are implementation details and should never remain in output.
    $output['body'] = preg_replace(
        '~<span\s+class="betterposts-preview-marker"\s+data-preview-id="\d+"\s+data-preview-url="[^"]*"\s*></span>~i',
        '',
        $output['body']
    );

    if (empty($markers) || !betterposts_link_previews_available())
        return;

    $cards = array();
    $seen = array();

    foreach ($markers as $marker)
    {
        $preview_id = (int)$marker[1];
        $url = html_entity_decode($marker[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $key = $preview_id . '|' . $url;

        if (isset($seen[$key]))
            continue;

        $seen[$key] = true;

        $card = betterposts_render_link_card($preview_id, $url);

        // The normal URL already appears in the post. Do not duplicate a plain
        // fallback link in the preview area if cached metadata is unavailable.
        if (strpos($card, 'betterposts-link-card') === false)
            continue;

        $cards[] = $card;
    }

    if (empty($cards))
        return;

    $output['body'] .= '<div class="betterposts-link-previews" aria-label="Link previews">'
        . implode('', $cards)
        . '</div>';
}

/**
 * Add a small "No Preview" author control to SMF's BBC toolbar.
 *
 * Select or paste a URL and use this button to wrap it in [nopreview].
 * That URL stays clickable but Better Links will intentionally skip the card.
 */
function betterposts_editor_buttons(&$bbc_tags, &$editor_tag_map)
{
    global $txt;

    if (!betterposts_link_previews_available())
        return;

    betterposts_load_language();

    if (empty($bbc_tags) || !is_array($bbc_tags))
        return;

    $row = count($bbc_tags) - 1;

    if ($row < 0)
        $row = 0;

    if (!isset($bbc_tags[$row]) || !is_array($bbc_tags[$row]))
        $bbc_tags[$row] = array();

    $bbc_tags[$row][] = array(
        'code' => 'nopreview',
        'before' => '[nopreview]',
        'after' => '[/nopreview]',
        'description' => $txt['betterposts_no_preview_button'],
        'image' => 'betterposts_nopreview',
    );

    $editor_tag_map['nopreview'] = 'nopreview';
}

/**
 * Add Better Links to Admin -> Configuration.
 */
function betterposts_admin_areas(&$admin_areas)
{
    global $txt, $context;

    betterposts_load_language();

    // Branded Better Links admin icon for both the dropdown menu and
    // the main Admin Configuration tile, independent of active theme assets.
    if (empty($context['betterposts_admin_icon_css_loaded']))
    {
        $context['betterposts_admin_icon_css_loaded'] = true;

        if (!isset($context['html_headers']))
            $context['html_headers'] = '';

        $context['html_headers'] .= '<style id="betterlinks-admin-icon-css">'
            . '.main_icons.better_links_brand:before,.main_icons.better_links_brand::before,.large_admin_menu_icon.better_links_brand:before,.large_admin_menu_icon.better_links_brand::before{content:none!important;display:none!important;}'
            . '.main_icons.better_links_brand{display:inline-block!important;width:16px!important;height:16px!important;min-width:16px!important;min-height:16px!important;padding:0!important;margin:0 6px 0 0!important;line-height:16px!important;font-size:0!important;color:transparent!important;text-indent:-9999px!important;overflow:hidden!important;background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAADc0lEQVR4nG2TW2yTdRjGn/f/fT2Mrt1Ks2PdmOs0DIYQJCpssloiWYAQmBayaOY5aLwgakxMjCnGEOM0Rq7EBTcWZWiDS2AJiThkQNjRUCgwhGbs0DHoOtaDXdd+7fe9XsxgMn0u3+fN7+r5AUvjZqmeWa73sAw3S/WeczIteREA4GEBAEs6JoD4P1Cw3t6K9UU2OPQGZAa+CfvQUxgAM/0L8LDAp6RVfz6zOX9dwe45v3pLDkn+vIp55/zc1KvpSLDYYDLont5eaywrFZmWQzNvJA4V/bAIcHsl9ro12jqzqWBbTu828z25Py4hmmWUF5oRHLqB1GT6uBLMehcCs1VdfXtaOoYzoZNvHa2WAQB3KgUADUNXi4xbnfLLG00LVaNxeTycEuNXxjRJVZGsyG+ybq7YqyjSePheSkmFHxQDlrUywMR/IEtEXPrK5KNqOsb3S+y64Xk75dqAnXWQSqUMRuPJbCLGwmzNr7x+NcQXe0Y0HbSIYAYRtcqN7dzhKBn5arr3vOqbVIX/p2s4e/QSvuue5LvRLJ4zxUWNOYEtK7Lp0Zv3KTkw2q3wm9eEINJcX794eI1psjk1Nqu0HnCJdYUSVlcvp8fMQOGffmAmBJtOwEFJrkiF9GvLls0b1CvvAYDMDVNb6tbkvJ66Pp5p/6JR7js/AHOiCG1NVeiP2yk0EcNqOYLEQgY+tRjf3zBwwfKEPp3emJWIWFhqLDuVvBy12FEMk4hgv+c0djcdwzsdvbDOT2MwoOKTMSsmMnnI1Qny+6Nqjpl09n0NnVoz22UlqDy4m4G0kIxmjg8acPH0x2LfmQB1uaq45OQAJRMSLjc5cedxPfw/TmAVZqXu7kjGuWlDXSCKD4XUO3X417Zp30R6hfHUbUV38EJUhFWDVmm2kq/cil86exDb04mD/RGcMCg48v63mP2LtGdX5SP4+8j0P0s8YsaGmrcLHMrzKhbqyGLMcVTK6ZmnVoqobRnWnzpL4YYnOeDPcsNoQuxqXim3HLhwbuTnL3cQwAQiJgYWJXjBKT3j6tDbrOW5sRjkXdvx2gdl6PstBdvlcViNafYeu9keH/5sP9FIgh5KVN8rud918om9pFr4I6u+sfYlk4lc4LxHqPYJae7SrbC47RuODA12AW0+EgBrvFRUAPCI/zk+jESA2+2VFs0F/gbslof/fBMV2wAAAABJRU5ErkJggg==)!important;background-size:16px 16px!important;background-position:center center!important;background-repeat:no-repeat!important;background-color:transparent!important;box-shadow:none!important;border:0!important;vertical-align:middle!important;}'
            . '.large_admin_menu_icon.better_links_brand{display:block!important;width:32px!important;height:32px!important;min-width:32px!important;min-height:32px!important;padding:0!important;margin:0 auto 2px!important;line-height:32px!important;font-size:0!important;color:transparent!important;text-indent:-9999px!important;overflow:hidden!important;background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAK10lEQVR4nN1Xe3RU1b3+fvucM5OZySQzw5DHTJ7GQNIEggECQgIRrAL1ShESW3V5q94FSrXehbft9S6WQ6RF0bZy1WprrXVVuReCLyqI10dRsChaICEhJBDyTsjkMUkm8z6P3/0j4bpqocvln/e31jlr7332+v2+b+991v4+4P9XMKGGJfhYBrMEZuFjFuDpMTB908wEHwv4WFw2SU29hHqWiAAB4HJV6NKrpl76u/F/HD5Bos6AMd0VwAadpX3bQGgBox4siIypz4cdKJtTgXmmheYcabbNbrhVCWIypAzhrH4cewZfAwr88LFAHRlfAwALEBlgAOsnS0zDRiJx5OUe4MH430zL7F9K33HcnT/XvNqTK2WmzQBsNsDpBvIzgVwJ+GAUqH9L9RvPDd4XOJnz5iUQ/wCATxDqDMbRq0qervhD4VJTVUNLXLVNiL6oX2uOTxifDp+LtaupkbvmL7CuyctUMdLWgN7GCxgeGMdkRMTYnKrYSudLa79/jbF7taTuikjmHb+N6OZfNS7p6732C9A+cWXmzGRyfVqsPDjat+AznVc/dU575rO4Ufx4By99XeMFzwV53isa/7Cf+efDupb5g/1xeB4JAlt+AVSVA3AAFq8Zmzdh7sHAtsOsM2uxigPM7tqWDwHAxyzky9avB4HISCw/93Ol0OUdbR6MjX1w2LTekmOc746yKSvKyRNjbLVn0L5HBuEoyxLOBXNkb+UyIXRHbWIM14x1Rr7oa5KOxI+ZX8Xp9b3/vaf4bV91vijX4vqZlKRlduwqrJPo/OUBnNnGACAiI061OY+z1rmkjf+2ks41DdA/pyvc9clZdJEEf4cfoaN9PHSkEdASjFQLIdOVXVRZkT2rOndFSRV+yndEoucOb2sODfRpQL4sTQY1FWRSbPZFCOMKAD6qFqjZRqLpzQtGIlw9FLLzTbcUQKkuwFkdlJgETMEIEmNhBO7RMDwUxOmOcbReGOeOjmGt++BR7s3ys22mnfKLZ1iWbJ6zMHxylOOJGDd3joBjcY6q8XQAuAwAJsw+QeIF0jTxUgfKxjE4ZMXpkMon/QL7A+ChYYKcMLNDsVKhHWRxZ+L+aiDFDuoc0cgSDmFyQsNgQEfXiKHH5UnumOEU7xwbwKmeMWA4REZCmQD9HQAWRGTgBahGaee9s+5w3N91cVgP9kekMSMJ0QlCTxfhqhSZNRPQN6riV+WEXaeYd34CrCw16NnzMpLtDjhMgNcDeLNA305Xaf+Ajoefa0DQ5JBEWzcD5cfBfwNgqngKH3BO3DzvxX/d6r1loPlTdH5wyqA5t+PCuI5cq0CaQ8HY8TMiGgijeF0Fv9qtI6LIUAwD61OjmJc5gHFWMJhQ0D8uoSOWhFbFhAx7HG0NE5riCpvUEenPQEUT4Lv0F0wV9zg/y+q/0fvOyzu8c9qONyTqf/hLCVlugvM7ODngwqY8CcN7OnHhw/MGtBgrZlmc6B9lR4EXi9Z8C4NxgZiqIwkaZslRFAkdG9Js8GS5+PWgHYQJxWgdUWH80xbAAFBCUxeED5i765SjcaXj0Kvbs0qTe07HH9v4vIL8BcioXgRnoQUtEYn+Z9Tg0JBBVkc6IuERNB46wzA0HjjRQwnVEOMPXA1JECYTjLgQ0CHBP5rAxdAg5aYVGLI1XVMnmu4FlTcCNRJQq8uo3yek2lq9cVXbCxvvzivd4JmIO9bsUa5ecSd2+JbiumLAbQYGwwa/O6Rjy8MF0AJeMg0FaaYaxuBYhAcDURoZG4eIxpGUbIMaikM3DFiFDl0AwmBONmtyRu6sQG/jhj1sMBHBAAgy1dbqWNi+0lk+s+bxVVB//PhHysz8NfjzS0uRnRpGPEE41zUJr9uCH+RbOBCO07sQOCrSICfpqDaGKUXWwESIhCegSRLMZgVWmw3H/AoE67ALneZJmp7ttTt7rTuLADQCYAAsCwB6dtLmVdemsI4EHzphxRP/XgahjeDUBcauZ9/HOx924arycmy+rYhuWZaG27KtfINLp/88reOn3S6sy5hEgW0SSWSAOQHBBpzChBfbUzmoAjEN2JZq6MX5VulYRM41EZ3E/L8qOAFDno3DyS1ptoV5HlDDgCo53Bm4Jk+FJFvQdu4C/vj0biCZMTLQgs+PleH5IhvuuimfVl43B9uv9eBHvSE8+jpjxUIPFnuCKExRIZEOMKEsQ6O9b3VxMKqjq+QqKd+j6mLRyidVDhj43LUfPhaiD+70JLvsHNGBiGxCyGJgKEYIh+IoLfbgrs2rIKXfCiRXwjbeisRiD35b5MEd+0+gqWUAD1QI6IE2dHkl7B114QPDjajdDZiS8C19HAtsUSxUgtC7L1K2W0fOQi4o+5HzLVSMfp/qyBBBRMIundWTfTpMTgWzCkz4yR/aEZMVCCL8x9Z7cODVG1GS50Xh9ocwtvW7fLa6jE+ELTjf2oVJlZHQunBxpcHxVQYqigS8GSa8Mygjw2nFljtn0SP3z6GqRVk42Bomf0Nz4l+uA9uWmB5LQ4NNBhb5lQF/88U+e2X90Un90fuK5Q23/QnX392PH/tuwIpCgZLCFNjzh9C/sgq9w4Dl83YqPfgeyh98FJt3/gXx7jEYRxQartT4rDAQgkS7/Rpan/oYQoqALDMg0rKgvv828lcslZemMDnsnN4PKUcmAve9599ekOt4782hqESjk9ozz94g//JnH+KhzYdw08M34tbFSeiOE6SABMkAzN6ZcK4tx+rb/wutDZ+BtDD8PymF+85qem21ga5kna9ZbiPXy+MIHHgFcGSANBvgXIJtW5ZyZMLgoc5QxIaOAF2SRuaZn2/2fDfnmRGzIdItw7x2lVfTJgJ47/Ao9U5aRKz7DKggB2mbFsOZR6wrBPHupxT54xF0NoUYaj8BxUiuWoKkHUt4LFmCd28T9fl+A8l5NVTXIty7cwmev95QFz+tmo+/+MVeaqr63rQkq5eAWh3Yv9hVmb014lCvj4HN7lw7Zpekwmy1aBGVRVQY0LNmwlcF/InB+zQgewyUcqwDPfVnETh+CEW3V6H/vls5KgOOve3kf+wgcO1abK3Lo+0LDG3980PKGy+dGU/9a0v5BD/Q9aUmrKmX6LVanRkAnpmleJ3LVaHOgzq+HJnJJVJaUkKJj0rMUSJPGZJdObB8OwuBslQmFzA/GqNVwRjac5LxhiGz6ViM/HXvo7A4F7/eMZdLzXGt5sle81/eaA7ZOjtuDocfOgzwVzXhtPafhkUAclDpFLaNx6nyd6zcvDuB2dvjSLpZhbxWR+YTunVjgz7z44j+vaCmtzDrm/yanvGbLt2z7oD+i/o29WyY40+83WPY1n3CyH25LSXlyYVfrvoVZblPABCY7yGc3KSCd9rJGXjKuqziHnf1IqQYGqjnjBrvbmE14UI463rKusuL5YsZu3+vce6Zc1hzXaoUCUN65c02XDxxIYpg5HdliUlf40Td+Jdb/rWMCRNAPNWuXYaMovuT5119g7uoONWVnoZUK2FScSGz0garA/j4jQRMrS3oazwNo73nPOLq/hkG/T4Qqmvl/yNXd8nmfB0Al+b5CFQ3ZVKwxAMUL4aUXgZ3eh4cOS7MmitBViJoPj0I/+lWRIJfPJCX2fBs+4NxBqYs2b4a40sy3yhqJNTUSCDgkg/86kNfZVVTL01v6ZWYfZOYPiPLp7tp2xg10+1ffzSV8+NhBmoNTF27V4z/BWvxC2aMVSa7AAAAAElFTkSuQmCC)!important;background-size:32px 32px!important;background-position:center center!important;background-repeat:no-repeat!important;background-color:transparent!important;box-shadow:none!important;border:0!important;}'
            . '</style>';
    }

    $admin_areas['config']['areas']['betterposts'] = array(
        'label' => $txt['betterposts_admin_title'],
        'file' => 'BetterPosts.php',
        'function' => 'betterposts_admin',
        'permission' => array('admin_forum'),
        'icon' => 'better_links_brand',
    );
}

/**
 * Better Links admin settings / capability screen.
 */
function betterposts_admin()
{
    global $context, $txt, $modSettings, $scripturl;

    isAllowedTo('admin_forum');
    betterposts_load_language();
    loadTemplate('BetterPostsAdmin');

    $curl = betterposts_curl_capabilities();
    $context['betterposts_error'] = '';
    $context['betterposts_diagnostic'] = null;
    $context['betterposts_test_url'] = '';

    if (isset($_POST['save_betterposts']))
    {
        checkSession();

        $requested = !empty($_POST['betterposts_link_previews_enabled']);
        $enabled = $requested && !empty($curl['available']) ? 1 : 0;

        $connect = max(1, min(15, (int)($_POST['betterposts_connect_timeout'] ?? 5)));
        $total = max(3, min(45, (int)($_POST['betterposts_total_timeout'] ?? 15)));

        if ($total < $connect)
            $total = $connect;

        updateSettings(array(
            'betterposts_link_previews_enabled' => $enabled,
            'betterposts_cache_days' => max(1, min(90, (int)($_POST['betterposts_cache_days'] ?? 7))),
            'betterposts_connect_timeout' => $connect,
            'betterposts_total_timeout' => $total,
            'betterposts_max_html_kb' => max(256, min(4096, (int)($_POST['betterposts_max_html_kb'] ?? 1536))),
            'betterposts_max_image_kb' => max(128, min(8192, (int)($_POST['betterposts_max_image_kb'] ?? 2048))),
        ));

        redirectexit('action=admin;area=betterposts;saved=1');
    }

    if (isset($_POST['expire_betterposts_cache']))
    {
        checkSession();
        betterposts_expire_preview_cache();
        redirectexit('action=admin;area=betterposts;cache_expired=1');
    }

    if (isset($_POST['clear_betterposts_images']))
    {
        checkSession();
        betterposts_clear_cached_images();
        redirectexit('action=admin;area=betterposts;images_cleared=1');
    }

    if (isset($_POST['scan_betterposts_orphans']))
    {
        checkSession();
        $context['betterposts_orphan_scan'] = betterposts_orphan_scan();
    }

    if (isset($_POST['cleanup_betterposts_orphans']))
    {
        checkSession();

        $result = betterposts_cleanup_orphans();

        redirectexit(
            'action=admin;area=betterposts'
            . ';orphan_rows_removed=' . (int)$result['rows_removed']
            . ';orphan_files_removed=' . (int)$result['stray_files_removed']
            . ';orphan_bytes_reclaimed=' . (int)$result['bytes_reclaimed']
        );
    }

    if (isset($_POST['recover_betterposts_previews']))
    {
        checkSession();

        $recovery = betterposts_recover_preserved_previews();

        redirectexit(
            'action=admin;area=betterposts'
            . ';recovered_links=' . (int)$recovery['links_recovered']
            . ';recovered_messages=' . (int)$recovery['messages_changed']
            . ';recovery_scanned=' . (int)$recovery['messages_scanned']
        );
    }

    if (isset($_POST['test_betterposts_url']))
    {
        checkSession();
        $context['betterposts_test_url'] = trim((string)($_POST['betterposts_test_url'] ?? ''));

        if (empty($curl['available']))
            $context['betterposts_error'] = $txt['betterposts_test_requires_curl'];
        else
        {
            $normalized = betterposts_normalize_url($context['betterposts_test_url']);

            if ($normalized === false)
                $context['betterposts_error'] = $txt['betterposts_test_invalid_url'];
            else
                $context['betterposts_diagnostic'] = betterposts_diagnose_url($normalized);
        }
    }

    if (isset($_POST['manager_refresh_preview']))
    {
        checkSession();

        $preview_id = (int)($_POST['preview_id'] ?? 0);

        if (empty($curl['available']))
            redirectexit('action=admin;area=betterposts;manager_error=curl');
        elseif (!betterposts_refresh_preview($preview_id))
            redirectexit('action=admin;area=betterposts;manager_error=refresh');
        else
            redirectexit('action=admin;area=betterposts;manager_refreshed=' . $preview_id);
    }

    if (isset($_POST['manager_expire_preview']))
    {
        checkSession();

        $preview_id = (int)($_POST['preview_id'] ?? 0);

        if (!betterposts_expire_preview($preview_id))
            redirectexit('action=admin;area=betterposts;manager_error=expire');
        else
            redirectexit('action=admin;area=betterposts;manager_expired=' . $preview_id);
    }

    if (isset($_POST['manager_inspect_preview']))
    {
        checkSession();

        $preview_id = (int)($_POST['preview_id'] ?? 0);
        $preview_row = betterposts_get_preview_row($preview_id);

        if (empty($preview_row))
            $context['betterposts_error'] = $txt['betterposts_manager_missing'];
        elseif (empty($curl['available']))
            $context['betterposts_error'] = $txt['betterposts_test_requires_curl'];
        else
        {
            $context['betterposts_manager_inspect_id'] = $preview_id;
            $context['betterposts_manager_inspect_row'] = $preview_row;
            $context['betterposts_manager_diagnostic'] = betterposts_diagnose_url($preview_row['url']);
        }
    }

    $context['page_title'] = $txt['betterposts_admin_title'];
    $context['sub_template'] = 'betterposts_admin';
    $context['betterposts_curl'] = $curl;
    $context['betterposts_saved'] = !empty($_GET['saved']);
    $context['betterposts_cache_expired'] = !empty($_GET['cache_expired']);
    $context['betterposts_images_cleared'] = !empty($_GET['images_cleared']);
    $context['betterposts_link_previews_enabled'] =
        !empty($modSettings['betterposts_link_previews_enabled'])
        && !empty($curl['available']);
    $context['betterposts_settings_url'] = $scripturl . '?action=admin;area=betterposts';
    $context['betterposts_runtime_settings'] = betterposts_runtime_settings();
    $context['betterposts_cache_stats'] = betterposts_cache_stats();

    $manager_filter = isset($_REQUEST['bpfilter']) ? (string)$_REQUEST['bpfilter'] : 'all';
    $manager_search = isset($_REQUEST['bpsearch']) ? trim((string)$_REQUEST['bpsearch']) : '';
    $manager_start = isset($_REQUEST['bpstart']) ? max(0, (int)$_REQUEST['bpstart']) : 0;

    $context['betterposts_preview_manager'] = betterposts_preview_manager_data(
        $manager_filter,
        $manager_search,
        $manager_start,
        15
    );
    $context['betterposts_preview_manager_pagination'] = betterposts_preview_manager_pagination(
        $context['betterposts_preview_manager'],
        $scripturl
    );
    $context['betterposts_manager_refreshed'] = !empty($_GET['manager_refreshed'])
        ? (int)$_GET['manager_refreshed']
        : 0;
    $context['betterposts_manager_expired'] = !empty($_GET['manager_expired'])
        ? (int)$_GET['manager_expired']
        : 0;
    $context['betterposts_manager_error'] = !empty($_GET['manager_error'])
        ? (string)$_GET['manager_error']
        : '';
    $context['betterposts_orphan_rows_removed'] = isset($_GET['orphan_rows_removed'])
        ? max(0, (int)$_GET['orphan_rows_removed'])
        : null;
    $context['betterposts_orphan_files_removed'] = isset($_GET['orphan_files_removed'])
        ? max(0, (int)$_GET['orphan_files_removed'])
        : 0;
    $context['betterposts_orphan_bytes_reclaimed'] = isset($_GET['orphan_bytes_reclaimed'])
        ? max(0, (int)$_GET['orphan_bytes_reclaimed'])
        : 0;
    $context['betterposts_recovered_links'] = isset($_GET['recovered_links'])
        ? max(0, (int)$_GET['recovered_links'])
        : null;
    $context['betterposts_recovered_messages'] = isset($_GET['recovered_messages'])
        ? max(0, (int)$_GET['recovered_messages'])
        : 0;
    $context['betterposts_recovery_scanned'] = isset($_GET['recovery_scanned'])
        ? max(0, (int)$_GET['recovery_scanned'])
        : 0;
}



/**
 * Convert standalone HTTP(S) URLs into cached Better Links link cards.
 *
 * This runs at the end of SMF's preparser. By that point line breaks are <br>
 * when saving a real post. We only enhance a URL that occupies the whole line.
 * URLs embedded in prose are intentionally left as normal links.
 */

/**
 * Turn one resolved URL into Better Links' private BBC while keeping it inline.
 */
function betterposts_inline_preview_bbc($url, $preview_id)
{
    return '[betterlink=' . (int)$preview_id . ']' . $url . '[/betterlink]';
}

/**
 * Preview a fully qualified URL and return inline Better Links BBC on success.
 */
function betterposts_inline_qualified_url($url)
{
    $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $url = betterposts_normalize_url($url);

    if ($url === false)
        return false;

    $preview_id = betterposts_get_or_fetch_preview($url);

    if (empty($preview_id))
        return false;

    return betterposts_inline_preview_bbc($url, $preview_id);
}

/**
 * Preview a bare/schemeless domain and return inline Better Links BBC on success.
 */
function betterposts_inline_schemeless_url($candidate)
{
    $candidate = html_entity_decode(trim((string)$candidate), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $preview_id = 0;
    $url = betterposts_resolve_schemeless_preview($candidate, $preview_id);

    if ($url === false || empty($preview_id))
        return false;

    return betterposts_inline_preview_bbc($url, $preview_id);
}

/**
 * Process inline links inside ordinary message text without disturbing
 * surrounding prose or existing Better Links/No Preview BBC.
 *
 * Supported inline forms:
 *   Raw https://example.com/path
 *   Raw example.com/path
 *   [url]https://example.com[/url]
 *   [url=https://example.com]https://example.com[/url]
 *
 * Deliberately named links such as [url=https://example.com]click here[/url]
 * stay ordinary links.
 */
function betterposts_preview_inline_links($text)
{
    if ($text === '')
        return $text;

    $protected = array();

    // Protect private Better Links BBC, No Preview BBC, and deliberately named
    // URL tags before scanning raw text. Self-labelled URL tags are handled
    // separately below.
    $protect = function ($match) use (&$protected)
    {
        $key = "\x1A" . count($protected) . "\x1A";
        $protected[$key] = $match[0];
        return $key;
    };

    // Existing Better Links / explicit No Preview are untouchable.
    $text = preg_replace_callback(
        '~\[(?:betterlink=\d+|nopreview)\].*?\[/(?:betterlink|nopreview)\]~isu',
        $protect,
        $text
    );

    // Handle [url]URL[/url] anywhere in a sentence.
    $text = preg_replace_callback(
        '~\[(url|iurl)\](https?://[^\[]+)\[/\1\]~iu',
        function ($m)
        {
            $replacement = betterposts_inline_qualified_url($m[2]);
            return $replacement !== false ? $replacement : $m[0];
        },
        $text
    );

    // Handle [url=URL]URL[/url] only when label == target.
    $text = preg_replace_callback(
        '~\[(url|iurl)=((?:&quot;|")?)(https?://[^\]"]+?)\2\](.*?)\[/\1\]~isu',
        function ($m) use ($protect)
        {
            $target = html_entity_decode(trim($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = html_entity_decode(trim(strip_tags($m[4])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($label !== $target)
                return $protect($m);

            $replacement = betterposts_inline_qualified_url($target);
            return $replacement !== false ? $replacement : $m[0];
        },
        $text
    );

    // Protect any remaining URL BBC, which is intentionally named or otherwise
    // not eligible for automatic previews.
    $text = preg_replace_callback(
        '~\[(?:url|iurl)(?:=[^\]]+)?\].*?\[/(?:url|iurl)\]~isu',
        $protect,
        $text
    );

    // Inline images are content, not preview candidates. SMF can hand this
    // hook [img]URL[/img] as well as parameterized forms such as
    // [img width=260]URL[/img]. Protect the entire BBC before the raw URL
    // scanners run, otherwise Better Links can replace the image source with
    // [betterlink] storage BBC and leave a broken image plus a preview card.
    $text = preg_replace_callback(
        '~\[img(?:\s+[^\]]*)?\].*?\[/img\]~isu',
        $protect,
        $text
    );

    // Raw fully-qualified links inside prose. Keep trailing sentence
    // punctuation outside the URL.
    $text = preg_replace_callback(
        '~(?<![\w@])https?://[^\s<>\[\]\x1A]+~iu',
        function ($m)
        {
            $raw = $m[0];
            $trail = '';

            while ($raw !== '' && preg_match('~[\.,!?;:]$~u', $raw))
            {
                $trail = substr($raw, -1) . $trail;
                $raw = substr($raw, 0, -1);
            }

            // Balance a common sentence-ending ")" without stripping one that
            // actually belongs to the URL.
            if (
                substr($raw, -1) === ')'
                && substr_count($raw, '(') < substr_count($raw, ')')
            )
            {
                $trail = ')' . $trail;
                $raw = substr($raw, 0, -1);
            }

            $replacement = betterposts_inline_qualified_url($raw);

            return ($replacement !== false ? $replacement : $raw) . $trail;
        },
        $text
    );

    // Raw bare domains in prose. Negative lookarounds keep this from eating
    // email addresses or the middle of a larger token.
    $text = preg_replace_callback(
        '~(?<![a-z0-9_@:/.-])((?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?::(?:80|443))?(?:[/?#][^\s<>\[\]\x1A]*)?)~iu',
        function ($m)
        {
            $raw = $m[1];
            $trail = '';

            while ($raw !== '' && preg_match('~[\.,!?;:]$~u', $raw))
            {
                $trail = substr($raw, -1) . $trail;
                $raw = substr($raw, 0, -1);
            }

            if (
                substr($raw, -1) === ')'
                && substr_count($raw, '(') < substr_count($raw, ')')
            )
            {
                $trail = ')' . $trail;
                $raw = substr($raw, 0, -1);
            }

            $replacement = betterposts_inline_schemeless_url($raw);

            return ($replacement !== false ? $replacement : $raw) . $trail;
        },
        $text
    );

    if (!empty($protected))
        $text = strtr($text, $protected);

    return $text;
}

function betterposts_preparse_link_cards(&$message, $previewing = false)
{
    if (!betterposts_link_previews_available())
        return;

    if ($previewing || empty($message))
        return;

    // Process each logical line independently so line breaks remain exactly
    // where the author placed them. Unlike earlier builds, a link no longer
    // has to occupy the entire line to earn a preview.
    $parts = preg_split(
        '~(<br\s*/?>|\r\n|\r|\n)~i',
        $message,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );

    foreach ($parts as $i => $part)
    {
        if ($i % 2 === 1 || $part === '')
            continue;

        $parts[$i] = betterposts_preview_inline_links($part);
    }

    $message = implode('', $parts);
}

/**
 * Recognize all standalone URL forms SMF can hand to the end of preparsecode.
 *
 * Depending on editor mode and SMF's URL normalization, a pasted URL can be:
 *
 *   https://example.com/
 *   [url]https://example.com/[/url]
 *   [url=https://example.com/]https://example.com/[/url]
 *   [url=&quot;https://example.com/&quot;]https://example.com/[/url]
 *
 * Internal-forum iurl variants are accepted too.
 */
/**
 * Extract a standalone schemeless web address such as:
 *
 *   example.com
 *   www.example.com
 *   example.com/path?x=1
 *
 * Named links are deliberately excluded. This is only for a line whose visible
 * content itself is the destination.
 */
function betterposts_extract_schemeless_standalone($line)
{
    $line = trim((string)$line);

    if ($line === '')
        return false;

    $candidate = '';

    if (preg_match(
        '~^(?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?::(?:80|443))?(?:[/?#][^\s<>\[\]]*)?$~iu',
        $line
    ))
        $candidate = $line;
    elseif (preg_match(
        '~^\[(?:url|iurl)\]((?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?::(?:80|443))?(?:[/?#][^\[]*)?)\[/(?:url|iurl)\]$~iu',
        $line,
        $m
    ))
        $candidate = $m[1];
    elseif (preg_match(
        '~^\[(?:url|iurl)=((?:&quot;|")?)([^\]"]+?)\1\](.*?)\[/(?:url|iurl)\]$~isu',
        $line,
        $m
    ))
    {
        $target = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = html_entity_decode(trim(strip_tags($m[3])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (
            $label === $target
            && preg_match(
                '~^(?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?::(?:80|443))?(?:[/?#][^\s<>\[\]]*)?$~iu',
                $target
            )
        )
            $candidate = $target;
    }

    if ($candidate === '')
        return false;

    $candidate = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (
        preg_match('~[\x00-\x1F\x7F]~', $candidate)
        || strlen($candidate) > 2048
    )
        return false;

    return $candidate;
}

/**
 * Build the strongest reasonable URL candidates for a bare domain.
 *
 * Preferred order:
 *   1. https://www.
 *   2. https://
 *   3. http://www.
 *   4. http://
 *
 * Duplicate forms are removed automatically when the user already typed www.
 */
function betterposts_schemeless_url_candidates($candidate)
{
    $candidate = trim((string)$candidate);

    if ($candidate === '')
        return array();

    $parts = preg_split('~(?=[/?#])~', $candidate, 2);
    $authority = $parts[0];
    $suffix = isset($parts[1]) ? $parts[1] : '';

    $authority_no_www = preg_replace('~^www\.~i', '', $authority);

    $raw = array(
        'https://www.' . $authority_no_www . $suffix,
        'https://' . $authority_no_www . $suffix,
        'http://www.' . $authority_no_www . $suffix,
        'http://' . $authority_no_www . $suffix,
    );

    $out = array();

    foreach ($raw as $url)
    {
        $url = betterposts_normalize_url($url);

        if ($url !== false && !in_array($url, $out, true))
            $out[] = $url;
    }

    return $out;
}

/**
 * Lightweight existence probe for schemeless candidates.
 *
 * This uses the same SSRF/DNS/TLS/redirect protections as normal preview
 * fetching, but caps the probe body at 128 KiB. A candidate must return a
 * successful HTML/XHTML response before a bare domain is converted into a
 * Better Links preview.
 */
function betterposts_probe_preview_candidate($url)
{
    $runtime = betterposts_runtime_settings();
    $max_bytes = min($runtime['max_html_kb'] * 1024, 131072);

    if ($max_bytes < 32768)
        $max_bytes = 32768;

    $response = betterposts_safe_http_get(
        $url,
        $max_bytes,
        array('text/html', 'application/xhtml+xml'),
        0,
        true
    );

    return $response !== false && !empty($response['body']);
}

/**
 * Resolve a bare domain to a real web URL and create/reuse its preview.
 *
 * Existing READY cache rows are reused immediately. Otherwise each candidate is
 * probed in order. If none succeeds, the caller leaves the user's original text
 * untouched.
 */
function betterposts_resolve_schemeless_preview($candidate, &$preview_id = 0)
{
    $preview_id = 0;

    foreach (betterposts_schemeless_url_candidates($candidate) as $url)
    {
        // If we already know this destination, don't perform a separate probe.
        if (betterposts_find_cached_preview_id($url))
        {
            $preview_id = betterposts_get_or_fetch_preview($url);

            if (!empty($preview_id))
                return $url;
        }

        if (!betterposts_probe_preview_candidate($url))
            continue;

        $preview_id = betterposts_get_or_fetch_preview($url);

        if (!empty($preview_id))
            return $url;
    }

    return false;
}

function betterposts_extract_standalone_url($line)
{
    $line = trim((string)$line);

    if ($line === '')
        return false;

    $candidate = '';

    if (preg_match('~^https?://[^\s<>\[\]]+$~iu', $line))
        $candidate = $line;
    elseif (preg_match('~^\[(?:url|iurl)\](https?://[^\[]+)\[/(?:url|iurl)\]$~iu', $line, $m))
        $candidate = $m[1];
    elseif (preg_match(
        '~^\[(?:url|iurl)=((?:&quot;|")?)(https?://[^\]"]+?)\1\](.*?)\[/(?:url|iurl)\]$~isu',
        $line,
        $m
    ))
    {
        $target = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = html_entity_decode(trim(strip_tags($m[3])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Only upgrade an editor-generated link when the visible text is the
        // URL itself. A deliberately named link remains an ordinary link.
        if ($label === $target)
            $candidate = $target;
    }

    if ($candidate === '')
        return false;

    $candidate = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return betterposts_normalize_url($candidate);
}

/**
 * Make the editor show the author's original URL instead of our storage BBC.
 */
function betterposts_unpreparse_link_cards(&$message)
{
    if (stripos($message, '[betterlink=') === false)
        return;

    $message = preg_replace(
        '~\[betterlink=\d+\](https?://.*?)\[/betterlink\]~is',
        '$1',
        $message
    );
}

/**
 * Register Better Links' private storage BBC.
 */
function betterposts_bbc_codes(&$codes, &$no_autolink_tags)
{
    $codes[] = array(
        'tag' => 'betterlink',
        'type' => 'unparsed_equals_content',
        'content' => '$1',
        'validate' => function (&$tag, &$data, $disabled, $params)
        {
            $preview_id = isset($data[1]) ? (int)$data[1] : 0;
            $fallback_url = isset($data[0]) ? $data[0] : '';

            $data[0] = betterposts_render_link_reference($preview_id, $fallback_url);
        },
        'block_level' => true,
    );

    // Explicit author opt-out. The selected URL remains a normal clickable
    // link and is deliberately ignored by betterposts_preparse_link_cards().
    $codes[] = array(
        'tag' => 'nopreview',
        'type' => 'unparsed_content',
        'content' => '$1',
        'validate' => function (&$tag, &$data, $disabled, $params)
        {
            // For SMF's unparsed_content BBC type, $data is the complete
            // content string, not the two-element array used by
            // unparsed_equals_content. dev6 treated it like an array, so the
            // URL survived only as ordinary text instead of the anchor HTML
            // returned by betterposts_render_plain_link().
            $raw_url = html_entity_decode((string)$data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = betterposts_normalize_url($raw_url);

            $data = $url === false
                ? htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8')
                : betterposts_render_plain_link($url);
        },
        'block_level' => false,
    );

    $no_autolink_tags[] = 'betterlink';
    $no_autolink_tags[] = 'nopreview';
}

/**
 * Load a cached preview or fetch/cache it once.
 */
function betterposts_get_or_fetch_preview($url)
{
    global $smcFunc;

    $url = betterposts_normalize_url($url);

    if ($url === false)
        return 0;

    $hash = hash('sha256', $url);
    $now = time();

    $request = $smcFunc['db_query']('', '
        SELECT id_preview, status, expires_at, image_path
        FROM {db_prefix}betterposts_link_previews
        WHERE url_hash = {string:hash}
        LIMIT 1',
        array('hash' => $hash)
    );

    $existing = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (!empty($existing))
    {
        if ($existing['status'] === 'ready' && (int)$existing['expires_at'] > $now)
            return (int)$existing['id_preview'];

        // dev2/dev2a may contain negative-cache rows. dev2b no longer lets a
        // remote fetch failure suppress the card, so always retry/rebuild them.
    }

    $meta = betterposts_fetch_page_metadata($url);

    /*
     * A standalone valid URL should still become a useful card even when the
     * remote site blocks bots, requires JavaScript, times out, or provides no
     * parsable metadata. Rich metadata is an enhancement, not a requirement.
     *
     * This also means card rendering no longer depends on third-party fetch
     * success. The fallback never makes a second outbound request.
     */
    if ($meta === false)
        $meta = betterposts_build_fallback_metadata($url);

    $image_path = '';

    if (!empty($meta['image']))
        $image_path = betterposts_cache_preview_image($meta['image']);

    $values = array(
        'url' => $url,
        'final_url' => $meta['final_url'],
        'domain' => $meta['domain'],
        'site_name' => $meta['site_name'],
        'title' => $meta['title'],
        'description' => $meta['description'],
        'image_path' => $image_path,
        'status' => 'ready',
        'fetched_at' => $now,
        'expires_at' => $now + (betterposts_runtime_settings()['cache_days'] * 86400),
        'error_text' => '',
    );

    if (!empty($existing))
    {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}betterposts_link_previews
            SET
                url = {string:url},
                final_url = {string:final_url},
                domain = {string:domain},
                site_name = {string:site_name},
                title = {string:title},
                description = {string:description},
                image_path = {string:image_path},
                status = {string:status},
                fetched_at = {int:fetched_at},
                expires_at = {int:expires_at},
                error_text = {string:error_text}
            WHERE id_preview = {int:id_preview}',
            $values + array('id_preview' => (int)$existing['id_preview'])
        );

        if (
            !empty($existing['image_path'])
            && $existing['image_path'] !== $image_path
        )
            betterposts_delete_cached_image_path($existing['image_path']);

        return (int)$existing['id_preview'];
    }

    $smcFunc['db_insert'](
        'insert',
        '{db_prefix}betterposts_link_previews',
        array(
            'url_hash' => 'string',
            'url' => 'string',
            'final_url' => 'string',
            'domain' => 'string',
            'site_name' => 'string',
            'title' => 'string',
            'description' => 'string',
            'image_path' => 'string',
            'status' => 'string',
            'fetched_at' => 'int',
            'expires_at' => 'int',
            'error_text' => 'string',
        ),
        array(
            $hash,
            $url,
            $values['final_url'],
            $values['domain'],
            $values['site_name'],
            $values['title'],
            $values['description'],
            $values['image_path'],
            'ready',
            $now,
            $now + (betterposts_runtime_settings()['cache_days'] * 86400),
            '',
        ),
        array('id_preview')
    );

    return (int)$smcFunc['db_insert_id']('{db_prefix}betterposts_link_previews', 'id_preview');
}

/**
 * Build a no-fetch fallback card from the URL itself.
 */
function betterposts_build_fallback_metadata($url)
{
    $host = parse_url($url, PHP_URL_HOST);

    if (empty($host))
        $host = 'Link';

    $domain = preg_replace('~^www\.~i', '', $host);

    return array(
        'final_url' => $url,
        'domain' => $domain,
        'site_name' => $domain,
        'title' => $domain,
        'description' => '',
        'image' => '',
    );
}


/**
 * Remember a failed fetch for one hour so a bad URL cannot cause repeated
 * outbound requests on every edit/repost.
 */
function betterposts_store_preview_failure($hash, $url)
{
    global $smcFunc;

    $now = time();

    $request = $smcFunc['db_query']('', '
        SELECT id_preview
        FROM {db_prefix}betterposts_link_previews
        WHERE url_hash = {string:hash}
        LIMIT 1',
        array('hash' => $hash)
    );

    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (!empty($row))
    {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}betterposts_link_previews
            SET status = {string:status},
                fetched_at = {int:fetched_at},
                expires_at = {int:expires_at},
                error_text = {string:error_text}
            WHERE id_preview = {int:id_preview}',
            array(
                'status' => 'failed',
                'fetched_at' => $now,
                'expires_at' => $now + 3600,
                'error_text' => 'Preview fetch failed',
                'id_preview' => (int)$row['id_preview'],
            )
        );
        return;
    }

    $smcFunc['db_insert'](
        'insert',
        '{db_prefix}betterposts_link_previews',
        array(
            'url_hash' => 'string',
            'url' => 'string',
            'final_url' => 'string',
            'domain' => 'string',
            'site_name' => 'string',
            'title' => 'string',
            'description' => 'string',
            'image_path' => 'string',
            'status' => 'string',
            'fetched_at' => 'int',
            'expires_at' => 'int',
            'error_text' => 'string',
        ),
        array(
            $hash, $url, $url, '', '', '', '', '', 'failed',
            $now, $now + 3600, 'Preview fetch failed',
        ),
        array('id_preview')
    );
}

/**
 * Fetch and parse Open Graph / standard metadata.
 */
function betterposts_fetch_page_metadata($url, &$diagnostics = null)
{
    $runtime = betterposts_runtime_settings();

    $response = betterposts_safe_http_get(
        $url,
        $runtime['max_html_kb'] * 1024,
        array('text/html', 'application/xhtml+xml'),
        0,
        true
    );

    if ($response === false || empty($response['body']))
        return false;

    $html = $response['body'];
    $final_url = $response['url'];
    $host = parse_url($final_url, PHP_URL_HOST);

    if (is_array($diagnostics))
    {
        $diagnostics['http_status'] = isset($response['status']) ? (int)$response['status'] : 0;
        $diagnostics['content_type'] = isset($response['content_type']) ? $response['content_type'] : '';
        $diagnostics['fetch_seconds'] = isset($response['total_time']) ? (float)$response['total_time'] : 0;
        $diagnostics['response_bytes'] = isset($response['bytes']) ? (int)$response['bytes'] : strlen($html);
        $diagnostics['truncated'] = !empty($response['truncated']);
        $diagnostics['final_url'] = $final_url;
        $diagnostics['title_source'] = '';
        $diagnostics['site_source'] = '';
        $diagnostics['description_source'] = '';
        $diagnostics['image_source'] = '';
    }

    if (empty($host))
        return false;

    $domain = preg_replace('~^www\\.~i', '', $host);
    $meta = array(
        'final_url' => $final_url,
        'domain' => $domain,
        'site_name' => '',
        'title' => '',
        'description' => '',
        'image' => '',
    );

    if (!class_exists('DOMDocument'))
        return betterposts_build_fallback_metadata($final_url);

    $old = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    $xpath = new DOMXPath($dom);

    $first_meta = function ($keys, $attribute = 'property') use ($xpath)
    {
        foreach ((array)$keys as $key)
        {
            $query = '//meta[translate(@' . $attribute
                . ', "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="'
                . strtolower($key) . '"]/@content';
            $nodes = $xpath->query($query);
            if (!$nodes) continue;
            foreach ($nodes as $node)
            {
                $value = trim($node->nodeValue);
                if ($value !== '') return $value;
            }
        }
        return '';
    };

    $first_text = function ($queries) use ($xpath)
    {
        foreach ((array)$queries as $query)
        {
            $nodes = $xpath->query($query);
            if (!$nodes) continue;
            foreach ($nodes as $node)
            {
                $value = trim(preg_replace('~\\s+~u', ' ', $node->textContent));
                if ($value !== '') return $value;
            }
        }
        return '';
    };

    $jsonld = array('title'=>'','description'=>'','image'=>'','site_name'=>'');
    $collect_jsonld = function ($value) use (&$collect_jsonld, &$jsonld)
    {
        if (!is_array($value)) return;
        $is_assoc = array_keys($value) !== range(0, count($value) - 1);
        if ($is_assoc)
        {
            if ($jsonld['title'] === '')
            {
                foreach (array('headline','name') as $key)
                    if (!empty($value[$key]) && is_string($value[$key])) { $jsonld['title']=$value[$key]; break; }
            }
            if ($jsonld['description'] === '' && !empty($value['description']) && is_string($value['description']))
                $jsonld['description'] = $value['description'];
            if ($jsonld['image'] === '' && !empty($value['image']))
            {
                if (is_string($value['image'])) $jsonld['image'] = $value['image'];
                elseif (is_array($value['image']))
                {
                    if (!empty($value['image']['url']) && is_string($value['image']['url'])) $jsonld['image']=$value['image']['url'];
                    elseif (!empty($value['image'][0]) && is_string($value['image'][0])) $jsonld['image']=$value['image'][0];
                    elseif (!empty($value['image'][0]['url']) && is_string($value['image'][0]['url'])) $jsonld['image']=$value['image'][0]['url'];
                }
            }
            if ($jsonld['site_name'] === '')
            {
                if (!empty($value['publisher']['name']) && is_string($value['publisher']['name'])) $jsonld['site_name']=$value['publisher']['name'];
                elseif (!empty($value['isPartOf']['name']) && is_string($value['isPartOf']['name'])) $jsonld['site_name']=$value['isPartOf']['name'];
            }
        }
        foreach ($value as $child) if (is_array($child)) $collect_jsonld($child);
    };

    $json_nodes = $xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "ld+json")]');
    if ($json_nodes)
    {
        foreach ($json_nodes as $node)
        {
            $raw = trim($node->textContent);
            if ($raw === '') continue;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $collect_jsonld($decoded);
        }
    }

    $meta['title'] = $first_meta(array('og:title'));
    if ($meta['title'] !== '' && is_array($diagnostics)) $diagnostics['title_source'] = 'Open Graph';
    if ($meta['title'] === '')
    {
        $meta['title'] = $first_meta(array('twitter:title'), 'name');
        if ($meta['title'] !== '' && is_array($diagnostics)) $diagnostics['title_source'] = 'Twitter Card';
    }
    if ($meta['title'] === '')
    {
        $meta['title'] = $jsonld['title'];
        if ($meta['title'] !== '' && is_array($diagnostics)) $diagnostics['title_source'] = 'JSON-LD';
    }
    if ($meta['title'] === '')
    {
        $meta['title'] = $first_text(array('//title'));
        if ($meta['title'] !== '' && is_array($diagnostics)) $diagnostics['title_source'] = 'HTML title';
    }
    if ($meta['title'] === '')
    {
        $meta['title'] = $first_text(array('//h1'));
        if ($meta['title'] !== '' && is_array($diagnostics)) $diagnostics['title_source'] = 'H1 fallback';
    }

    $meta['site_name'] = $first_meta(array('og:site_name'));
    if ($meta['site_name'] !== '' && is_array($diagnostics)) $diagnostics['site_source'] = 'Open Graph';
    if ($meta['site_name'] === '')
    {
        $meta['site_name'] = $first_meta(array('application-name'), 'name');
        if ($meta['site_name'] !== '' && is_array($diagnostics)) $diagnostics['site_source'] = 'Application name';
    }
    if ($meta['site_name'] === '')
    {
        $meta['site_name'] = $jsonld['site_name'];
        if ($meta['site_name'] !== '' && is_array($diagnostics)) $diagnostics['site_source'] = 'JSON-LD';
    }
    if ($meta['site_name'] === '')
    {
        $meta['site_name'] = $domain;
        if (is_array($diagnostics)) $diagnostics['site_source'] = 'Domain fallback';
    }

    $meta['description'] = $first_meta(array('og:description'));
    if ($meta['description'] !== '' && is_array($diagnostics)) $diagnostics['description_source'] = 'Open Graph';
    if ($meta['description'] === '')
    {
        $meta['description'] = $first_meta(array('twitter:description'), 'name');
        if ($meta['description'] !== '' && is_array($diagnostics)) $diagnostics['description_source'] = 'Twitter Card';
    }
    if ($meta['description'] === '')
    {
        $meta['description'] = $jsonld['description'];
        if ($meta['description'] !== '' && is_array($diagnostics)) $diagnostics['description_source'] = 'JSON-LD';
    }
    if ($meta['description'] === '')
    {
        $meta['description'] = $first_meta(array('description'), 'name');
        if ($meta['description'] !== '' && is_array($diagnostics)) $diagnostics['description_source'] = 'Meta description';
    }

    if ($meta['description'] === '')
    {
        foreach (array('//article//p','//main//p','//*[@role="main"]//p','//body//p') as $query)
        {
            $nodes = $xpath->query($query);
            if (!$nodes) continue;

            foreach ($nodes as $node)
            {
                $text = betterposts_clean_meta_text($node->textContent, 420);

                if (betterposts_text_is_meaningful($text))
                {
                    $meta['description'] = $text;

                    if (is_array($diagnostics))
                    {
                        if ($query === '//article//p') $diagnostics['description_source'] = 'Article paragraph';
                        elseif ($query === '//main//p' || $query === '//*[@role="main"]//p') $diagnostics['description_source'] = 'Main-content paragraph';
                        else $diagnostics['description_source'] = 'Body paragraph';
                    }

                    break 2;
                }
            }
        }
    }

    $image = $first_meta(array('og:image:secure_url','og:image'));
    if ($image !== '' && is_array($diagnostics)) $diagnostics['image_source'] = 'Open Graph';
    if ($image === '')
    {
        $image = $first_meta(array('twitter:image','twitter:image:src'),'name');
        if ($image !== '' && is_array($diagnostics)) $diagnostics['image_source'] = 'Twitter Card';
    }
    if ($image === '')
    {
        $image = $jsonld['image'];
        if ($image !== '' && is_array($diagnostics)) $diagnostics['image_source'] = 'JSON-LD';
    }
    if ($image === '')
    {
        $nodes = $xpath->query('//link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " image_src ")]/@href');
        if ($nodes && $nodes->length)
        {
            $image = trim($nodes->item(0)->nodeValue);
            if ($image !== '' && is_array($diagnostics)) $diagnostics['image_source'] = 'image_src';
        }
    }
    if ($image === '')
    {
        $image = betterposts_find_meaningful_page_image($xpath, $final_url);
        if ($image !== '' && is_array($diagnostics)) $diagnostics['image_source'] = 'Page image fallback';
    }
    if ($image === '')
    {
        $image = betterposts_find_page_icon($xpath, $final_url);
        if ($image !== '' && is_array($diagnostics)) $diagnostics['image_source'] = 'Icon/favicon fallback';
    }

    $meta['title']=betterposts_clean_meta_text($meta['title'],220);
    $meta['description']=betterposts_clean_meta_text($meta['description'],420);
    $meta['site_name']=betterposts_clean_meta_text($meta['site_name'],120);
    if ($meta['title']==='') $meta['title']=$meta['site_name']!=='' ? $meta['site_name'] : $domain;
    if ($image!=='') $meta['image']=betterposts_resolve_url($final_url, html_entity_decode($image, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    libxml_clear_errors();
    libxml_use_internal_errors($old);
    return $meta;
}

function betterposts_diagnose_url($url)
{
    $diagnostics = array(
        'requested_url' => $url,
        'success' => false,
        'final_url' => '',
        'http_status' => 0,
        'content_type' => '',
        'fetch_seconds' => 0,
        'response_bytes' => 0,
        'truncated' => false,
        'title_source' => '',
        'site_source' => '',
        'description_source' => '',
        'image_source' => '',
        'title' => '',
        'site_name' => '',
        'description' => '',
        'image' => '',
        'image_fetchable' => false,
        'image_content_type' => '',
        'image_bytes' => 0,
        'error' => '',
    );

    $meta = betterposts_fetch_page_metadata($url, $diagnostics);

    if ($meta === false)
    {
        $diagnostics['error'] = 'The page could not be fetched or parsed by the link preview engine.';
        return $diagnostics;
    }

    $diagnostics['success'] = true;
    $diagnostics['title'] = $meta['title'];
    $diagnostics['site_name'] = $meta['site_name'];
    $diagnostics['description'] = $meta['description'];
    $diagnostics['image'] = !empty($meta['image']) ? $meta['image'] : '';

    if (!empty($meta['image']))
    {
        $runtime = betterposts_runtime_settings();

        $image_response = betterposts_safe_http_get(
            $meta['image'],
            $runtime['max_image_kb'] * 1024,
            array(
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/x-icon',
                'image/vnd.microsoft.icon',
            ),
            0,
            false
        );

        if ($image_response !== false)
        {
            $diagnostics['image_fetchable'] = true;
            $diagnostics['image_content_type'] = $image_response['content_type'];
            $diagnostics['image_bytes'] = $image_response['bytes'];
        }
    }

    return $diagnostics;
}


function betterposts_text_is_meaningful($text)
{
    $text=trim((string)$text);
    if ($text==='') return false;
    $length=function_exists('mb_strlen') ? mb_strlen($text,'UTF-8') : strlen($text);
    if ($length<40) return false;
    $lower=function_exists('mb_strtolower') ? mb_strtolower($text,'UTF-8') : strtolower($text);
    foreach (array('accept cookies','cookie policy','privacy policy','sign in','log in','subscribe to','enable javascript') as $needle)
        if (strpos($lower,$needle)!==false && $length<180) return false;
    return true;
}

function betterposts_find_meaningful_page_image($xpath,$base_url)
{
    $nodes=$xpath->query('//img');
    if (!$nodes) return '';
    $best=''; $best_score=-9999;
    foreach ($nodes as $img)
    {
        $src='';
        foreach (array('src','data-src','data-lazy-src','data-original') as $attr)
            if ($img->hasAttribute($attr) && trim($img->getAttribute($attr))!=='') { $src=trim($img->getAttribute($attr)); break; }
        if ($src==='' && $img->hasAttribute('srcset')) $src=betterposts_pick_srcset_url($img->getAttribute('srcset'));
        if ($src==='' || strpos($src,'data:')===0 || strpos($src,'blob:')===0) continue;
        $lower=strtolower($src.' '.$img->getAttribute('class').' '.$img->getAttribute('id'));
        foreach (array('pixel','spacer','tracking','avatar','emoji','icon-','sprite','badge') as $bad)
            if (strpos($lower,$bad)!==false) continue 2;
        $width=(int)$img->getAttribute('width'); $height=(int)$img->getAttribute('height');
        if (($width>0 && $width<120) || ($height>0 && $height<80)) continue;
        $score=0;
        if ($width>=300) $score+=4; elseif ($width>=180) $score+=2;
        if ($height>=160) $score+=3; elseif ($height>=100) $score+=1;
        if (strpos($lower,'hero')!==false || strpos($lower,'featured')!==false) $score+=4;
        if (strpos($lower,'article')!==false || strpos($lower,'content')!==false) $score+=2;
        if (strpos($lower,'logo')!==false) $score-=1;
        if ($score>$best_score)
        {
            $resolved=betterposts_resolve_url($base_url, html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($resolved!==false) { $best=$resolved; $best_score=$score; }
        }
    }
    return $best;
}

function betterposts_pick_srcset_url($srcset)
{
    $best=''; $best_width=0;
    foreach (explode(',',(string)$srcset) as $candidate)
    {
        $candidate=trim($candidate); if ($candidate==='') continue;
        $parts=preg_split('~\\s+~',$candidate); $url=$parts[0]; $width=0;
        if (!empty($parts[1]) && preg_match('~^(\\d+)w$~',$parts[1],$m)) $width=(int)$m[1];
        if ($best==='' || $width>$best_width) { $best=$url; $best_width=$width; }
    }
    return $best;
}

function betterposts_find_page_icon($xpath,$base_url)
{
    foreach (array(
        '//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "apple-touch-icon")]/@href',
        '//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "icon")]/@href'
    ) as $query)
    {
        $nodes=$xpath->query($query); if (!$nodes) continue;
        foreach ($nodes as $node)
        {
            $href=trim($node->nodeValue); if ($href==='') continue;
            $resolved=betterposts_resolve_url($base_url, html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($resolved!==false) return $resolved;
        }
    }
    $parts=parse_url($base_url);
    if (!empty($parts['scheme']) && !empty($parts['host']))
    {
        $port=!empty($parts['port']) ? ':'.(int)$parts['port'] : '';
        return $parts['scheme'].'://'.$parts['host'].$port.'/favicon.ico';
    }
    return '';
}

/**
 * Security-focused HTTP(S) fetch.
 *
 * Protections:
 * - HTTP/HTTPS only
 * - rejects credentials in URL
 * - DNS resolution must be public
 * - cURL is pinned to the validated IP using CURLOPT_RESOLVE
 * - private/reserved/loopback ranges rejected
 * - manual redirect handling with validation at every hop
 * - TLS verification remains enabled
 * - strict connection/total timeout
 * - response body byte cap
 * - content-type allowlist
 */
function betterposts_safe_http_get($url, $max_bytes, $allowed_types, $redirects = 0, $accept_truncated = false)
{
    if (!function_exists('curl_init') || $redirects > 3)
        return false;

    $url = betterposts_normalize_url($url);

    if ($url === false)
        return false;

    $parts = parse_url($url);

    if (empty($parts['host']))
        return false;

    $resolved_ip = betterposts_resolve_public_host($parts['host']);

    if ($resolved_ip === false)
        return false;

    $scheme = strtolower($parts['scheme']);
    $port = !empty($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);

    $headers = array();
    $body = '';
    $truncated = false;
    $runtime = betterposts_runtime_settings();

    $ch = curl_init($url);

    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => $runtime['connect_timeout'],
        CURLOPT_TIMEOUT => $runtime['total_timeout'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BetterLinks-LinkPreview/1.0.4; +forum-link-preview)',
        CURLOPT_HTTPHEADER => array(
            'Accept: text/html,application/xhtml+xml,image/avif,image/webp,image/*;q=0.8,*/*;q=0.1',
            'Accept-Language: en-US,en;q=0.8',
        ),
        CURLOPT_ENCODING => '',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_RESOLVE => array(
            $parts['host'] . ':' . $port . ':' . $resolved_ip,
        ),
        CURLOPT_IPRESOLVE => filter_var($resolved_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? CURL_IPRESOLVE_V4
            : CURL_IPRESOLVE_WHATEVER,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers)
        {
            $len = strlen($line);
            $line = trim($line);

            if ($line !== '' && strpos($line, ':') !== false)
            {
                list($name, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            return $len;
        },
        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$truncated, $max_bytes)
        {
            $remaining = $max_bytes - strlen($body);

            if ($remaining <= 0)
            {
                $truncated = true;
                return 0;
            }

            if (strlen($chunk) > $remaining)
            {
                $body .= substr($chunk, 0, $remaining);
                $truncated = true;

                // Deliberately abort once we have enough metadata bytes.
                return 0;
            }

            $body .= $chunk;
            return strlen($chunk);
        },
    ));

    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $content_type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $total_time = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $curl_error = curl_errno($ch);
    $curl_error_text = $curl_error ? curl_error($ch) : '';
    curl_close($ch);

    // Redirects are handled manually so every destination is SSRF-checked.
    if ($status >= 300 && $status < 400 && !empty($headers['location']))
    {
        $next = betterposts_resolve_url($url, $headers['location']);

        if ($next === false)
            return false;

        return betterposts_safe_http_get(
            $next,
            $max_bytes,
            $allowed_types,
            $redirects + 1,
            $accept_truncated
        );
    }

    /*
     * cURL reports CURLE_WRITE_ERROR when our write callback intentionally
     * stops at the byte cap. For HTML metadata this is a successful bounded
     * fetch: Open Graph/title data normally lives near the start of the page.
     * Images remain strict and are rejected if they exceed their cap.
     */
    $intentional_cutoff = $truncated
        && $accept_truncated
        && defined('CURLE_WRITE_ERROR')
        && $curl_error === CURLE_WRITE_ERROR;

    if ((!$ok || $curl_error) && !$intentional_cutoff)
        return false;

    if ($status < 200 || $status >= 300)
        return false;

    $content_type = strtolower(trim(explode(';', $content_type)[0]));
    $allowed = false;

    foreach ($allowed_types as $type)
    {
        if ($content_type === strtolower($type))
        {
            $allowed = true;
            break;
        }

        if (substr($type, -2) === '/*' && strpos($content_type, substr($type, 0, -1)) === 0)
        {
            $allowed = true;
            break;
        }
    }

    if (!$allowed)
        return false;

    return array(
        'url' => $url,
        'body' => $body,
        'content_type' => $content_type,
        'status' => $status,
        'total_time' => $total_time,
        'bytes' => strlen($body),
        'truncated' => $truncated,
        'curl_error' => $curl_error,
        'curl_error_text' => $curl_error_text,
    );
}

/**
 * Resolve a hostname and return one validated public IP.
 *
 * If any resolved address is private/reserved, reject the hostname completely.
 * This deliberately prefers safety over preview coverage.
 */
function betterposts_resolve_public_host($host)
{
    $host = trim($host, '[]');

    if (filter_var($host, FILTER_VALIDATE_IP))
        return betterposts_ip_is_public($host) ? $host : false;

    $ips = array();

    if (function_exists('dns_get_record'))
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records))
        {
            foreach ($records as $record)
            {
                if (!empty($record['ip']))
                    $ips[] = $record['ip'];
                elseif (!empty($record['ipv6']))
                    $ips[] = $record['ipv6'];
            }
        }
    }

    if (empty($ips))
    {
        $fallback = @gethostbynamel($host);
        if (is_array($fallback))
            $ips = $fallback;
    }

    $ips = array_values(array_unique($ips));

    if (empty($ips))
        return false;

    foreach ($ips as $ip)
    {
        if (!betterposts_ip_is_public($ip))
            return false;
    }

    // Prefer IPv4 for broad CURLOPT_RESOLVE compatibility.
    foreach ($ips as $ip)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            return $ip;

    return $ips[0];
}

function betterposts_ip_is_public($ip)
{
    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function betterposts_normalize_url($url)
{
    $url = trim($url);

    if ($url === '' || strlen($url) > 2048)
        return false;

    $parts = @parse_url($url);

    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']))
        return false;

    $scheme = strtolower($parts['scheme']);

    if ($scheme !== 'http' && $scheme !== 'https')
        return false;

    if (isset($parts['user']) || isset($parts['pass']))
        return false;

    // Link previews are an HTTP document feature, not a general-purpose port
    // scanner. Restrict explicit ports to normal web service ports.
    if (isset($parts['port']) && !in_array((int)$parts['port'], array(80, 443), true))
        return false;

    // Control characters are never valid in a preview target.
    if (preg_match('~[\x00-\x1F\x7F]~', $url))
        return false;

    return $url;
}

/**
 * Resolve absolute, scheme-relative, root-relative and path-relative URLs.
 */
function betterposts_resolve_url($base, $relative)
{
    $relative = trim($relative);

    if ($relative === '')
        return false;

    if (preg_match('~^https?://~i', $relative))
        return betterposts_normalize_url($relative);

    $base_parts = parse_url($base);

    if (empty($base_parts['scheme']) || empty($base_parts['host']))
        return false;

    if (strpos($relative, '//') === 0)
        return betterposts_normalize_url($base_parts['scheme'] . ':' . $relative);

    $port = isset($base_parts['port']) ? ':' . (int)$base_parts['port'] : '';
    $origin = $base_parts['scheme'] . '://' . $base_parts['host'] . $port;

    if ($relative[0] === '/')
        return betterposts_normalize_url($origin . $relative);

    $path = isset($base_parts['path']) ? $base_parts['path'] : '/';
    $dir = preg_replace('~/[^/]*$~', '/', $path);
    $combined = $dir . $relative;

    $segments = array();
    foreach (explode('/', $combined) as $segment)
    {
        if ($segment === '' || $segment === '.')
            continue;

        if ($segment === '..')
            array_pop($segments);
        else
            $segments[] = $segment;
    }

    return betterposts_normalize_url($origin . '/' . implode('/', $segments));
}

function betterposts_clean_meta_text($text, $max_length)
{
    $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('~\s+~u', ' ', strip_tags($text));
    $text = trim($text);

    if (function_exists('mb_substr'))
        return mb_substr($text, 0, $max_length, 'UTF-8');

    return substr($text, 0, $max_length);
}

/**
 * Download and cache an Open Graph image on the forum server.
 *
 * This avoids exposing every reader's IP address to the previewed site.
 */
function betterposts_cache_preview_image($image_url)
{
    global $boarddir;

    $runtime = betterposts_runtime_settings();

    $response = betterposts_safe_http_get(
        $image_url,
        $runtime['max_image_kb'] * 1024,
        array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'),
        0,
        false
    );

    if ($response === false || empty($response['body']))
        return '';

    $mime = $response['content_type'];
    $extensions = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    );

    if (empty($extensions[$mime]))
        return '';

    // Confirm normal raster bytes are actually images. ICO parsing support
    // varies across PHP/GD builds, so MIME + byte cap guard favicon ICO files.
    if (
        $mime !== 'image/x-icon'
        && $mime !== 'image/vnd.microsoft.icon'
        && function_exists('getimagesizefromstring')
        && @getimagesizefromstring($response['body']) === false
    )
        return '';

    $dir = $boarddir . '/betterposts_cache';

    if (!is_dir($dir) && !@mkdir($dir, 0755, true))
        return '';

    if (!file_exists($dir . '/index.html'))
        @file_put_contents($dir . '/index.html', '');

    $filename = hash('sha256', $response['url']) . '.' . $extensions[$mime];
    $path = $dir . '/' . $filename;

    if (!file_exists($path) && @file_put_contents($path, $response['body'], LOCK_EX) === false)
        return '';

    return 'betterposts_cache/' . $filename;
}

/**
 * Normalize cached metadata for display and remove redundant fields.
 *
 * A surprising number of sites use the same text for og:title,
 * og:description and og:site_name. That is technically valid metadata but
 * produces a clumsy card. Keep the card hierarchy clean:
 *
 *   site name
 *   page title
 *   useful description (only when meaningfully different)
 *   domain
 */
function betterposts_polish_card_metadata($row)
{
    $domain = trim((string)$row['domain']);
    $site = trim((string)$row['site_name']);
    $title = trim((string)$row['title']);
    $description = trim((string)$row['description']);

    $norm = function ($value)
    {
        $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('~\s+~u', ' ', strip_tags($value));
        $value = trim($value);

        if (function_exists('mb_strtolower'))
            return mb_strtolower($value, 'UTF-8');

        return strtolower($value);
    };

    $domain_key = $norm(preg_replace('~^www\.~i', '', $domain));
    $site_key = $norm($site);
    $title_key = $norm($title);
    $description_key = $norm($description);

    // Site name fallback.
    if ($site === '')
        $site = $domain;

    // Title fallback.
    if ($title === '' || $title_key === $domain_key)
        $title = $site !== '' ? $site : $domain;

    // If title and site are identical, keep the title as the prominent line
    // and suppress the smaller site label to avoid visual repetition.
    $show_site = $site !== '' && $norm($site) !== $norm($title);

    // Description must add information. Suppress repeated/near-empty metadata.
    if (
        $description === ''
        || $description_key === $title_key
        || $description_key === $site_key
        || $description_key === $domain_key
        || strlen($description_key) < 12
    )
        $description = '';

    return array(
        'site_name' => $site,
        'title' => $title,
        'description' => $description,
        'domain' => $domain,
        'show_site' => $show_site,
    );
}


/**
 * Render the author's original URL in-place and leave a private marker for
 * integrate_prepare_display_context to collect later.
 */
function betterposts_render_link_reference($preview_id, $fallback_url)
{
    $url = betterposts_normalize_url(
        html_entity_decode((string)$fallback_url, ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );

    if ($url === false)
        return '';

    $link = betterposts_render_plain_link($url);

    if (!betterposts_link_previews_available() || empty($preview_id))
        return $link;

    return $link
        . '<span class="betterposts-preview-marker"'
        . ' data-preview-id="' . (int)$preview_id . '"'
        . ' data-preview-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
        . '></span>';
}


/**
 * Render a cached card. No remote requests happen here.
 */
function betterposts_render_link_card($preview_id, $fallback_url)
{
    global $smcFunc, $boardurl;

    if (!betterposts_link_previews_available())
        return betterposts_render_plain_link(
            betterposts_normalize_url(
                html_entity_decode((string)$fallback_url, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            )
        );

    $fallback_url = betterposts_normalize_url(
        html_entity_decode((string)$fallback_url, ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );

    if (empty($preview_id))
        return betterposts_render_plain_link($fallback_url);

    $request = $smcFunc['db_query']('', '
        SELECT url, final_url, domain, site_name, title, description, image_path, status
        FROM {db_prefix}betterposts_link_previews
        WHERE id_preview = {int:id_preview}
        LIMIT 1',
        array('id_preview' => (int)$preview_id)
    );

    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    if (empty($row) || $row['status'] !== 'ready')
        return betterposts_render_plain_link($fallback_url);

    $href = betterposts_normalize_url($row['final_url']);

    if ($href === false)
        $href = betterposts_normalize_url($row['url']);

    if ($href === false)
        return betterposts_render_plain_link($fallback_url);

    $esc = function ($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    };

    $meta = betterposts_polish_card_metadata($row);

    $image_html = '';

    if (!empty($row['image_path']))
    {
        $image_src = rtrim($boardurl, '/') . '/' . ltrim($row['image_path'], '/');
        $image_html = '<span class="betterposts-link-card-image"><img src="'
            . $esc($image_src)
            . '" alt="" loading="lazy"></span>';
    }

    $site_html = $meta['show_site']
        ? '<span class="betterposts-link-card-site">' . $esc($meta['site_name']) . '</span>'
        : '';

    $description_html = $meta['description'] === ''
        ? ''
        : '<span class="betterposts-link-card-description">' . $esc($meta['description']) . '</span>';

    return '<a class="betterposts-link-card' . ($image_html === '' ? ' betterposts-link-card-no-image' : '')
        . '" href="' . $esc($href)
        . '" target="_blank" rel="noopener noreferrer nofollow">'
        . $image_html
        . '<span class="betterposts-link-card-body">'
        . $site_html
        . '<span class="betterposts-link-card-title">' . $esc($meta['title']) . '</span>'
        . $description_html
        . '<span class="betterposts-link-card-footer">'
        . '<span class="betterposts-link-card-domain">' . $esc($meta['domain']) . '</span>'
        . '<span class="betterposts-link-card-arrow" aria-hidden="true">↗</span>'
        . '</span>'
        . '</span></a>';
}

function betterposts_render_plain_link($url)
{
    if ($url === false || $url === '')
        return '';

    $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    return '<a class="bbc_link" href="' . $safe
        . '" target="_blank" rel="noopener noreferrer nofollow">'
        . $safe . '</a>';
}

