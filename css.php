<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

add_action( 'wp_head', function () {
    // works only on 'checkout' page
    if (! (is_page( 'checkout' ) || is_checkout())) {
        return;
    }
    global $speedy_region_field, $speedy_city_field, $speedy_office_field, $econt_region_field, $econt_city_field,
           $econt_office_field, $address_region_field, $address_city_field, $address_address_field;
    $custom_deliv_fields = array($speedy_region_field, $speedy_city_field, $speedy_office_field,
        $econt_region_field, $econt_city_field, $econt_office_field, $address_region_field);
    $address_deliv_fields = array($address_city_field, $address_address_field);
    $add_fields = getAdditionallyHiddenFields();
    ?>
    <style>
        <?php echo implode(", ", array_merge($custom_deliv_fields, $address_deliv_fields, $add_fields)); ?> {
            display: none;
        }

        <?php if (! isShowDelivOpts()) {?>
        #shipping_to_field {
            display: none;
        }
        <?php } ?>

        <?php echo implode(' .select2-container, ', array_merge($custom_deliv_fields)) . ' .select2-container'; ?> {
            width:100%!important;
        }

        #shipping_to_field span label {
            display: inline;
            margin-left: 5px;
            margin-right: 15px;
        }

        #shipping_to_field input[type='radio'] {
            transform: scale(1.4);
        }

        <?php global $shipping_to_speedy_key, $shipping_to_econt_key, $shipping_to_address_key;
        if (! isSpeedyEnabled()) {
            echo '#'.$shipping_to_speedy_key.', label[for="'.$shipping_to_speedy_key.'"] {display: none!important;}', PHP_EOL;
        }
        if (! isEcontEnabled()) {
            echo '#'.$shipping_to_econt_key.', label[for="'.$shipping_to_econt_key.'"] {display: none!important;}', PHP_EOL;
        }
        if (! isAddressEnabled()) {
            echo '#'.$shipping_to_address_key.', label[for="'.$shipping_to_address_key.'"] {display: none!important;}', PHP_EOL;
        } ?>
    </style>
<?php } );
