<?php
/**
 * Plugin Name: WPR Affiliate Reference Product
 * Description: Plugin to select a reference product in WooCommerce external/affiliate products.
 * Version: 1.0
 * Author: Mohammad Amin Azadian + GPT 4o :)
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-wpr-product-selector.php';

class WPRAffiliateReferenceProduct
{
    public function __construct()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_reference_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_reference_product_fields'));
        add_action('save_post_product', array($this, 'update_external_products_on_reference_update'), 20, 3);
        add_action('wp_ajax_wpr_search_products', array('WPR_Product_Selector', 'search_products'));
        add_action('woocommerce_before_single_product', array($this, 'update_affiliate_product_price_on_view'));
        add_action('woocommerce_before_add_to_cart_quantity', array($this, 'show_quantity_field_for_external_products'));

        // Add new action to display the last update date of reference product and its own update date
        add_action('woocommerce_single_product_summary', array($this, 'display_reference_product_last_update'), 25);
        add_action('woocommerce_single_product_summary', array($this, 'display_product_last_update'), 26);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('wpr-affiliate-reference-product', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('select2-css', plugins_url('assets/css/select2.min.css', __FILE__), [], '4.0.13');
        wp_enqueue_style('wpr-affiliate-reference-product-css', plugins_url('assets/css/wpr-affiliate-reference-product.css', __FILE__), [], '1.0');
        wp_enqueue_script('select2-js', plugins_url('assets/js/select2.min.js', __FILE__), ['jquery'], '4.0.13', true);
        wp_enqueue_script('wpr-admin-js', plugins_url('assets/js/wpr-admin.js', __FILE__), ['jquery', 'select2-js'], '1.0', true);

        wp_localize_script('wpr-admin-js', 'wprAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('search-products'),
        ]);
    }

    public function add_reference_product_fields()
    {
        global $post;
        echo '<div class="options_group show_if_external">';
        WPR_Product_Selector::render($post->ID);
        echo '</div>';
    }

    public function save_reference_product_fields($post_id)
    {
        $enable_reference_product = isset($_POST['_wpr_enable_reference_product']) ? 'yes' : 'no';
        $reference_product_id = isset($_POST['_wpr_reference_product_id']) ? sanitize_text_field($_POST['_wpr_reference_product_id']) : '';

        update_post_meta($post_id, '_wpr_enable_reference_product', $enable_reference_product);
        update_post_meta($post_id, '_wpr_reference_product_id', $reference_product_id);
    }

    public function update_affiliate_product_status_and_price($post_id)
    {
        $product = wc_get_product($post_id);
        if (!$product || $product->get_type() != 'external') {
            return;
        }

        $enable_reference_product = get_post_meta($post_id, '_wpr_enable_reference_product', true);
        if ($enable_reference_product !== 'yes') {
            return;
        }

        $reference_product_id = get_post_meta($post_id, '_wpr_reference_product_id', true);
        if (!$reference_product_id) {
            return;
        }

        $reference_product = wc_get_product($reference_product_id);
        if (!$reference_product) {
            return;
        }

        $regular_price = $reference_product->get_regular_price();
        $sale_price = $reference_product->get_sale_price();
        $stock_status = $reference_product->get_stock_status();

        update_post_meta($post_id, '_regular_price', $regular_price);
        update_post_meta($post_id, '_sale_price', $sale_price);
        update_post_meta($post_id, '_price', $sale_price ? $sale_price : $regular_price);
        update_post_meta($post_id, '_stock_status', $stock_status);
    }

    public function update_affiliate_product_price_on_view()
    {
        global $post;

        if (!$post || $post->post_type != 'product') {
            return;
        }

        $this->update_affiliate_product_status_and_price($post->ID);
    }

    public function update_external_products_on_reference_update($post_id, $post, $update)
    {
        if (!$update || $post->post_type != 'product') {
            return;
        }

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_wpr_reference_product_id',
                    'value' => $post_id,
                    'compare' => '='
                )
            )
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->update_affiliate_product_status_and_price(get_the_ID());
            }
        }

        wp_reset_postdata();
    }

    public function show_quantity_field_for_external_products()
    {
        global $product;

        if ($product->get_type() != 'external') {
            return;
        }

        $enable_reference_product = get_post_meta($product->get_id(), '_wpr_enable_reference_product', true);
        if ($enable_reference_product !== 'yes') {
            return;
        }

        $reference_product_id = get_post_meta($product->get_id(), '_wpr_reference_product_id', true);
        if (!$reference_product_id) {
            return;
        }

        $reference_product = wc_get_product($reference_product_id);
        if (!$reference_product) {
            return;
        }

        $max_quantity = $reference_product->get_stock_quantity();
        if ($max_quantity) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('input.qty').attr('max', '<?php echo esc_attr($max_quantity); ?>');
                });
            </script>
            <?php
        }
    }

    // Add new function to display last update date of reference product
    public function display_reference_product_last_update()
    {
        global $post;
        $enable_reference_product = get_post_meta($post->ID, '_wpr_enable_reference_product', true);
        if ($enable_reference_product !== 'yes') {
            return;
        }

        $reference_product_id = get_post_meta($post->ID, '_wpr_reference_product_id', true);
        if (!$reference_product_id) {
            return;
        }

        $reference_product = wc_get_product($reference_product_id);
        if (!$reference_product) {
            return;
        }

        $last_update = get_the_modified_date('j F Y, H:i', $reference_product_id);

        echo '<p class="reference-product-last-update">' . sprintf(__('Last update of reference product: %s', 'wpr-affiliate-reference-product'), $last_update) . '</p>';
    }

    // Add new function to display last update date of the product itself
    public function display_product_last_update()
    {
        global $post;

        if ($post->post_type != 'product') {
            return;
        }

        $last_update = get_the_modified_date('j F Y, H:i', $post->ID);

        echo '<p class="product-last-update">' . sprintf(__('Last update of this product: %s', 'wpr-affiliate-reference-product'), $last_update) . '</p>';
    }
}

new WPRAffiliateReferenceProduct();
