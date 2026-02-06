<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<div class="wrapper">
    <div class="content">
        <div class="auth-wrapper">
            <img src="<?php echo esc_url(plugins_url('img/claspo-logo-black.svg', dirname(__FILE__))); ?>" alt="">
            <h1 class="h1 mt-38 mb-20">Let’s begin!</h1>

            <?php
            $wp_domain = get_site_url();
            $wp_domain = str_replace('https://', '', $wp_domain);
            $wp_domain = str_replace('http://', '', $wp_domain);
            
            // Generate nonce for secure callback (CVE-2025-68568 fix)
            $callback_nonce = wp_create_nonce('claspo_script_callback');
            $callback_url = admin_url('admin.php?page=claspo_script_plugin&claspo_nonce=' . $callback_nonce);
            ?>
            <a href="<?php echo esc_url("https://my.claspo.io/auth-ui/#registration?domain=" . urlencode($wp_domain) . "&integration_source=wordpress&callback_url=" . urlencode($callback_url)); ?>" class="cl-btn-primary">
                <span class="cl-btn-label">Sign up and create new widget</span>
            </a>

            <div class="pt-24 pb-24">
                <div class="h3 text-center">Or</div>
            </div>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="claspo_save_script">
                <?php wp_nonce_field('claspo_save_script', 'claspo_nonce'); ?>

                <div class="cl-form-group">
                    <label for="script-id">Enter Script ID</label>
                    <input type="text" id="script-id" name="claspo_script_id" placeholder="Script ID" maxlength="50" required>
                    <span class="error" id="script-id-error">Field can't be blank</span>
                    <?php
                        if ( isset($error_message) && !empty($error_message) ) {
                            echo '<span class="error" style="display: block;">' . esc_html($error_message) . '</span>';
                        }
                    ?>
                </div>
                <button class="cl-btn-secondary"><span class="cl-btn-label">Connect</span></button>
            </form>
            <div>
                <h3 class="pt-24">How to get Script ID</h3>
                <ol>
                    <li><a href="https://my.claspo.io" class="link" target="_blank">Sign in</a> to Claspo account.</li>
                    <li>Select <span class="link hover-link-script">Script</span> in the left menu.</li>
                    <li>In the <span class="link hover-link-wordpress"> WordPress block</span>, click Install</li>
                    <li>Click <span class="link hover-link-copyid">Сopy ID</span> in the instructions and paste it here</li>
                </ol>

            </div>
        </div>
    </div>
    <div class="banner"></div>
</div>