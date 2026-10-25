<?php
/**
 * Keeping the shown status true.
 *
 * Two screens are about the status: the plugin page and the dashboard. There
 * a wrong answer is the whole screen being wrong, so they ask Claspo on every
 * load. Everywhere else in wp-admin the status is a notice in the corner, and
 * an answer a few minutes old is as good as a new one; what is not fine is
 * every admin page waiting on another company's service. Those screens reuse
 * the stored answer while it is younger than CLASPO_STATUS_TTL.
 *
 * The front end never asks inside the request. A visitor's page may not wait
 * on Claspo, so a stale answer there is handed to cron instead.
 *
 * The stored copy is a record of the last answer. The toolbar reads it on the
 * front end, and six places read it within one admin request.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// admin_init rather than init: it runs only in wp-admin, after the user is
// known and before admin_menu draws the badge. template_redirect is the front
// end only, after REST, AJAX and cron requests have already gone their own way.
add_action( 'admin_init', 'claspo_keep_status_fresh' );
add_action( 'template_redirect', 'claspo_keep_status_fresh' );

/**
 * How long to leave Claspo alone after a failed check.
 *
 * This is about a service that is down, not about the data. Without it, an
 * outage would make every admin page wait for the timeout; with it, one page a
 * minute pays that and the rest are quick.
 */
const CLASPO_BACKOFF = 60;

/**
 * How old the stored answer may be on screens that are not about the status.
 * Five minutes.
 */
const CLASPO_STATUS_TTL = 300;

/**
 * Whether this wp-admin request is one of the two screens that show the
 * status as their main content.
 *
 * @return bool
 */
function claspo_is_status_screen() {
    $pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';

    if ( 'index.php' === $pagenow ) {
        return true;
    }

    return 'admin.php' === $pagenow
        && isset( $_GET['page'] )
        && 'claspo_script_plugin' === $_GET['page'];
}

/**
 * @return bool Whether the stored answer is recent enough to reuse.
 */
function claspo_status_is_fresh() {
    $status = claspo_get_status();

    return ! empty( $status['checked_at'] )
        && ( time() - (int) $status['checked_at'] ) < CLASPO_STATUS_TTL;
}

/**
 * What this request should do about the status.
 *
 * Split out from the hook so the decision can be read, and tested, on its own.
 *
 * @return string One of 'ask', 'queue' or 'no'.
 */
function claspo_freshness_action() {
    // Cron is already running the check, and AJAX is somebody waiting on a
    // different answer entirely.
    if ( wp_doing_cron() || wp_doing_ajax() ) {
        return 'no';
    }

    if ( ! get_option( 'claspo_script_code' ) ) {
        return 'no';
    }

    // Nobody else can act on the answer, and this also keeps ordinary visitors
    // out of it.
    if ( ! current_user_can( 'manage_options' ) ) {
        return 'no';
    }

    if ( ! is_admin() ) {
        // The toolbar is the only place the front end shows the answer.
        if ( ! is_admin_bar_showing() || claspo_status_is_fresh() ) {
            return 'no';
        }

        return 'queue';
    }

    // Form handlers. Connecting refreshes on its own, and "Check now" lands on
    // the plugin page, which asks anyway; a check here would be a second round
    // trip for the same click.
    if ( 'admin-post.php' === ( isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '' ) ) {
        return 'no';
    }

    if ( get_transient( 'claspo_check_backoff' ) ) {
        return 'no';
    }

    if ( claspo_is_status_screen() ) {
        return 'ask';
    }

    return claspo_status_is_fresh() ? 'no' : 'ask';
}

/**
 * @return void
 */
function claspo_keep_status_fresh() {
    // Six places read the status within one request; without this they would
    // be six calls to Claspo for one page.
    static $ran = false;

    if ( $ran ) {
        return;
    }

    $ran = true;

    $action = claspo_freshness_action();

    if ( 'ask' === $action ) {
        claspo_refresh_status();
        return;
    }

    if ( 'queue' === $action ) {
        claspo_queue_status_check();
    }
}

/**
 * Hands a check to cron, which WordPress spawns at the end of this request.
 *
 * Used where the answer is wanted but the page cannot wait for it: the toolbar
 * on the front end, which renders for an administrator on every page a visitor
 * would otherwise be served instantly.
 *
 * @return void
 */
function claspo_queue_status_check() {
    if ( wp_next_scheduled( 'claspo_status_check_now' ) ) {
        return;
    }

    wp_schedule_single_event( time(), 'claspo_status_check_now' );
}
