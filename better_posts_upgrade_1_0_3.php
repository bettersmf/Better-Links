<?php

if (!defined('SMF'))
    die('No direct access...');

global $sourcedir, $modSettings;

require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/BetterPosts.php');
require_once(dirname(__FILE__) . '/better_posts_dev2_db.php');

$curl = betterposts_curl_capabilities();

$betterposts_settings = array(
    'betterposts_version' => '1.0.3',
);

$defaults = array(
    'betterposts_link_previews_enabled' => !empty($curl['available']) ? 1 : 0,
    'betterposts_cache_days' => 7,
    'betterposts_connect_timeout' => 5,
    'betterposts_total_timeout' => 15,
    'betterposts_max_html_kb' => 1536,
    'betterposts_max_image_kb' => 2048,
);

foreach ($defaults as $key => $value)
    if (!array_key_exists($key, $modSettings))
        $betterposts_settings[$key] = $value;

updateSettings($betterposts_settings);

add_integration_function('integrate_load_theme', 'betterposts_load_theme', true, '$sourcedir/BetterPosts.php');
add_integration_function('integrate_preparsecode', 'betterposts_preparse_link_cards', true, '$sourcedir/BetterPosts.php');
add_integration_function('integrate_unpreparsecode', 'betterposts_unpreparse_link_cards', true, '$sourcedir/BetterPosts.php');
add_integration_function('integrate_bbc_codes', 'betterposts_bbc_codes', true, '$sourcedir/BetterPosts.php');
add_integration_function('integrate_admin_areas', 'betterposts_admin_areas', true, '$sourcedir/BetterPosts.php');
add_integration_function('integrate_prepare_display_context', 'betterposts_prepare_display_context', true, '$sourcedir/BetterPosts.php');
add_integration_function('integrate_bbc_buttons', 'betterposts_editor_buttons', true, '$sourcedir/BetterPosts.php');
