<?php

require 'utils.php';

add_action( 'wp_head', function () {
    global $speedy_region_sel, $speedy_city_sel, $speedy_office_sel, $econt_region_sel, $econt_city_sel, $econt_office_sel,
           $speedy_region_field, $speedy_city_field, $speedy_office_field, $econt_region_field, $econt_city_field, $econt_office_field,
           $shipping_to_regex, $delivOpts, $defaultOpt;
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
                jQuery("#shipping_to_field").show("slow", function(){});
            }
        }

        function showTillFreeDeliveryMsg() {
            const chosenShippingOpt = jQuery('<?php echo $shipping_to_regex ?>:checked').val();
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
                msg = 'Честито, спечелихте безплатна доставка до '+chosenOrDefaultOpt.label+'!';
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
            const priceAdd = curPrice === 0 ? 'безплатно' : '+'+curPrice.toFixed(2)+' лв.';
            const delivText = ' '+chosenOption.label+' ('+priceAdd+')';
            jQuery("label[for='"+chosenOption.id+"']").text(delivText);
        }

        function populateFields(key, selectedRegion=null, selectedCity=null) {
            if (! [delivOptions.speedy.name, delivOptions.econt.name].includes(key)) {
                console.log('populateFields: Skipping key: '+key);
                return;
            }
            const regionSel = locs[key].inner.region;
            const citySel = locs[key].inner.city;
            const officeSel = locs[key].inner.office;
            // since we store only json variable name, not its actual contents
            const data = eval(delivOptions[key].data);
            const regionDom = jQuery(regionSel);
            regionDom.empty().trigger('change.select2');
            const cityDom = jQuery(citySel);
            cityDom.empty().trigger('change.select2');
            const officeDom = jQuery(officeSel);
            officeDom.empty().trigger('change.select2');

            Object.entries(data).forEach(([regionName, cities]) => {
                regionDom.append(jQuery('<option id="' + regionName + '">' + regionName + '</option>'));
                cities.forEach(city => {
                    if (selectedRegion && selectedRegion !== regionName) {
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
                // special way of setting the region drop-down field
                jQuery(locs.address.inner.region+" option").filter(function() {
                    return jQuery(this).text() === region;
                }).prop('selected', true);
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
                if (officeDom.find('option').length === 1) {
                    officeDom.val(jQuery(locs[key].inner.office+' option:eq(0)').val()).trigger('change.select2');
                } else {
                    officeDom.val("").trigger('change.select2')
                }

                officeDomOuter.show();
            });
            officeDom.change(function() {
                const office = officeDom.find('option:selected').text();
                jQuery(locs.address.inner.office).val(office);
            });
        }

        // populate the offices data once DOM is loaded - it is happening later that onReady() is fired
        let checkExist = setInterval(function() {
            if (jQuery(locs.speedy.inner.region).length) {
                clearInterval(checkExist);
                [delivOptions.speedy.name, delivOptions.econt.name].forEach(function(key) {
                    populateFields(key);
                });
            }
        }, 100); // check every 100ms

        function onDeliveryOptionChange() {
            showTillFreeDeliveryMsg();
            const option = jQuery('<?php echo $shipping_to_regex ?>:checked').val();
            const speedyOuter = jQuery([locs.speedy.outer.region, locs.speedy.outer.city, locs.speedy.outer.office].join(','));
            const econtOuter = jQuery([locs.econt.outer.region, locs.econt.outer.city, locs.econt.outer.office].join(','));
            const addressOuter = jQuery([locs.address.outer.region, locs.address.outer.city, locs.address.outer.office].join(','));
            // hide all the selectors till we know what is chosen
            speedyOuter.hide();
            econtOuter.hide();
            addressOuter.hide();
            // set really saved address to empty
            jQuery(locs.address.inner.office).val("");
            if ([locs.speedy.name, locs.econt.name].includes(option)) {
                jQuery(locs[option].outer.region).show("slow", function(){});
                jQuery(locs[option].outer.city).show("slow", function(){});
            } else if (option === locs.address.name) {
                addressOuter.show("slow", function(){});
            }
        }

        jQuery( document ).ready(function() {
            let radioButtons = jQuery('<?php echo $shipping_to_regex ?>');
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
            jQuery('<?php echo $shipping_to_regex ?>').change(onDeliveryOptionChange);
            [delivOptions.speedy.name, delivOptions.econt.name].forEach(function(key) {
                processPopulatedData(key);
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
