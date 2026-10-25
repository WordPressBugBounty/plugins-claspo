<?php
/**
 * The plugin screen once a script is connected.
 *
 * Expects $claspo_status, $claspo_history, $claspo_tab and $claspo_connected.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$claspo_base = admin_url( 'admin.php?page=claspo_script_plugin' );
?>

<div class="wrap claspo-page">

    <h1 class="claspo-title">Claspo</h1>

    <h2 class="nav-tab-wrapper claspo-tabs">
        <a href="<?php echo esc_url( $claspo_base ); ?>" data-claspo-tab="widgets" class="nav-tab<?php echo 'widgets' === $claspo_tab ? ' nav-tab-active' : ''; ?>">Widgets</a>
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'targeting', $claspo_base ) ); ?>" data-claspo-tab="targeting" class="nav-tab<?php echo 'targeting' === $claspo_tab ? ' nav-tab-active' : ''; ?>">Targeting data</a>
    </h2>

    <?php if ( $claspo_connected ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Claspo is connected. Your published widgets will start showing on this site.</p>
        </div>
    <?php endif; ?>

    <?php
    // Both panels are rendered and toggled in the browser. Switching tabs used to
    // cost a full page load for markup that is already cheap to produce, and the
    // delay read as the plugin being slow. The links still work without scripting.
    ?>
    <div class="claspo-panel" data-claspo-panel="widgets"<?php echo 'widgets' === $claspo_tab ? '' : ' hidden'; ?>>
        <?php include plugin_dir_path( __FILE__ ) . 'tab-widgets.php'; ?>
    </div>

    <div class="claspo-panel" data-claspo-panel="targeting"<?php echo 'targeting' === $claspo_tab ? '' : ' hidden'; ?>>
        <?php include plugin_dir_path( __FILE__ ) . 'tab-targeting.php'; ?>
    </div>

</div>
