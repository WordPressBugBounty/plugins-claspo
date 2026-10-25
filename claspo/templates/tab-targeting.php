<?php
/**
 * The variable reference, which doubles as the on/off control.
 *
 * Claspo does not know these names, so this table is where the site owner
 * reads them before typing them into a display condition. That is why it shows
 * names and example values rather than hiding behind four checkboxes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$claspo_groups   = claspo_variable_groups();
$claspo_settings = claspo_variable_settings();
$claspo_hidden   = array();
?>

<?php if ( isset( $_GET['updated'] ) ) : ?>
    <div class="notice notice-success is-dismissible"><p>Saved.</p></div>
<?php endif; ?>

<div class="claspo-card claspo-howto">
    <div>
        <h3>How to use these in Claspo</h3>
        <p>The plugin hands these values to Claspo on every page view. Claspo does not know their names, so copy the one you need from the table below.</p>
    </div>
    <ol>
        <li>Open a widget, go to <strong>Triggering &rsaquo; On pages</strong></li>
        <li>Add a condition and pick <strong>JavaScript variable</strong></li>
        <li>Paste the variable name, then choose a comparison</li>
    </ol>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <input type="hidden" name="action" value="claspo_save_variables">
    <?php wp_nonce_field( 'claspo_save_variables', 'claspo_nonce' ); ?>

    <?php foreach ( $claspo_groups as $claspo_id => $claspo_group ) : ?>
        <?php
        // A group that cannot produce anything on this site is not shown at all.
        // Offering "WooCommerce product" on a site with no shop is eleven
        // variables that will never hold a value.
        if ( ! claspo_group_available( $claspo_id ) ) {
            $claspo_hidden[] = $claspo_group['title'];
            continue;
        }

        $claspo_on = ! empty( $claspo_settings[ $claspo_id ] );
        ?>
        <div class="claspo-group<?php echo $claspo_on ? '' : ' is-off'; ?>">

            <div class="claspo-group-head">
                <label class="claspo-toggle" for="claspo-var-<?php echo esc_attr( $claspo_id ); ?>">
                    <input
                        type="checkbox"
                        role="switch"
                        id="claspo-var-<?php echo esc_attr( $claspo_id ); ?>"
                        name="claspo_vars[<?php echo esc_attr( $claspo_id ); ?>]"
                        value="1"
                        <?php checked( $claspo_on ); ?>>
                    <span class="claspo-track"><span class="claspo-knob"></span></span>
                    <span class="claspo-group-title"><?php echo esc_html( $claspo_group['title'] ); ?></span>
                </label>
                <span class="claspo-badge"><?php echo $claspo_on ? 'On' : 'Off'; ?></span>
            </div>

            <table class="widefat claspo-vars">
                <thead>
                    <tr>
                        <th scope="col">Variable</th>
                        <th scope="col">Holds</th>
                        <th scope="col">Example value</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $claspo_group['vars'] as $claspo_name => $claspo_meta ) : ?>
                    <tr>
                        <td>
                            <span class="claspo-var">
                                <code><?php echo esc_html( $claspo_name ); ?></code>
                                <?php // The name has to reach a Claspo display condition verbatim; a typo here is a rule that silently never matches. ?>
                                <button
                                    type="button"
                                    class="claspo-copy"
                                    data-claspo-copy="<?php echo esc_attr( $claspo_name ); ?>"
                                    aria-label="Copy <?php echo esc_attr( $claspo_name ); ?>">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">
                                        <rect x="5.2" y="5.2" width="8.3" height="8.3" rx="1.6" stroke="currentColor" stroke-width="1.3"></rect>
                                        <path d="M10.8 5.2V4a1.6 1.6 0 0 0-1.6-1.6H4a1.6 1.6 0 0 0-1.6 1.6v5.2A1.6 1.6 0 0 0 4 10.8h1.2" stroke="currentColor" stroke-width="1.3"></path>
                                    </svg>
                                </button>
                            </span>
                        </td>
                        <td class="claspo-dim"><?php echo esc_html( $claspo_meta[0] ); ?></td>
                        <td><code class="claspo-dim"><?php echo esc_html( $claspo_meta[1] ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    <?php endforeach; ?>

    <?php if ( ! empty( $claspo_hidden ) ) : ?>
        <?php // Both hidden groups depend on the same plugin, so they always go together. ?>
        <p class="claspo-dim">Product and cart variables need WooCommerce. Activate it and they appear here, with whatever you had switched on before.</p>
    <?php endif; ?>

    <p class="claspo-save">
        <button type="submit" class="button claspo-primary" data-busy="Saving&hellip;">Save changes</button>
        <span class="claspo-unsaved" hidden>Not saved yet</span>
    </p>

    <?php // Announces a copy to a screen reader, which cannot see the button change. ?>
    <p class="screen-reader-text" aria-live="polite" id="claspo-copy-status"></p>
</form>
