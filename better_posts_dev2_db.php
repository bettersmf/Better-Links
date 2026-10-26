<?php

if (!defined('SMF'))
    die('No direct access...');

global $smcFunc;

if (!isset($smcFunc['db_create_table']))
    db_extend('packages');

$columns = array(
    array('name' => 'id_preview', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
    array('name' => 'url_hash', 'type' => 'char', 'size' => 64, 'default' => ''),
    array('name' => 'url', 'type' => 'text'),
    array('name' => 'final_url', 'type' => 'text'),
    array('name' => 'domain', 'type' => 'varchar', 'size' => 255, 'default' => ''),
    array('name' => 'site_name', 'type' => 'varchar', 'size' => 255, 'default' => ''),
    array('name' => 'title', 'type' => 'varchar', 'size' => 255, 'default' => ''),
    array('name' => 'description', 'type' => 'text'),
    array('name' => 'image_path', 'type' => 'varchar', 'size' => 255, 'default' => ''),
    array('name' => 'status', 'type' => 'varchar', 'size' => 16, 'default' => 'ready'),
    array('name' => 'fetched_at', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'expires_at', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'error_text', 'type' => 'varchar', 'size' => 255, 'default' => ''),
);

$indexes = array(
    array('type' => 'primary', 'columns' => array('id_preview')),
    array('type' => 'unique', 'name' => 'url_hash', 'columns' => array('url_hash')),
    array('type' => 'index', 'name' => 'expires_at', 'columns' => array('expires_at')),
);

$smcFunc['db_create_table'](
    '{db_prefix}betterposts_link_previews',
    $columns,
    $indexes,
    array(),
    'ignore'
);

