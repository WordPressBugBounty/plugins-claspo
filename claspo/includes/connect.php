<?php
/**
 * The registration route out of the connect screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'claspo_register_connected_route' );

/**
 * Lets the connect screen find out that the site got connected behind its back.
 *
 * The registration route opens Claspo in a second tab, and the connection is
 * made server to server from there. The tab the owner came from knows nothing
 * about it and keeps offering to register — which is what they see when they
 * switch back, after they have already signed up.
 *
 * @return void
 */
function claspo_register_connected_route() {
    register_rest_route(
        'claspo/v1',
        '/connected',
        array(
            'methods'             => 'GET',
            'callback'            => 'claspo_rest_connected',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        )
    );
}

/**
 * @return WP_REST_Response
 */
function claspo_rest_connected() {
    $response = new WP_REST_Response(
        array( 'connected' => (bool) get_option( 'claspo_script_code' ) ),
        200
    );

    $response->header( 'Cache-Control', 'no-store, max-age=0' );

    return $response;
}

/**
 * URL for "No Claspo account yet?".
 *
 * Three things ride on it and all three matter:
 *
 *   domain              the site being connected, so Claspo can pre-fill it
 *   integration_source  where the signup came from
 *   state               a one-time secret this site generates and remembers
 *                       for CLASPO_STATE_TTL
 *
 * The state token is the whole mechanism. After registration Claspo calls this
 * site's handshake endpoint back with that token and the new script id.
 * claspo_rest_connect(), behind POST claspo/v1/connect, consumes it to save the
 * id. Without the token this is an ordinary signup page and the site is never
 * connected: the user has to go and copy a Script ID by hand, which is the
 * thing this route exists to avoid.
 *
 * The domain keeps any subdirectory the site is installed in, so only the
 * scheme is stripped rather than parsing out the host.
 *
 * @return string
 */
function claspo_registration_url() {
    $domain = get_site_url();
    $domain = str_replace( array( 'https://', 'http://' ), '', $domain );

    $state = wp_generate_password( 32, false );

    set_transient( CLASPO_STATE_TRANSIENT . $state, 1, CLASPO_STATE_TTL );

    return 'https://my.claspo.io/auth-ui/#registration?'
        . 'domain=' . urlencode( $domain )
        . '&integration_source=wordpress'
        . '&state=' . urlencode( $state );
}
