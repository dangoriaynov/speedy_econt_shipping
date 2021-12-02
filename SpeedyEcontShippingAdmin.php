<?php

class SpeedyEcontShippingAdmin {
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
            <p><?php _e('Specify here the information needed for the plugin to work', 'speedy_econt_shipping') ?></p>
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
    }

    public function speedy_econt_shipping_sanitize($input) {
        $sanitary_values = array();
        if ( isset( $input['speedy_username_0'] ) ) {
            $sanitary_values['speedy_username_0'] = sanitize_text_field( $input['speedy_username_0'] );
        }

        if ( isset( $input['speedy_password_1'] ) ) {
            $sanitary_values['speedy_password_1'] = sanitize_text_field( $input['speedy_password_1'] );
        }

        if ( isset( $input['econt_username_2'] ) ) {
            $sanitary_values['econt_username_2'] = sanitize_text_field( $input['econt_username_2'] );
        }

        if ( isset( $input['econt_password_3'] ) ) {
            $sanitary_values['econt_password_3'] = sanitize_text_field( $input['econt_password_3'] );
        }

        if ( isset( $input['currency_symbol_4'] ) ) {
            $sanitary_values['currency_symbol_4'] = sanitize_text_field( $input['currency_symbol_4'] );
        }

        if ( isset( $input['shop_page_5'] ) ) {
            $sanitary_values['shop_page_5'] = sanitize_text_field( $input['shop_page_5'] );
        }

        return $sanitary_values;
    }

    public function speedy_econt_shipping_section_info() {

    }

    public function speedy_username_0_callback() {
        printf(
            '<input class="regular-text" type="text" name="speedy_econt_shipping_option_name[speedy_username_0]" id="speedy_username_0" value="%s">',
            isset( $this->speedy_econt_shipping_options['speedy_username_0'] ) ? esc_attr( $this->speedy_econt_shipping_options['speedy_username_0']) : ''
        );
    }

    public function speedy_password_1_callback() {
        printf(
            '<input class="regular-text" type="password" name="speedy_econt_shipping_option_name[speedy_password_1]" id="speedy_password_1" value="%s">',
            isset( $this->speedy_econt_shipping_options['speedy_password_1'] ) ? esc_attr( $this->speedy_econt_shipping_options['speedy_password_1']) : ''
        );
    }

    public function econt_username_2_callback() {
        printf(
            '<input class="regular-text" type="text" name="speedy_econt_shipping_option_name[econt_username_2]" id="econt_username_2" value="%s">',
            isset( $this->speedy_econt_shipping_options['econt_username_2'] ) ? esc_attr( $this->speedy_econt_shipping_options['econt_username_2']) : ''
        );
    }

    public function econt_password_3_callback() {
        printf(
            '<input class="regular-text" type="password" name="speedy_econt_shipping_option_name[econt_password_3]" id="econt_password_3" value="%s">',
            isset( $this->speedy_econt_shipping_options['econt_password_3'] ) ? esc_attr( $this->speedy_econt_shipping_options['econt_password_3']) : ''
        );
    }

    public function currency_symbol_4_callback() {
        printf(
            '<input class="regular-text" type="text" name="speedy_econt_shipping_option_name[currency_symbol_4]" id="currency_symbol_4" value="%s">',
            isset( $this->speedy_econt_shipping_options['currency_symbol_4'] ) ? esc_attr( $this->speedy_econt_shipping_options['currency_symbol_4']) : ''
        );
    }

    public function shop_page_5_callback() {
        printf(
            '<input class="regular-text" type="text" name="speedy_econt_shipping_option_name[shop_page_5]" id="shop_page_5" value="%s">',
            isset( $this->speedy_econt_shipping_options['shop_page_5'] ) ? esc_attr( $this->speedy_econt_shipping_options['shop_page_5']) : ''
        );
    }
}

if ( is_admin() )
    $speedy_econt_shipping = new SpeedyEcontShippingAdmin();

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