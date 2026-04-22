<?php

/**
 * Plugin Name: Claspo - Popups, Spin the Wheel & Email Capture
 * Description: Grow your email list and increase sales! Use the Claspo Popup Maker plugin to create pop-up windows, Spin the Wheel, Exit Intent, and Lead Gen forms.
 * Version: 1.2.0
 * Author: Claspo Popup Builder team
 * Author URI: https://www.claspo.io
 * License: GPL-2.0+
 * WC requires at least: 9.0
 * WC tested up to: 10.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const CLASPO_GET_SCRIPT_URL  = 'https://script.claspo.io/site-script/v1/site/script/';
const CLASPO_EVENT_URL       = 'https://script.claspo.io/site-script/v1/event';
const CLASPO_STATE_TRANSIENT = 'claspo_connect_state_';
const CLASPO_STATE_TTL       = 30 * MINUTE_IN_SECONDS;

add_action( 'admin_menu', 'claspo_add_admin_menu' );
add_action( 'admin_post_claspo_save_script', 'claspo_save_script' );
add_action( 'admin_init', 'claspo_check_script_id' );
add_action( 'admin_enqueue_scripts', 'claspo_enqueue_admin_scripts' );
add_action( 'rest_api_init', 'claspo_register_rest_routes' );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

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
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'claspo_script_plugin' ) {
        return;
    }

    $has_script_id_param = isset( $_GET['script_id'] ) && ! empty( $_GET['script_id'] );
    $has_state_param     = isset( $_GET['claspo_state'] ) && ! empty( $_GET['claspo_state'] );

    if ( ! $has_script_id_param && ! $has_state_param ) {
        return;
    }

    // If the plugin is already activated (e.g. background POST already succeeded, or a previous manual save),
    // strip the query string so the user lands on a clean plugin page instead of seeing the form again.
    if ( get_option( 'claspo_script_id' ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
        exit;
    }

    // Not yet activated: stash the incoming script_id so templates/form.php can prefill the input.
    // The actual save still requires an explicit click on the Connect button, which is protected by a
    // nonce and capability check in claspo_save_script(). Real validity of the ID is determined by
    // the subsequent call to Claspo's API, which is a stronger guarantee than any local format check.
    if ( $has_script_id_param ) {
        $GLOBALS['claspo_prefill_script_id'] = sanitize_text_field( wp_unslash( $_GET['script_id'] ) );
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

    if ( $success_message && $script_code ) {
        $claspo_verified = get_transient( 'claspo_script_verified' );
        include plugin_dir_path( __FILE__ ) . 'templates/success.php';
        delete_transient( 'claspo_success_message' );
        delete_transient( 'claspo_script_verified' );
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
    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_save_script' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    if ( isset( $_POST['claspo_script_id'] ) ) {
        $script_id = sanitize_text_field( wp_unslash( $_POST['claspo_script_id'] ) );
        claspo_apply_script_id( $script_id );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin' ) );
    exit;
}

/**
 * Fetch the script from Claspo by its ID and, on success, persist it locally,
 * clear caches, and perform the installation self-check ping.
 *
 * Shared by the manual Connect form and the background REST handshake so both
 * entry points stay behaviourally identical. The actual validity of the ID is
 * determined by Claspo's API response, not by any local format check.
 *
 * @param string $script_id
 * @return true|WP_Error True on success; WP_Error with code `api_error` or
 *                       `empty_body` otherwise. Errors also populate the
 *                       `claspo_api_error` transient for UI display.
 */
function claspo_apply_script_id( $script_id ) {
    $response = wp_remote_get( CLASPO_GET_SCRIPT_URL . rawurlencode( $script_id ) );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
        } else {
            $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
            $error_message = $response_body['errorMessage'] ?? 'Invalid response from API';
        }

        set_transient( 'claspo_api_error', $error_message, 30 );
        return new WP_Error( 'api_error', $error_message );
    }

    $body = wp_remote_retrieve_body( $response );

    if ( empty( $body ) ) {
        set_transient( 'claspo_api_error', 'Invalid response from API', 30 );
        return new WP_Error( 'empty_body', 'Invalid response from API' );
    }

    update_option( 'claspo_script_id', $script_id );
    update_option( 'claspo_script_code', $body );
    set_transient( 'claspo_success_message', true, 30 );
    delete_transient( 'claspo_api_error' );

    claspo_clear_cache();

    $verified = claspo_verify_and_ping( $script_id );
    set_transient( 'claspo_script_verified', $verified, 30 );

    return true;
}

function claspo_register_rest_routes() {
    register_rest_route(
        'claspo/v1',
        '/connect',
        array(
            'methods'             => 'POST',
            'callback'            => 'claspo_rest_connect',
            'permission_callback' => '__return_true',
        )
    );
}

/**
 * Background handshake endpoint called by Claspo right after registration.
 *
 * Authentication is based solely on a short-lived one-time `state` token that
 * was generated by this site and passed to Claspo at registration time. The
 * token is atomically consumed via delete_transient() return value, so two
 * concurrent requests cannot both succeed.
 *
 * Responses are intentionally opaque: 200 on success, 400 on any failure,
 * with no details about which step failed. This prevents the endpoint from
 * being used as an enumeration or probing oracle.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function claspo_rest_connect( WP_REST_Request $request ) {
    $params = json_decode( (string) $request->get_body(), true );
    if ( ! is_array( $params ) ) {
        return claspo_rest_connect_error();
    }

    $state     = isset( $params['state'] ) ? sanitize_text_field( (string) $params['state'] ) : '';
    $script_id = isset( $params['script_id'] ) ? sanitize_text_field( (string) $params['script_id'] ) : '';

    // The state token we generate has a known shape (wp_generate_password, 32 chars, [A-Za-z0-9]).
    if ( $state === '' || ! preg_match( '/^[A-Za-z0-9]{16,128}$/', $state ) ) {
        return claspo_rest_connect_error();
    }

    if ( $script_id === '' ) {
        return claspo_rest_connect_error();
    }

    // Atomically consume the state: delete_transient() returns true only if it
    // actually existed. This closes the race window between validation and use.
    if ( ! delete_transient( CLASPO_STATE_TRANSIENT . $state ) ) {
        return claspo_rest_connect_error();
    }

    // If a script is already connected, do not overwrite it from a public endpoint.
    if ( get_option( 'claspo_script_id' ) ) {
        return claspo_rest_connect_error();
    }

    // Real validation of the script_id happens inside claspo_apply_script_id() via the
    // call to Claspo's API — if the ID is not valid, the API returns a non-200 and we bail.
    $result = claspo_apply_script_id( $script_id );
    if ( is_wp_error( $result ) ) {
        return claspo_rest_connect_error();
    }

    return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
}

function claspo_rest_connect_error() {
    return new WP_REST_Response( array( 'error' => 'invalid_request' ), 400 );
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

add_action( 'admin_init', 'claspo_register_settings' );
function claspo_register_settings() {
    register_setting( 'claspo_options_group', 'claspo_script_id', array(
        'sanitize_callback' => 'sanitize_text_field',
    ) );
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
        wp_safe_redirect(admin_url('admin.php?page=claspo_script_plugin'));
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
            global $file_prefix;
            if (function_exists('get_supercache_dir')) {
                get_supercache_dir();
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


function claspo_verify_and_ping( $script_id ) {
    try {
        $home_response = wp_remote_get( home_url( '/' ) . '?claspo_nocache=' . time(), array(
            'timeout'   => 15,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $home_response ) ) {
            return false;
        }

        $html = wp_remote_retrieve_body( $home_response );

        if ( empty( $html ) || stripos( $html, $script_id ) === false ) {
            return false;
        }

        $site_url  = home_url( '/' );
        $site_host = wp_parse_url( $site_url, PHP_URL_HOST );

        $payload = array(
            'scriptVersion' => 'latest',
            'orgId'         => null,
            'siteId'        => null,
            'guid'          => $script_id,
            'url'           => $site_url,
            'message'       => 'SCRIPT_INITIAL_LOAD',
            'log_level'     => 'INFO',
            'data'          => wp_json_encode( array(
                'site'                => $site_host,
                'SCRIPT_INITIAL_LOAD' => 0,
            ) ),
        );

        $ping = wp_remote_post( CLASPO_EVENT_URL, array(
            'timeout' => 10,
            'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            'body'    => wp_json_encode( $payload ),
        ) );

        return ! is_wp_error( $ping ) && wp_remote_retrieve_response_code( $ping ) < 400;
    } catch ( Exception $e ) {
        return false;
    }
}


?>