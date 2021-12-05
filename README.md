# speedy_econt_shipping
This plugin adds functionality to specify delivery addresses for the Speedy and Econt couriers in Bulgaria.
The functionality might get extended to other countries by simply adding parameters for these.

Functionality provided:
 - offload list of regions, cities and offices for Econt and Speedy in the Bulgaria
 - update the data offloaded on daily basis
 - generates select boxes for the region-city-office bundle for each courier and for delivery to address
 - hides all shipping methods available (since other way of doing this is used)
 - shows how much order value left till free delivery (the value is manually specified)
 
Plugin settings allow to set the following:
 - credentials to access Speedy / Econt APIs
 - shipping labels
 - shipping fees
 - free shipping from <sum>
 - currency to be used
