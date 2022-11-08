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
            <p><?php _e('Specify here the information needed for the plugin to work.<br><b>Please request API access from Speedy and Econt couriers and specify the values given by them.</b>', 'speedy_econt_shipping') ?></p>
            <p><?php _e('<b>In case of errors, please check output of the following commands on your hosting:</b>', 'speedy_econt_shipping') ?></p>
            <p><i>curl -X POST -H "Content-Type: application/json" --data '{"countryCode": "BGR"} ' https://ee.econt.com/services/Nomenclatures/NomenclaturesService.getCities.json</i><br><i>curl -X POST -H "Content-Type: application/json" --data '{"userName": "&lt;speedy username&gt;","password": "&lt;speedy password&gt;","language": "BG","countryId": 100}' https://api.speedy.bg/v1/location/office/</i>
            </p>
            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
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
            'speedy_username_0', // id
            __('Speedy Username', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_username_0_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'speedy_password_1', // id
            __('Speedy Password', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_password_1_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'econt_username_2', // id
            __('Econt Username', 'speedy_econt_shipping'), // title
            array( $this, 'econt_username_2_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'econt_password_3', // id
            __('Econt Password', 'speedy_econt_shipping'), // title
            array( $this, 'econt_password_3_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'currency_symbol_4', // id
            __('Currency Symbol', 'speedy_econt_shipping'), // title
            array( $this, 'currency_symbol_4_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'shop_page_5', // id
            __('Shop page', 'speedy_econt_shipping'), // title
            array( $this, 'shop_page_5_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'speedy_free_from_6', // id
            __('Speedy free delivery from', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_free_from_6_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'speedy_shipping_7', // id
            __('Speedy shipping fee', 'speedy_econt_shipping'), // title
            array( $this, 'speedy_shipping_7_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'econt_free_from_8', // id
            __('Econt free delivery from', 'speedy_econt_shipping'), // title
            array( $this, 'econt_free_from_8_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'econt_shipping_9', // id
            __('Econt shipping fee', 'speedy_econt_shipping'), // title
            array( $this, 'econt_shipping_9_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'address_free_from_10', // id
            __('To address free delivery from', 'speedy_econt_shipping'), // title
            array( $this, 'address_free_from_10_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );

        add_settings_field(
            'address_shipping_11', // id
            __('To address shipping fee', 'speedy_econt_shipping'), // title
            array( $this, 'address_shipping_11_callback' ), // callback
            'speedy-econt-shipping-admin', // page
            'speedy_econt_shipping_setting_section' // section
        );
    }

    public function speedy_econt_shipping_sanitize($input) {
        $sanitary_values = array();
        $keys = array('speedy_username_0', 'speedy_password_1', 'econt_username_2', 'econt_password_3',
            'currency_symbol_4', 'shop_page_5', 'speedy_free_from_6', 'speedy_shipping_7', 'econt_free_from_8',
            'econt_shipping_9', 'address_free_from_10', 'address_shipping_11');
        foreach($keys as &$value) {
            if ( isset( $input[$value] ) ) {
                $sanitary_values[$value] = sanitize_text_field( $input[$value] );
            }
        }
        return $sanitary_values;
    }

    public function speedy_econt_shipping_section_info() {}

    private function generic_callback($value, $type='text', $placeholder='') {
        $add = "";
        if ($type == 'number') {
            $add = 'step="0.1" min="0"';
        }
        $placeholderHtml = $placeholder ? 'placeholder="'.$placeholder.'"' : '';
        printf(
            '<input class="regular-text" type="'.$type.'" name="speedy_econt_shipping_option_name['.$value.']" id="'.$value.'" value="%s" '.$placeholderHtml.' '.$add.'>',
            isset( $this->speedy_econt_shipping_options[$value] ) ? esc_attr( $this->speedy_econt_shipping_options[$value]) : ''
        );
    }

    public function speedy_username_0_callback() {
        $this->generic_callback('speedy_username_0', 'text', __('digit value like: 123456', 'speedy_econt_shipping'));
    }

    public function speedy_password_1_callback() {
        $this->generic_callback('speedy_password_1', 'password', __('digit value like: 123456', 'speedy_econt_shipping'));
    }

    public function econt_username_2_callback() {
        $this->generic_callback('econt_username_2', 'text', __('email address like: abc@mail.com', 'speedy_econt_shipping'));
    }

    public function econt_password_3_callback() {
        $this->generic_callback('econt_password_3', 'password');
    }

    public function currency_symbol_4_callback() {
        $this->generic_callback('currency_symbol_4');
    }

    public function shop_page_5_callback() {
        $this->generic_callback('shop_page_5');
    }

    public function speedy_free_from_6_callback() {
        $this->generic_callback('speedy_free_from_6', 'number');
    }

    public function speedy_shipping_7_callback() {
        $this->generic_callback('speedy_shipping_7', 'number');
    }

    public function econt_free_from_8_callback() {
        $this->generic_callback('econt_free_from_8', 'number');
    }

    public function econt_shipping_9_callback() {
        $this->generic_callback('econt_shipping_9', 'number');
    }

    public function address_free_from_10_callback() {
        $this->generic_callback('address_free_from_10', 'number');
    }

    public function address_shipping_11_callback() {
        $this->generic_callback('address_shipping_11', 'number');
    }
}

if ( is_admin() )
    $speedy_econt_shipping = new SeshSpeedyEcontShippingAdmin();

function getStoredOption($name) {
    $speedy_econt_shipping_options = get_option( 'speedy_econt_shipping_option_name' );
    return $speedy_econt_shipping_options[$name];
}

function getSpeedyUser() {
    return getStoredOption('speedy_username_0');
}

function getSpeedyPass() {
    return getStoredOption('speedy_password_1');
}

function getEcontUser() {
    return getStoredOption('econt_username_2');
}

function getEcontPass() {
    return getStoredOption('econt_password_3');
}

function getCurrencySymbol() {
    return getStoredOption('currency_symbol_4');
}

function getShopUrl() {
    return getStoredOption('shop_page_5');
}

function getSpeedyFreeFrom() {
    return (float) getStoredOption('speedy_free_from_6');
}

function getSpeedyShipping() {
    return (float) getStoredOption('speedy_shipping_7');
}

function getEcontFreeFrom() {
    return (float) getStoredOption('econt_free_from_8');
}

function getEcontShipping() {
    return (float) getStoredOption('econt_shipping_9');
}

function getAddressFreeFrom() {
    return (float) getStoredOption('address_free_from_10');
}

function getAddressShipping() {
    return (float) getStoredOption('address_shipping_11');
}