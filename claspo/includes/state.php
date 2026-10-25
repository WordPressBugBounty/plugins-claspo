<?php
/**
 * Pure state derivation. No WordPress calls live in this file, so it can be
 * exercised by tests/test-state.php without loading WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const CLASPO_STATE_NOT_CONNECTED  = 'NOT_CONNECTED';
// A script is connected but Claspo has not been asked about it yet. Every
// install passes through this once, right after updating to the version that
// introduced the check; the first successful check replaces it.
const CLASPO_STATE_UNKNOWN        = 'UNKNOWN';
const CLASPO_STATE_NO_WIDGETS     = 'NO_WIDGETS';
const CLASPO_STATE_RESTRICTED     = 'RESTRICTED';
const CLASPO_STATE_IDLE           = 'IDLE';
const CLASPO_STATE_ACTIVE         = 'ACTIVE';

/**
 * States that justify an admin-wide notice. ACTIVE and NOT_CONNECTED are absent
 * on purpose: the first is not a problem, the second is already covered by the
 * activation redirect and the menu badge.
 *
 * @return string[]
 */
function claspo_problem_states() {
    return array(
        CLASPO_STATE_NO_WIDGETS,
        CLASPO_STATE_RESTRICTED,
        CLASPO_STATE_IDLE,
    );
}

/**
 * A widget is live when it is published to everyone. testingStatus does not
 * matter: a widget in an A/B test is still shown to visitors.
 *
 * Campaign schedules are deliberately not evaluated — see section 4 of the
 * design document. When the shape of absolute schedule predicates becomes
 * known, this is the only function that needs to change.
 *
 * @param array $form One entry of the `forms` object.
 * @return bool
 */
function claspo_is_form_live( array $form ) {
    return isset( $form['publishStatus'] ) && 'FOR_ALL' === $form['publishStatus'];
}

/**
 * Widget types that collect visitor data, and therefore have a leads page.
 *
 * Confirmed with the Claspo team: these two collect, every other type does not.
 * Anything unfamiliar is treated as not collecting, so a use case added later
 * can never produce a link to a leads page that has nothing behind it.
 *
 * @return string[]
 */
function claspo_lead_collecting_types() {
    return array( 'SUBSCRIPTION_FORM', 'REQUEST_FORM' );
}

/**
 * @param string|null $type Value of widgetType.
 * @return bool
 */
function claspo_type_collects( $type ) {
    return in_array( $type, claspo_lead_collecting_types(), true );
}

/**
 * Pairs each form with its variant, which is where the type and rules live.
 *
 * @param array $config
 * @return array formId (string) => variant array
 */
function claspo_variants_by_form( array $config ) {
    $variants = isset( $config['formVariants'] ) && is_array( $config['formVariants'] ) ? $config['formVariants'] : array();
    $by_form  = array();

    foreach ( $variants as $variant ) {
        if ( is_array( $variant ) && isset( $variant['formId'] ) ) {
            $by_form[ (string) $variant['formId'] ] = $variant;
        }
    }

    return $by_form;
}

/**
 * Counts live campaigns.
 *
 * Teasers are excluded: a teaser is the small reopener attached to another
 * widget, not a campaign of its own, so counting it would overstate the number.
 * They are identified by widgetType rather than by the reciprocal
 * linkedToVariantId, which only ever let us guess.
 *
 * @param array $config Decoded /config response.
 * @return int
 */
function claspo_count_live_widgets( array $config ) {
    $forms   = isset( $config['forms'] ) && is_array( $config['forms'] ) ? $config['forms'] : array();
    $by_form = claspo_variants_by_form( $config );
    $total   = 0;

    foreach ( $forms as $form_id => $form ) {
        if ( ! is_array( $form ) || ! claspo_is_form_live( $form ) ) {
            continue;
        }

        $variant = isset( $by_form[ (string) $form_id ] ) ? $by_form[ (string) $form_id ] : array();

        if ( isset( $variant['widgetType'] ) && 'TEASER' === $variant['widgetType'] ) {
            continue;
        }

        $total++;
    }

    return $total;
}

/**
 * Maps a successful /config response to a state.
 *
 * Only handles HTTP 200 bodies. NOT_CONNECTED is decided by
 * claspo_refresh_status(), because it cannot be derived from a response body.
 *
 * @param array $config Decoded /config response.
 * @return string One of the CLASPO_STATE_* constants.
 */
function claspo_compute_state( array $config ) {
    $forms = isset( $config['forms'] ) && is_array( $config['forms'] ) ? $config['forms'] : array();

    if ( empty( $forms ) ) {
        return empty( $config['hasRestrictedWidgets'] )
            ? CLASPO_STATE_NO_WIDGETS
            : CLASPO_STATE_RESTRICTED;
    }

    return claspo_count_live_widgets( $config ) > 0
        ? CLASPO_STATE_ACTIVE
        : CLASPO_STATE_IDLE;
}

/**
 * @return array Empty history for an install that has never been checked.
 */
function claspo_default_history() {
    return array(
        'ever_live'        => false,
        'first_live_at'    => null,
        'last_live_at'     => null,
        'live_streak_days' => 0,
        'last_streak_day'  => '',
        'last_branded'     => null,
        'last_widgets'     => array(),
    );
}

/**
 * Advances the consecutive-days-live counter.
 *
 * Counted up per calendar day rather than per check, so two checks in one day
 * cannot add two days. Skipped days do not break the streak: what we care
 * about is whether widgets keep running, not whether cron keeps firing.
 *
 * A stop, on the other hand, counts whenever it is seen. Widgets switched off
 * on the afternoon of day seven and back on an hour later have not been live
 * for seven days, and the day they came back is not counted again either:
 * the streak restarts with the next full day.
 *
 * Callers must not invoke this for a failed check. A network failure says
 * nothing about the widgets, and resetting on it would deny a review to a
 * perfectly happy user over one flaky week.
 *
 * @param array  $history Previous history.
 * @param string $state   State from a SUCCESSFUL check.
 * @param string $today   Current date in site timezone, Y-m-d.
 * @param int    $now     Current timestamp.
 * @return array Updated history.
 */
function claspo_next_streak( array $history, $state, $today, $now ) {
    $history = array_merge( claspo_default_history(), $history );
    $is_live = ( CLASPO_STATE_ACTIVE === $state );

    if ( ! $is_live ) {
        $history['live_streak_days'] = 0;
        $history['last_streak_day']  = $today;

        return $history;
    }

    $history['ever_live']    = true;
    $history['last_live_at'] = (int) $now;

    if ( null === $history['first_live_at'] ) {
        $history['first_live_at'] = (int) $now;
    }

    if ( $history['last_streak_day'] === $today ) {
        return $history;
    }

    $history['live_streak_days'] = (int) $history['live_streak_days'] + 1;
    $history['last_streak_day']  = $today;

    return $history;
}

/**
 * Single source of copy for a state. Shared by the status card, the dashboard
 * widget and the admin notices so the three can never drift apart.
 *
 * @param string $state
 * @param array  $history
 * @return array{title:string,body:string,cta_label:string,cta_url:string}
 */
function claspo_status_copy( $state, array $history ) {
    $widgets_list = 'https://my.claspo.io/dashboard-ui/#/widgets-list';
    $new_widget   = 'https://my.claspo.io/dashboard-ui/#/widget/from-template/new';

    switch ( $state ) {
        case CLASPO_STATE_ACTIVE:
            return array(
                'title'     => 'Your popups are running',
                'body'      => 'Claspo is installed and your published widgets are being shown to visitors.',
                'cta_label' => 'Manage widgets',
                'cta_url'   => $widgets_list,
            );

        case CLASPO_STATE_RESTRICTED:
            return array(
                'title'     => 'Your popups are not being shown',
                'body'      => 'You have used all 3,000 pageviews on the Free plan this month. A 14-day Pro trial brings them back right away.',
                'cta_label' => 'Open Claspo',
                'cta_url'   => $widgets_list,
            );

        case CLASPO_STATE_IDLE:
            if ( ! empty( $history['ever_live'] ) ) {
                return array(
                    'title'     => 'None of your widgets are live',
                    'body'      => 'They were running before and all of them are now unpublished. Open Claspo to see what changed.',
                    'cta_label' => 'Open Claspo',
                    'cta_url'   => $widgets_list,
                );
            }

            return array(
                'title'     => 'Nothing is published yet',
                'body'      => 'You have widgets, but none of them is published. Publish one to start collecting subscribers.',
                'cta_label' => 'Open Claspo',
                'cta_url'   => $widgets_list,
            );

        case CLASPO_STATE_NO_WIDGETS:
            return array(
                'title'     => 'No widgets yet',
                'body'      => 'Claspo is live on your site. Create your first popup and it will show up here.',
                'cta_label' => 'Create a widget',
                'cta_url'   => $new_widget,
            );

        case CLASPO_STATE_UNKNOWN:
            return array(
                'title'     => 'Not checked yet',
                'body'      => 'Claspo is installed, but the plugin has not been able to ask about your widgets yet. It will on the next check.',
                'cta_label' => 'Open Claspo',
                'cta_url'   => $widgets_list,
            );

        default:
            return array(
                'title'     => 'Claspo is not connected',
                'body'      => 'The plugin is active but no script is on your site, so no popup can be shown.',
                'cta_label' => 'Open Claspo',
                'cta_url'   => $widgets_list,
            );
    }
}

/**
 * @return array Review state for an install that has never been asked.
 */
function claspo_default_review() {
    return array(
        'status'       => 'pending',
        'snooze_until' => 0,
        'asked_count'  => 0,
        'shown_at'     => 0,
    );
}

/**
 * Whether this install has earned a review request.
 *
 * Deliberately strict: we only ask people whose widgets have been live for a
 * full week, because asking everyone is how a plugin collects one-star reviews.
 *
 * @param array $status
 * @param array $history
 * @param array $review
 * @param int   $now
 * @return bool
 */
function claspo_should_ask_for_review( array $status, array $history, array $review, $now ) {
    $review = array_merge( claspo_default_review(), $review );

    if ( 'done' === $review['status'] || 'never' === $review['status'] ) {
        return false;
    }

    // The cap is on starting a request, not on keeping one on screen. Showing
    // the third one raises the count to three, and that request still has
    // its week to run; a non-zero shown_at is what says it is running.
    if ( (int) $review['asked_count'] >= 3 && empty( $review['shown_at'] ) ) {
        return false;
    }

    if ( (int) $review['snooze_until'] > (int) $now ) {
        return false;
    }

    if ( ! isset( $status['state'] ) || CLASPO_STATE_ACTIVE !== $status['state'] ) {
        return false;
    }

    $streak = isset( $history['live_streak_days'] ) ? (int) $history['live_streak_days'] : 0;

    return $streak >= 7;
}

/**
 * Remembers whether the account carries the Claspo badge.
 *
 * This belongs in the history rather than the status because the status is
 * overwritten on every check, and the one state where we most want to know the
 * plan — RESTRICTED — returns an empty `forms` object, so the badge cannot be
 * read from that response at all. Remembering the last readable value is what
 * lets us still recognise a free account whose widgets have just been cut off.
 *
 * @param array     $history
 * @param bool|null $branded Null when the response carried no widgets to judge by.
 * @return array Updated history.
 */
function claspo_next_branded( array $history, $branded ) {
    $history = array_merge( claspo_default_history(), $history );

    if ( null !== $branded ) {
        $history['last_branded'] = (bool) $branded;
    }

    return $history;
}

/**
 * Whether to treat this account as being on the free plan.
 *
 * Only true when we have positively seen the badge. An account we have never
 * been able to read is not assumed to be free, so a fresh install that has not
 * created a widget yet is never shown an upgrade offer.
 *
 * @param array $history
 * @return bool
 */
function claspo_history_is_free( array $history ) {
    return isset( $history['last_branded'] ) && true === $history['last_branded'];
}

/**
 * Builds the upgrade link.
 *
 * The campaign parameters go BEFORE the fragment on purpose. Everything after
 * `#` is the single-page app's own route, which already carries its own query
 * string, so appending to it would hand the router parameters it does not expect.
 *
 * @param string $medium Where the click came from, e.g. plugins_row.
 * @return string
 */
function claspo_build_upgrade_url( $medium ) {
    return 'https://my.claspo.io/dashboard-ui/'
        . '?utm_source=wordpress_plugin'
        . '&utm_medium=' . rawurlencode( $medium )
        . '&utm_campaign=free_to_pro'
        . '#/billing/subscriptions?sb=PRICING_PLANS';
}

/**
 * Flattens the config into a list the admin can be shown.
 *
 * Live campaigns come first, then live teasers, then drafts, each group in
 * the account's own order. The cap exists so the stored option cannot grow
 * without bound, but the toolbar and the dashboard pick their live entries
 * from the capped list while the counts come from the whole account; cutting
 * in account order could drop every live widget behind twenty drafts.
 *
 * Grouped by hand rather than with usort(), which is only stable from PHP 8.
 *
 * @param array $config
 * @param int   $limit Hard cap on the stored list.
 * @return array[] Each entry: id, name, type, layout, live, testing, collects, teaser, prizes.
 */
function claspo_widget_list( array $config, $limit = 20 ) {
    $forms   = isset( $config['forms'] ) && is_array( $config['forms'] ) ? $config['forms'] : array();
    $by_form = claspo_variants_by_form( $config );
    $groups  = array( array(), array(), array() );

    foreach ( $forms as $form_id => $form ) {
        if ( ! is_array( $form ) ) {
            continue;
        }

        $variant = isset( $by_form[ (string) $form_id ] ) ? $by_form[ (string) $form_id ] : array();
        $type    = isset( $variant['widgetType'] ) ? (string) $variant['widgetType'] : '';
        $live    = claspo_is_form_live( $form );
        $teaser  = 'TEASER' === $type;

        $entry = array(
            'id'       => (int) $form_id,
            'name'     => isset( $form['name'] ) && '' !== $form['name'] ? (string) $form['name'] : 'Untitled widget',
            'type'     => $type,
            'layout'   => isset( $variant['type'] ) ? (string) $variant['type'] : '',
            'live'     => $live,
            'testing'  => isset( $form['testingStatus'] ) && 'TESTING' === $form['testingStatus'],
            'collects' => claspo_type_collects( $type ),
            'teaser'   => $teaser,
            'prizes'   => ! empty( $variant['prizePoolIds'] ),
        );

        if ( ! $live ) {
            $groups[2][] = $entry;
        } elseif ( $teaser ) {
            $groups[1][] = $entry;
        } else {
            $groups[0][] = $entry;
        }
    }

    return array_slice( array_merge( $groups[0], $groups[1], $groups[2] ), 0, $limit );
}

/**
 * @param int $widget_id
 * @return string Link to the widget in Claspo.
 */
function claspo_widget_url( $widget_id ) {
    return 'https://my.claspo.io/dashboard-ui/#/widget/' . (int) $widget_id;
}

/**
 * @param int $widget_id
 * @return string Link to the widget's collected submissions.
 */
function claspo_widget_leads_url( $widget_id ) {
    return 'https://my.claspo.io/dashboard-ui/#/leads/widget/' . (int) $widget_id . '/submissions';
}

/**
 * Turns a SNAKE_CASE constant from Claspo into something readable.
 *
 * Works on values we have never seen, which matters: Claspo keeps adding use
 * cases, and an unknown one should read as "Age gate" rather than break the row.
 *
 * @param string $value
 * @return string
 */
function claspo_label( $value ) {
    $value = trim( (string) $value );

    if ( '' === $value ) {
        return '';
    }

    return ucfirst( strtolower( str_replace( '_', ' ', $value ) ) );
}

/**
 * Keeps the last widget list we were actually able to read.
 *
 * In RESTRICTED the `forms` object comes back empty, so a plain overwrite would
 * wipe the names at exactly the moment the page most needs them: that state is
 * where we tell the owner which campaigns have been paused.
 *
 * @param array   $history
 * @param array[] $widgets Freshly parsed list, possibly empty.
 * @return array Updated history.
 */
function claspo_remember_widgets( array $history, array $widgets ) {
    $history = array_merge( claspo_default_history(), $history );

    if ( ! empty( $widgets ) ) {
        $history['last_widgets'] = $widgets;
    }

    return $history;
}

/**
 * The list the plugin page shows.
 *
 * Normally the current one. In RESTRICTED the response carries no widgets, so
 * the last list that could be read stands in: that is the state where naming
 * the paused campaigns matters most. The stand-in stays in RESTRICTED. An
 * account that deleted everything has no widgets, and listing the deleted ones
 * as live under "No widgets yet" would be the page contradicting itself.
 *
 * @param array $status
 * @param array $history
 * @return array[]
 */
function claspo_widgets_to_show( array $status, array $history ) {
    $current = isset( $status['widgets'] ) && is_array( $status['widgets'] ) ? $status['widgets'] : array();

    if ( ! empty( $current ) ) {
        return $current;
    }

    $restricted = isset( $status['state'] ) && CLASPO_STATE_RESTRICTED === $status['state'];
    $remembered = isset( $history['last_widgets'] ) && is_array( $history['last_widgets'] ) ? $history['last_widgets'] : array();

    return $restricted ? $remembered : array();
}
