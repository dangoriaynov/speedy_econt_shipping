<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly....
}

require 'utils.php';
require_once 'SeshSpeedyEcontShippingAdmin.php';

add_action( 'wp_head', function () {
    if (is_page( 'cart' ) || is_cart() || is_page( 'checkout' ) || is_checkout()) {
        if (isLoadCustomJQuery()) { ?>
        <script>
            let script = document.createElement('script');
            script.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
            script.onload = function() {
                let newJQuery = jQuery.noConflict(true);
                newJQuery(document).ajaxComplete(function() {
                    if (typeof priceManipulationsTimer === 'function') {priceManipulationsTimer();}
                    // do the focusing only once
                    if (typeof setFocusedTimer === 'function') {setFocusedTimer();}
                });
            };
            document.head.appendChild(script);
            (function($) {$.ajaxComplete = function(){};})(jQuery);
        </script>
        <?php }
    }
    # works on 'cart' page only
    if (is_page( 'cart' ) || is_cart()) { ?>
        <script>
            jQuery( document ).ready(function() {
                jQuery(".woocommerce-shipping-totals.shipping").html('<?php echo getDeliveryDetailsCartHtml(); ?>');
            });
        </script>
    <?php } // works only on 'checkout' page
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
        let originalOrderPrice = 0;
        let pricesCopy = {};
        let delivOptionChosen;
        let isTillFreeMsgUpdated = false;
        let priceVarManipulatedTimes = 0;

        function isCartPage() {
            return jQuery(".cart-contents .woocommerce-Price-amount.amount").length > 0;
        }

        function isOrderPage() {
            return jQuery(".order-total .woocommerce-Price-amount.amount").length > 0;
        }

        function getPriceClass() {
            if (isCartPage()) {
                return ".cart-contents";
            }
            if (isOrderPage()) {
                return ".order-total";
            }
            console.log("Unable to get final order price from the page");
            return NaN;
        }

        function orderPrice() {
            return new Promise(function(resolve) {
                let attempts = 0;
                const maxAttempts = 20; // 10 seconds

                let interval = setInterval(function() {
                    attempts++;
                    let finalPrice = 0;

                    let $priceElement = jQuery('.order-total .amount').not('.secondary-currency .amount').first();

                    if ($priceElement.length === 0) {
                         $priceElement = jQuery('.cart-total .amount').not('.secondary-currency .amount').first();
                    }
                    
                    if ($priceElement.length > 0 && $priceElement.closest("form[name='checkout']").length === 0) {
                         // Optional checks
                    }

                    if ($priceElement.length > 0) {
                        let priceText = $priceElement.text(); 
                        priceText = priceText.replace(",", ".").replace(/[^0-9.]/g, "");
                        let price = parseFloat(priceText);
                        
                        if (!isNaN(price) && price > 0) {
                            finalPrice = price;
                        }
                    }

                    if (finalPrice > 0) {
                        clearInterval(interval);
                        resolve(finalPrice);
                    } 
                    else if (attempts >= maxAttempts) {
                        clearInterval(interval);
                        resolve(0);
                    }
                }, 500);
            });
        }

        function setCustomShippingPrice(customPrice) {
            let origPriceElem = jQuery(getPriceClass() + " .woocommerce-Price-amount.amount").not('.secondary-currency *').last();
            origPriceElem.hide();
            let customPriceElem = jQuery("#custom_price");
            if (customPriceElem.length === 0) {
                origPriceElem.before('<span id="custom_price" class="woocommerce-Price-amount amount">'+customPrice+'</span>');
            } else {
                customPriceElem.text(customPrice);
            }
        }

        function doShippingPricesCopy() {
            // do the copy of prices
            Object.values(delivOptions).forEach(function(value) {
                pricesCopy[value.id] = value.shipping;
            });
        }

        async function showTillFreeDeliveryMsg() {
            const currentPrice = await orderPrice();
            if (! '<?php echo showStoreMessages(); ?>'.includes(delivOptionChosen.name) || Math.abs(currentPrice) < 1e-10) {
                jQuery("#deliv_msg").remove();
                isTillFreeMsgUpdated = true;
                return;
            }
            if (isNaN(currentPrice)) {
                jQuery("#deliv_msg").remove();
                isTillFreeMsgUpdated = true;
                return;
            }
            const msgContainer = jQuery('div#deliv_msg');
            if (! msgContainer.length ) {
                const p = jQuery('<div role="alert">').appendTo(jQuery(".woocommerce-notices-wrapper").first());
                p.append('<div id="deliv_msg" class="message-inner" style="display: block;">');
            }

            const labelBold = '<b style="font-weight:900;">'+delivOptionChosen.label+'</b>';
            const msgDiv = jQuery('div#deliv_msg').first();
            
            msgDiv.removeClass();
            msgDiv.hide();
            msgDiv.css("background-color", "");

            let msg;

            // Notice we await isFreeDelivery here
            if (await isFreeDelivery(delivOptionChosen)) {
                msgDiv.addClass("woocommerce-message");
                msg = '<?php _e('Congrats, you won free delivery using', 'speedy_econt_shipping'); ?> '+labelBold+'!';
            } else if (parseFloat(delivOptionChosen.free_from) !== -1) {
                msgDiv.addClass("woocommerce-message");
                msgDiv.css("background-color","#e2401c");
                
                const leftTillFree = parseFloat(delivOptionChosen.free_from) - currentPrice;
                
                msg = '<?php _e('Still left', 'speedy_econt_shipping'); ?> <span class="woocommerce-Price-amount amount">'+leftTillFree.toFixed(2)+'&nbsp;<span class="woocommerce-Price-currencySymbol">'+currencySymbol+'</span></span> <?php _e('to get a free shipping to', 'speedy_econt_shipping')?> '+labelBold+'! <a class="button" href="'+shopUrl+'"><?php _e('To shop', 'speedy_econt_shipping') ?></a>';
            } else {
                msgDiv.addClass("woocommerce-message");
                msgDiv.css("background-color","darkgray");
                msg = '<?php _e('Sorry, these is no free shipping available for the option chosen: ', 'speedy_econt_shipping'); ?> '+labelBold+'!';
            }

            msgDiv.html(msg);
            msgDiv.show();
            isTillFreeMsgUpdated = msgDiv.length > 0;
        }

        // 1. ADD ASYNC
        async function isFreeDelivery(option) {
            let finalPrice = await orderPrice();
            return parseFloat(option.free_from) !== -1 && finalPrice >= parseFloat(option.free_from);
        }

        // 2. ADD ASYNC
        async function calculateDeliveryPrice(option) {
            return (await isFreeDelivery(option)) ? 0 : parseFloat(option.shipping);
        }

        // 3. ADD ASYNC
        async function populateDeliveryOption(key){
            const chosenOption = delivOptions[key];
            const delivPrice = await calculateDeliveryPrice(chosenOption);
            // convert to id here since we do care about real DOM elements here
            const delivPriceNormal = delivPrice.toFixed(2);
            pricesCopy[delivOptions[key].id] = delivPriceNormal;
            // Get Suffix Async
            const suffix = await getFreeShippingLabelSuffix(chosenOption);
            const priceAdd = delivPrice === 0 ? suffix : '+'+delivPriceNormal+' '+currencySymbol;
            const delivText = priceAdd === "" ? ' '+chosenOption.label : ' '+chosenOption.label+' ('+priceAdd+')';
            jQuery(".woocommerce-input-wrapper > label[for='"+chosenOption.id+"']").text(delivText);
        }

        // 4. ADD ASYNC
        async function populateDeliveryOptions() {
            doShippingPricesCopy();
            // Use for..of instead of forEach to respect await
            for (const key of enabledOptions) {
                await populateDeliveryOption(key);
            }
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

        // 5. ADD ASYNC
        async function changeFinalPriceElem() {
            updateChosenShippingOpt();
            return await changeFinalPrice();
        }

        // 6. ADD ASYNC
        async function changeFinalPrice() {
            const deliveryPrice = pricesCopy[delivOptionChosen.id];
            let price = await orderPrice();
            if (isNaN(price) || isNaN(deliveryPrice)) {
                return;
            }
            let customPrice;
            const deliveryMsg = '<?php _e('delivery', 'speedy_econt_shipping') ?>';
            <?php if (isCalculateFinalPrice()) { ?>
                jQuery(".cart-subtotal th").last().text(deliveryMsg);
                const delivPrice = deliveryPrice + ' ' + currencySymbol;
                jQuery("<?php echo getDeliveryPriceSelector(); ?>").last().text(delivPrice);
                customPrice = (price + parseFloat(deliveryPrice)).toFixed(2) + ' ' + currencySymbol;
            <?php } else { ?>
                // const suffix = deliveryPrice > 0 ? " + " + deliveryMsg : "";
                const suffix = "";
                customPrice = price.toFixed(2) + ' ' + currencySymbol + suffix;
            <?php } ?>
            setTimeout(function(){
                setCustomShippingPrice(customPrice);
                runTillFreeMsgTimer();
            }, 500);
        }

        function onChangePhoneNumber(){
            if (jQuery("#billing_phone").val() !== '') {
                jQuery("<?php echo esc_js($shipping_to_field); ?>").show("slow", function(){});
            }
        }

        function sortWithFirstValue(arr, firstValue) {
            const firstIndex = arr.indexOf(firstValue);
            if (firstIndex !== -1) {
                arr.splice(firstIndex, 1);
            }
            arr.sort();
            if (firstIndex !== -1) {
                arr.unshift(firstValue);
            }
            return arr;
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
            const officeDom = jQuery(officeSel);
            officeDom.empty().trigger('change.select2');

            if (key === '<?php global $address_label; echo $address_label; ?>') {
                cityDom.empty().trigger('change.select2');
                return;
            }
            let citiesIds = [];
            let officesIds = [];

            Object.entries(data).forEach(([regionName, cities]) => {
                // regions are not filled in here since they are pre-populated when fields are created
                cities.forEach(city => {
                    if (!selectedRegion || selectedRegion !== regionName) {
                        return;
                    }
                    citiesIds.push({ id: city.id, value: city.name });
                    // cityDom.append(jQuery('<option id="'+city.id+'">'+city.name+'</option>'));
                    city.offices.forEach(office => {
                        if (selectedCity && selectedCity !== city.name) {
                            return;
                        }
                        let officeName = office.name+' ('+office.address+')';
                        // output office # for the Speedy offices
                        if (key === locs.speedy.name) {
                            officeName = '№' + office.id + ', ' + officeName;
                        }
                        officesIds.push({ id: office.id, value: officeName });
                        // officeDom.append(jQuery('<option id="' + office.id + '">'+officeName+'</option>'));
                    });
                });
            });
            if (selectedCity === null || cityDom.select2('data').length === 0) {
                citiesIds.sort(function(a, b) {
                    return a.value.localeCompare(b.value);
                });
                const matchingCityIdx = citiesIds.findIndex(function(option) {
                    return option.value === selectedRegion;
                });
                if (matchingCityIdx !== -1) {
                    const matchingCIty = citiesIds.splice(matchingCityIdx, 1)[0];
                    citiesIds.unshift(matchingCIty);
                }
                cityDom.empty().trigger('change.select2');
                jQuery.each(citiesIds, function(index, option) {
                    cityDom.append(jQuery('<option>', {
                        id: option.id,
                        text: option.value
                    }));
                });
            }
            officesIds.sort(function(a, b) {
                return a.value.localeCompare(b.value);
            });
            jQuery.each(officesIds, function(index, option) {
                officeDom.append(jQuery('<option>', {
                    id: option.id,
                    text: option.value
                }));
            });
        }

        // 7. ADD ASYNC
        async function getFreeShippingLabelSuffix(delivOpt) {
            <?php $freeShippingSuffix = getFreeShippingLabelSuffix(); ?>
            // Await isFreeDelivery
            const isFree = await isFreeDelivery(delivOpt);
            return isFree && "<?php echo $freeShippingSuffix; ?>" !== "" ? " - <?php echo $freeShippingSuffix; ?>" : "";
        }

        // 8. ADD ASYNC
        async function officeValueChange(key, value) {
            if (value) {
                const delivOpt = delivOptions[key];
                // Await getFreeShippingLabelSuffix
                const suffix = await getFreeShippingLabelSuffix(delivOpt);
                value = delivOpt.label + suffix + ": " + value;
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
            // Update to async callback for officeValueChange
            cityDom.change(async function() {
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
                await officeValueChange(key, oneOffice);

                officeDomOuter.show();
            });
            // Update to async callback for officeValueChange
            officeDom.change(async function() {
                let office = officeDom.find('option:selected').text();
                await officeValueChange(key, office);
            });
        }

        function runTillFreeMsgTimer() {
            // doing this since msg div is not populated on 1 run - we need to control this
            isTillFreeMsgUpdated = false;
            let tillFreeMsgShown = setInterval(function () {
                if (isTillFreeMsgUpdated) {
                    clearInterval(tillFreeMsgShown);
                    return;
                }
                showTillFreeDeliveryMsg();
            }, 500); // run every 500ms
        }

        function onDeliveryOptionChange() {
            runTillFreeMsgTimer();
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

        function setFocusedTimer() {
            let fieldFocused = setInterval(function () {
                const first_name = jQuery("#billing_first_name");
                if (first_name.is(":focus") || first_name.val()) {
                    clearInterval(fieldFocused);
                    return;
                }
                first_name.focus();
            }, 500); // run every 500ms
        }

function priceManipulationsTimer() {
            priceVarManipulatedTimes = 0;
            let priceManipulated = setInterval(async function () {
                const currentPrice = await orderPrice();
                
                // CHECK 1: If price is exactly the same as before
                if (originalOrderPrice === currentPrice) {
                    priceVarManipulatedTimes++;
                    
                    // Stop checking after a while if stable
                    if (priceVarManipulatedTimes > 6) { 
                        clearInterval(priceManipulated);
                    }
                    
                    // CRITICAL FIX: Return immediately. 
                    // Do not execute the code below that updates the HTML.
                    // This stops the flickering.
                    return;
                }

                // CHECK 2: Price has changed (or it is the first run)
                originalOrderPrice = currentPrice;
                priceVarManipulatedTimes = 0; // Reset counter because price changed
                
                // Only update the visual elements when the price actually changes
                await changeFinalPriceElem();
                await populateDeliveryOptions();
                runTillFreeMsgTimer();
            }, 500); 
        }

        jQuery( document ).ajaxComplete(function() {
            priceManipulationsTimer();
            // do the focusing only once
            setFocusedTimer();
        });

        jQuery( document ).ready(function() {
            priceManipulationsTimer();
            // do the focusing only once
            setFocusedTimer();

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

            jQuery('<?php echo $shipping_to_sel; ?>').change(onDeliveryOptionChange);
            enabledOptionsNoAddress.forEach(function(key) {
                processPopulatedData(key);
                jQuery(locs[key].inner.region).val("").trigger('change.select2');
            });
            [locs.address.inner.region, locs.econt.inner.region, locs.econt.inner.city, locs.econt.inner.office,
                locs.speedy.inner.region, locs.speedy.inner.city, locs.speedy.inner.office].forEach(function(key) {
                jQuery(key).select2();
            });
        });
    </script>
<?php } );
