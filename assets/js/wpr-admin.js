jQuery(document).ready(function ($) {
    function formatProduct(product) {
        if (product.loading) {
            return product.text;
        }
        return $('<span>' + product.text + '</span>');
    }

    function formatProductSelection(product) {
        return product.text || product.id;
    }

    function initializeSelect2() {
        $('#wpr_reference_product_selector').select2({
            ajax: {
                url: wprAdmin.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'wpr_search_products',
                        q: params.term,
                        security: wprAdmin.security
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                },
                cache: true
            },
            minimumInputLength: 3,
            placeholder: 'Search for a product...',
            templateResult: formatProduct,
            templateSelection: formatProductSelection
        });

        $('#wpr_reference_product_selector').on('select2:select', function (e) {
            var selectedProduct = e.params.data;
            $('#_wpr_reference_product_id').val(selectedProduct.id);
        });

        // Load the initial selected product
        var selectedProductId = $('#_wpr_reference_product_id').val();
        if (selectedProductId) {
            $.ajax({
                url: wprAdmin.ajax_url,
                dataType: 'json',
                data: {
                    action: 'wpr_search_products',
                    q: selectedProductId,
                    security: wprAdmin.security
                },
                success: function (data) {
                    if (data && data.length > 0) {
                        var product = data.find(item => item.id == selectedProductId);
                        if (product) {
                            var option = new Option(product.text, product.id, true, true);
                            $('#wpr_reference_product_selector').append(option).trigger('change');
                        }
                    }
                }
            });
        }
    }

    function toggleReferenceProductFields() {
        if ($('#_wpr_enable_reference_product').is(':checked')) {
            $('#wpr_reference_product_wrapper').show();
            $('.pricing').hide();
        } else {
            $('#wpr_reference_product_wrapper').hide();
            $('.pricing').show();
        }
    }

    $('#_wpr_enable_reference_product').change(function () {
        toggleReferenceProductFields();
    });

    toggleReferenceProductFields();
    initializeSelect2();
});
