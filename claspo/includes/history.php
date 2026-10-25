<?php
/**
 * Accumulated history. Kept in its own option because claspo_status is
 * overwritten wholesale on every check, and this must survive that.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return array History with every key present.
 */
function claspo_get_history() {
    $stored = get_option( 'claspo_history' );

    return is_array( $stored )
        ? array_merge( claspo_default_history(), $stored )
        : claspo_default_history();
}

/**
 * Folds the outcome of a SUCCESSFUL check into the history.
 *
 * Never call this for a failed check — see claspo_next_streak().
 *
 * @param string    $state
 * @param bool|null $branded Whether the account carries the Claspo badge, or
 *                           null when the response carried no widgets to judge by.
 * @param array[]   $widgets Freshly parsed widget list, possibly empty.
 * @return array Updated history.
 */
function claspo_update_history( $state, $branded = null, array $widgets = array() ) {
    $history = claspo_next_streak(
        claspo_get_history(),
        $state,
        wp_date( 'Y-m-d' ),
        time()
    );

    $history = claspo_next_branded( $history, $branded );
    $history = claspo_remember_widgets( $history, $widgets );

    update_option( 'claspo_history', $history, false );

    return $history;
}
