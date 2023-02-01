<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

require 'utils.php';
require_once 'SeshSpeedyEcontShippingAdmin.php';

add_action( 'wp_head', function () {
    // works only on 'checkout' page
    if (! (is_page( 'checkout' ) || is_checkout())) {
        return;
    }
    // works only when we have items in the cart
    if (WC()->cart->get_cart_contents_count() == 0) {
        return;
    }
    global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel, $econt_region_sel, $econt_city_sel, $econt_office_sel,
           $speedy_region_field, $speedy_city_field, $speedy_office_field, $econt_region_field, $econt_city_field,
           $econt_office_field, $shipping_to_sel, $address_region_sel, $address_city_sel, $address_address_sel,
           $address_region_field, $address_city_field, $address_address_field, $shipping_to_field;
    ?>
    <script>
        const locs = {
            'econt': {
                'name' : 'econt',
                'outer': {
                    'region': '<?php echo esc_js($econt_region_field); ?>',
                    'city': '<?php echo esc_js($econt_city_field); ?>',
                    'office': '<?php echo esc_js($econt_office_field); ?>'},
                'inner': {
                    'region': '<?php echo esc_js($econt_region_sel); ?>',
                    'city': '<?php echo esc_js($econt_city_sel); ?>',
                    'office': '<?php echo esc_js($econt_office_sel); ?>'}
            },
            'speedy': {
                'name' : 'speedy',
                'outer': {
                    'region': '<?php echo esc_js($speedy_region_field); ?>',
                    'city': '<?php echo esc_js($speedy_city_field); ?>',
                    'office': '<?php echo esc_js($speedy_office_field); ?>'},
                'inner': {
                    'region': '<?php echo esc_js($speedy_region_sel); ?>',
                    'city': '<?php echo esc_js($speedy_city_sel); ?>',
                    'office': '<?php echo esc_js($speedy_office_sel); ?>'}
            },
            'address': {
                'name' : 'address',
                'outer': {
                    'region': '<?php echo esc_js($address_region_field); ?>',
                    'city': '<?php echo esc_js($address_city_field); ?>',
                    'office': '<?php echo esc_js($address_address_field); ?>'},
                'inner': {
                    'region': '<?php echo esc_js($address_region_sel); ?>',
                    'city': '<?php echo esc_js($address_city_sel); ?>',
                    'office': '<?php echo esc_js($address_address_sel); ?>'}
            }
        };

        const alphabet = "abcdefghijklmnopqrstuvwxyzабвгдежзийклмнопрстуфхцчшщъьюя";
        const delivOptions = <?php echo json_encode(seshDelivOptions()); ?>;
        const defaultShippingMethod = '<?php echo seshDefaultDelivOpt(); ?>';
        const currencySymbol = '<?php echo html_entity_decode(get_woocommerce_currency_symbol()); ?>';
        const shopUrl = '<?php echo get_permalink( wc_get_page_id( 'shop' ) ); ?>';
        const enabledOptions = <?php echo json_encode(getShippingOptionsOrder()) ?>;
        let enabledOptionsNoAddress = [];
        let idx = 0;
        for (let i = 0; i < enabledOptions.length; i++) {
            const val = enabledOptions[i];
            if (val === '<?php global $address_label; echo $address_label; ?>') {
                continue;
            }
            enabledOptionsNoAddress[idx] = val;
            idx++;
        }
        let originalOrderPrice = orderPrice().toFixed(2);
        let pricesCopy = {};
        let isFree = false;
        let isFocused = false;
        let delivOptionChosen;

        function orderPrice() {
            return parseFloat(jQuery(".cart-contents .woocommerce-Price-amount.amount").last().text().replace(",", ".")
                || jQuery(".order-total .woocommerce-Price-amount.amount").last().text().replace(",", "."));
        }

        function doShippingPricesCopy() {
            // do the copy of prices
            Object.values(delivOptions).forEach(function(value) {
                pricesCopy[value.id] = value.shipping;
            });
        }

        function showTillFreeDeliveryMsg() {
            if (<?php echo isShowStoreMessages() ? 'false' : 'true'; ?> || Math.abs(originalOrderPrice) < 1e-10) {
                return;
            }
            const leftTillFree = parseFloat(delivOptionChosen.free_from) - parseFloat(originalOrderPrice);
            // don't do anything if unable to calculate the amount left till free shipping
            if (isNaN(leftTillFree)) {
                return;
            }
            const msgContainer = jQuery('div#deliv_msg');
            if (! msgContainer.length ) {
                const p = jQuery('<div role="alert">').appendTo(jQuery(".woocommerce-notices-wrapper").first());
                p.append('<div id="deliv_msg" class="message-inner" style="display: block;">');
            }
            const labelBold = '<b style="font-weight:900;">'+delivOptionChosen.label+'</b>';
            const msgDiv = msgContainer.first();
            msgDiv.removeClass();
            msgDiv.hide();
            let msg;
            isFree = leftTillFree <= 0;
            if (isFree) {
                msgDiv.addClass("woocommerce-message");
                msg = '<?php _e('Congrats, you won free delivery using', 'speedy_econt_shipping'); ?> '+labelBold+'!';
            } else {
                msgDiv.addClass("woocommerce-error");
                msg = '<?php _e('Still left', 'speedy_econt_shipping'); ?> <span class="woocommerce-Price-amount amount">'+leftTillFree.toFixed(2)+'&nbsp;<span class="woocommerce-Price-currencySymbol">'+currencySymbol+'</span></span> <?php _e('to get a free shipping to', 'speedy_econt_shipping')?> '+labelBold+'! <a class="button" href="'+shopUrl+'"><?php _e('To shop', 'speedy_econt_shipping') ?></a>';
            }
            msgDiv.html(msg);
            msgDiv.show();
        }

        function populateDeliveryOption(key){
            const chosenOption = delivOptions[key];
            const delivPrice = parseFloat(originalOrderPrice) >= parseFloat(chosenOption.free_from) ? 0 : parseFloat(chosenOption.shipping);
            // convert to id here since we do care about real DOM elements here
            const delivPriceNormal = delivPrice.toFixed(2);
            pricesCopy[delivOptions[key].id] = delivPriceNormal;
            const priceAdd = delivPrice === 0 ? "<?php _e('for free', 'speedy_econt_shipping') ?>" : '+'+delivPriceNormal+' '+currencySymbol;
            const delivText = ' '+chosenOption.label+' ('+priceAdd+')';
            jQuery(".woocommerce-input-wrapper > label[for='"+chosenOption.id+"']").text(delivText);
        }

        function populateDeliveryOptions() {
            doShippingPricesCopy();
            enabledOptions.forEach(function(key) {
                populateDeliveryOption(key);
            });
        }

        function updateChosenShippingOpt() {
            const opt = jQuery('<?php echo $shipping_to_sel; ?>:checked');
            const val = opt.val();
            if (val === undefined) {
                delivOptionChosen = delivOptions[defaultShippingMethod];
                return;
            }
            let foundShippingMethod = null;
            // there are some themes which do the rendering in strange way, so let's guess
            if (delivOptions[val] !== undefined) {
                foundShippingMethod = delivOptions[val].name;
            } else {
                let guessed = false;
                for (const [_, delivOptionData] of Object.entries(delivOptions)) {
                    if (delivOptionData.label === val) {
                        foundShippingMethod = delivOptionData.name;
                        guessed = true;
                        break;
                    }
                }
                if (! guessed) {
                    console.log('Not guessed the way shipping methods are represented in the theme. Please contact developers');
                    return;
                }
            }
            delivOptionChosen = delivOptions[foundShippingMethod ?? defaultShippingMethod];
        }

        function changeFinalPriceElem() {
            updateChosenShippingOpt();
            return changeFinalPrice();
        }

        function changeFinalPrice() {
            const deliveryPrice = pricesCopy[delivOptionChosen.id];
            let elemText;
            <?php if (isCalculateFinalPrice()) { ?>
                jQuery(".cart-subtotal th").last().text('<?php _e('delivery', 'speedy_econt_shipping') ?>');
                const delivPrice = deliveryPrice + ' ' + currencySymbol;
                jQuery("<?php echo getDeliveryPriceSelector(); ?>").last().text(delivPrice);
                elemText = (parseFloat(originalOrderPrice) + parseFloat(deliveryPrice)).toFixed(2) + ' ' + currencySymbol;
            <?php } else { ?>
                const suffix = deliveryPrice > 0 ? " + <?php _e('delivery', 'speedy_econt_shipping') ?>" : "";
                elemText = originalOrderPrice + ' ' + currencySymbol + suffix;
            <?php } ?>
            jQuery(".order-total  .woocommerce-Price-amount.amount").last().text(elemText);
        }

        function onChangePhoneNumber(){
            if (jQuery("#billing_phone").val() !== '') {
                jQuery("<?php echo esc_js($shipping_to_field); ?>").show("slow", function(){});
            }
        }

        function populateFields(key, selectedRegion=null, selectedCity=null) {
            if (! enabledOptions.includes(key)) {
                return;
            }
            const citySel = locs[key].inner.city;
            const officeSel = locs[key].inner.office;
            // since we store only json variable name, not its actual contents
            const data = eval(delivOptions[key].data);
            const cityDom = jQuery(citySel);
            cityDom.empty().trigger('change.select2');
            const officeDom = jQuery(officeSel);
            officeDom.empty().trigger('change.select2');

            if (key === '<?php global $address_label; echo $address_label; ?>') {
                return;
            }
            Object.entries(data).forEach(([regionName, cities]) => {
                // regions are not filled in here since they are pre-populated when fields are created
                cities.forEach(city => {
                    if (!selectedRegion || selectedRegion !== regionName) {
                        return;
                    }
                    cityDom.append(jQuery('<option id="'+city.id+'">'+city.name+'</option>'));
                    city.offices.forEach(office => {
                        if (selectedCity && selectedCity !== city.name) {
                            return;
                        }
                        let officeName = office.name+' ('+office.address+')';
                        // output office # for the Speedy offices
                        if (key === locs.speedy.name) {
                            officeName = '№' + office.id + ', ' + officeName;
                        }
                        officeDom.append(jQuery('<option id="' + office.id + '">'+officeName+'</option>'));
                    });
                });
            });
        }

        function getFreeLabel() {
            return isFree ? " - <?php _e('for free', 'speedy_econt_shipping') ?>" : "";
        }

        function officeValueChange(key, value) {
            if (value) {
                value = delivOptions[key].label + getFreeLabel() + ": " + value;
            }
            jQuery(locs.address.inner.office).val(value);
        }

        function processPopulatedData(key) {
            const regionDom = jQuery(locs[key].inner.region);
            const cityDomOuter = jQuery(locs[key].outer.city);
            const cityDom = jQuery(locs[key].inner.city);
            const officeDomOuter = jQuery(locs[key].outer.office);
            const officeDom = jQuery(locs[key].inner.office);
            regionDom.change(function() {
                const region = regionDom.find('option:selected').text();
                // special way of setting the region drop-down field since the keys are different from text displayed
                jQuery(locs.address.inner.region+" option").filter(function() {
                    return jQuery(this).text() === region;
                }).prop('selected', true).trigger('change.select2');
                // set city to empty value
                jQuery(locs.address.inner.city).val("");
                populateFields(key, region);
                regionDom.val(region).trigger('change.select2');
                cityDom.val("").trigger('change.select2');

                cityDomOuter.show();
                officeDomOuter.hide();
            });
            cityDom.change(function() {
                const region = regionDom.find('option:selected').text();
                const city = cityDom.find('option:selected').text();
                jQuery(locs.address.inner.city).val(city);
                populateFields(key, region, city);
                regionDom.val(region).trigger('change.select2');
                cityDom.val(city).trigger('change.select2');

                // choose the first office if only 1 is available in the list
                let oneOffice = officeDom.find('option').length === 1 ? jQuery(locs[key].inner.office+' option:eq(0)').val() : "";
                officeDom.val(oneOffice).trigger('change.select2');
                // auto-populate address field with single office available
                officeValueChange(key, oneOffice);

                officeDomOuter.show();
            });
            officeDom.change(function() {
                let office = officeDom.find('option:selected').text();
                officeValueChange(key, office);
            });
        }

        function onDeliveryOptionChange() {
            showTillFreeDeliveryMsg();
            const option = jQuery('<?php echo $shipping_to_sel ?>:checked').val();
            // hide all the selectors till we know what is chosen
            Object.keys(locs).forEach(function(key) {
                jQuery([locs[key].outer.region, locs[key].outer.city, locs[[key]].outer.office].join(',')).hide();
            });
            // set really saved address (office) to empty value
            jQuery(locs.address.inner.office).val("");
            if ([locs.speedy.name, locs.econt.name].includes(option)) {
                jQuery(locs[option].outer.region).show("slow", function(){});
                jQuery(locs[option].inner.city).val("").trigger('change.select2');
                jQuery(locs[option].outer.city).show("slow", function(){});
            } else if (option === locs.address.name) {
                jQuery([locs.address.outer.region, locs.address.outer.city, locs.address.outer.office].join(',')).show("slow", function(){});
            }
        }

        function alphabetically(a, b) {
            a = a.toLowerCase()
            b = b.toLowerCase();
            // Find the first position where the strings do not match
            let position = 0;
            while(a[position] === b[position]) {
                // If both are the same don't swap
                if(!a[position] && !b[position]) return 0;
                // Otherwise - the shorter one goes first
                if(!a[position]) return 1;
                if(!b[position]) return -1;
                position++;
            }
            // Then sort by the characters position
            return alphabet.indexOf(a[position]) - alphabet.indexOf(b[position]);
        }

        jQuery( document ).ajaxComplete(function() {
            // do the focusing only once
            if (! isFocused) {
                isFocused = true;
                jQuery("#billing_first_name").focus();
            }
            showTillFreeDeliveryMsg();
            populateDeliveryOptions();
            changeFinalPriceElem();
        });

        jQuery( document ).ready(function() {
            originalOrderPrice = orderPrice().toFixed(2);
            // doing this since msg div is not populated on 1 run - we need to control this
            let tillFreeMsgShown = setInterval(function () {
                if (jQuery('div#deliv_msg').first().text()) {
                    clearInterval(tillFreeMsgShown);
                }
                showTillFreeDeliveryMsg();
            }, 500); // run every 500ms
            populateDeliveryOptions();
            changeFinalPriceElem();

            // populate the offices data once DOM is loaded - it is happening later that onReady() is fired
            let regionExists = setInterval(function() {
                if (jQuery(locs.speedy.inner.region).length) {
                    clearInterval(regionExists);
                    jQuery([locs.address.outer.region, locs.address.outer.city, locs.address.outer.office].join(',')).attr("style", "");
                    enabledOptions.forEach(function(key) {
                        populateFields(key);
                    });
                }
            }, 100); // check every 100ms

            let radioButtons = jQuery('<?php echo $shipping_to_sel; ?>');
            // populate the offices data once DOM is loaded - it is happening later that onReady() is fired
            let phonePopulated = setInterval(function() {
                if (jQuery("#billing_phone").val().length > 0 && !radioButtons.is(":visible")) {
                    clearInterval(phonePopulated);
                    onChangePhoneNumber();
                }
            }, 100); // check every 100ms

            // for auto-populated fields - once they appear show values according to the option chosen
            let shippingChosen = setInterval(function() {
                const checkedOpt = jQuery('<?php echo $shipping_to_sel; ?>:checked');
                if (checkedOpt.length === 0 &&
                    jQuery([locs.address.outer.region, locs.address.outer.city, locs.address.outer.office].join(',')).is(":visible")) {
                    jQuery('#'+delivOptions.speedy.id).prop("checked", true);
                }
                if (checkedOpt.length) {
                    clearInterval(shippingChosen);
                    changeFinalPriceElem();
                    onDeliveryOptionChange();
                }
            }, 500); // check every 500ms

            radioButtons.change(function () {
                if (! jQuery(this).val()) {
                    return;
                }
                changeFinalPriceElem();
            });

            showTillFreeDeliveryMsg();
            jQuery('<?php echo $shipping_to_sel; ?>').change(onDeliveryOptionChange);
            enabledOptionsNoAddress.forEach(function(key) {
                processPopulatedData(key);
                jQuery(locs[key].inner.region).val("").trigger('change.select2');
            });
            [locs.address.inner.region, locs.econt.inner.region, locs.econt.inner.city, locs.econt.inner.office,
                locs.speedy.inner.region, locs.speedy.inner.city, locs.speedy.inner.office].forEach(function(key) {
                jQuery(key).select2({
                    sortResults: data => data.sort(alphabetically)
                });
            });
        });
    </script>
<?php } );
