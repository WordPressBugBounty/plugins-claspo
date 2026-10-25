<?php
/**
 * The upgrade offer shown to accounts on the free plan.
 *
 * Deliberately restrained. The only element that is always on screen is a text
 * link in the plugin's own row on the Plugins page, which is what every other
 * plugin does there. Everywhere else the offer rides inside the notice that
 * already exists for a restricted account, so no new banner is introduced.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return bool True only when a branded account has actually been observed.
 */
function claspo_is_free_plan() {
    return claspo_history_is_free( claspo_get_history() );
}

/**
 * @param string $medium Where the click came from, for campaign tracking.
 * @param string $label
 * @param string $class
 * @return string Escaped anchor markup.
 */
function claspo_upgrade_link( $medium, $label = 'Try Pro free for 14 days', $class = '' ) {
    return sprintf(
        '<a href="%s" target="_blank" rel="noopener"%s>%s</a>',
        esc_url( claspo_build_upgrade_url( $medium ) ),
        '' === $class ? '' : ' class="' . esc_attr( $class ) . '"',
        esc_html( $label )
    );
}

/**
 * Adds "Upgrade to Pro" to the plugin's row on the Plugins page.
 *
 * This one is always present for a free account. It is a row action rather than
 * a notice, so it sits where people already look for plugin links and nags nobody.
 *
 * @param string[] $links
 * @return string[]
 */
function claspo_plugin_action_links( $links ) {
    if ( ! claspo_is_free_plan() ) {
        return $links;
    }

    $upgrade = sprintf(
        '<a href="%s" target="_blank" rel="noopener" style="color:#16a34a;font-weight:600;">%s</a>',
        esc_url( claspo_build_upgrade_url( 'plugins_row' ) ),
        esc_html( 'Try Pro free' )
    );

    array_unshift( $links, $upgrade );

    return $links;
}
