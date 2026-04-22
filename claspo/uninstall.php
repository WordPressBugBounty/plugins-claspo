<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'claspo_script_id' );
delete_option( 'claspo_script_code' );
delete_option( 'claspo_plugin_activated' );
delete_transient( 'claspo_success_message' );
delete_transient( 'claspo_api_error' );
delete_transient( 'claspo_script_verified' );

// Handshake state transients have dynamic names (claspo_connect_state_<random>),
// so clean them up by pattern directly from wp_options on both single-site and
// multisite installations.
global $wpdb;

$claspo_state_cleanup = function( $table ) use ( $wpdb ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_claspo_connect_state_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_claspo_connect_state_' ) . '%'
        )
    );
};

if ( is_multisite() ) {
    $site_ids = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        $claspo_state_cleanup( $wpdb->options );
        restore_current_blog();
    }
} else {
    $claspo_state_cleanup( $wpdb->options );
}
