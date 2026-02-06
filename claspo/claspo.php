<?php

/**
 * Plugin Name: Claspo
 * Plugin URI: https://github.com/Claspo/claspo-wordpress-plugin
 * Description: Grow your email list and increase sales! Use the Claspo Popup Maker plugin to create pop-up windows, Spin the Wheel, Exit Intent, and Lead Gen forms.
 * Version: 1.0.8
 * Author: Claspo Popup Builder team
 * Author URI: https://www.claspo.io
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const CLASPO_GET_SCRIPT_URL = 'https://script.claspo.io/site-script/v1/site/script/';

add_action( 'admin_menu', 'claspo_add_admin_menu' );
add_action( 'admin_post_claspo_save_script', 'claspo_save_script' );
add_action( 'admin_post_claspo_disconnect_script', 'claspo_disconnect_script' );
add_action( 'admin_init', 'claspo_check_script_id' );
add_action( 'admin_enqueue_scripts', 'claspo_enqueue_admin_scripts' );

function claspo_add_admin_menu() {
    $claspo_script_id = get_option('claspo_script_id');
    $menu_title = 'Claspo';

    // Add badge if the script ID is not set
    if (!$claspo_script_id) {
        $menu_title .= ' <span class="awaiting-mod update-plugins count-1"><span class="pending-count">1</span></span>';
    }


//    add_options_page( 'Claspo', 'Claspo', 'manage_options', 'claspo_script_plugin', 'claspo_options_page' );
    add_menu_page( 'Claspo', $menu_title, 'manage_options', 'claspo_script_plugin', 'claspo_options_page', plugin_dir_url( __FILE__ ) . 'img/claspo_logo.png');
}

function claspo_check_script_id() {
    // Security fix for CVE-2025-68568: Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_GET['script_id'] ) && ! empty( $_GET['script_id'] ) ) {
        // Security fix for CVE-2025-68568: Verify nonce for GET requests
        if ( ! isset( $_GET['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['claspo_nonce'] ) ), 'claspo_script_callback' ) ) {
            wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
        }

        $script_id = sanitize_text_field( wp_unslash($_GET['script_id']) );
        update_option( 'claspo_script_id', $script_id );

        $response = wp_remote_get( CLASPO_GET_SCRIPT_URL . $script_id);

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
            } else {
                $responseBody = json_decode( wp_remote_retrieve_body( $response), true);
                $error_message = $responseBody['errorMessage'] ?? 'Invalid response from API';
            }

            set_transient( 'claspo_api_error', $error_message, 30 );
        } else {
            $body = wp_remote_retrieve_body( $response );

            if ( ! empty( $body ) ) {
                update_option( 'claspo_script_id', $script_id );
                update_option( 'claspo_script_code', $body );
                set_transient( 'claspo_success_message', true, 30 );
                delete_transient( 'claspo_api_error' );

                claspo_clear_cache();
            } else {
                set_transient( 'claspo_api_error', 'Invalid response from API', 30 );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
        exit;
    }
}

function claspo_enqueue_admin_scripts( $hook ) {
    if ( $hook != 'toplevel_page_claspo_script_plugin' ) {
        return;
    }

    wp_enqueue_style( 'claspo-admin-style', plugin_dir_url( __FILE__ ) . 'css/main.css' );
    wp_enqueue_script( 'claspo-admin-script', plugin_dir_url( __FILE__ ) . 'js/main2.js', array(), false, true );
}

function claspo_options_page() {
    $script_code     = get_option( 'claspo_script_code' );
    $error_message   = get_transient( 'claspo_api_error' );
    $success_message = get_transient( 'claspo_success_message' );

    if ( isset( $_GET['deactivation_feedback'] ) && $_GET['deactivation_feedback'] == 1 ) {
        include plugin_dir_path( __FILE__ ) . 'templates/feedback.php';
    } elseif ( $success_message && $script_code ) {
        include plugin_dir_path( __FILE__ ) . 'templates/success.php';
        delete_transient( 'claspo_success_message' );
    } /*elseif ( $error_message ) {
        include plugin_dir_path( __FILE__ ) . 'templates/error.php';
        delete_transient( 'claspo_api_error' );
    }*/ elseif ( ! $script_code || $error_message) {
        include plugin_dir_path( __FILE__ ) . 'templates/form.php';

        if ( $error_message ) {
            delete_transient( 'claspo_api_error' );
        }
    } else {
        include plugin_dir_path( __FILE__ ) . 'templates/main.php';
    }
}

function claspo_save_script() {
    /*if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( $_POST['claspo_nonce'], 'claspo_save_script' ) ) {
        return;
    }*/

    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_save_script' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    if ( isset( $_POST['claspo_script_id'] ) ) {
        $script_id = sanitize_text_field( wp_unslash($_POST['claspo_script_id'] ));

        $response = wp_remote_get( CLASPO_GET_SCRIPT_URL . $script_id);

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
            } else {
                $responseBody = json_decode( wp_remote_retrieve_body( $response), true);
                $error_message = $responseBody['errorMessage'] ?? 'Invalid response from API';
            }

            set_transient( 'claspo_api_error', $error_message, 30 );
        } else {
            $body = wp_remote_retrieve_body( $response );

            if ( ! empty( $body ) ) {
                update_option( 'claspo_script_id', $script_id );
                update_option( 'claspo_script_code', $body );
                set_transient( 'claspo_success_message', true, 30 );
                delete_transient( 'claspo_api_error' );

                claspo_clear_cache();
            } else {
                set_transient( 'claspo_api_error', 'Invalid response from API', 30 );
            }
        }
    }

    wp_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
    exit;
}

function claspo_disconnect_script() {
    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_disconnect_script' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    delete_option( 'claspo_script_id' );
    delete_option( 'claspo_script_code' );

    claspo_clear_cache();

    wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
    exit;
}

add_action( 'wp_footer', 'claspo_add_claspo_script' );

function claspo_add_claspo_script() {
    // Реєструємо пустий скрипт
    wp_register_script('claspo-script', false);
    wp_enqueue_script('claspo-script');

    // Отримуємо скрипт з бази даних
    $script_code = get_option( 'claspo_script_code' );

    // Видаляємо теги <script> з коду
    $script_code = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '$1', $script_code);

    // Додаємо скрипт без тегів <script>, якщо він існує
    if ( $script_code ) {
        wp_add_inline_script('claspo-script', $script_code);
    }
}

register_deactivation_hook( __FILE__, 'claspo_deactivation_feedback' );

function claspo_deactivation_feedback() {
    if ( current_user_can( 'manage_options' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin&deactivation_feedback=1' ) );
        exit;
    }
}

add_action( 'admin_post_claspo_send_feedback', 'claspo_send_feedback' );

function claspo_send_feedback() {
    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_feedback_nonce' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    $wp_domain = get_site_url();
    $wp_domain = str_replace('https://', '', $wp_domain);
    $wp_domain = str_replace('http://', '', $wp_domain);

    $script_id = get_option( 'claspo_script_id' );
    $feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash($_POST['feedback']) ) : 'No feedback provided';

    $to = 'integrations.feedback@claspo.io';
    $subject = 'Feedback from WordPress plugin';
    $body = "Domain: " . $wp_domain
            . "\n\nScript ID: " . esc_html($script_id)
            . "\n\nFeedback:\n" . esc_html($feedback)
            . "\n\nPlugin version:\n" . esc_html( get_plugin_data( __FILE__ )['Version'] )
            . "\n\nWordPress version:\n" . esc_html( get_bloginfo( 'version' ) )
            . "\n\nPHP version:\n" . esc_html( phpversion() );
    ;

    wp_mail( $to, $subject, $body );

    delete_option( 'claspo_script_id' );
    delete_option( 'claspo_script_code' );

    deactivate_plugins( plugin_basename( __FILE__ ), true );

    claspo_clear_cache();
    wp_safe_redirect( admin_url( 'plugins.php?deactivated=true' ) );
    exit;
}

add_action( 'admin_init', 'claspo_register_settings' );
function claspo_register_settings() {
    register_setting( 'claspo_options_group', 'claspo_script_id' );
}

// Додаємо функцію для редіректу після активації плагіну
function claspo_plugin_activate() {
    // Зберігаємо змінну, щоб перевірити чи був плагін щойно активований
    add_option('claspo_plugin_activated', true);
}

// Реєструємо функцію активації
register_activation_hook(__FILE__, 'claspo_plugin_activate');

// Перевіряємо чи плагін був щойно активований, і виконуємо редірект
function claspo_plugin_redirect() {
    if (get_option('claspo_plugin_activated', false)) {
        delete_option('claspo_plugin_activated');
        wp_redirect(admin_url('admin.php?page=claspo_script_plugin'));
        exit;
    }
}

// Додаємо дію для виконання редіректу після ініціалізації адміністративної частини
add_action('admin_init', 'claspo_plugin_redirect');


function claspo_clear_cache() {
    try {
        global $wp_fastest_cache;
        // if W3 Total Cache is being used, clear the cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        /* if WP Super Cache is being used, clear the cache */
        if (function_exists('wp_cache_clean_cache')) {
            global $file_prefix, $supercachedir;
            if (empty($supercachedir) && function_exists('get_supercache_dir')) {
                $supercachedir = get_supercache_dir();
            }
            wp_cache_clean_cache($file_prefix);
        }

        if (method_exists('WpFastestCache', 'deleteCache') && !empty($wp_fastest_cache)) {
            $wp_fastest_cache->deleteCache();
        }
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            // Preload cache.
            if (function_exists('run_rocket_sitemap_preload')) {
                run_rocket_sitemap_preload();
            }
        }

        if (class_exists("autoptimizeCache") && method_exists("autoptimizeCache", "clearall")) {
            autoptimizeCache::clearall();
        }

        if (class_exists("LiteSpeed_Cache_API") && method_exists("LiteSpeed_Cache_API", "purge_all")) {
            LiteSpeed_Cache_API::purge_all();
        }

        if (class_exists('\Hummingbird\Core\Utils')) {
            $modules = \Hummingbird\Core\Utils::get_active_cache_modules();
            foreach ($modules as $module => $name) {
                $mod = \Hummingbird\Core\Utils::get_module($module);

                if ($mod->is_active()) {
                    if ('minify' === $module) {
                        $mod->clear_files();
                    } else {
                        $mod->clear_cache();
                    }
                }
            }
        }
    } catch (Exception $e) {
        // do nothing
    }
}


?>