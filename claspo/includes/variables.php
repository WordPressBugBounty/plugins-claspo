<?php
/**
 * Values the plugin hands to Claspo so they can be used as display conditions.
 *
 * One registry drives everything: the collectors below, the reference table on
 * the plugin page, and the REST payload. Names and descriptions live in exactly
 * one place, because a variable whose name differs between the page and the
 * documentation is worse than no variable at all.
 *
 * Delivery is split on purpose. Anything that depends only on the URL is
 * printed into the page; anything that differs per visitor is fetched after
 * load. Printing cart contents inline would either break full page caching or,
 * worse, serve one shopper's cart to another from the cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return array Group id => title, default state, delivery, and its variables.
 */
function claspo_variable_groups() {
    return array(
        'content'  => array(
            'title'    => 'Page content',
            'default'  => true,
            'delivery' => 'inline',
            'vars'     => array(
                'wp_page_type'           => array( 'Which kind of page this is', "'product'" ),
                'wp_post_type'           => array( 'Post type of the current entry', "'post'" ),
                'wp_post_categories'     => array( 'Categories, separated by a pipe', "'Guides|SEO'" ),
                'wp_post_tags'           => array( 'Tags, separated by a pipe', "'wordpress|popups'" ),
                'wp_search_result_count' => array( 'Results found, on search pages', '0' ),
                'wp_referrer'            => array( 'Where the visitor came from', "'google.com'" ),
            ),
        ),
        'product'  => array(
            'title'    => 'WooCommerce product',
            'default'  => true,
            'delivery' => 'inline',
            'requires' => 'woocommerce',
            'vars'     => array(
                'wp_product_name'       => array( 'Name of the product being viewed', "'Cotton Shirt'" ),
                'wp_product_sku'        => array( 'Its SKU', "'CS-4410-M'" ),
                'wp_product_price'      => array( 'Current price, as a number', '59.99' ),
                'wp_product_stock'      => array( 'Stock level, when tracked', '12' ),
                'wp_product_categories' => array( 'Product categories', "'Men|Shirts'" ),
                'wp_product_tags'       => array( 'Product tags', "'cotton|summer'" ),
            ),
        ),
        'cart'     => array(
            'title'    => 'WooCommerce cart',
            'default'  => false,
            'delivery' => 'fetched',
            'requires' => 'woocommerce',
            'vars'     => array(
                'wp_cart_total'                   => array( 'Products total after discounts, with tax, before shipping', '148.50' ),
                'wp_cart_total_without_discounts' => array( 'Products total before discounts, with tax', '165.00' ),
                'wp_cart_items'                   => array( 'How many items are in the cart', '3' ),
                'wp_cart_item_kinds'              => array( 'How many different products', '2' ),
                'wp_cart_coupons'                 => array( 'Applied coupon codes', "'WELCOME10'" ),
            ),
        ),
        'visitor'  => array(
            'title'    => 'Visitor',
            'default'  => false,
            'delivery' => 'fetched',
            'vars'     => array(
                'wp_logged_in' => array( 'Whether the visitor is signed in', 'false' ),
            ),
        ),
    );
}

/**
 * @return array Group id => bool, every group present.
 */
function claspo_variable_settings() {
    $stored   = get_option( 'claspo_variables' );
    $stored   = is_array( $stored ) ? $stored : array();
    $settings = array();

    foreach ( claspo_variable_groups() as $id => $group ) {
        $settings[ $id ] = array_key_exists( $id, $stored )
            ? (bool) $stored[ $id ]
            : (bool) $group['default'];
    }

    return $settings;
}

/**
 * @param string $group_id
 * @return bool
 */
function claspo_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Whether a group can do anything on this site at all.
 *
 * WooCommerce is an ordinary plugin: it can be absent, and it can be switched
 * off on a site that had it yesterday. Availability is therefore asked at the
 * moment it matters rather than recorded anywhere, so the answer follows the
 * site instead of going stale.
 *
 * This is deliberately not the same question as "is this group switched on".
 * The stored choice is left untouched while WooCommerce is away, so turning
 * the shop back on restores the variables the owner had picked rather than
 * silently handing them a default.
 *
 * @param string $group_id
 * @return bool
 */
function claspo_group_available( $group_id ) {
    $groups = claspo_variable_groups();

    if ( ! isset( $groups[ $group_id ] ) ) {
        return false;
    }

    if ( empty( $groups[ $group_id ]['requires'] ) ) {
        return true;
    }

    return 'woocommerce' === $groups[ $group_id ]['requires'] && claspo_woocommerce_active();
}

/**
 * The settings to store for a submitted form.
 *
 * Separate from the request handler because this is where the thinking is: a
 * group the form never offered keeps whatever it had, and only the groups that
 * were actually on screen follow the checkboxes.
 *
 * @param array $submitted The claspo_vars part of the POST, already sanitised.
 * @return array Group id => bool, ready for the option.
 */
function claspo_merge_variable_settings( $submitted ) {
    $stored = get_option( 'claspo_variables' );
    $stored = is_array( $stored ) ? $stored : array();

    $settings = array();

    foreach ( array_keys( claspo_variable_groups() ) as $group_id ) {
        if ( ! claspo_group_available( $group_id ) ) {
            if ( array_key_exists( $group_id, $stored ) ) {
                $settings[ $group_id ] = (bool) $stored[ $group_id ];
            }

            continue;
        }

        $settings[ $group_id ] = isset( $submitted[ $group_id ] );
    }

    return $settings;
}

/**
 * Whether a group is on AND can actually produce anything.
 *
 * Everything that collects or emits variables goes through here, so a site
 * without WooCommerce prints no product values, fetches nothing and starts no
 * cart observer, whatever the saved settings happen to say.
 *
 * @param string $group_id
 * @return bool
 */
function claspo_group_enabled( $group_id ) {
    if ( ! claspo_group_available( $group_id ) ) {
        return false;
    }

    $settings = claspo_variable_settings();

    return ! empty( $settings[ $group_id ] );
}

/**
 * @return bool Whether any group needs the after-load request.
 */
function claspo_needs_fetched_variables() {
    foreach ( claspo_variable_groups() as $id => $group ) {
        if ( 'fetched' === $group['delivery'] && claspo_group_enabled( $id ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Names a page in terms the site owner recognises, rather than exposing
 * WordPress's query flags one by one.
 *
 * @return string
 */
function claspo_page_type() {
    if ( function_exists( 'is_product' ) ) {
        if ( is_product() ) {
            return 'product';
        }
        if ( is_cart() ) {
            return 'cart';
        }
        if ( is_checkout() ) {
            return 'checkout';
        }
        if ( is_account_page() ) {
            return 'account';
        }
        if ( is_shop() || is_product_category() || is_product_tag() ) {
            return 'shop';
        }
    }

    if ( is_front_page() ) {
        return 'front';
    }
    if ( is_home() ) {
        return 'blog';
    }
    if ( is_search() ) {
        return 'search';
    }
    if ( is_404() ) {
        return '404';
    }
    if ( is_singular() ) {
        return is_page() ? 'page' : 'single';
    }
    if ( is_category() ) {
        return 'category';
    }
    if ( is_tag() ) {
        return 'tag';
    }
    if ( is_author() ) {
        return 'author';
    }
    if ( is_archive() ) {
        return 'archive';
    }

    return 'other';
}

/**
 * @param array|WP_Error $terms
 * @return string Names joined by a pipe, which is what Claspo's "contains one
 *                of" operator expects.
 */
function claspo_join_terms( $terms ) {
    if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
        return '';
    }

    $names = array();

    foreach ( $terms as $term ) {
        if ( isset( $term->name ) ) {
            $names[] = $term->name;
        }
    }

    return implode( '|', $names );
}

/**
 * Values that depend only on which URL is being viewed, so they are safe to
 * print into a page that may be cached and served to anyone.
 *
 * @return array
 */
function claspo_inline_variables() {
    $vars = array();

    if ( claspo_group_enabled( 'content' ) ) {
        $vars['wp_page_type'] = claspo_page_type();

        if ( is_singular() ) {
            $post_id = get_queried_object_id();

            $vars['wp_post_type']       = (string) get_post_type( $post_id );
            $vars['wp_post_categories'] = claspo_join_terms( get_the_category( $post_id ) );
            $vars['wp_post_tags']       = claspo_join_terms( get_the_tags( $post_id ) );
        }

        if ( is_search() ) {
            global $wp_query;
            $vars['wp_search_result_count'] = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;
        }
    }

    if ( claspo_group_enabled( 'product' ) && function_exists( 'is_product' ) && is_product() ) {
        $product = wc_get_product( get_queried_object_id() );

        if ( $product ) {
            $vars['wp_product_name']       = (string) $product->get_name();
            $vars['wp_product_sku']        = (string) $product->get_sku();
            $vars['wp_product_price']      = (float) $product->get_price();
            $vars['wp_product_categories'] = claspo_join_terms( get_the_terms( $product->get_id(), 'product_cat' ) );
            $vars['wp_product_tags']       = claspo_join_terms( get_the_terms( $product->get_id(), 'product_tag' ) );

            if ( $product->managing_stock() ) {
                $vars['wp_product_stock'] = (int) $product->get_stock_quantity();
            }
        }
    }

    return array_filter( $vars, 'claspo_variable_is_set' );
}

/**
 * Values that differ per visitor. Never printed into the page.
 *
 * @return array
 */
function claspo_fetched_variables() {
    $vars = array();
    $cart = null;

    if ( claspo_group_enabled( 'visitor' ) ) {
        $vars['wp_logged_in'] = is_user_logged_in();
    }

    if ( claspo_group_enabled( 'cart' ) && function_exists( 'WC' ) ) {
        // WooCommerce loads the cart only for what it calls a frontend request.
        // Whether this one counts depends on how it arrived, so load it here
        // if it has not been; otherwise every cart variable is silently absent.
        if ( empty( WC()->cart ) && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        $cart = WC()->cart;
    }

    if ( ! empty( $cart ) ) {
        // Both figures count the products only. WooCommerce's grand total also
        // carries shipping, fees and their taxes, which the "before discounts"
        // figure never had, so the pair disagreed the moment shipping was
        // chosen. Products are also the basis WooCommerce itself uses for the
        // free-shipping threshold, which is what "add X more" popups want.
        $vars['wp_cart_total']                   = (float) $cart->get_cart_contents_total() + (float) $cart->get_cart_contents_tax();
        $vars['wp_cart_total_without_discounts'] = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
        $vars['wp_cart_items']                   = (int) $cart->get_cart_contents_count();
        $vars['wp_cart_item_kinds']              = count( $cart->get_cart() );
        $vars['wp_cart_coupons']                 = implode( '|', (array) $cart->get_applied_coupons() );
    }

    return $vars;
}

/**
 * An empty string carries no targeting value and only adds noise, while `0`
 * and `false` are real answers that rules can test against.
 *
 * @param mixed $value
 * @return bool
 */
function claspo_variable_is_set( $value ) {
    return '' !== $value && null !== $value;
}
