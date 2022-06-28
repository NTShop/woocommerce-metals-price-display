/**
 * This script runs the timer countdown, sends an Ajax request, and updates the HTML elements on the screen with new prices.
 *
 * Class names that should exist on the page for the script to insert prices, current date/time, and the countdown timer:
 * gold_price
 * silver_price
 * platinum_price
 * palladium_price
 * countdown
 * current_date
 *
 * Example HTML:
 * <p style="text-align: right;">Gold: <span class="gold_price"></span> <span class="metal_price_separator">|</span> Silver: <span class="silver_price"></span> <span class="metal_price_separator">|</span> Platinum: <span class="platinum_price"></span> <span class="metal_price_separator">|</span> Palladium: <span class="palladium_price"></span> <span class="metal_price_separator">|</span> Date: <span class="current_date"></span> <span class="metal_price_separator">|</span> Next update: <span class="countdown"></span></p>
 *
 * @package Metals_Price_Display
 *
 */
( function( $ ) {
	$( function() {
		var data = metals_price_updater.metal_price_json;
		var tempData = $.parseJSON( data );
		var nextUpdate = parseInt( metals_price_updater.next_update, 10 );
		var countDownDate = new Date( nextUpdate ).getTime();
		function getMetalPrices() { 
			$.ajax({
				url: metals_price_updater.ajax_url,
				type: 'post',
				data: { 'action' : 'get_metal_prices' },
				dataType: 'json',
				success: function( data ) {
					updatePriceDisplay( data );
				}
			});
		}
		function updatePriceDisplay( data ) {
			var timeToUpdate = parseInt( data.timeToUpdate, 10 );
			countDownDate = new Date( timeToUpdate ).getTime();
			if ( metals_price_updater.price_unit_of_measure === 'oz' ) { 
				$( '.gold_price' ).html( data.goldPriceOunce );
				$( '.silver_price' ).html( data.silverPriceOunce );
				$( '.platinum_price' ).html( data.platinumPriceOunce );
				$( '.palladium_price' ).html( data.palladiumPriceOunce );
			} else if ( metals_price_updater.price_unit_of_measure === 'gr' ) { 
				$( '.gold_price' ).html( data.goldPriceGram );
				$( '.silver_price' ).html( data.silverPriceGram );
				$( '.platinum_price' ).html( data.platinumPriceGram );
				$( '.palladium_price' ).html( data.palladiumPriceGram );
			}
			$( '.current_date' ).html( data.currentDate );
		}
		setInterval( function () {
			let now = new Date().getTime();
			let timeDifference = countDownDate - now;
			if ( timeDifference < 0 ) {
				getMetalPrices();
			} else {
				var minutes = Math.floor( ( timeDifference % ( 1000 * 60 * 60 ) ) / ( 1000 * 60 ) );
				var seconds = Math.floor( ( timeDifference % ( 1000 * 60 ) ) / 1000 );
				$( '.countdown' ).html( minutes + 'm ' + seconds + 's' );
			}
		}, 1000 );
		if ( typeof( tempData.timeToUpdate ) === 'undefined' ) {
			getMetalPrices();
		} else { 
			updatePriceDisplay( tempData );
		}
	});
})(jQuery);
