<?php
/**
 * Talks to Claspo and persists the result. Everything that touches the network
 * or the database lives here; the derivation itself is in state.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return array Status with every key present, even on a fresh install.
 */
function claspo_get_status() {
    // Nothing stored means nothing has been checked, which is not the same as
    // nothing being connected: the script may well be on the site already.
    $defaults = array(
        'state'         => get_option( 'claspo_script_id' ) ? CLASPO_STATE_UNKNOWN : CLASPO_STATE_NOT_CONNECTED,
        'widgets_total' => 0,
        'widgets_live'  => 0,
        'branded'       => null,
        'account_id'    => null,
        'site_id'       => null,
        'checked_at'    => 0,
        'last_error'    => null,
        'widgets'       => array(),
    );

    $stored = get_option( 'claspo_status' );

    return is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
}

/**
 * Fetches the public configuration for a script ID.
 *
 * @param string $script_id
 * @return array|WP_Error Decoded body, or WP_Error with code
 *                        claspo_not_found / claspo_http_error / claspo_bad_json / the transport error.
 */
function claspo_fetch_config( $script_id ) {
    $url = CLASPO_GET_SCRIPT_URL . rawurlencode( $script_id ) . '/config';

    // Short, because an admin page is waiting on this. The body is a few
    // kilobytes of JSON; anything slower than this is Claspo being unwell.
    $response = wp_remote_get( $url, array(
        'timeout' => 5,
        'headers' => array( 'Accept' => 'application/json' ),
    ) );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );

    if ( 404 === $code ) {
        return new WP_Error( 'claspo_not_found', 'Claspo does not recognise this script ID.' );
    }

    if ( 200 !== $code ) {
        return new WP_Error(
            'claspo_http_error',
            sprintf( 'Claspo returned an unexpected response code (%d).', $code )
        );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // A 200 is only an answer when it has the shape of one. A proxy, a WAF or
    // a maintenance page can answer 200 with something else, and reading `{}`
    // as "no widgets" would flip a working site to a problem state, reset its
    // streak and raise a notice on one bad response. Every real answer carries
    // the forms object, empty or not.
    if ( ! is_array( $body ) || ! isset( $body['forms'] ) || ! is_array( $body['forms'] ) ) {
        return new WP_Error( 'claspo_bad_json', 'Claspo returned a response we could not read.' );
    }

    return $body;
}

/**
 * Runs one check and persists the outcome.
 *
 * Any failure deliberately leaves the previous state in place and only records
 * last_error: the user cannot fix their host's connectivity, and flipping the UI
 * to a problem state over it would be a lie. That includes a 404, which in
 * practice does not happen: Claspo does not delete scripts, and the plugin
 * refuses to connect an ID Claspo has not confirmed.
 *
 * @return string|null State after the check, or null when the check failed.
 */
function claspo_refresh_status() {
    $script_id = get_option( 'claspo_script_id' );

    if ( empty( $script_id ) ) {
        claspo_store_status( array( 'state' => CLASPO_STATE_NOT_CONNECTED ) );
        return CLASPO_STATE_NOT_CONNECTED;
    }

    $config = claspo_fetch_config( $script_id );

    if ( is_wp_error( $config ) ) {
        $status               = claspo_get_status();
        $status['last_error'] = $config->get_error_message();
        update_option( 'claspo_status', $status, false );

        // Claspo is unreachable. Every admin page asks, so without this one
        // outage would make all of them wait for the timeout.
        set_transient( 'claspo_check_backoff', 1, CLASPO_BACKOFF );

        return null;
    }

    $state   = claspo_compute_state( $config );
    $widgets = claspo_widget_list( $config );

    claspo_store_status( array(
        'state'         => $state,
        'widgets_total' => isset( $config['forms'] ) && is_array( $config['forms'] ) ? count( $config['forms'] ) : 0,
        'widgets_live'  => claspo_count_live_widgets( $config ),
        'widgets'       => $widgets,
        'branded'       => claspo_any_form_branded( $config ),
        'account_id'    => isset( $config['accountId'] ) ? (int) $config['accountId'] : null,
        'site_id'       => isset( $config['siteId'] ) ? (int) $config['siteId'] : null,
    ) );

    claspo_update_history( $state, claspo_any_form_branded( $config ), $widgets );

    return $state;
}

/**
 * Writes a status, filling in checked_at and clearing any previous error.
 *
 * @param array $fields
 * @return void
 */
function claspo_store_status( array $fields ) {
    $status = array_merge( claspo_get_status(), $fields );

    $status['checked_at'] = time();
    $status['last_error'] = null;

    update_option( 'claspo_status', $status, false );
}

/**
 * True when at least one widget carries the Claspo badge, which indicates a
 * free plan. Collected for context only; no copy is driven off it.
 *
 * @param array $config
 * @return bool|null Null when there are no widgets to judge by.
 */
function claspo_any_form_branded( array $config ) {
    if ( empty( $config['forms'] ) || ! is_array( $config['forms'] ) ) {
        return null;
    }

    foreach ( $config['forms'] as $form ) {
        if ( is_array( $form ) && ! empty( $form['branded'] ) ) {
            return true;
        }
    }

    return false;
}
