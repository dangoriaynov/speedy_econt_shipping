<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

class SeshSpeedyEcontShippingAdmin {
    private $speedy_econt_shipping_options;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'speedy_econt_shipping_add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'speedy_econt_shipping_page_init' ) );
    }

    public function speedy_econt_shipping_add_plugin_page() {
        add_options_page(
            __('Speedy & Econt shipping', 'speedy_econt_shipping'), // page_title
            __('Speedy & Econt shipping', 'speedy_econt_shipping'), // menu_title
            'manage_options', // capability
            'speedy-econt-shipping', // menu_slug
            array( $this, 'speedy_econt_shipping_create_admin_page' ) // function
        );
    }

    public function speedy_econt_shipping_create_admin_page() {
        $this->speedy_econt_shipping_options = get_option( 'speedy_econt_shipping_option_name' ); ?>

        <div class="wrap">
            <h2><?php _e('Speedy & Econt shipping', 'speedy_econt_shipping') ?></h2>
            <p><?php _e('Specify here the information needed for the plugin to work.<br><b>Please request API access from Speedy courier and specify the values given.</b>', 'speedy_econt_shipping') ?></p>
            <p><?php _e('<b>In case of errors, please check output of the following commands on your hosting:</b>', 'speedy_econt_shipping') ?></p>
            <p><i>curl -X POST -H "Content-Type: application/json" --data '{"countryCode": "BGR"} ' https://ee.econt.com/services/Nomenclatures/NomenclaturesService.getCities.json</i><br><i>curl -X POST -H "Content-Type: application/json" --data '{"userName": "&lt;speedy username&gt;","password": "&lt;speedy password&gt;","language": "BG","countryId": 100}' https://api.speedy.bg/v1/location/office/</i>
            </p>
            <p><?php _e('<b>You may also enable the <a href="https://wordpress.org/support/article/debugging-in-wordpress/" target="_blank">debug mode</a> in your site and check the debug.log file for errors.</b>', 'speedy_econt_shipping') ?></p>
            <p><?php _e('<b>Be aware that in order to do the force update of the Econt/Speedy offices and sites tables you could simply disable and re-enable the plugin.</b>', 'speedy_econt_shipping') ?></p>
            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                submit_button();
                settings_fields( 'speedy_econt_shipping_option_group' );
                do_settings_sections( 'speedy-econt-shipping-admin' );
                submit_button();
                ?>
            </form>
        </div>
    <?php }

    public function speedy_econt_shipping_page_init() {
        register_setting(
            'speedy_econt_shipping_option_group', // option_group
            'speedy_econt_shipping_option_name', // option_name
            array( $this, 'speedy_econt_shipping_sanitize' ) // sanitize_callback
        );

        add_settings_section(
            'speedy_econt_shipping_setting_section', // id
            __('Settings', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_econt_shipping_section_info' ), // callback
            'speedy-econt-shipping-admin' // page
        );

        add_settings_field(
            'enable_speedy_0', // id
            __('Enable `Speedy` shipping method', 'speedy_econt_shipping'), // title
            array( $this, 'enable_speedy_0_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'speedy_username_0', // id
            __('`Speedy` Username', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_username_0_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'speedy_password_1', // id
            __('`Speedy` Password', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_password_1_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'speedy_free_from_6', // id
            __('`Speedy` free delivery from', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_free_from_6_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'speedy_shipping_7', // id
            __('`Speedy` shipping fee', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_shipping_7_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'enable_econt_1', // id
            __('Enable `Econt` shipping method', 'speedy_econt_shipping'), // title
            array( $this, 'enable_econt_1_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'econt_free_from_8', // id
            __('`Econt` free delivery from', 'speedy_econt_shipping'), // title
            array( $this, 'econt_free_from_8_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'econt_shipping_9', // id
            __('`Econt` shipping fee', 'speedy_econt_shipping'), // title
            array( $this, 'econt_shipping_9_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'enable_address_2', // id
            __('Enable `To Address` shipping method', 'speedy_econt_shipping'), // title
            array( $this, 'enable_address_2_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'address_free_from_10', // id
            __('`To address` free delivery from', 'speedy_econt_shipping'), // title
            array( $this, 'address_free_from_10_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'address_shipping_11', // id
            __('`To address` shipping fee', 'speedy_econt_shipping'), // title
            array( $this, 'address_shipping_11_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'address_fields_3', // id
            __('List of `To address` shipping fields', 'speedy_econt_shipping'), // title
            array( $this, 'address_fields_3_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'additionally_hidden_fields_3', // id
            __('Additionally hidden checkout fields', 'speedy_econt_shipping'), // title
            array( $this, 'additionally_hidden_fields_3_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'show_store_messages_6', // id
            __('Show store messages', 'speedy_econt_shipping'), // title
            array( $this, 'show_store_messages_6_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'show_deliv_opts_6', // id
            __('Immediately show delivery options', 'speedy_econt_shipping'), // title
            array( $this, 'show_deliv_opts_6_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

//        add_settings_field(
//            'repopulate_tables_7', // id
//            __('Force re-populate tables', 'speedy_econt_shipping'), // title
//            array( $this, 'repopulate_tables_7_callback' ), // callback
//            'speedy-econt-shipping-admin', // page
//            'speedy_econt_shipping_setting_section' // section
//        );

        add_settings_field(
            'calculate_final_price_8', // id
            __('Calculate final price on checkout', 'speedy_econt_shipping'), // title
            array( $this, 'calculate_final_price_8_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );
    }

    public function speedy_econt_shipping_sanitize($input): array {
        $sanitary_values = array();
        $keys = array('speedy_username_0', 'speedy_password_1', 'speedy_free_from_6', 'speedy_shipping_7',
            'econt_free_from_8', 'econt_shipping_9',
            'address_free_from_10', 'address_shipping_11',
            'address_fields_3', 'additionally_hidden_fields_3');
        $checkboxes = array('enable_speedy_0', 'enable_econt_1', 'enable_address_2',
            'show_store_messages_6', 'show_deliv_opts_6', 'repopulate_tables_7', 'calculate_final_price_8');
        foreach($keys as &$value) {
            if ( isset( $input[$value] ) ) {
                $sanitary_values[$value] = sanitize_text_field( $input[$value] );
            }
        }
        foreach($checkboxes as &$value) {
            $sanitary_values[$value] = isset( $input[$value] );  # set true or false
        }
        return $sanitary_values;
    }

    public function speedy_econt_shipping_section_info() {}

    private function generic_callback($name, $type='text', $placeholder='', $value='') {
        $add = "";
        if ($type == 'number') {
            $add = 'step="0.1" min="0"';
        }
        $placeholderHtml = $placeholder ? 'placeholder="'.$placeholder.'"' : '';
        if (! isset( $this->speedy_econt_shipping_options[$name] )) {
            $this->speedy_econt_shipping_options[$name] = $value;
        }
        printf(
            '<input class="regular-text" type="'.$type.'" name="speedy_econt_shipping_option_name['.$name.']" id="'.$name.'" value="%s" '.$placeholderHtml.' '.$add.'>',
            esc_attr( $this->speedy_econt_shipping_options[$name])
        );
    }

    private function checkbox_callback($name, $options_in_group=array(), $default=true) {
        $script = "";
        if ( $options_in_group ) {
            $options_str = '#' . implode(', #', $options_in_group);
            $script = '<script>
                jQuery( document ).ready(function() {
                    if(! jQuery("#' . $name . '").is(":checked")) {
                        jQuery("' . $options_str . '").parent().parent().hide();
                    }
                });
                jQuery("#' . $name . '").click(function() {
                    if(jQuery(this).is(":checked")) {
                        jQuery("' . $options_str . '").parent().parent().show(300);
                    } else {
                        jQuery("' . $options_str . '").parent().parent().hide(200);
                    }
                });
                </script>';
        }
        $default_value = $default ? 1 : 0;
        echo '<input type="checkbox" id="'.$name.'" name="speedy_econt_shipping_option_name['.$name.']" value="1" ' . checked( 1, getStoredOption($name, $default_value), false ) . '/>' .$script;
    }

    public function enable_speedy_0_callback() {
        $this->checkbox_callback('enable_speedy_0', array('speedy_username_0', 'speedy_password_1', 'speedy_free_from_6', 'speedy_shipping_7'), true);
    }

    public function speedy_username_0_callback() {
        $this->generic_callback('speedy_username_0', 'text', __('digit value like: 123456', 'speedy_econt_shipping'));
    }

    public function speedy_password_1_callback() {
        $this->generic_callback('speedy_password_1', 'password', __('digit value like: 123456', 'speedy_econt_shipping'));
    }

    public function speedy_free_from_6_callback() {
        $this->generic_callback('speedy_free_from_6', 'number');
    }

    public function speedy_shipping_7_callback() {
        $this->generic_callback('speedy_shipping_7', 'number');
    }

    public function enable_econt_1_callback() {
        $this->checkbox_callback('enable_econt_1', array('econt_free_from_8', 'econt_shipping_9'), true);
    }

    public function econt_free_from_8_callback() {
        $this->generic_callback('econt_free_from_8', 'number');
    }

    public function econt_shipping_9_callback() {
        $this->generic_callback('econt_shipping_9', 'number');
    }

    public function enable_address_2_callback() {
        $this->checkbox_callback('enable_address_2', array('address_free_from_10', 'address_shipping_11'), true);
    }

    public function address_free_from_10_callback() {
        $this->generic_callback('address_free_from_10', 'number');
    }

    public function address_shipping_11_callback() {
        $this->generic_callback('address_shipping_11', 'number');
    }

    public function address_fields_3_callback() {
        $this->generic_callback('address_fields_3', 'text', '', '#billing_state, #billing_city, #billing_address_1');
    }

    public function additionally_hidden_fields_3_callback() {
        $this->generic_callback('additionally_hidden_fields_03', 'text', '', '#billing_address_2_field, #billing_company_field, #billing_country_field, #billing_postcode_field, #ship-to-different-address, .cart-subtotal, .checkout-wrap, .woocommerce-shipping-totals.shipping');
    }

    public function show_store_messages_6_callback() {
        $this->checkbox_callback('show_store_messages_6');
    }

    public function show_deliv_opts_6_callback() {
        $this->checkbox_callback('show_deliv_opts_6', array(), false);
    }

    public function repopulate_tables_7_callback() {
        $this->checkbox_callback('repopulate_tables_7', array(), false);
    }

    public function calculate_final_price_8_callback() {
        $this->checkbox_callback('calculate_final_price_8', array(), false);
    }
}

if ( is_admin() )
    $speedy_econt_shipping = new SeshSpeedyEcontShippingAdmin();

//do_action( 'updated_option', 'repopulate_tables_7', '0', '1' );
//function chosen_repopulate_tables_7_callback($key, $new_val)
//{
//    if ($key !== 'repopulate_tables_7') {
//        return;
//    }
//    echo '>>> chosen_repopulate_tables_7_callback clicked!';
//    update_option($key, '0');  // return to the original value
//}
//add_action( 'updated_option', 'chosen_repopulate_tables_7_callback', 10, 2 );

function getStoredOption($name, $default="") {
    return get_option( 'speedy_econt_shipping_option_name' )[$name] ?? $default;
}

function isSpeedyEnabled() {
    return getStoredOption('enable_speedy_0', true);
}

function getSpeedyUser() {
    return getStoredOption('speedy_username_0');
}

function getSpeedyPass() {
    return getStoredOption('speedy_password_1');
}

function getSpeedyFreeFrom() {
    return (float) getStoredOption('speedy_free_from_6');
}

function getSpeedyShipping() {
    return (float) getStoredOption('speedy_shipping_7');
}

function isEcontEnabled() {
    return getStoredOption('enable_econt_1', true);
}

function getEcontFreeFrom() {
    return (float) getStoredOption('econt_free_from_8');
}

function getEcontShipping() {
    return (float) getStoredOption('econt_shipping_9');
}

function isAddressEnabled() {
    return getStoredOption('enable_address_2', true);
}

function getAddressFreeFrom() {
    return (float) getStoredOption('address_free_from_10');
}

function getAddressShipping() {
    return (float) getStoredOption('address_shipping_11');
}

function getAddressFields() {
    return array_map('trim', explode(',', getStoredOption('address_fields_03', '#billing_state, #billing_city, #billing_address_1')));
}

function getAdditionallyHiddenFields() {
    return array_map('trim', explode(',', getStoredOption('additionally_hidden_fields_03', '#billing_address_2_field, #billing_company_field, #billing_country_field, #billing_postcode_field, #ship-to-different-address, .cart-subtotal, .checkout-wrap, .woocommerce-shipping-totals.shipping')));
}

function isShowStoreMessages() {
    return getStoredOption('show_store_messages_6', true);
}

function isShowDelivOpts() {
    return getStoredOption('show_deliv_opts_6', false);
}

function isRepopulateTables() {
    return getStoredOption('repopulate_tables_7', false);
}

function isCalculateFinalPrice() {
    return getStoredOption('calculate_final_price_8', false);
}

