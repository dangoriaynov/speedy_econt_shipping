<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

add_action( 'wp_head', function () {
    // works only on 'checkout' page
    if (! (is_page( 'checkout' ) || is_checkout())) {
        return;
    }
    ?>
    <style>
        #speedy_region_sel_field, #speedy_city_sel_field, #speedy_office_sel_field,
        #econt_region_sel_field, #econt_city_sel_field, #econt_office_sel_field,
        #billing_state_field, #billing_city_field, #billing_address_1_field, #shipping_to_field,
        #billing_address_2_field, #billing_company_field, #ship-to-different-address, #billing_country_field,
        .cart-subtotal, .checkout-wrap, .woocommerce-shipping-totals.shipping, #billing_postcode_field {
            display: none;
        }

        #speedy_region_sel_field .select2-container, #speedy_city_sel_field .select2-container,
        #speedy_office_sel_field .select2-container, #econt_region_sel_field .select2-container,
        #econt_city_sel_field .select2-container, #econt_office_sel_field .select2-container,
        #billing_state_field .select2-container{
            width:100%!important;
        }

        #shipping_to_field span label {
            display: inline!important;
            margin-left: 5px;
            margin-right: 15px;
        }

        #shipping_to_field input[type='radio'] {
            transform: scale(1.4);
        }
    </style>
<?php } );
