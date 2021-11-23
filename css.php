<?php
add_action( 'wp_head', function () { ?>
    <style>
        #ship-to-different-address, #billing_country_field, #shipping_to_field,
        .woocommerce-shipping-totals.shipping, .cart-subtotal, .checkout-wrap, #speedy_region_sel_field,
        #speedy_city_sel_field, #speedy_office_sel_field, #econt_region_sel_field, #econt_city_sel_field,
        #econt_office_sel_field, #billing_state_field, #billing_city_field, #billing_address_1_field {
            display:none;
        }
        #speedy_region_sel_field .select2-container, #speedy_city_sel_field .select2-container,
        #speedy_office_sel_field .select2-container, #econt_region_sel_field .select2-container,
        #econt_city_sel_field .select2-container, #econt_office_sel_field .select2-container {
            width:100%!important;
        }
    </style>
<?php } );
