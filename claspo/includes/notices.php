<?php
/**
 * Admin-wide notices for problem states. Nothing is shown while everything works.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_notices', 'claspo_render_problem_notice' );
add_action( 'wp_ajax_claspo_dismiss_notice', 'claspo_ajax_dismiss_notice' );

/**
 * The problem state to act on right now, or null when there is none.
 *
 * @return string|null
 */
function claspo_current_problem_state() {
    $status = claspo_get_status();

    return in_array( $status['state'], claspo_problem_states(), true ) ? $status['state'] : null;
}

/**
 * @return void
 */
function claspo_render_problem_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Not on our own page, and not on the dashboard. The status strip on the
    // first and the Claspo widget on the second already say the same sentence
    // with the same button under it. A notice is for catching someone who is
    // somewhere else in wp-admin.
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( $screen && in_array( $screen->id, array( 'toplevel_page_claspo_script_plugin', 'dashboard' ), true ) ) {
        return;
    }

    $state = claspo_current_problem_state();

    if ( null === $state ) {
        return;
    }

    if ( get_transient( 'claspo_notice_muted_' . $state ) ) {
        return;
    }

    $copy = claspo_status_copy( $state, claspo_get_history() );

    // The upgrade offer appears only when the account is cut off AND on the free
    // plan. A paid account that hit its own ceiling is not helped by "Upgrade to Pro".
    $show_upgrade = ( CLASPO_STATE_RESTRICTED === $state && claspo_is_free_plan() );
    ?>
    <div class="notice notice-warning is-dismissible claspo-notice" data-claspo-state="<?php echo esc_attr( $state ); ?>">
        <p>
            <strong>Claspo:</strong> <?php echo esc_html( $copy['title'] ); ?>
            <?php echo esc_html( $copy['body'] ); ?>
        </p>
        <p>
            <?php if ( $show_upgrade ) : ?>
                <?php echo claspo_upgrade_link( 'restricted_notice', 'Try Pro free for 14 days', 'button claspo-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <a class="button<?php echo $show_upgrade ? '' : ' claspo-primary'; ?>" href="<?php echo esc_url( $copy['cta_url'] ); ?>" target="_blank" rel="noopener">
                <?php echo esc_html( $copy['cta_label'] ); ?>
            </a>
        </p>
    </div>
    <script>
    jQuery( function ( $ ) {
        $( '.claspo-notice' ).on( 'click', '.notice-dismiss', function () {
            $.post( ajaxurl, {
                action: 'claspo_dismiss_notice',
                state: $( this ).closest( '.claspo-notice' ).data( 'claspoState' ),
                _wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'claspo_dismiss_notice' ) ); ?>
            } );
        } );
    } );
    </script>
    <?php
}

/**
 * Mutes one state for a week. A different state shows a notice again, because
 * that is new information rather than the same nag.
 *
 * @return void
 */
function claspo_ajax_dismiss_notice() {
    check_ajax_referer( 'claspo_dismiss_notice' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( null, 403 );
    }

    $state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';

    if ( ! in_array( $state, claspo_problem_states(), true ) ) {
        wp_send_json_error( null, 400 );
    }

    set_transient( 'claspo_notice_muted_' . $state, 1, 7 * DAY_IN_SECONDS );

    wp_send_json_success();
}
