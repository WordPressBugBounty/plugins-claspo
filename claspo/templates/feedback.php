<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrapper-blank">
    <header>
        <img src="<?php echo esc_url(plugins_url('img/claspo-logo-black.svg', dirname(__FILE__))); ?>" alt="">
    </header>

    <div class="content-page">
        <h1 class="mb-30">What should we improve?</h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cl-form-group">
            <input type="hidden" name="action" value="claspo_send_feedback">
            <?php wp_nonce_field('claspo_feedback_nonce', 'claspo_nonce'); ?>

            <div class="textarea-container">
                <textarea id="custom-textarea" name="feedback" maxlength="200" placeholder="Default"></textarea>
            </div>

            <div class="cl-controls-wrapper">
                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="cl-btn-text"><span class="cl-btn-label">Cancel</span></a>
                <button type="submit" class="cl-btn-primary w-auto"><span class="cl-btn-label">Submit and deactivate</span></button>
            </div>
        </form>
    </div>
</div>
