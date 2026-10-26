<?php

/**
 * Better Links OPTIONAL destructive uninstall.
 *
 * Registered as an SMF package <database> uninstall action. SMF executes this
 * file only when the administrator checks the native database-changes removal
 * checkbox on the uninstall confirmation screen.
 *
 * @package BetterLinks
 * @version 1.0.4
 */

if (!defined('SMF'))
    die('No direct access...');

global $smcFunc, $boarddir;

if (!isset($smcFunc['db_drop_table']))
    db_extend('packages');

// Remove Better Links preview data.
$smcFunc['db_drop_table']('{db_prefix}betterposts_link_previews');

// Remove locally cached preview images.
$cache_dir = $boarddir . '/betterposts_cache';

if (is_dir($cache_dir))
{
    foreach (glob($cache_dir . '/*') as $file)
        if (is_file($file))
            @unlink($file);

    @rmdir($cache_dir);
}

// Remove all Better Links settings/configuration that may have been preserved.
$smcFunc['db_query']('', '
    DELETE FROM {db_prefix}settings
    WHERE variable LIKE {string:prefix}',
    array('prefix' => 'betterposts_%')
);
