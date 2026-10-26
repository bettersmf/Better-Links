<?php

if (!defined('SMF'))
    die('No direct access...');

function template_betterposts_admin()
{
    global $context, $txt, $scripturl;

    $curl = $context['betterposts_curl'];
    $available = !empty($curl['available']);
    $settings = $context['betterposts_runtime_settings'];
    $stats = $context['betterposts_cache_stats'];

    echo '
    <div class="cat_bar"><h3 class="catbg">', $txt['betterposts_admin_title'], '</h3></div>';

    if (!empty($context['betterposts_saved']))
        echo '<div class="infobox">', $txt['betterposts_admin_saved'], '</div>';
    if (!empty($context['betterposts_cache_expired']))
        echo '<div class="infobox">', $txt['betterposts_cache_expired_notice'], '</div>';
    if (!empty($context['betterposts_images_cleared']))
        echo '<div class="infobox">', $txt['betterposts_images_cleared_notice'], '</div>';
    if (!empty($context['betterposts_manager_refreshed']))
        echo '<div class="infobox">', sprintf($txt['betterposts_manager_refreshed_notice'], (int)$context['betterposts_manager_refreshed']), '</div>';
    if (!empty($context['betterposts_manager_expired']))
        echo '<div class="infobox">', sprintf($txt['betterposts_manager_expired_notice'], (int)$context['betterposts_manager_expired']), '</div>';
    if (!empty($context['betterposts_manager_error']))
        echo '<div class="errorbox">', $txt['betterposts_manager_action_failed'], '</div>';
    if ($context['betterposts_orphan_rows_removed'] !== null)
        echo '<div class="infobox">', sprintf(
            $txt['betterposts_orphan_cleanup_notice'],
            (int)$context['betterposts_orphan_rows_removed'],
            (int)$context['betterposts_orphan_files_removed'],
            htmlspecialchars(betterposts_format_bytes($context['betterposts_orphan_bytes_reclaimed']), ENT_QUOTES, 'UTF-8')
        ), '</div>';
    if ($context['betterposts_recovered_links'] !== null)
        echo '<div class="infobox">', sprintf(
            $txt['betterposts_recovery_notice'],
            (int)$context['betterposts_recovered_links'],
            (int)$context['betterposts_recovered_messages'],
            (int)$context['betterposts_recovery_scanned']
        ), '</div>';
    if (!empty($context['betterposts_error']))
        echo '<div class="errorbox">', htmlspecialchars($context['betterposts_error'], ENT_QUOTES, 'UTF-8'), '</div>';

    echo '
    <div class="windowbg">
        <p class="information">', $txt['betterposts_admin_intro'], '</p>

        <div class="title_bar betterposts-admin-joined-title" style="margin-bottom:0!important;border-bottom:0!important;border-radius:7px 7px 0 0!important;"><h4 class="titlebg" style="margin-bottom:0!important;border-radius:7px 7px 0 0!important;">', $txt['betterposts_admin_curl_title'], '</h4></div>
        <div class="betterposts-admin-cell" style="margin-top:0!important;border-top:0!important;border-radius:0 0 7px 7px!important;">
        <dl class="settings">
            <dt><strong>', $txt['betterposts_admin_curl_title'], '</strong></dt>
            <dd><strong>', $available ? $txt['betterposts_admin_curl_available'] : $txt['betterposts_admin_curl_unavailable'], '</strong></dd>
            <dt>', $txt['betterposts_admin_curl_version'], '</dt>
            <dd>', !empty($curl['curl_version']) ? htmlspecialchars($curl['curl_version'], ENT_QUOTES, 'UTF-8') : '&mdash;', '</dd>
            <dt>', $txt['betterposts_admin_ssl_version'], '</dt>
            <dd>', !empty($curl['ssl_version']) ? htmlspecialchars($curl['ssl_version'], ENT_QUOTES, 'UTF-8') : '&mdash;', '</dd>
            <dt>', $txt['betterposts_admin_http'], '</dt>
            <dd>', !empty($curl['http']) ? $txt['betterposts_admin_yes'] : $txt['betterposts_admin_no'], '</dd>
            <dt>', $txt['betterposts_admin_https'], '</dt>
            <dd>', !empty($curl['https']) ? $txt['betterposts_admin_yes'] : $txt['betterposts_admin_no'], '</dd>
        </dl>
        <p class="information">', htmlspecialchars($curl['reason'], ENT_QUOTES, 'UTF-8'), '</p>';

    if (!$available)
        echo '<div class="errorbox">', $txt['betterposts_admin_forced_off'], '</div>';

    echo '
        </div>

        <div class="title_bar betterposts-admin-joined-title" style="margin-bottom:0!important;border-bottom:0!important;border-radius:7px 7px 0 0!important;"><h4 class="titlebg" style="margin-bottom:0!important;border-radius:7px 7px 0 0!important;">', $txt['betterposts_admin_settings_title'], '</h4></div>
        <div class="betterposts-admin-cell" style="margin-top:0!important;border-top:0!important;border-radius:0 0 7px 7px!important;">
        <form action="', $scripturl, '?action=admin;area=betterposts" method="post">
            <dl class="settings">
                <dt><strong>', $txt['betterposts_link_previews'], '</strong><br><span class="smalltext">', $txt['betterposts_link_previews_desc'], '</span></dt>
                <dd><input type="checkbox" name="betterposts_link_previews_enabled" value="1"',
                    !empty($context['betterposts_link_previews_enabled']) ? ' checked' : '',
                    !$available ? ' disabled' : '', '></dd>

                <dt><strong>', $txt['betterposts_cache_days'], '</strong><br><span class="smalltext">', $txt['betterposts_cache_days_desc'], '</span></dt>
                <dd><input type="number" name="betterposts_cache_days" min="1" max="90" value="', (int)$settings['cache_days'], '"></dd>

                <dt><strong>', $txt['betterposts_connect_timeout'], '</strong><br><span class="smalltext">', $txt['betterposts_connect_timeout_desc'], '</span></dt>
                <dd><input type="number" name="betterposts_connect_timeout" min="1" max="15" value="', (int)$settings['connect_timeout'], '"></dd>

                <dt><strong>', $txt['betterposts_total_timeout'], '</strong><br><span class="smalltext">', $txt['betterposts_total_timeout_desc'], '</span></dt>
                <dd><input type="number" name="betterposts_total_timeout" min="3" max="45" value="', (int)$settings['total_timeout'], '"></dd>

                <dt><strong>', $txt['betterposts_max_html_kb'], '</strong><br><span class="smalltext">', $txt['betterposts_max_html_kb_desc'], '</span></dt>
                <dd><input type="number" name="betterposts_max_html_kb" min="256" max="4096" step="128" value="', (int)$settings['max_html_kb'], '"></dd>

                <dt><strong>', $txt['betterposts_max_image_kb'], '</strong><br><span class="smalltext">', $txt['betterposts_max_image_kb_desc'], '</span></dt>
                <dd><input type="number" name="betterposts_max_image_kb" min="128" max="8192" step="128" value="', (int)$settings['max_image_kb'], '"></dd>

            </dl>

            <div class="betterposts-admin-actions">
                <button type="submit" name="save_betterposts" value="1" class="button"', !$available ? ' disabled' : '', '>', $txt['betterposts_admin_save'], '</button>
            </div>
            <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
        </form>
        </div>

        <div class="title_bar"><h4 class="titlebg">', $txt['betterposts_cache_title'], '</h4></div>
        <div class="information">
            <strong>', $txt['betterposts_cache_total'], ':</strong> ', (int)$stats['total'],
            ' &nbsp; | &nbsp; <strong>', $txt['betterposts_cache_ready'], ':</strong> ', (int)$stats['ready'],
            ' &nbsp; | &nbsp; <strong>', $txt['betterposts_cache_failed'], ':</strong> ', (int)$stats['failed'],
            ' &nbsp; | &nbsp; <strong>', $txt['betterposts_cache_expired'], ':</strong> ', (int)$stats['expired'],
            '<br><strong>', $txt['betterposts_cache_images'], ':</strong> ', (int)$stats['images'],
            ' (', betterposts_format_bytes($stats['image_bytes']), ')
        </div>

        <div class="flow_hidden">
            <form action="', $scripturl, '?action=admin;area=betterposts" method="post" style="display:inline-block;margin:0 8px 8px 0;">
                <button type="submit" name="expire_betterposts_cache" value="1" class="button">', $txt['betterposts_expire_cache_button'], '</button>
                <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
            </form>

            <form action="', $scripturl, '?action=admin;area=betterposts" method="post" style="display:inline-block;margin:0 0 8px 0;">
                <button type="submit" name="clear_betterposts_images" value="1" class="button">', $txt['betterposts_clear_images_button'], '</button>
                <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
            </form>
        </div>

        <p class="smalltext">', $txt['betterposts_cache_safe_note'], '</p>

        <div class="betterposts-maintenance-actions">
            <form action="', $scripturl, '?action=admin;area=betterposts" method="post">
                <button type="submit" name="scan_betterposts_orphans" value="1" class="button">', $txt['betterposts_scan_orphans_button'], '</button>
                <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
            </form>

            <form action="', $scripturl, '?action=admin;area=betterposts" method="post" onsubmit="return confirm(', htmlspecialchars(json_encode($txt['betterposts_cleanup_orphans_confirm']), ENT_QUOTES, 'UTF-8'), ');">
                <button type="submit" name="cleanup_betterposts_orphans" value="1" class="button">', $txt['betterposts_cleanup_orphans_button'], '</button>
                <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
            </form>

            <form action="', $scripturl, '?action=admin;area=betterposts" method="post" onsubmit="return confirm(', htmlspecialchars(json_encode($txt['betterposts_recovery_confirm']), ENT_QUOTES, 'UTF-8'), ');">
                <button type="submit" name="recover_betterposts_previews" value="1" class="button">', $txt['betterposts_recovery_button'], '</button>
                <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
            </form>
        </div>

        <p class="smalltext betterposts-recovery-note">', $txt['betterposts_recovery_desc'], '</p>';

    if (!empty($context['betterposts_orphan_scan']))
    {
        $scan = $context['betterposts_orphan_scan'];

        echo '
        <div class="betterposts-orphan-report">
            <strong>', $txt['betterposts_orphan_scan_title'], '</strong><br>
            ', sprintf(
                $txt['betterposts_orphan_scan_result'],
                (int)$scan['orphan_count'],
                htmlspecialchars(betterposts_format_bytes($scan['orphan_bytes']), ENT_QUOTES, 'UTF-8'),
                (int)$scan['stray_count'],
                htmlspecialchars(betterposts_format_bytes($scan['stray_bytes']), ENT_QUOTES, 'UTF-8')
            ), '
        </div>';
    }

    echo '
        <div class="title_bar"><h4 class="titlebg">', $txt['betterposts_test_title'], '</h4></div>
        <p class="information">', $txt['betterposts_test_desc'], '</p>

        <form action="', $scripturl, '?action=admin;area=betterposts" method="post">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="url" name="betterposts_test_url" value="', htmlspecialchars($context['betterposts_test_url'], ENT_QUOTES, 'UTF-8'), '" placeholder="https://example.com/page" style="min-width:320px;flex:1;">
                <button type="submit" name="test_betterposts_url" value="1" class="button"', !$available ? ' disabled' : '', '>', $txt['betterposts_test_button'], '</button>
            </div>
            <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
        </form>';

    if (!empty($context['betterposts_diagnostic']))
        template_betterposts_diagnostic($context['betterposts_diagnostic']);

    template_betterposts_preview_manager();

    echo '</div>';
}

function template_betterposts_diagnostic($diag)
{
    global $txt;

    echo '<div class="title_bar" style="margin-top:14px;"><h4 class="titlebg">', $txt['betterposts_test_results'], '</h4></div>';

    if (empty($diag['success']))
    {
        echo '<div class="errorbox">', htmlspecialchars($diag['error'], ENT_QUOTES, 'UTF-8'), '</div>';
        return;
    }

    $rows = array(
        $txt['betterposts_test_final_url'] => $diag['final_url'],
        $txt['betterposts_test_http_status'] => (string)$diag['http_status'],
        $txt['betterposts_test_content_type'] => $diag['content_type'],
        $txt['betterposts_test_fetch_time'] => number_format((float)$diag['fetch_seconds'], 3) . ' s',
        $txt['betterposts_test_response_size'] => betterposts_format_bytes($diag['response_bytes']),
        $txt['betterposts_test_truncated'] => !empty($diag['truncated']) ? $txt['betterposts_admin_yes'] : $txt['betterposts_admin_no'],
        $txt['betterposts_test_site_source'] => $diag['site_source'],
        $txt['betterposts_test_site'] => $diag['site_name'],
        $txt['betterposts_test_title_source'] => $diag['title_source'],
        $txt['betterposts_test_page_title'] => $diag['title'],
        $txt['betterposts_test_description_source'] => $diag['description_source'],
        $txt['betterposts_test_description'] => $diag['description'],
        $txt['betterposts_test_image_source'] => $diag['image_source'],
        $txt['betterposts_test_image_url'] => $diag['image'],
        $txt['betterposts_test_image_fetchable'] => !empty($diag['image_fetchable']) ? $txt['betterposts_admin_yes'] : $txt['betterposts_admin_no'],
        $txt['betterposts_test_image_type'] => $diag['image_content_type'],
        $txt['betterposts_test_image_size'] => !empty($diag['image_bytes']) ? betterposts_format_bytes($diag['image_bytes']) : '',
    );

    echo '<dl class="settings">';

    foreach ($rows as $label => $value)
        echo '<dt><strong>', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), '</strong></dt><dd>', $value !== '' ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '&mdash;', '</dd>';

    echo '</dl>';
}



function template_betterposts_preview_manager()
{
    global $context, $txt, $scripturl;

    $manager = $context['betterposts_preview_manager'];
    $pager = $context['betterposts_preview_manager_pagination'];

    echo '
        <div class="title_bar betterposts-manager-title">
            <h4 class="titlebg">', $txt['betterposts_manager_title'], '</h4>
        </div>
        <div class="betterposts-manager-shell">
            <p class="information">', $txt['betterposts_manager_desc'], '</p>

            <form action="', $scripturl, '" method="get" class="betterposts-manager-filter">
                <input type="hidden" name="action" value="admin">
                <input type="hidden" name="area" value="betterposts">

                <label>
                    <span class="smalltext">', $txt['betterposts_manager_filter'], '</span>
                    <select name="bpfilter">
                        <option value="all"', $manager['filter'] === 'all' ? ' selected' : '', '>', $txt['betterposts_manager_filter_all'], '</option>
                        <option value="ready"', $manager['filter'] === 'ready' ? ' selected' : '', '>', $txt['betterposts_manager_filter_ready'], '</option>
                        <option value="expired"', $manager['filter'] === 'expired' ? ' selected' : '', '>', $txt['betterposts_manager_filter_expired'], '</option>
                        <option value="failed"', $manager['filter'] === 'failed' ? ' selected' : '', '>', $txt['betterposts_manager_filter_failed'], '</option>
                    </select>
                </label>

                <label class="betterposts-manager-search">
                    <span class="smalltext">', $txt['betterposts_manager_search'], '</span>
                    <input type="search" name="bpsearch" value="', htmlspecialchars($manager['search'], ENT_QUOTES, 'UTF-8'), '" placeholder="', htmlspecialchars($txt['betterposts_manager_search_placeholder'], ENT_QUOTES, 'UTF-8'), '">
                </label>

                <button class="button" type="submit">', $txt['betterposts_manager_apply'], '</button>
            </form>

            <div class="betterposts-manager-summary">
                ', sprintf(
                    $txt['betterposts_manager_showing'],
                    (int)$pager['first_item'],
                    (int)$pager['last_item'],
                    (int)$manager['total']
                ), '
            </div>';

    if (empty($manager['rows']))
    {
        echo '<div class="noticebox">', $txt['betterposts_manager_empty'], '</div>';
    }
    else
    {
        echo '<div class="betterposts-preview-manager-list">';

        foreach ($manager['rows'] as $row)
        {
            $status_key = $row['display_status'];
            $status_text = $txt['betterposts_manager_status_' . $status_key];
            $title = trim((string)$row['title']) !== ''
                ? $row['title']
                : (trim((string)$row['site_name']) !== '' ? $row['site_name'] : $row['domain']);

            echo '
                <div class="betterposts-preview-admin-row">
                    <div class="betterposts-preview-admin-thumb">';

            if (!empty($row['image_url']))
                echo '<img src="', htmlspecialchars($row['image_url'], ENT_QUOTES, 'UTF-8'), '" alt="">';
            else
                echo '<span aria-hidden="true">↗</span>';

            echo '
                    </div>

                    <div class="betterposts-preview-admin-main">
                        <div class="betterposts-preview-admin-heading">
                            <strong>#', (int)$row['id_preview'], ' &mdash; ', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), '</strong>
                            <span class="betterposts-preview-status betterposts-preview-status-', htmlspecialchars($status_key, ENT_QUOTES, 'UTF-8'), '">', htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8'), '</span>
                        </div>

                        <div class="smalltext betterposts-preview-admin-domain">', htmlspecialchars($row['domain'], ENT_QUOTES, 'UTF-8'), '</div>
                        <div class="betterposts-preview-admin-url" title="', htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8'), '">', htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8'), '</div>

                        <div class="smalltext betterposts-preview-admin-dates">
                            ', $txt['betterposts_manager_fetched'], ': ', !empty($row['fetched_at']) ? timeformat($row['fetched_at']) : '&mdash;',
                            ' &nbsp; &bull; &nbsp; ',
                            $txt['betterposts_manager_expires'], ': ', !empty($row['expires_at']) ? timeformat($row['expires_at']) : '&mdash;',
                        '</div>';

            if (!empty($row['error_text']))
                echo '<div class="smalltext betterposts-preview-admin-error">', htmlspecialchars($row['error_text'], ENT_QUOTES, 'UTF-8'), '</div>';

            echo '
                    </div>

                    <div class="betterposts-preview-admin-actions">
                        <form action="', $scripturl, '?action=admin;area=betterposts" method="post">
                            <input type="hidden" name="preview_id" value="', (int)$row['id_preview'], '">
                            <button class="button" type="submit" name="manager_inspect_preview" value="1">', $txt['betterposts_manager_inspect'], '</button>
                            <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
                        </form>

                        <form action="', $scripturl, '?action=admin;area=betterposts" method="post">
                            <input type="hidden" name="preview_id" value="', (int)$row['id_preview'], '">
                            <button class="button" type="submit" name="manager_refresh_preview" value="1">', $txt['betterposts_manager_refresh'], '</button>
                            <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
                        </form>

                        <form action="', $scripturl, '?action=admin;area=betterposts" method="post">
                            <input type="hidden" name="preview_id" value="', (int)$row['id_preview'], '">
                            <button class="button" type="submit" name="manager_expire_preview" value="1">', $txt['betterposts_manager_expire'], '</button>
                            <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
                        </form>
                    </div>
                </div>';
        }

        echo '</div>';
    }

    if (!empty($pager['previous']) || !empty($pager['next']))
    {
        echo '<div class="betterposts-manager-pagination">';

        if (!empty($pager['previous']))
            echo '<a class="button" href="', htmlspecialchars($pager['previous'], ENT_QUOTES, 'UTF-8'), '">', $txt['betterposts_manager_previous'], '</a>';

        if (!empty($pager['next']))
            echo '<a class="button" href="', htmlspecialchars($pager['next'], ENT_QUOTES, 'UTF-8'), '">', $txt['betterposts_manager_next'], '</a>';

        echo '</div>';
    }

    if (!empty($context['betterposts_manager_diagnostic']))
    {
        echo '
            <div class="title_bar betterposts-manager-inspect-title">
                <h4 class="titlebg">', sprintf(
                    $txt['betterposts_manager_inspect_title'],
                    (int)$context['betterposts_manager_inspect_id']
                ), '</h4>
            </div>';

        if (!empty($context['betterposts_manager_inspect_row']['url']))
            echo '<p class="information"><strong>', $txt['betterposts_manager_cached_url'], ':</strong> ', htmlspecialchars($context['betterposts_manager_inspect_row']['url'], ENT_QUOTES, 'UTF-8'), '</p>';

        template_betterposts_diagnostic($context['betterposts_manager_diagnostic']);
    }

    echo '</div>';
}
