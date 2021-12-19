## Speedy and Econt_shipping
This plugin adds the checkout functionality to chose from offices of Speedy and Econt couriers in Bulgaria.
The functionality might get extended to other countries by simply adding parameters to respective API calls.

### Functionality provided
 - offload list of regions, cities and offices for Econt and Speedy in the Bulgaria
 - update the offices' data on daily basis
 - generates select boxes for the region-city-office bundle for each courier
 - provides option for delivery to home address
 - hides all shipping methods available (since other way of chosing them is used)
 - shows how much order value left till free delivery with selected delivery option
 
### Plugin settings allow to set the following
 - credentials to access Speedy and Econt APIs
 - shipping labels
 - shipping fees
 - free shipping from <sum>
 - currency to be used
 
### Setup steps
 - install and activate plugin
 - create 1 shipping method (with any name)
 - open plugin' settings and specify all the parameters requested
 - click [Save] button
 - add few items to your cart and proceed to checkout
 - verify checkout process is smooth and no errors are raised when placing the order

### Note
This plugin creates tables and populate data from respective APIs asynchronously.
So, please expect empty regions/cities/offices lists for first few minutes after plugin activation.