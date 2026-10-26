<?php

/**
 * Better Links language strings.
 *
 * @package BetterLinks
 * @version 1.0.4
 */

$txt['betterposts_name'] = 'Better Links';
$txt['betterposts_admin_title'] = 'Better Links';
$txt['betterposts_admin_intro'] = 'Better Links enhances standalone links with safe, cached preview cards.';
$txt['betterposts_admin_curl_title'] = 'cURL Capability';
$txt['betterposts_admin_curl_available'] = 'Available';
$txt['betterposts_admin_curl_unavailable'] = 'Unavailable';
$txt['betterposts_admin_curl_version'] = 'cURL version';
$txt['betterposts_admin_ssl_version'] = 'SSL/TLS';
$txt['betterposts_admin_http'] = 'HTTP support';
$txt['betterposts_admin_https'] = 'HTTPS support';
$txt['betterposts_admin_requirement'] = 'Link previews require PHP cURL with HTTP, HTTPS and SSL/TLS support.';
$txt['betterposts_admin_settings_title'] = 'Link Preview Settings';
$txt['betterposts_link_previews'] = 'Enable link previews';
$txt['betterposts_link_previews_desc'] = 'Turn standalone URLs into cached preview cards. Inline links remain normal SMF links.';
$txt['betterposts_admin_forced_off'] = 'Link previews cannot be enabled because the required cURL capabilities are unavailable.';
$txt['betterposts_admin_saved'] = 'Better Links settings were saved.';
$txt['betterposts_admin_yes'] = 'Yes';
$txt['betterposts_admin_no'] = 'No';
$txt['betterposts_admin_save'] = 'Save Settings';

$txt['betterposts_cache_days'] = 'Preview cache lifetime (days)';
$txt['betterposts_cache_days_desc'] = 'How long fetched metadata remains fresh before the same URL is eligible for a refresh.';
$txt['betterposts_connect_timeout'] = 'Connection timeout (seconds)';
$txt['betterposts_connect_timeout_desc'] = 'Maximum time allowed to establish the remote connection.';
$txt['betterposts_total_timeout'] = 'Total fetch timeout (seconds)';
$txt['betterposts_total_timeout_desc'] = 'Maximum overall time for a page or image fetch. This is never allowed below the connection timeout.';
$txt['betterposts_max_html_kb'] = 'Maximum HTML size (KB)';
$txt['betterposts_max_html_kb_desc'] = 'Maximum amount of page HTML Better Links will inspect for metadata and opening content.';
$txt['betterposts_max_image_kb'] = 'Maximum preview image size (KB)';
$txt['betterposts_max_image_kb_desc'] = 'Maximum remote image size Better Links will cache locally.';

$txt['betterposts_cache_title'] = 'Preview Cache';
$txt['betterposts_cache_total'] = 'Total previews';
$txt['betterposts_cache_ready'] = 'Ready';
$txt['betterposts_cache_failed'] = 'Failed';
$txt['betterposts_cache_expired'] = 'Expired';
$txt['betterposts_cache_images'] = 'Cached images';
$txt['betterposts_expire_cache_button'] = 'Expire Preview Cache';
$txt['betterposts_clear_images_button'] = 'Clear Cached Images';
$txt['betterposts_cache_safe_note'] = 'Expiring the preview cache does not delete stored preview rows referenced by existing posts. Existing cards keep rendering; the next post of the same URL refreshes its metadata.';
$txt['betterposts_cache_expired_notice'] = 'The preview metadata cache was expired. Existing cards remain intact and matching URLs will refresh when posted again.';
$txt['betterposts_images_cleared_notice'] = 'Cached preview images were cleared and affected preview rows were expired. Existing cards will temporarily render without those images until refreshed.';

$txt['betterposts_test_title'] = 'Test URL';
$txt['betterposts_test_desc'] = 'Run the live Better Links extractor without creating a post or writing a preview-cache row. This shows exactly what the fetcher and extractor can see.';
$txt['betterposts_test_button'] = 'Test Link Preview';
$txt['betterposts_test_results'] = 'Diagnostic Results';
$txt['betterposts_test_requires_curl'] = 'The URL diagnostic requires the same cURL capabilities as live link previews.';
$txt['betterposts_test_invalid_url'] = 'Enter a valid public HTTP or HTTPS URL.';
$txt['betterposts_test_final_url'] = 'Final URL';
$txt['betterposts_test_http_status'] = 'HTTP status';
$txt['betterposts_test_content_type'] = 'Content type';
$txt['betterposts_test_fetch_time'] = 'Fetch time';
$txt['betterposts_test_response_size'] = 'HTML received';
$txt['betterposts_test_truncated'] = 'HTML limit reached';
$txt['betterposts_test_site_source'] = 'Site-name source';
$txt['betterposts_test_site'] = 'Site name';
$txt['betterposts_test_title_source'] = 'Title source';
$txt['betterposts_test_page_title'] = 'Page title';
$txt['betterposts_test_description_source'] = 'Description source';
$txt['betterposts_test_description'] = 'Description / opening text';
$txt['betterposts_test_image_source'] = 'Image source';
$txt['betterposts_test_image_url'] = 'Image URL';
$txt['betterposts_test_image_fetchable'] = 'Image fetchable';
$txt['betterposts_test_image_type'] = 'Image content type';
$txt['betterposts_test_image_size'] = 'Image size';



$txt['betterposts_no_preview_button'] = 'No Preview — keep the selected URL as a normal clickable link';


$txt['betterposts_manager_title'] = 'Preview Manager';
$txt['betterposts_manager_desc'] = 'Browse cached link previews, inspect what the live extractor sees, refresh a single preview, or safely expire one without deleting IDs referenced by existing posts.';
$txt['betterposts_manager_filter'] = 'Status';
$txt['betterposts_manager_filter_all'] = 'All previews';
$txt['betterposts_manager_filter_ready'] = 'Ready';
$txt['betterposts_manager_filter_expired'] = 'Expired';
$txt['betterposts_manager_filter_failed'] = 'Failed';
$txt['betterposts_manager_search'] = 'Search';
$txt['betterposts_manager_search_placeholder'] = 'URL, domain, site name or title';
$txt['betterposts_manager_apply'] = 'Apply';
$txt['betterposts_manager_showing'] = 'Showing %1$d–%2$d of %3$d previews';
$txt['betterposts_manager_empty'] = 'No cached previews match this view.';
$txt['betterposts_manager_status_ready'] = 'Ready';
$txt['betterposts_manager_status_expired'] = 'Expired';
$txt['betterposts_manager_status_failed'] = 'Failed';
$txt['betterposts_manager_fetched'] = 'Fetched';
$txt['betterposts_manager_expires'] = 'Expires';
$txt['betterposts_manager_inspect'] = 'Inspect';
$txt['betterposts_manager_refresh'] = 'Refresh';
$txt['betterposts_manager_expire'] = 'Expire';
$txt['betterposts_manager_previous'] = 'Previous';
$txt['betterposts_manager_next'] = 'Next';
$txt['betterposts_manager_inspect_title'] = 'Live Inspection — Preview #%d';
$txt['betterposts_manager_cached_url'] = 'Cached URL';
$txt['betterposts_manager_missing'] = 'That cached preview could not be found.';
$txt['betterposts_manager_refreshed_notice'] = 'Preview #%d was refreshed in place.';
$txt['betterposts_manager_expired_notice'] = 'Preview #%d was safely expired. Its row was preserved for existing posts.';
$txt['betterposts_manager_action_failed'] = 'The requested Preview Manager action could not be completed.';


$txt['betterposts_scan_orphans_button'] = 'Scan Orphans';
$txt['betterposts_cleanup_orphans_button'] = 'Clean Orphans';
$txt['betterposts_cleanup_orphans_confirm'] = 'Remove preview rows that are no longer referenced by any post, plus unreferenced Better Links cache images? Referenced previews will not be touched.';
$txt['betterposts_orphan_scan_title'] = 'Orphan / cache integrity scan';
$txt['betterposts_orphan_scan_result'] = '%1$d orphan preview row(s) (%2$s of associated images) and %3$d stray cache image(s) (%4$s) found.';
$txt['betterposts_orphan_cleanup_notice'] = 'Orphan cleanup removed %1$d preview row(s), %2$d stray cache image(s), and reclaimed approximately %3$s.';


$txt['betterposts_recovery_button'] = 'Recover Preserved Previews';
$txt['betterposts_recovery_desc'] = 'After reinstalling with preserved data, this reconnects old preview links to matching cached preview rows without fetching the web. Former No Preview links stay normal links.';
$txt['betterposts_recovery_confirm'] = 'Reconnect eligible native links to matching preserved Better Links preview rows? This does not fetch remote pages and does not change former No Preview links.';
$txt['betterposts_recovery_notice'] = 'Recovered %1$d preview link(s) across %2$d message(s) after scanning %3$d candidate message(s).';
