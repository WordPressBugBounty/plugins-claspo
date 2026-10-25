<?php
/**
 * The connect screen.
 *
 * The Script ID field comes first because most people arrive here from Claspo
 * already holding one. Creating an account is the second route, not the first.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$claspo_prefill = isset( $GLOBALS['claspo_prefill_script_id'] ) ? $GLOBALS['claspo_prefill_script_id'] : '';
?>

<div class="wrap claspo-page is-connect">

    <h1 class="screen-reader-text">Claspo</h1>

    <div class="claspo-center">
    <div
        class="claspo-connect"
        data-claspo-connected="<?php echo esc_url( rest_url( 'claspo/v1/connected' ) ); ?>"
        data-claspo-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">

        <div class="claspo-connect-head">
            <img class="claspo-connect-mark" src="<?php echo esc_url( plugins_url( 'img/claspo-mark-color.svg', dirname( __FILE__ ) ) ); ?>" alt="" width="40" height="40">
            <div>
                <h2>Connect your Claspo account</h2>
                <p>One step, and your popups start showing on this site.</p>
            </div>
        </div>

        <?php if ( ! empty( $claspo_error ) ) : ?>
            <div class="notice notice-error inline"><p><?php echo esc_html( $claspo_error ); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="claspo_save_script">
            <?php wp_nonce_field( 'claspo_save_script', 'claspo_nonce' ); ?>

            <label class="claspo-label" for="claspo_script_id">Script ID</label>
            <div class="claspo-field">
                <input
                    type="text"
                    id="claspo_script_id"
                    name="claspo_script_id"
                    value="<?php echo esc_attr( $claspo_prefill ); ?>"
                    placeholder="Paste your Script ID here"
                    maxlength="50"
                    required>
                <button type="submit" class="button claspo-primary" data-busy="Connecting…">Connect</button>
            </div>

            <details class="claspo-where">
                <summary>Where do I find it?</summary>
                <ol>
                    <li>Open Claspo and pick <strong>Script</strong> in the left menu</li>
                    <li>In the <strong>WordPress</strong> block, click <strong>Install</strong></li>
                    <li>Click <strong>Copy ID</strong> and paste it here</li>
                </ol>
            </details>
        </form>

        <div class="claspo-or"><span>or</span></div>

        <div class="claspo-signup">
            <div>
                <h3>No Claspo account yet?</h3>
                <p>Every feature, unlimited widgets, 3,000 pageviews a month, free.</p>
            </div>
            <a class="button claspo-secondary" href="<?php echo esc_url( claspo_registration_url() ); ?>" target="_blank" rel="noopener">Create an account</a>
        </div>

    </div>
    </div>

</div>
