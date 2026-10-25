<?php
/**
 * The review request.
 *
 * It sits in the same slot as the status strip on the plugin page, because a
 * request tucked into a corner reads as decoration. On the dashboard, where
 * there is no such slot, it falls back to a notice.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const CLASPO_REVIEW_URL = 'https://wordpress.org/support/plugin/claspo/reviews/#new-post';

add_action( 'admin_notices', 'claspo_render_review_notice' );
add_action( 'admin_post_claspo_review_action', 'claspo_handle_review_action' );

/**
 * @return array Review state with every key present.
 */
function claspo_get_review() {
    $stored = get_option( 'claspo_review' );

    return is_array( $stored )
        ? array_merge( claspo_default_review(), $stored )
        : claspo_default_review();
}

/**
 * Whether a request is earned right now, ignoring which screen we are on.
 *
 * @return bool
 */
function claspo_review_due() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return false;
    }

    // Never ask on top of a problem the owner still has to deal with.
    if ( null !== claspo_current_problem_state() ) {
        return false;
    }

    $review = claspo_get_review();

    // A request left unanswered for a week snoozes itself, so an ignored banner
    // does not sit there forever.
    if ( ! empty( $review['shown_at'] ) && time() - (int) $review['shown_at'] > 7 * DAY_IN_SECONDS ) {
        $review['status']       = 'later';
        $review['snooze_until'] = time() + 30 * DAY_IN_SECONDS;
        $review['shown_at']     = 0;
        update_option( 'claspo_review', $review, false );

        return false;
    }

    return claspo_should_ask_for_review( claspo_get_status(), claspo_get_history(), $review, time() );
}

/**
 * Counts one request, not one page load. Without this, three refreshes would
 * burn the lifetime allowance of three.
 *
 * @return void
 */
function claspo_mark_review_shown() {
    $review = claspo_get_review();

    if ( ! empty( $review['shown_at'] ) ) {
        return;
    }

    $review['shown_at']    = time();
    $review['asked_count'] = (int) $review['asked_count'] + 1;

    update_option( 'claspo_review', $review, false );
}

/**
 * The request as it appears on the plugin page.
 *
 * The page renders both tab panels and hides the inactive one, so this runs
 * on the Targeting tab too, inside a panel nobody is looking at. Marking the
 * request as shown there would spend one of the three asks and start the
 * seven-day window on a strip that was never seen.
 *
 * @param bool $visible Whether the panel this sits in is the one on screen.
 * @return void
 */
function claspo_render_review_strip( $visible = true ) {
    if ( ! $visible || ! claspo_review_due() ) {
        return;
    }

    claspo_mark_review_shown();
    ?>
    <div class="claspo-strip is-review">
        <div class="claspo-strip-text">
            <h2>Enjoying Claspo?</h2>
            <p>Your popups have run for a week straight. A short review helps other WordPress users find the plugin.</p>
        </div>
        <div class="claspo-strip-actions">
            <?php echo claspo_review_button( 'review', 'Leave a review', 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo claspo_review_button( 'later', 'Maybe later', 'button-link claspo-quiet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo claspo_review_button( 'never', "Don't ask again", 'button-link claspo-quiet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>
    <?php
}

/**
 * The same request on the dashboard, where there is no strip to live in.
 *
 * @return void
 */
function claspo_render_review_notice() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( ! $screen || 'dashboard' !== $screen->id ) {
        return;
    }

    if ( ! claspo_review_due() ) {
        return;
    }

    claspo_mark_review_shown();
    ?>
    <div class="notice notice-info claspo-review-notice">
        <p>
            <strong>Claspo:</strong>
            your popups have run for a week straight. A short review helps other WordPress users find the plugin.
        </p>
        <p>
            <?php echo claspo_review_button( 'review', 'Leave a review', 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo claspo_review_button( 'later', 'Maybe later', 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo claspo_review_button( 'never', "Don't ask again", 'button-link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </p>
    </div>
    <?php
}

/**
 * Builds one nonce-protected action link. Returns escaped markup.
 *
 * @param string $choice review|later|never
 * @param string $label
 * @param string $class
 * @return string
 */
function claspo_review_button( $choice, $label, $class ) {
    $url = wp_nonce_url(
        add_query_arg(
            array(
                'action' => 'claspo_review_action',
                'choice' => $choice,
            ),
            admin_url( 'admin-post.php' )
        ),
        'claspo_review_action'
    );

    return sprintf(
        '<a class="%s" href="%s">%s</a> ',
        esc_attr( $class ),
        esc_url( $url ),
        esc_html( $label )
    );
}

/**
 * @return void
 */
function claspo_handle_review_action() {
    check_admin_referer( 'claspo_review_action' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    $choice = isset( $_GET['choice'] ) ? sanitize_text_field( wp_unslash( $_GET['choice'] ) ) : '';
    $review = claspo_get_review();

    // This request is answered; the next one starts a fresh campaign.
    $review['shown_at'] = 0;

    switch ( $choice ) {
        case 'review':
            $review['status'] = 'done';
            update_option( 'claspo_review', $review, false );
            wp_redirect( CLASPO_REVIEW_URL );
            exit;

        case 'never':
            $review['status'] = 'never';
            break;

        case 'later':
            $review['status']       = 'later';
            $review['snooze_until'] = time() + 30 * DAY_IN_SECONDS;
            break;

        default:
            wp_die( 'Unknown choice', 'Bad Request', array( 'response' => 400 ) );
    }

    update_option( 'claspo_review', $review, false );

    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
    exit;
}
