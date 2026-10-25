<?php
/**
 * Compact status on the WordPress dashboard.
 *
 * Same height in every state so the dashboard does not jump, and the first line
 * always answers the only question that matters: is it working.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_dashboard_setup', 'claspo_register_dashboard_widget' );

/**
 * @return void
 */
function claspo_register_dashboard_widget() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    wp_add_dashboard_widget( 'claspo_status_widget', 'Claspo', 'claspo_render_dashboard_widget' );
}

/**
 * Picks the one action worth a button in this state, if any.
 *
 * @param string $state
 * @return array{label:string,url:string,upgrade:bool}|null
 */
function claspo_dashboard_action( $state ) {
    if ( CLASPO_STATE_NOT_CONNECTED === $state ) {
        return array(
            'label'   => 'Connect Claspo',
            'url'     => admin_url( 'admin.php?page=claspo_script_plugin' ),
            'upgrade' => false,
        );
    }

    if ( CLASPO_STATE_NO_WIDGETS === $state ) {
        return array(
            'label'   => 'Create a widget',
            'url'     => 'https://my.claspo.io/dashboard-ui/#/widget/from-scratch/new',
            'upgrade' => false,
        );
    }

    if ( CLASPO_STATE_RESTRICTED === $state && claspo_is_free_plan() ) {
        return array(
            'label'   => 'Try Pro free for 14 days',
            'url'     => '',
            'upgrade' => true,
        );
    }

    return null;
}

/**
 * @return void
 */
function claspo_render_dashboard_widget() {
    $status  = claspo_get_status();
    $history = claspo_get_history();
    $copy    = claspo_status_copy( $status['state'], $history );
    $action  = claspo_dashboard_action( $status['state'] );
    $live    = ( CLASPO_STATE_ACTIVE === $status['state'] );

    // One filled button, the way every other dashboard widget does it. It
    // carries the urgent action when the state has one, and the ordinary route
    // into Claspo otherwise.
    $primary = $action ? $action : array(
        'label'   => $copy['cta_label'],
        'url'     => $copy['cta_url'],
        'upgrade' => false,
    );

    // Whatever did not become the button stays a plain link — unless it would
    // just repeat it, which is what "No widgets yet" used to do: the same
    // "Create a widget" as both the button and the link under it.
    $secondary = ( $action && $copy['cta_label'] !== $action['label'] )
        ? array( 'label' => $copy['cta_label'], 'url' => $copy['cta_url'] )
        : null;
    ?>
    <p class="claspo-dash-title">
        <span class="claspo-dot <?php echo esc_attr( claspo_state_tone( $status['state'] ) ); ?>"></span>
        <strong><?php echo esc_html( $copy['title'] ); ?></strong>
    </p>

    <p><?php echo esc_html( $copy['body'] ); ?></p>

    <?php if ( $live && ! empty( $status['widgets'] ) ) : ?>
        <ul class="claspo-dash-list">
            <?php
            $shown = 0;
            foreach ( $status['widgets'] as $widget ) {
                if ( $shown >= 3 || empty( $widget['live'] ) || ! empty( $widget['teaser'] ) ) {
                    continue;
                }
                $shown++;
                ?>
                <li>
                    <a href="<?php echo esc_url( claspo_widget_url( $widget['id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( claspo_shorten( $widget['name'], 34 ) ); ?></a>
                    <?php if ( ! empty( $widget['testing'] ) ) : ?>
                        <span class="claspo-tag">A/B</span>
                    <?php endif; ?>
                </li>
                <?php
            }
            ?>
        </ul>
    <?php endif; ?>

    <p>
        <?php if ( $primary['upgrade'] ) : ?>
            <?php echo claspo_upgrade_link( 'dashboard_widget', $primary['label'], 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
            <a class="button button-primary" href="<?php echo esc_url( $primary['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $primary['label'] ); ?></a>
        <?php endif; ?>
    </p>

    <p class="claspo-dash-links">
        <?php if ( $secondary ) : ?>
            <a href="<?php echo esc_url( $secondary['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $secondary['label'] ); ?></a>
        <?php endif; ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=claspo_script_plugin' ) ); ?>">Plugin settings</a>
    </p>
    <?php
}

/**
 * @param string $state
 * @return string Class name for the status dot.
 */
function claspo_state_tone( $state ) {
    if ( CLASPO_STATE_ACTIVE === $state ) {
        return 'is-ok';
    }

    // Not checked yet: the plain grey dot, neither good nor bad.
    if ( CLASPO_STATE_UNKNOWN === $state ) {
        return '';
    }

    if ( CLASPO_STATE_RESTRICTED === $state || CLASPO_STATE_NOT_CONNECTED === $state ) {
        return 'is-bad';
    }

    return 'is-warn';
}
