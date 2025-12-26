<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('sst_settings');

global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_sst_rate_%'
        OR option_name LIKE '_transient_timeout_sst_rate_%'"
);
