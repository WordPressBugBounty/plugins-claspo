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
