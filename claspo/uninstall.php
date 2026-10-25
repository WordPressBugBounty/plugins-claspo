<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/**
 * Removes every option and transient this plugin creates on one site.
 *
 * Previously the delete_option() calls ran once for the current site while the
 * transient cleanup looped over the network, so options were left behind on
 * every other site of a multisite install. Both now run per site.
 */
$claspo_cleanup = function ( $table ) use ( $wpdb ) {
    $options = array(
        'claspo_script_id',
        'claspo_script_code',
        'claspo_plugin_activated',
        'claspo_status',
        'claspo_history',
        'claspo_review',
        'claspo_variables',
        'claspo_version',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    $transients = array(
        'claspo_success_message',
        'claspo_api_error',
        'claspo_script_verified',
        'claspo_check_backoff',
    );

    foreach ( $transients as $transient ) {
        delete_transient( $transient );
    }

    // Handshake state and muted notices have dynamic names, so clear them by pattern.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_claspo_connect_state_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_claspo_connect_state_' ) . '%',
            $wpdb->esc_like( '_transient_claspo_notice_muted_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_claspo_notice_muted_' ) . '%'
        )
    );

    // Cron events are stored per site too, so they are cleared per site.
    wp_clear_scheduled_hook( 'claspo_daily_status_check' );
    wp_clear_scheduled_hook( 'claspo_status_check_now' );
};

if ( is_multisite() ) {
    // get_sites() returns 100 sites unless told otherwise; 0 lifts the limit.
    $site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        $claspo_cleanup( $wpdb->options );
        restore_current_blog();
    }
} else {
    $claspo_cleanup( $wpdb->options );
}
