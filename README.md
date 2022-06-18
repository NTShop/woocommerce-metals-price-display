# Metal Price Updater for WooCommerce

This plugins requires the Precious Metals plugin from IgniteWoo.com. It reads metal prices from the database via an Ajax request and updates the current prices into HTML elements on the page. The update happens every 5 minutes.

Below is sample HTML that contains all the class names that the Javascript looks for. Adjust the HTML structure to suit your needs but be sure to use the correct class names:

```<p>Gold: <span class="gold_price"></span> <span class="metal_price_separator">|</span> Silver: <span class="silver_price"></span> <span class="metal_price_separator">|</span> Platinum: <span class="platinum_price"></span> <span class="metal_price_separator">|</span> Palladium: <span class="palladium_price"></span> <span class="metal_price_separator">|</span> Date: <span class="current_date"></span> <span class="metal_price_separator">|</span> Next update: <span class="countdown"></span></p>```