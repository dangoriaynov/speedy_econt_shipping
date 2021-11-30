<?php
add_action( 'wp_head', function () { ?>
    <script>
        let focused = false;
        let cur_prices = [];
        let deliv_opt = '';
        let deliv_opt_idx = -1;
        const def_prices = [3.4, 5.4, 4.2];
        const free_from = [5, 50, 50];
        const deliv_opts = ['офис на Speedy', 'офис на Еконт', 'адрес'];
        const deliv_opts_ids = ['shipping_to_speedy', 'shipping_to_econt', 'shipping_to_address'];
        const free_shipping_default_idx = 0;

        const locs = {
            'econt': {
                'outer': {
                    'region': '#econt_region_sel_field',
                    'city': '#econt_city_sel_field',
                    'office': '#econt_office_sel_field'},
                'inner': {
                    'region': '#econt_region_sel',
                    'city': '#econt_city_sel',
                    'office': '#econt_office_sel'}
            },
            'speedy': {
                'outer': {
                    'region': '#speedy_region_sel_field',
                    'city': '#speedy_city_sel_field',
                    'office': '#speedy_office_sel_field'},
                'inner': {
                    'region': '#speedy_region_sel',
                    'city': '#speedy_city_sel',
                    'office': '#speedy_office_sel'}
            },
            'address': {
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
            let priceStr = jQuery(".woocommerce-Price-amount.amount").last().text();
            return parseFloat(priceStr);
        }

        function changePhoneNumber(){
            if (jQuery("#billing_phone").val() !== '') {
                jQuery("#shipping_to_field").show("slow", function(){});
            }
        }

        function showTillFreeDeliveryMsg() {
            let idx = deliv_opt_idx !== -1 ? deliv_opt_idx : free_shipping_default_idx;
            let freeFromOption = free_from[idx];
            let leftTillFree = freeFromOption - orderPrice();
            let delivMsg = jQuery('div#deliv_msg');
            if (! delivMsg.length ) {
                jQuery(".woocommerce-notices-wrapper").first().append('<div id="deliv_msg">');
            }
            let $msgDiv = delivMsg.first();
            $msgDiv.removeClass();
            let msg;
            if (leftTillFree <= 0) {
                $msgDiv.addClass("woocommerce-message");
                msg = 'Честито, спечелихте безплатна доставка до '+deliv_opts[idx]+'!';
            } else {
                $msgDiv.addClass("woocommerce-error");
                msg = 'Остава Ви още <span class="woocommerce-Price-amount amount">'+leftTillFree.toFixed(2)+'&nbsp;<span class="woocommerce-Price-currencySymbol">лв</span></span> за да спечелите безплатна доставка до '+deliv_opts[idx]+'! <a class="button" href="https://dobavki.club/shop/">Към магазина</a>';
            }
            $msgDiv.html(msg);
        }

        function populateDeliveryOpts() {
            for (let idx = 0; idx < deliv_opts_ids.length; idx++) {
                populateDeliveryOpt(idx);
            }
        }

        function populateDeliveryOpt(idx){
            let price = orderPrice();
            let edge = free_from[idx];
            let curPrice = price >= edge ? 0 : def_prices[idx];
            cur_prices[idx] = curPrice;
            let priceAdd = curPrice === 0 ? 'безплатно' : '+'+curPrice.toFixed(2)+' лв.';
            let delivText = ' '+deliv_opts[idx]+' ('+priceAdd+')';
            jQuery("label[for='"+deliv_opts_ids[idx]+"']").text(delivText);
        }

        function populateFields(key, selectedRegion=null, selectedCity=null) {
            let regionSel = locs[key].inner.region;
            let citySel = locs[key].inner.city;
            let officeSel = locs[key].inner.office;
            let data;
            if (key === 'speedy') {
                data = speedyData;
            } else if (key === 'econt') {
                data = econtData;
            } else {
                throw 'populateFields: Unknown key was specified - ' + key;
            }
            let regionDom = jQuery(regionSel);
            regionDom.empty().trigger('change.select2');
            let cityDom = jQuery(citySel);
            cityDom.empty().trigger('change.select2');
            let officeDom = jQuery(officeSel);
            officeDom.empty().trigger('change.select2');

            Object.entries(data).forEach(([regionName, cities]) => {
                regionDom.append(jQuery('<option id="' + regionName + '">' + regionName + '</option>'));
                cities.forEach(city => {
                    if (selectedRegion && selectedRegion !== regionName) {
                        return;
                    }
                    console.log('adding city '+city.name+' to region '+regionName);
                    cityDom.append(jQuery('<option id="'+city.id+'">'+city.name+'</option>'));
                    city.offices.forEach(office => {
                        if (selectedCity && selectedCity !== city.name) {
                            return;
                        }
                        officeDom.append(jQuery('<option id="' + office.id + '">#' + office.id + ' ' + office.name + ' (' + office.address + ')</option>'));
                    });
                });
            });
            regionDom.trigger('change.select2');
            cityDom.trigger('change.select2');
            officeDom.trigger('change.select2');
        }

        function processPopulatedData(key) {
            console.log('processPopulatedData: ' + key);
            let regionDom = jQuery(locs[key].inner.region);
            let cityDomOuter = jQuery(locs[key].outer.city);
            let cityDom = jQuery(locs[key].inner.city);
            let officeDomOuter = jQuery(locs[key].outer.office);
            let officeDom = jQuery(locs[key].inner.office);
            regionDom.change(function() {
                let region = regionDom.find('option:selected').text();
                console.log('regionDom::onchange, region='+region);
                // special way of setting the region drop-down field
                jQuery("#billing_state option").filter(function() {
                    return jQuery(this).text() === region;
                }).prop('selected', true);
                populateFields(key, region);
                regionDom.val(region).trigger('change.select2');

                cityDomOuter.show();
                officeDomOuter.hide();
            });
            cityDom.change(function() {
                let region = regionDom.find('option:selected').text();
                let city = cityDom.find('option:selected').text();
                console.log('cityDom::onchange, region='+region+', city='+city);
                jQuery(locs.address.inner.city).val(city);
                populateFields(key, region, city);
                cityDom.val(city).trigger('change.select2');
                // chose the first office if only 1 is available in the list
                if (officeDom.find('option').length === 1) {
                    officeDom.val(jQuery(locs[key].inner.office+' option:eq(0)').val()).trigger('change.select2');
                }

                officeDomOuter.show();
            });
            officeDom.change(function() {
                let office = officeDom.find('option:selected').text();
                jQuery(locs.address.inner.office).val(office);
            });
        }

        // populate the offices data once DOM is loaded - it is happening later that onReady() is fired
        let checkExist = setInterval(function() {
            if (jQuery(locs.speedy.inner.region).length) {
                clearInterval(checkExist);
                populateFields('speedy');
                populateFields('econt');
            }
        }, 100); // check every 100ms

        function onDeliveryOptionChange() {
            console.log('onDeliveryOptionChange');
            let option = jQuery("input[name='shipping_to']:checked").val();
            let speedyOuter = jQuery([locs.speedy.outer.region, locs.speedy.outer.city, locs.speedy.outer.office].join(','));
            let econtOuter = jQuery([locs.econt.outer.region, locs.econt.outer.city, locs.econt.outer.office].join(','));
            let addressOuter = jQuery([locs.address.outer.region, locs.address.outer.city, locs.address.outer.office].join(','));
            // hide all selectors till we know what is chosen
            speedyOuter.hide();
            econtOuter.hide();
            addressOuter.hide();
            // set really saved values to empty
            // jQuery(locs.address.inner.region).val("");
            // jQuery(locs.address.inner.city).val("");
            // jQuery(locs.address.inner.address).val("");
            if (option === 'speedy') {
                jQuery(locs.speedy.inner.region).val("").trigger('change.select2');
                jQuery(locs.speedy.outer.region).show("slow", function(){});
            } else if (option === 'econt') {
                jQuery(locs.econt.inner.region).val("").trigger('change.select2');
                jQuery(locs.econt.outer.region).show("slow", function(){});
            } else if (option === 'address') {
                addressOuter.show("slow", function(){});
            }
        }

        jQuery( document ).ready(function() {
            let radioButtons = jQuery('input:radio[name="shipping_to"]');
            radioButtons.change(function () {
                if (jQuery(this).val() !== '') {
                    var idx_checked = radioButtons.index(radioButtons.filter(':checked'));
                    var deliv_add = cur_prices[idx_checked] > 0 ? " + доставка" : "";
                    jQuery(".woocommerce-Price-amount.amount").last().text(orderPrice().toFixed(2) + ' лв.' + deliv_add);
                }
            });

            changePhoneNumber();
            jQuery('#billing_phone').keypress(changePhoneNumber);

            showTillFreeDeliveryMsg();
            jQuery('input[type=radio][name=shipping_to]').change(onDeliveryOptionChange);
            processPopulatedData("speedy");
            processPopulatedData("econt");
        });

        jQuery( document ).ajaxComplete(function() {
            // focus only once
            if (! focused) {
                focused = true;
                jQuery("#billing_first_name").focus();
            }
            // do the copy of prices
            cur_prices = [...def_prices];
            populateDeliveryOpts();
            showTillFreeDeliveryMsg();
        });
    </script>
<?php } );
