=== Speedy And Econt Shipping ===
Contributors: winter2007d
Tags: econt, еконт, speedy, спиди, shipping, bulgaria, bulgaria couriers
Requires at least: 4.4
Requires PHP: 7.0
Tested up to: 6.0
Stable tag: 1.5.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate link: http://

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
 - contact Speedy and Econt couriers to provide you with API access
 - store username (should be digits only) and password provided by them

== Setup steps ==
 - install and activate plugin
 - create 1 shipping method (with any name)
 - open plugin' settings and specify all the parameters requested + data obtained in prerequisites
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