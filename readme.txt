=== Speedy And Econt Shipping ===
Contributors: winter2007d
Tags: econt, еконт, speedy, спиди, shipping, bulgaria, bulgaria couriers
Requires at least: 7.0
Requires PHP: 7.0
Tested up to: 6.4
Stable tag: 1.14.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: http://revolut.me/danq6lus

Adds functionality to specify delivery addresses for the Speedy and Econt couriers in Bulgaria.

== Description ==
This plugin adds the checkout functionality to chose from offices of Speedy and Econt couriers in Bulgaria.
The functionality might get extended to other countries by simply adding parameters to respective API calls.

== Functionality provided ==
 - offload list of regions, cities and offices for Econt and Speedy in the Bulgaria
 - update the offices' data on daily basis
 - generates select boxes for the region-city-office bundle for each courier
 - provides option for delivery to home address
 - hides all shipping methods available (since other way of chosing them is used)
 - shows how much order value left till free delivery with selected delivery option

== Plugin settings allow to set the following ==
 - credentials to access Speedy and Econt APIs
 - shipping labels
 - shipping fees
 - free shipping from <sum>
 - currency to be used

== Prerequisites ==
 - contact Speedy courier to provide you with API access
 - store username (should be digits only) and password provided in the settings

== Setup steps ==
 - install and activate plugin
 - create 1 fixed_fee shipping method
 - open plugin' settings and specify all the parameters requested
 - click [Save] button
 - wait till data is refreshed (for first set - wait for 1 minute, for subsequent change - at 3:05 AM daily)
 - add few items to your cart and proceed to checkout
 - verify checkout process is smooth and no errors are raised when placing the order

== Note ==
This plugin creates tables and populate data from respective APIs asynchronously.
So, please expect empty regions/cities/offices lists for first few minutes after plugin activation.

== Frequently Asked Questions ==
 - Question: The region/city/office fields are empty for the selected delivery option.
 - Answer: Be sure that you have specified proper credentials to access APIs. Please wait for the few minutes if you have just done this.

 - Question: The region/city/office fields continue to be empty after waiting for more that 5 mins after plugin's activation.
 - Answer: Please check whether the credentials you have provided are correct ones. If you continue having problems - contact developers at winter2007d (at) gmail.com

 - Question: There are errors while making the order after plugin activation.
 - Answer: Be sure that you have created one shipping method for the region where you provide shipping options.

== Upgrade Notice ==
 - be sure to check that no changes are needed in the plugin' settings page once you update the plugin

== Screenshots ==
1. 'Left till free shipping' shown in cart page, separate warning is shown next to the order price
2. Same information is shown at the checkout page
3. Delivery options are displayed once phone number is populated
4. Region, city and office fields are shown for the corresponding shipping option
5. Cities list is populated once region is selected
6. Office field is automatically populated when only 1 office is available in the chosen city
7. Original region, city and address fields are shown when 'to address' delivery option is chosen

== Donation ==
If you wish to donate to support this plugin please do this to one of the non-profits you adore. They need it more.

== Changelog ==
### 0.1 - 2021-12-27
#### Enhancements
Created initial version of the plugin
### 0.2 - 2022-01-14
#### Bug fixes
Incorrect final order price calculation by JS script
#### Enhancements
Only Econt sites having offices are now inserted in the table
Prevent cleaning up of the courier tables when API request returns incomplete data
### 0.3 - 2022-01-14
#### Enhancements
Fixed wrong version in the main php file
### 0.4 - 2022-01-30
#### Enhancements
Compatible with WordPress 5.9
Not showing the 'Left till free' message on empty cart
### 0.5 - 2022-05-03
#### Fixes
Force opening of the checkout page won't show NaN warning
Offices update logic was not working
#### Enhancements
Econt office number is now skipped from the order details
Delivery method is now bold in the top bar notification
### 0.6 - 2022-05-03
#### Fixes
Fixed the readme file format
### 0.7 - 2022-05-03
#### Fixes
Changed stable version link and tested up to
### 0.8 - 2022-05-03
#### Fixes
Aligned the versions along different files to fix plugin update issues
### 0.9 - 2022-05-03
#### Fixes
Aligned readme files to have same content
Fixed necessity to re-enable plugin once its data was initially set or changed
### 1.0 - 2022-05-03
#### Fixes
Made new tag since previous was submitted with incorrect files
### 1.1 - 2022-05-25
#### Fixes
Made new way to showing the 'shipping left till free' message
Loading css styles on checkout page only
#### Enhancements
Compatible with WP 6.0
### 1.2 - 2022-05-25
#### Fixes
Made new tag since previous was submitted with incorrect files
### 1.3 - 2022-06-28
#### Fixes
Fixed incorrect MySQL syntax while deleting entries from the DB (actual for some versions of MySQL)
### 1.4 - 2022-06-29
#### Fixes
Fixed incorrect MySQL syntax while deleting entries from the DB. Part 2 (actual for some versions of MySQL)
### 1.5 - 2022-06-30
#### Fixes
Do not use the auth for the econt data retrieval
### 1.5.1 - 2022-07-09
#### Fixes
Added debugging logic
### 1.5.2 - 2022-07-09
#### Fixes
Fixed small typos
### 1.5.3 - 2022-10-09
#### Fixes
Fixed Speedy offices selection for the Sofia city
### 1.5.5 - 2022-10-09
#### Fixes
Fixed Speedy offices selection for the Sofia city №2
### 1.5.6 - 2022-10-09
#### Fixes
Fixed Speedy offices selection for the Sofia city №3
### 1.6 - 2023-01-19
#### Enhancements
Removed Econt username and password fields since they are not needed for the plugin to work
Added an option to disable messages about the free shipping earned
Added ability to enable only particular shipping methods (not all at the same time)
Made Econt office alias visible along with the address to ease up the search
Added ability to specify custom list of fields which should be used when "Delivery to address" option is chosen and hidden otherwise
Added option to show all delivery fields altogether (without entering the phone number)
Added check whether Speedy office is opened before adding it to the list of available
Preventing plugin logic from being loaded on non-checkout pages
### 1.6.1 - 2023-01-19
#### Fixes
fixed version issue
### 1.6.2 - 2023-01-21
#### Fixes
Fixed Econt shipping calculation issue
### 1.7 - 2023-01-22
#### Fixes
Added option to explicitly calculate final order price in the checkout page
Speedy/Econt data collection is done only when proper office' option is enabled
DB tables are now removed on plugin deactivation
### 1.7.1 - 2023-01-22
#### Fixes
fixed version issue
### 1.7.2 - 2023-01-22
#### Fixes
Fixed Speedy offices storage logic
### 1.7.3 - 2023-01-22
#### Fixes
Fixed issue of not loading offices
Added settings link to the plugins view
### 1.8 - 2023-01-23
#### Fixes
Added ability to force refresh the offices/sites tables
Made faster data insertion logic
### 1.8.1 - 2023-01-23
#### Fixes
Fixed translation
Fixed _options table name
### 1.8.2 - 2023-01-23
#### Fixes
fixed version issue
### 1.8.3 - 2023-01-26
#### Fixes
Fixed table data refresh issues
### 1.8.4 - 2023-01-26
#### Fixes
Fixed sporadically clearing of the offices tables
### 1.8.5 - 2023-01-26
#### Fixes
Small improvement on price calculation on checkout page
### 1.9 - 2023-01-28
#### Fixes
Made delivery prices shown at the thank you page and in the confirmation email
Added option to make email address optional
All offices are now updated in 1 transaction
Fixed nasty econt offices update issue
### 1.9.1 - 2023-01-28
#### Fixes
Fixed shipping method calculation issue
### 1.9.2 - 2023-01-28
#### Fixes
Small improvement on the way labels are stored
### 1.9.3 - 2023-01-31
#### Fixes
Made correct delivery price shown in the rightmost column on checkout
Made possible setting the order of delivery options as they appear at checkout page
### 1.9.4 - 2023-01-31
#### Fixes
Fixed to address delivery method
Fixed translation part
### 1.9.5 - 2023-02-01
#### Fixes
Made delivery price included in emails and order total only when option is chosen
Fixed absent free delivery label from the orders placed
### 1.9.6 - 2023-03-07
#### Fixes
Fixed onAjaxComplete handler so that it properly work with the final price changes
### 1.9.7 - 2023-03-15
#### Fixes
Removed final price re-calculation logic for the case when delivery price is included in the final order price.
This might cause issues with other plugins which do the ajax refresh. Not sure how to fix it properly for now.
### 1.9.8 - 2023-03-15
#### Fixes
Added ability to specify shipping methods which will have store message `left till free delivery` shown
Now possible to have shipping methods w/o free delivery option
### 1.9.9 - 2023-03-20
#### Fixes
Fixed 'till free delivery' message and price calculation (again)
### 1.10.0 - 2023-03-25
#### Fixes
Fixed overall price calculation. Hiding original div and adding totally new one to handle all ajax-based refreshes correctly
Made delivery fields mandatory (in UI)
### 1.10.1 - 2023-03-25
#### Fixes
Fixed 'left till free delivery' message
Fixed unnecessary execution of functions which were invoked by timer
### 1.10.2 - 2023-03-25
#### Fixes
Fixed the refresh logic not working in some cases
Fixed first name focusing logic
### 1.10.3 - 2023-03-25
#### Fixes
Fixed appearance of 'left till free delivery' message when has just opened the checkout page
### 1.10.4 - 2023-03-29
#### Fixes
Fixed first name field focus on first time checkout page is opened
Fixed 'free delivery' label not added to the office name (admin only)
### 1.11.0 - 2023-04-18
#### Fixes
Improved handing of the ajax price refresh on the checkout page
Compatible with WP version 6.2
### 1.11.1 - 2023-05-14
#### Fixes
Made no focusing on the first name field if it already has a value
Hidden delivery details from the cart page
Preventing scroll to top on cart contents update (left till free delivery re-calculation)
### 1.12.0 - 2023-06-01
#### Fixes
Fixed name of delivery html option
Fixed escaping for the delivery html option
Fixed offices update logic
Fixed jQuery loading in case site uses some custom version
### 1.12.1 - 2023-06-01
#### Fixes
Fixed version issue
### 1.12.2 - 2023-06-02
#### Fixes
Added option to use custom jQuery version
### 1.12.3 - 2023-06-02
#### Fixes
Added new option to not validate the region/city/address
### 1.12.4 - 2023-06-16
#### Improvements
Declared compatibility with WooCommerce HPOS
### 1.13.0 - 2023-06-19
#### Fixes
Fixed the force table refresh issue
### 1.13.1 - 2023-08-10
#### Fixes
Compatible with WP 6.3
### 1.13.2 - 2024-03-12
#### Fixes
Made to office field value wrap
Compatible with WP 6.4
### 1.13.3 - 2024-03-14
#### Fixes
Regional centers are set to be first in list of cities
### 1.13.4 - 2024-03-14
#### Fixes
Fixed version tag
### 1.14.0 - 2024-03-14
#### Fixes
Cities selection fixed
### 1.14.1 - 2024-04-29
#### Fixes
Fixed indices errors in logs