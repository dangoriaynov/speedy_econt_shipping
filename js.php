<?php

require 'utils.php';

add_action( 'wp_head', function () {
    global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel, $econt_region_sel, $econt_city_sel, $econt_office_sel,
           $speedy_region_field, $speedy_city_field, $speedy_office_field, $econt_region_field, $econt_city_field, $econt_office_field,
           $shipping_to_sel, $delivOpts, $defaultOpt, $shipping_to_field;
    ?>
    <script>
        let isFocused = false;
        let pricesCopy = {};
        const delivOptions = <?php echo json_encode($delivOpts); ?>;
        const defaultShippingMethod = '<?php echo $defaultOpt; ?>';

        const locs = {
            'econt': {
                'name' : 'econt',
                'outer': {
                    'region': '<?php echo $econt_region_field; ?>',
                    'city': '<?php echo $econt_city_field; ?>',
                    'office': '<?php echo $econt_office_field; ?>'},
                'inner': {
                    'region': '<?php echo $econt_region_sel; ?>',
                    'city': '<?php echo $econt_city_sel; ?>',
                    'office': '<?php echo $econt_office_sel; ?>'}
            },
            'speedy': {
                'name' : 'speedy',
                'outer': {
                    'region': '<?php echo $speedy_region_field; ?>',
                    'city': '<?php echo $speedy_city_field; ?>',
                    'office': '<?php echo $speedy_office_field; ?>'},
                'inner': {
                    'region': '<?php echo $speedy_region_sel; ?>',
                    'city': '<?php echo $speedy_city_sel; ?>',
                    'office': '<?php echo $speedy_office_sel; ?>'}
            },
            'address': {
                'name' : 'address',
                'outer': {
                    'region': '#billing_state_field',
                    'city': '#billing_city_field',
                    'office': '#billing_address_1_field'},
                'inner': {
                    'region': '#billing_state',
                    'city': '#billing_city',
                    'office': '#billing_address_1'}
            }
        };

        function orderPrice() {
            return parseFloat(jQuery(".woocommerce-Price-amount.amount").last().text());
        }

        function onChangePhoneNumber(){
            if (jQuery("#billing_phone").val() !== '') {
                jQuery("<?php echo $shipping_to_field; ?>").show("slow", function(){});
            }
        }

        function showTillFreeDeliveryMsg() {
            const chosenShippingOpt = jQuery('<?php echo $shipping_to_sel ?>:checked').val();
            const chosenOrDefaultOpt = delivOptions[chosenShippingOpt ? delivOptions[chosenShippingOpt].name : defaultShippingMethod];
            const leftTillFree = chosenOrDefaultOpt.free_from - orderPrice();
            const msgContainer = jQuery('div#deliv_msg');
            if (! msgContainer.length ) {
                jQuery(".woocommerce-notices-wrapper").first().append('<div id="deliv_msg">');
            }
            const $msgDiv = msgContainer.first();
            $msgDiv.removeClass();
            let msg;
            if (leftTillFree <= 0) {
                $msgDiv.addClass("woocommerce-message");
                msg = __('Congrats, you won free delivery using ', 'speedy_econt_shipping')+chosenOrDefaultOpt.label+'!';
            } else {
                $msgDiv.addClass("woocommerce-error");
                msg = 'Остава Ви още <span class="woocommerce-Price-amount amount">'+leftTillFree.toFixed(2)+'&nbsp;<span class="woocommerce-Price-currencySymbol">лв</span></span> за да спечелите безплатна доставка до '+chosenOrDefaultOpt.label+'! <a class="button" href="https://dobavki.club/shop/">Към магазина</a>';
            }
            $msgDiv.html(msg);
        }

        function populateDeliveryOptions() {
            Object.keys(delivOptions).forEach(function(key) {
                populateDeliveryOption(key);
            });
        }

        function populateDeliveryOption(key){
            const chosenOption = delivOptions[key];
            const curPrice = orderPrice() >= chosenOption.free_from ? 0 : chosenOption.shipping;
            pricesCopy[key] = curPrice;
            const priceAdd = curPrice === 0 ? __('for free', speedy_econt_shipping) : '+'+curPrice.toFixed(2)+' лв.';
            const delivText = ' '+chosenOption.label+' ('+priceAdd+')';
            jQuery(".woocommerce-input-wrapper > label[for='"+chosenOption.id+"']").text(delivText);
        }

        function populateFields(key, selectedRegion=null, selectedCity=null) {
            if (! [delivOptions.speedy.name, delivOptions.econt.name].includes(key)) {
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
                        officeDom.append(jQuery('<option id="' + office.id + '">#' + office.id + ' ' + office.name + ' (' + office.address + ')</option>'));
                    });
                });
            });
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

                // chose the first office if only 1 is available in the list
                const oneOffice = officeDom.find('option').length === 1 ? jQuery(locs[key].inner.office+' option:eq(0)').val() : "";
                officeDom.val(oneOffice).trigger('change.select2');
                jQuery(locs.address.inner.office).val(oneOffice);

                officeDomOuter.show();
            });
            officeDom.change(function() {
                const office = officeDom.find('option:selected').text();
                jQuery(locs.address.inner.office).val(office);
            });
        }

        function onDeliveryOptionChange() {
            showTillFreeDeliveryMsg();
            const option = jQuery('<?php echo $shipping_to_sel ?>:checked').val();
            // hide all the selectors till we know what is chosen
            Object.keys(locs).forEach(function(key) {
                jQuery(locs[key].outer.region).hide();
                jQuery(locs[key].outer.city).hide();
                jQuery(locs[key].outer.office).hide();
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

        jQuery( document ).ready(function() {
            // populate the offices data once DOM is loaded - it is happening later that onReady() is fired
            let checkExist = setInterval(function() {
                if (jQuery(locs.speedy.inner.region).length) {
                    clearInterval(checkExist);
                    jQuery([locs.address.outer.region, locs.address.outer.city, locs.address.outer.office].join(',')).attr("style", "");
                    [delivOptions.speedy.name, delivOptions.econt.name].forEach(function(key) {
                        populateFields(key);
                    });
                }
            }, 100); // check every 100ms

            let radioButtons = jQuery('<?php echo $shipping_to_sel ?>');
            radioButtons.change(function () {
                if (! jQuery(this).val()) {
                    return;
                }
                const addText = pricesCopy[jQuery(this).attr("id")] > 0 ? " + доставка" : "";
                jQuery(".woocommerce-Price-amount.amount").last().text(orderPrice().toFixed(2) + ' лв.' + addText);
            });

            onChangePhoneNumber();
            jQuery('#billing_phone').keypress(onChangePhoneNumber);

            showTillFreeDeliveryMsg();
            jQuery('<?php echo $shipping_to_sel ?>').change(onDeliveryOptionChange);
            [delivOptions.speedy.name, delivOptions.econt.name].forEach(function(key) {
                processPopulatedData(key);
                jQuery(locs[key].inner.region).val("").trigger('change.select2');
            });
            [locs.address.inner.region, locs.econt.inner.region, locs.econt.inner.city, locs.econt.inner.office,
                locs.speedy.inner.region, locs.speedy.inner.city, locs.speedy.inner.office].forEach(function(key) {
                jQuery(key).select2({
                    sortResults: data => data.sort((a, b) => a.text.localeCompare(b.text))
                });
            });
        });

        jQuery( document ).ajaxComplete(function() {
            // do the focus only once
            if (! isFocused) {
                isFocused = true;
                jQuery("#billing_first_name").focus();
            }
            // do the copy of prices
            Object.values(delivOptions).forEach(function(value) {
                pricesCopy[value.id] = value.shipping;
            });
            populateDeliveryOptions();
            showTillFreeDeliveryMsg();
        });
    </script>
<?php } );
