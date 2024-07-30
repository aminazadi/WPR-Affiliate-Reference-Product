<?php
class WPR_Product_Selector
{
    public static function render($post_id)
    {
        $reference_product_id = get_post_meta($post_id, '_wpr_reference_product_id', true);
        $reference_product_title = $reference_product_id ? get_the_title($reference_product_id) : '';

        echo woocommerce_wp_checkbox(array(
            'id' => '_wpr_enable_reference_product',
            'label' => __('Enable Reference Product', 'wpr-affiliate-reference-product'),
            'description' => __('Enable to select a reference product.', 'wpr-affiliate-reference-product'),
            'value' => get_post_meta($post_id, '_wpr_enable_reference_product', true) === 'yes' ? 'yes' : 'no'
        ));

        echo woocommerce_wp_hidden_input(array(
            'id' => '_wpr_reference_product_id',
            'value' => $reference_product_id,
        ));

        echo '<div id="wpr_reference_product_wrapper" style="display: none;">';
        echo '<p class="form-field"><label for="wpr_reference_product_selector">' . __('Select Reference Product', 'wpr-affiliate-reference-product') . '</label>';
        echo '<select id="wpr_reference_product_selector" style="width: 50%;">';
        if ($reference_product_id) {
            echo '<option value="' . esc_attr($reference_product_id) . '" selected="selected">' . esc_html($reference_product_title) . ' (ID: ' . esc_attr($reference_product_id) . ')</option>';
        }
        echo '</select>';
        echo '<button type="button" style="margin-right:5px" id="wpr_clear_reference_product" class="button">' . __('Clear Product', 'wpr-affiliate-reference-product') . '</button></p>';
        echo '</div>';
    }

    public static function search_products()
    {
        check_ajax_referer('search-products', 'security');

        $term = sanitize_text_field($_GET['q']);
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            's' => $term
        );

        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $products[] = array(
                    'id' => get_the_ID(),
                    'text' => get_the_title() . ' (ID: ' . get_the_ID() . ')'
                );
            }
        }

        wp_reset_postdata();
        wp_send_json($products);
    }
}
