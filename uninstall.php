<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('sst_settings');

// Clean up cached transients without direct DB queries.
$zips = get_option('sst_cached_zips', []);
if (is_array($zips)) {
    foreach ($zips as $zip) {
        $zip = preg_replace('/\D+/', '', (string)$zip);
        if (strlen($zip) === 5) {
            delete_transient('sst_rate_' . $zip);
        }
    }
}
delete_option('sst_cached_zips');
