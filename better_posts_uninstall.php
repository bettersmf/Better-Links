<?php

/**
 * Better Links required uninstaller.
 *
 * ALWAYS runs. Stored Better Links BBC is first normalized to native SMF URL
 * BBC so uninstall never leaves raw private markup visible in posts.
 *
 * @package BetterLinks
 * @version 1.0.4
 */

if (!defined('SMF'))
    die('No direct access...');

global $sourcedir, $smcFunc;

require_once($sourcedir . '/Subs.php');

$betterposts_native_url = function ($url, $self_labelled)
{
    $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (
        !preg_match('~^https?://[^\s<>\[\]]+$~iu', $url)
        || strlen($url) > 2048
    )
        return $url;

    return $self_labelled
        ? '[url=' . $url . ']' . $url . '[/url]'
        : '[url]' . $url . '[/url]';
};

$request = $smcFunc['db_query']('', '
    SELECT id_msg, body
    FROM {db_prefix}messages
    WHERE body LIKE {string:betterlink}
        OR body LIKE {string:nopreview}',
    array(
        'betterlink' => '%[betterlink=%',
        'nopreview' => '%[nopreview]%',
    )
);

$updates = array();

while ($row = $smcFunc['db_fetch_assoc']($request))
{
    $body = (string)$row['body'];

    $new_body = preg_replace_callback(
        '~\[betterlink=\d+\](https?://.*?)\[/betterlink\]~isu',
        function ($match) use ($betterposts_native_url)
        {
            return $betterposts_native_url($match[1], true);
        },
        $body
    );

    $new_body = preg_replace_callback(
        '~\[nopreview\](https?://.*?)\[/nopreview\]~isu',
        function ($match) use ($betterposts_native_url)
        {
            return $betterposts_native_url($match[1], false);
        },
        $new_body
    );

    if ($new_body !== $body)
        $updates[] = array(
            'id_msg' => (int)$row['id_msg'],
            'body' => $new_body,
        );
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
}

$source_file = $sourcedir . '/BetterPosts.php';

$hooks = array(
    array('integrate_load_theme', 'betterposts_load_theme'),
    array('integrate_preparsecode', 'betterposts_preparse_link_cards'),
    array('integrate_unpreparsecode', 'betterposts_unpreparse_link_cards'),
    array('integrate_bbc_codes', 'betterposts_bbc_codes'),
    array('integrate_admin_areas', 'betterposts_admin_areas'),
    array('integrate_prepare_display_context', 'betterposts_prepare_display_context'),
    array('integrate_bbc_buttons', 'betterposts_editor_buttons'),
    array('integrate_preparsecode', 'betterposts_preparse_mentions'),
);

foreach ($hooks as $hook)
{
    remove_integration_function($hook[0], $hook[1], true, '$sourcedir/BetterPosts.php');
    remove_integration_function($hook[0], $hook[1], true, $source_file);
    remove_integration_function($hook[0], $hook[1], true);
}

updateSettings(array(
    'betterposts_version' => null,
));
