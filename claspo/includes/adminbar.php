<?php
/**
 * Claspo in the WordPress toolbar, and the icons for both the toolbar and the
 * admin menu.
 *
 * The toolbar menu is a system menu: one line of status that is not a link,
 * the three ways in, and the upgrade offer for free accounts, each in its own
 * group so the toolbar draws a separator between them. No widgets are listed.
 * The plugin cannot tell which widget is showing on the page you are looking
 * at, because the display rules run inside the Claspo script, and a list of
 * "New widget" entries answered nothing. The plugin page is one click away
 * with the full table and the leads links.
 *
 * Two different icons on purpose. The toolbar sits on a strip the plugin does
 * not own, so a full-colour mark reads as a brand. The admin menu is a column
 * of monochrome icons, so a colour logo there just looks out of place; that one
 * is drawn as a mask tinted with currentColor, which makes it follow whichever
 * of the eight admin colour schemes the user has picked, including hover and
 * active states.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_bar_menu', 'claspo_admin_bar_menu', 80 );
add_action( 'admin_head', 'claspo_icon_styles' );
add_action( 'wp_head', 'claspo_icon_styles' );

/**
 * The toolbar node belongs on the site itself, not in wp-admin.
 *
 * In the admin the plugin already has its own menu item; a second entry in the
 * toolbar pointing at the same page is noise. On the front end it is the only
 * way in, and the only place that says whether popups are running.
 *
 * @return bool
 */
function claspo_show_in_bar() {
    return ! is_admin() && is_admin_bar_showing() && current_user_can( 'manage_options' );
}

/**
 * @return void
 */
function claspo_icon_styles() {
    $in_bar = claspo_show_in_bar();

    if ( ! $in_bar && ! is_admin() ) {
        return;
    }

    $mask  = plugin_dir_url( dirname( __FILE__ ) ) . 'img/claspo-mark.svg';
    $color = plugin_dir_url( dirname( __FILE__ ) ) . 'img/claspo-mark-color.svg';
    ?>
    <style id="claspo-icons">
    <?php if ( is_admin() ) : ?>
        #adminmenu .toplevel_page_claspo_script_plugin .wp-menu-image {
            background-color: currentColor;
            -webkit-mask: url('<?php echo esc_url( $mask ); ?>') no-repeat center;
                    mask: url('<?php echo esc_url( $mask ); ?>') no-repeat center;
            -webkit-mask-size: 20px auto;
                    mask-size: 20px auto;
        }
        #adminmenu .toplevel_page_claspo_script_plugin .wp-menu-image::before {
            content: none;
        }
    <?php endif; ?>
    <?php if ( $in_bar ) : ?>
        #wpadminbar #wp-admin-bar-claspo .claspo-bar-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 6px;
            vertical-align: middle;
            background: url('<?php echo esc_url( $color ); ?>') no-repeat center / 18px auto;
        }
        #wpadminbar #wp-admin-bar-claspo .claspo-bar-count {
            display: inline-block;
            min-width: 18px;
            padding: 0 5px;
            margin-left: 6px;
            border-radius: 9px;
            background: #16a34a;
            color: #fff;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
        }
        #wpadminbar #wp-admin-bar-claspo .claspo-bar-count.is-problem {
            background: #f3492c;
        }
        /* The status line is information, not a control. */
        #wpadminbar #wp-admin-bar-claspo-status .ab-item {
            cursor: default;
        }
        #wpadminbar #wp-admin-bar-claspo .claspo-bar-dim {
            opacity: .65;
        }
    <?php endif; ?>
    </style>
    <?php
}

/**
 * Builds the toolbar menu.
 *
 * Reads two options that are not autoloaded, so this costs two queries — only
 * for administrators, who are the only ones who see it.
 *
 * @param WP_Admin_Bar $bar
 * @return void
 */
function claspo_admin_bar_menu( $bar ) {
    if ( ! claspo_show_in_bar() ) {
        return;
    }

    $status  = claspo_get_status();
    $history = claspo_get_history();
    $copy    = claspo_status_copy( $status['state'], $history );
    $problem = in_array( $status['state'], claspo_problem_states(), true );
    $live    = ( CLASPO_STATE_ACTIVE === $status['state'] ) ? (int) $status['widgets_live'] : 0;
    $plugin  = admin_url( 'admin.php?page=claspo_script_plugin' );

    // No number until there is an answer: a "0" before the first check would
    // read as "nothing is live", which is not something we know yet.
    if ( CLASPO_STATE_UNKNOWN === $status['state'] ) {
        $count = '';
    } else {
        $count = $problem ? '!' : (string) $live;
    }

    $bar->add_node( array(
        'id'    => 'claspo',
        'title' => '<span class="claspo-bar-icon"></span>Claspo'
            . ( '' === $count
                ? ''
                : '<span class="claspo-bar-count' . ( $problem ? ' is-problem' : '' ) . '">' . esc_html( $count ) . '</span>' ),
        'href'  => $plugin,
    ) );

    // 1. The state, as a line of text. No href, so the toolbar renders it as
    //    an item that does not react to hover; it is an answer, not a control.
    $bar->add_group( array( 'id' => 'claspo-state', 'parent' => 'claspo' ) );

    $bar->add_node( array(
        'id'     => 'claspo-status',
        'parent' => 'claspo-state',
        'title'  => esc_html( $copy['title'] )
            . ( $live > 0 ? ' <span class="claspo-bar-dim">&middot; ' . $live . ' live</span>' : '' ),
    ) );

    // 2. The ways in. A secondary group draws as a separate band.
    $bar->add_group( array(
        'id'     => 'claspo-actions',
        'parent' => 'claspo',
        'meta'   => array( 'class' => 'ab-sub-secondary' ),
    ) );

    $bar->add_node( array(
        'id'     => 'claspo-settings',
        'parent' => 'claspo-actions',
        'title'  => 'Claspo settings',
        'href'   => $plugin,
    ) );

    $bar->add_node( array(
        'id'     => 'claspo-all',
        'parent' => 'claspo-actions',
        'title'  => 'Manage widgets',
        'href'   => 'https://my.claspo.io/dashboard-ui/#/widgets-list',
        'meta'   => array( 'target' => '_blank', 'rel' => 'noopener' ),
    ) );

    $bar->add_node( array(
        'id'     => 'claspo-new',
        'parent' => 'claspo-actions',
        'title'  => 'Create a widget',
        'href'   => 'https://my.claspo.io/dashboard-ui/#/widget/from-template/new',
        'meta'   => array( 'target' => '_blank', 'rel' => 'noopener' ),
    ) );

    // 3. The offer, for free accounts only. Its own group again, so it sits
    //    apart from the actions rather than reading as a fourth one.
    if ( claspo_is_free_plan() ) {
        $bar->add_group( array( 'id' => 'claspo-plan', 'parent' => 'claspo' ) );

        $bar->add_node( array(
            'id'     => 'claspo-upgrade',
            'parent' => 'claspo-plan',
            'title'  => 'Try Pro free for 14 days',
            'href'   => claspo_build_upgrade_url( 'admin_bar' ),
            'meta'   => array( 'target' => '_blank', 'rel' => 'noopener' ),
        ) );
    }
}

/**
 * @param string $text
 * @param int    $length
 * @return string
 */
function claspo_shorten( $text, $length ) {
    $text = trim( (string) $text );

    if ( function_exists( 'mb_strlen' ) ) {
        return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length - 1 ) . '…' : $text;
    }

    return strlen( $text ) > $length ? substr( $text, 0, $length - 1 ) . '…' : $text;
}
