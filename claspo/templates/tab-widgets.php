<?php
/**
 * Status, the widget list, and the side column.
 *
 * Expects $claspo_status, $claspo_history and $claspo_tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$claspo_copy    = claspo_status_copy( $claspo_status['state'], $claspo_history );
$claspo_live    = ( CLASPO_STATE_ACTIVE === $claspo_status['state'] );
$claspo_unknown = ( CLASPO_STATE_UNKNOWN === $claspo_status['state'] );
$claspo_trial   = ( CLASPO_STATE_RESTRICTED === $claspo_status['state'] ) && claspo_is_free_plan();
$claspo_paused  = ( CLASPO_STATE_RESTRICTED === $claspo_status['state'] );
$claspo_widgets = claspo_widgets_to_show( $claspo_status, $claspo_history );

// Not checked yet is neither good nor bad, so the strip gets no tone at all.
if ( $claspo_live ) {
    $claspo_tone = 'is-ok';
} elseif ( $claspo_unknown ) {
    $claspo_tone = '';
} else {
    $claspo_tone = 'is-problem';
}
?>

<div class="claspo-strip <?php echo esc_attr( $claspo_tone ); ?>">
    <div class="claspo-strip-text">
        <h2><?php echo esc_html( $claspo_copy['title'] ); ?></h2>
        <p>
            <?php echo esc_html( $claspo_copy['body'] ); ?>
            <?php if ( ! empty( $claspo_status['checked_at'] ) ) : ?>
                <span class="claspo-dim">Checked <?php echo esc_html( human_time_diff( (int) $claspo_status['checked_at'] ) ); ?> ago.</span>
            <?php endif; ?>
        </p>
        <?php if ( ! empty( $claspo_status['last_error'] ) ) : ?>
            <p class="claspo-dim">The last check did not go through, so this is the previous known state.</p>
        <?php endif; ?>
    </div>
    <div class="claspo-strip-actions">
        <?php if ( $claspo_trial ) : ?>
            <?php echo claspo_upgrade_link( 'status_strip', 'Try Pro free for 14 days', 'button claspo-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
        <?php
        // With no widgets the empty state below already offers both routes into
        // Claspo. Repeating the same button here made three "Create a widget"
        // on one screen.
        if ( ! empty( $claspo_widgets ) ) :
            ?>
            <a class="button claspo-secondary" href="<?php echo esc_url( $claspo_copy['cta_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $claspo_copy['cta_label'] ); ?></a>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="claspo_refresh_status">
            <?php wp_nonce_field( 'claspo_refresh_status', 'claspo_nonce' ); ?>
            <button type="submit" class="button" data-busy="Checking…">Check now</button>
        </form>
    </div>
</div>

<?php claspo_render_review_strip( ! isset( $claspo_tab ) || 'widgets' === $claspo_tab ); ?>

<div class="claspo-columns">

    <div class="claspo-main">
        <?php if ( ! empty( $claspo_widgets ) ) : ?>
            <?php if ( $claspo_paused ) : ?>
                <p class="claspo-list-label">Paused until the limit resets</p>
            <?php endif; ?>
            <div class="claspo-table-card">
            <table class="widefat claspo-widgets">
                <thead>
                    <tr>
                        <th scope="col">Widget</th>
                        <th scope="col">Type</th>
                        <th scope="col">State</th>
                        <th scope="col" class="claspo-right">Leads</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $claspo_widgets as $claspo_widget ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( claspo_widget_url( $claspo_widget['id'] ) ); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html( $claspo_widget['name'] ); ?>
                            </a>
                        </td>
                        <td class="claspo-dim">
                            <?php echo esc_html( claspo_label( $claspo_widget['type'] ) ); ?>
                            <?php if ( '' !== $claspo_widget['layout'] ) : ?>
                                · <?php echo esc_html( claspo_label( $claspo_widget['layout'] ) ); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $claspo_paused && ! empty( $claspo_widget['live'] ) ) : ?>
                                <span class="claspo-state is-paused">Paused</span>
                            <?php elseif ( ! empty( $claspo_widget['live'] ) ) : ?>
                                <span class="claspo-state is-live">Live</span>
                            <?php else : ?>
                                <span class="claspo-state is-draft">Draft</span>
                            <?php endif; ?>
                            <?php if ( ! empty( $claspo_widget['testing'] ) ) : ?>
                                <span class="claspo-tag">A/B</span>
                            <?php endif; ?>
                        </td>
                        <td class="claspo-right">
                            <?php if ( ! empty( $claspo_widget['collects'] ) ) : ?>
                                <a href="<?php echo esc_url( claspo_widget_leads_url( $claspo_widget['id'] ) ); ?>" target="_blank" rel="noopener">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if ( (int) $claspo_status['widgets_total'] > count( $claspo_widgets ) ) : ?>
                <p class="claspo-dim claspo-list-note">
                    Showing <?php echo (int) count( $claspo_widgets ); ?> of <?php echo (int) $claspo_status['widgets_total']; ?> widgets, live ones first.
                    <a href="https://my.claspo.io/dashboard-ui/#/widgets-list" target="_blank" rel="noopener">See all in Claspo</a>
                </p>
            <?php endif; ?>
        <?php else : ?>
            <div class="claspo-card claspo-empty">
                <p>Create your first popup and it will show up here.</p>
                <p class="claspo-actions">
                    <a class="button claspo-primary" href="https://my.claspo.io/dashboard-ui/#/widget/from-scratch/new" target="_blank" rel="noopener">Create a widget</a>
                    <a class="button claspo-secondary" href="https://my.claspo.io/dashboard-ui/#/widget/from-template/new" target="_blank" rel="noopener">Browse templates</a>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div class="claspo-side">

        <?php if ( ! empty( $claspo_widgets ) ) : ?>
            <?php // With no widgets the main column already offers both buttons; a second
                  // copy here would be the same call to action twice on one screen. ?>
        <div class="claspo-card">
            <h3>Add a widget</h3>
            <a class="button claspo-primary claspo-block" href="https://my.claspo.io/dashboard-ui/#/widget/from-scratch/new" target="_blank" rel="noopener">Create a widget</a>
            <a class="button claspo-secondary claspo-block" href="https://my.claspo.io/dashboard-ui/#/widget/from-template/new" target="_blank" rel="noopener">Browse templates</a>
        </div>
        <?php endif; ?>

        <?php if ( claspo_is_free_plan() && ! $claspo_trial ) : ?>
            <div class="claspo-card">
                <h3>You are on the Free plan</h3>
                <p>3,000 pageviews a month. Pro lifts the limit and adds multiple sites, custom domains and advanced features.</p>
                <?php echo claspo_upgrade_link( 'side_card', 'Try Pro free for 14 days', 'button claspo-primary claspo-block' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

    </div>

</div>
