<?php
/**
 * Getting the variables to Claspo, and saving which groups are on.
 *
 * Everything rides on the same inline script handle as the Claspo loader.
 * A separate file would need to declare a dependency on a handle that is only
 * registered in the footer, and would cost an extra request for ten lines.
 *
 * The per-visitor values are served from admin-ajax.php rather than the REST
 * API, and that is not a matter of taste. A REST request without a nonce is
 * treated by WordPress as anonymous, so is_user_logged_in() is false for
 * everyone; and WooCommerce does not load the cart for REST requests at all.
 * A nonce would fix the first and break full-page caching, since the nonce is
 * per user and the page is served to everyone. admin-ajax has neither problem:
 * cookies identify the visitor without a nonce, and WooCommerce counts an AJAX
 * request as frontend, so the cart is there.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_footer', 'claspo_print_variables', 11 );
add_action( 'wp_ajax_claspo_vars', 'claspo_ajax_variables' );
add_action( 'wp_ajax_nopriv_claspo_vars', 'claspo_ajax_variables' );
add_action( 'admin_post_claspo_save_variables', 'claspo_handle_save_variables' );

/**
 * Appends the variable calls after the Claspo loader.
 *
 * Runs at priority 11, after claspo_add_claspo_script() has registered the
 * handle at 10 and before wp_print_footer_scripts() prints it at 20.
 *
 * @return void
 */
function claspo_print_variables() {
    if ( ! get_option( 'claspo_script_code' ) ) {
        return;
    }

    $js = '';

    $inline = claspo_inline_variables();
    if ( ! empty( $inline ) ) {
        $js .= 'claspo("addVariables",' . wp_json_encode( $inline ) . ');';
    }

    // The referrer is only knowable in the browser. Reading it there rather than
    // from the request keeps it out of any cached copy of this page.
    if ( claspo_group_enabled( 'content' ) ) {
        $js .= 'try{if(document.referrer){claspo("addVariables",{wp_referrer:new URL(document.referrer).hostname});}}catch(e){}';
    }

    if ( claspo_needs_fetched_variables() ) {
        $js .= claspo_fetch_snippet(
            esc_url_raw( claspo_variables_url() ),
            claspo_group_enabled( 'cart' )
        );
    }

    if ( '' !== $js ) {
        wp_add_inline_script( 'claspo-script', $js );
    }
}

/**
 * Where the browser fetches the per-visitor values from.
 *
 * Kept well away from anything in claspo_cart_markers(): the cart watcher fires
 * on any request whose URL carries a marker, and if this URL did, every answer
 * would schedule the next question.
 *
 * @return string
 */
function claspo_variables_url() {
    return admin_url( 'admin-ajax.php?action=claspo_vars' );
}

/**
 * Markers in a request URL that mean the cart just changed.
 *
 * Two families, because WooCommerce has two carts. The classic one posts to
 * ?wc-ajax=<action>; the Cart and Checkout blocks talk to the Store API under
 * /wc/store/. Matching on the whole /wc/store/ path rather than its individual
 * routes covers add-item, update-item, remove-item, coupons and batch in one
 * marker, at the cost of also firing on a plain cart read — which is harmless,
 * since a read means the cart is on screen and re-reading it is what we want.
 *
 * Not every classic change shows in a URL: see claspo_cart_events().
 *
 * @return array
 */
function claspo_cart_markers() {
    return array(
        'get_refreshed_fragments',
        'add_to_cart',
        'remove_from_cart',
        'apply_coupon',
        'remove_coupon',
        'update_shipping_method',
        'update_order_review',
        '/wc/store/',
    );
}

/**
 * WooCommerce's own jQuery events that mean the cart just changed.
 *
 * The classic cart page does two things no URL gives away: "Update cart" posts
 * the form to the cart page itself, and the remove link is a GET on that same
 * page. The fragments script that would then ask for get_refreshed_fragments
 * is only loaded alongside the mini-cart widget. What is always there is the
 * event WooCommerce fires on document.body once the page has been updated, so
 * the snippet listens to those as well, when jQuery is on the page.
 *
 * @return string[]
 */
function claspo_cart_events() {
    return array(
        'updated_wc_div',
        'updated_cart_totals',
        'updated_checkout',
        'added_to_cart',
        'removed_from_cart',
        'applied_coupon',
        'removed_coupon',
        'wc_fragments_refreshed',
    );
}

/**
 * The browser half of the per-visitor variables.
 *
 * Fetches them once on load, and — when the cart group is on — again whenever
 * the cart changes, so a widget with a cart rule sees the cart the shopper
 * actually has rather than the one they had when the page was built. Adding to
 * a cart on a modern WooCommerce storefront never reloads the page, so without
 * this the values go stale the moment someone shops.
 *
 * Changes are spotted two ways. A PerformanceObserver over the page's own
 * network requests covers both carts wherever the request URL says what
 * happened, which is everything the block cart does. WooCommerce's jQuery
 * events cover the classic cart and checkout, where the URL often does not.
 * One event can arrive by both routes; the debounce folds that into one fetch.
 *
 * @param string $url        Endpoint returning the per-visitor variables.
 * @param bool   $watch_cart Whether the cart group is on.
 * @return string JavaScript, ready to append to the Claspo loader.
 */
function claspo_fetch_snippet( $url, $watch_cart ) {
    $js = '(function(){var u=' . wp_json_encode( $url ) . ',t=null,busy=false,again=false;'
        . 'function push(){'
        . 'if(busy){again=true;return;}'
        . 'busy=true;'
        . 'fetch(u,{credentials:"same-origin",cache:"no-store"})'
        . '.then(function(r){return r.ok?r.json():null;})'
        . '.then(function(v){if(v&&typeof v==="object"){claspo("addVariables",v);}})'
        . '.catch(function(){})'
        . '.then(function(){busy=false;if(again){again=false;schedule();}});'
        . '}'
        // One shopper action can produce several requests: adding to a cart fires
        // add_to_cart and then get_refreshed_fragments. Without the wait that is
        // two or three identical round trips for one click.
        . 'function schedule(){window.clearTimeout(t);t=window.setTimeout(push,400);}'
        . 'push();';

    if ( $watch_cart ) {
        $js .= 'if(window.PerformanceObserver){'
            . 'var m=' . wp_json_encode( array_values( claspo_cart_markers() ) ) . ';'
            . 'try{new PerformanceObserver(function(l){'
            . 'var e=l.getEntries();'
            . 'for(var i=0;i<e.length;i++){'
            . 'if("xmlhttprequest"!==e[i].initiatorType&&"fetch"!==e[i].initiatorType){continue;}'
            . 'for(var j=0;j<m.length;j++){'
            . 'if(-1!==e[i].name.indexOf(m[j])){schedule();return;}'
            . '}'
            . '}'
            . '}).observe({entryTypes:["resource"]});}catch(err){}'
            . '}';

        // jQuery is normally in the head, but a script optimiser can delay it
        // past this point, so the binding is tried again once the page has
        // loaded. The flag keeps it from being bound twice.
        $js .= 'var ev=' . wp_json_encode( implode( ' ', claspo_cart_events() ) ) . ';'
            . 'function bind(){'
            . 'if(!window.jQuery||bind.done){return;}'
            . 'bind.done=true;'
            . 'try{jQuery(document.body).on(ev,schedule);}catch(err){}'
            . '}'
            . 'bind();'
            . 'window.addEventListener("load",bind);';
    }

    return $js . '}());';
}

/**
 * Returns the asking visitor's own cart and sign-in state.
 *
 * Open to everyone on purpose: it reads the caller's own cookies and can only
 * ever describe the caller. The response is marked no-store so neither a page
 * cache nor a proxy can hand it to somebody else.
 *
 * @return void
 */
function claspo_ajax_variables() {
    header( 'Cache-Control: no-store, max-age=0' );

    wp_send_json( (object) claspo_fetched_variables() );
}

/**
 * @return void
 */
function claspo_handle_save_variables() {
    if ( ! isset( $_POST['claspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['claspo_nonce'] ) ), 'claspo_save_variables' ) ) {
        wp_die( 'Security check failed', 'Security Error', array( 'response' => 403 ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have sufficient permissions to access this page.', 'Permission Error', array( 'response' => 403 ) );
    }

    $submitted = isset( $_POST['claspo_vars'] ) && is_array( $_POST['claspo_vars'] )
        ? array_map( 'sanitize_text_field', wp_unslash( $_POST['claspo_vars'] ) )
        : array();

    claspo_save_variable_settings( $submitted );

    wp_safe_redirect( admin_url( 'admin.php?page=claspo_script_plugin&tab=targeting&updated=1' ) );
    exit;
}

/**
 * Stores the submitted groups and empties the page cache.
 *
 * The variables are printed into every page, so a cached copy made before the
 * save keeps handing visitors the old set: a group switched off still leaks,
 * and a group switched on does nothing until the cache expires on its own.
 * claspo_clear_cache() already knows the common caching plugins; connecting
 * the script uses it for the same reason.
 *
 * @param array $submitted The claspo_vars part of the POST, already sanitised.
 * @return array The settings as stored.
 */
function claspo_save_variable_settings( $submitted ) {
    $settings = claspo_merge_variable_settings( $submitted );

    update_option( 'claspo_variables', $settings, false );

    claspo_clear_cache();

    return $settings;
}
