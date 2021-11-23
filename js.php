<?php
add_action( 'wp_head', function () { ?>
    <script>
        var focused = false;
        var def_prices = [3.4, 5.4, 4.2];
        var free_from = [5, 50, 50];
        var cur_prices = [];
        var deliv_opt = '';
        var deliv_opt_idx = -1;
        var deliv_opts = ['офис на Speedy', 'офис на Еконт', 'адрес'];
        var deliv_opts_ids = ['shipping_to_speedy', 'shipping_to_econt', 'shipping_to_address'];
        var free_shipping_default_idx = 0;

        function order_price() {
            var priceStr = jQuery(".woocommerce-Price-amount.amount").last().text();
            return parseFloat(priceStr);
        }

        function change_phone_number(){
            if (jQuery("#billing_phone").val() !== '') {
                jQuery("#shipping_to_field").show("slow", function(){});
            }
        }

        // function change_shipping_to() {
        //     jQuery("form[name=checkout] input[name=shipping_to]:checked").each(function() {
        //         var deliv_opt_id = jQuery(this).attr("id");
        //         var deliv_opt_raw = jQuery("label[for='"+deliv_opt_id+"']").text();
        //         deliv_opt = deliv_opt_raw.split('(')[0].trim();
        //         deliv_opt_idx = deliv_opts_ids.findIndex(el => el === deliv_opt_id);
        //         change_office();
        //         show_free_delivery_notif();
        //     });
        // };
        //
        // function change_office() {
        //     var deliv_office = jQuery("#currier_office option:selected").text();
        //     if (!deliv_opt || !deliv_office) {
        //         return;
        //     }
        //     jQuery("#billing_address_1").val(deliv_opt+': '+deliv_office);
        // };
        //
        // function change_autopopulate_fields() {
        //     setTimeout(function() {
        //         change_phone_number();
        //         change_autopopulate_fields();
        //     }, 200); //hold the code execution for 200 ms
        // };

        function show_free_delivery_notif() {
            var idx = deliv_opt_idx !== -1 ? deliv_opt_idx : free_shipping_default_idx;
            var free_from_opt = free_from[idx];
            var left_till_free = free_from_opt - order_price();
            var delivMsg = jQuery('div#deliv_msg');
            if (! delivMsg.length ) {
                jQuery(".woocommerce-notices-wrapper").first().append('<div id="deliv_msg">');
            }
            var $msgDiv = delivMsg.first();
            $msgDiv.removeClass();
            var msg;
            if (left_till_free <= 0) {
                $msgDiv.addClass("woocommerce-message");
                msg = 'Честито, спечелихте безплатна доставка до '+deliv_opts[idx]+'!';
            } else {
                $msgDiv.addClass("woocommerce-error");
                msg = 'Остава Ви още <span class="woocommerce-Price-amount amount">'+left_till_free.toFixed(2)+'&nbsp;<span class="woocommerce-Price-currencySymbol">лв</span></span> за да спечелите безплатна доставка до '+deliv_opts[idx]+'! <a class="button" href="https://dobavki.club/shop/">Към магазина</a>';
            }
            $msgDiv.html(msg);
        }

        function populate_delivery_opts() {
            for (var idx = 0; idx < deliv_opts_ids.length; idx++) {
                populate_delivery_opt(idx);
            }
        }

        function populate_delivery_opt(idx){
            var price = order_price();
            var edge = free_from[idx];
            var cur_price = price >= edge ? 0 : def_prices[idx];
            cur_prices[idx] = cur_price;
            var price_add = cur_price === 0 ? 'безплатно' : '+'+cur_price.toFixed(2)+' лв.';
            var deliv_text = ' '+deliv_opts[idx]+' ('+price_add+')';
            jQuery("label[for='"+deliv_opts_ids[idx]+"']").text(deliv_text);
        }

        // function change_delivery_option() {
        //     //jQuery("#billing_state_field").show("slow", function(){});
        //     //jQuery("#billing_city_field").show("slow", function(){});
        //     jQuery("#currier_office_field").show("slow", function(){});
        // }

        function populateFields(region_str=null, city_str=null) {
            let regionSel = jQuery('#speedy_region_sel');
            regionSel.empty();
            let citySel = jQuery('#speedy_city_sel');
            citySel.empty();
            let officeSel = jQuery('#speedy_office_sel');
            officeSel.empty();

            Object.entries(speedyData).forEach(([regionName, cities]) => {
                regionSel.append(jQuery('<option id="' + regionName + '">' + regionName + '</option>'));
                cities.forEach(city => {
                    if (region_str && region_str !== regionName) {
                        return;
                    }
                    citySel.append(jQuery('<option data-region="'+regionName+'" id="'+city.id+'">'+city.name+'</option>'));
                    city.offices.forEach(office => {
                        if (city_str && city_str !== city.name) {
                            return;
                        }
                        officeSel.append(jQuery('<option data-city="' + city.name + '" id="' + office.id + '">#' + office.id + ' ' + office.name + ' (' + office.address + ')</option>'));
                    });
                });
            });
            regionSel.val(1).trigger('change.select2');
            citySel.val(1).trigger('change.select2');
            officeSel.val(1).trigger('change.select2');
        }

        let checkExist = setInterval(function() {
            if (jQuery('#speedy_region_sel').length) {
                clearInterval(checkExist);
                populateFields();
            }
        }, 100); // check every 100ms

        function onDeliveryOptionChange() {
            let option = jQuery("input[name='shipping_to']:checked").val();
            let speedy = jQuery('#speedy_region_sel_field, #speedy_city_sel_field, #speedy_office_sel_field');
            let econt = jQuery('#econt_region_sel_field, #econt_city_sel_field, #econt_office_sel_field');
            let address = jQuery('#billing_state_field, #billing_city_field, #billing_address_1_field');
            speedy.hide();
            econt.hide();
            address.hide();
            if (option === 'speedy') {
                speedy.show("slow", function(){});
            } else if (option === 'econt') {
                // econt.show("slow", function(){});
                address.show("slow", function(){});
            } else if (option === 'address') {
                address.show("slow", function(){});
            }
        }

        jQuery( document ).ready(function(){
            // change_autopopulate_fields();

            var radioButtons = jQuery('input:radio[name="shipping_to"]');
            radioButtons.change(function(){
                if(jQuery(this).val() !== ''){
                    var idx_checked = radioButtons.index(radioButtons.filter(':checked'));
                    var deliv_add = cur_prices[idx_checked] > 0 ? " + доставка" : "";
                    jQuery(".woocommerce-Price-amount.amount").last().text(order_price().toFixed(2)+' лв.'+deliv_add);
                    // change_delivery_option();
                }
            });

            change_phone_number();
            jQuery('#billing_phone').keypress(change_phone_number);

            // change_shipping_to();
            // jQuery("#shipping_to_field").on('change', change_shipping_to);
            //
            // change_office();
            // jQuery('#currier_office').on('change', change_office);

            show_free_delivery_notif();

            jQuery('input[type=radio][name=shipping_to]').change(onDeliveryOptionChange);

            var speedy_region = jQuery('#speedy_region_sel');
            var speedy_city = jQuery('#speedy_city_sel');
            var speedy_office = jQuery('#speedy_office_sel');
            speedy_region.change(function () {
                let region = speedy_region.find('option:selected').text();
                jQuery("#billing_state").val(region);
                populateFields(region);
                speedy_region.val(region);
                speedy_city.val(1);
                speedy_city.show();
            });
            speedy_city.change(function () {
                let region = speedy_region.find('option:selected').text();
                let city = speedy_city.find('option:selected').text();
                jQuery("#billing_city").val(city);
                populateFields(region, city);
                speedy_city.val(city);
                speedy_office.val(1);
                speedy_office.show();
            });
            speedy_office.change(function () {
                let office = speedy_office.find('option:selected').text();
                jQuery("#billing_address_1").val(office);
            });
        });

        jQuery( document ).ajaxComplete(function( ) {
            if (!focused) {
                focused = true;
                jQuery("#billing_first_name").focus();
            }

            cur_prices = [...def_prices];
            populate_delivery_opts();
            show_free_delivery_notif();
        });
    </script>
<?php } );
