<?php
/**
 * Metal price display class file
 *
 * Sample HTML that would work with this plugin:
 * <p style="text-align: right;">Gold: <span class="gold_price"></span> <span class="metal_price_separator">|</span> Silver: <span class="silver_price"></span> <span class="metal_price_separator">|</span> Platinum: <span class="platinum_price"></span> <span class="metal_price_separator">|</span> Palladium: <span class="palladium_price"></span> <span class="metal_price_separator">|</span> Date: <span class="current_date"></span> <span class="metal_price_separator">|</span> Next update: <span class="countdown"></span></p>
 *
 * @package Metals Price Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class file
 */
class WC_Metals_Price_Display {

	/**
	 * Currency symbol
	 *
	 * @var string $symbol The currency symbol as set in the WooCommerce settings.
	 */
	protected $symbol;

	/**
	 * Update interval
	 *
	 * Controls the interval between Ajax calls to get updated prices.
	 *
	 * @var int $update_interval An integer valid for use with strtotime();
	 */
	protected $update_interval = 5; // update every 5 minutes.

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_get_metal_prices', ( array( &$this, 'get_metal_prices' ) ) );
		add_action( 'wp_ajax_nopriv_get_metal_prices', ( array( &$this, 'get_metal_prices' ) ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'inject_timer_script' ), 90 );
	}

	/**
	 * Inserts a Javascript timer and Ajax function into the page.
	 *
	 * The script sends an Ajax request to the server to get the latest price data and the next update time.
	 * It then looks for specific elements on the page to insert the results.
	 *
	 * This code tries to use data cached in a transient to avoid sending Ajax queries to the server until the update timer interval expires.
	 *
	 * Here is some sample HTML that could be used with this script, the class names are important - the JS looks for them!:
	 * <p style="text-align: right;">Gold: <span class="gold_price"></span> <span class="metal_price_separator">|</span> Silver: <span class="silver_price"></span> <span class="metal_price_separator">|</span> Platinum: <span class="platinum_price"></span> <span class="metal_price_separator">|</span> Palladium: <span class="palladium_price"></span> <span class="metal_price_separator">|</span> Date: <span class="current_date"></span> <span class="metal_price_separator">|</span> Next update: <span class="countdown"></span></p>
	 *
	 * @return void
	 */
	public function inject_timer_script() {

		$metal_price_json = get_transient( 'metal_price_json' );

		if ( ! empty( $metal_price_json ) ) {
			// There's JSON cached so parse that and use it.
			$metal_price_data = json_decode( $metal_price_json, true );

			if ( ! empty( $metal_price_json ) ) {
				$next_update = $metal_price_data['timeToUpdate'];
			} else {
				$metal_price_json = array();
			}

			// The function current_datetime() only exists in WP 5.3 and newer.
			if ( function_exists( 'current_datetime' ) ) {
				$time_now          = current_datetime();
				$current_date_time = $time_now->getTimestamp() + $time_now->getOffset();
				$current_date_time = gmdate( 'Y-m-d H:i', $current_date_time );
			} else {
				$current_date_time = current_time( 'mysql', false );
			}

			// Reset time string stored in the transient to the current time, for display.
			$metal_price_data['currentDate'] = $current_date_time;
			// Re-encode the array back to JSON format for us in the JS below.
			$metal_price_json = wp_json_encode( $metal_price_data );
		} else {
			// There is no JSON stored yet so build a simple empty JSON object for use in the Javascript "data" variable.
			$metal_price_json = wp_json_encode( array() );

			// The function current_datetime() only exists in WP 5.3 and newer.
			if ( function_exists( 'current_datetime' ) ) {
				$time_now          = current_datetime();
				$current_date_time = $time_now->getTimestamp() + $time_now->getOffset();
				$current_date_time = gmdate( 'Y-m-d H:i', $current_date_time );
			} else {
				$current_date_time = current_time( 'mysql', false );
			}
			$next_update = $current_date_time;
		}

		if ( empty( $next_update ) ) {
			// Time until the next update. This will be empty the first time this function runs on the site so set a value.
			$next_update = strtotime( '+' . $this->update_interval . ' minutes', time() );
			$next_update = gmdate( 'Y-m-d H:i:s+0000', $next_update );
		}

		// Allow filtering the price unit of measure to display. Valid filter return values are "oz" (for ounce price) and "gr" (for gram price).
		$price_unit_of_measure = apply_filters( 'metals_price_unit_of_measure_to_display', 'oz' );

		$args = array(
			'metal_price_json'      => $metal_price_json,
			'next_update'           => $next_update,
			'price_unit_of_measure' => $price_unit_of_measure,
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
		);

		wp_enqueue_script( 'metals-price-updater', plugin_dir_url( PLUGIN_FILE ) . 'assets/js/metals-price-updater.js', array( 'jquery' ), METALS_UPDATER_VERSION, true );
		wp_localize_script( 'metals-price-updater', 'metals_price_updater', $args );
	}

	/**
	 * Formats the price with HTML according to whether the price went up or down
	 *
	 * CSS classes can be used to style the colors of the up/down arrows.
	 *
	 * @param float   $price The current metal price.
	 * @param boolean $price_went_up true|false Whether the price went up or not.
	 * @param string  $unit_of_price 'oz' | 'gr' The unit of measure for the spot price being formatted.
	 *
	 * @return string $price The formatted price string.
	 */
	public function get_price_output( $price, $price_went_up, $unit_of_price ) {

		$price = round( $price, 2 );

		if ( $price_went_up ) {
			return $price . ' ' . $this->symbol . '/' . $unit_of_price . ' <span class="up_arrow">&#x25B2;</span>';
		} else {
			return $price . ' ' . $this->symbol . '/' . $unit_of_price . ' <span class="down_arrow">&#x25BC;</span>';
		}
	}

	/**
	 * Ajax worker to get metal prices per gram and ounce and send a JSON response
	 *
	 * @return void
	 */
	public function get_metal_prices() {
		// Set next time to update.
		$next_update = strtotime( '+' . $this->update_interval . ' minutes', time() );
		$next_update = gmdate( 'Y-m-d H:i:s+0000', $next_update );

		// Check if transient data already exists.
		$data = get_transient( 'metal_price_json' );

		// If data exists try to decode it to an array.
		if ( ! empty( $data ) ) {
			$data = json_decode( $data, true );
		}

		// If decoding data succeeded then check if time to update has arrive or been exceeded, if not send the data as the response now, no need for new data lookup.
		if ( ! empty( $data ) ) {
			if ( strtotime( $data['timeToUpdate'] ) > time() ) {
				wp_send_json( $data );
			}
		}

		// Grams per troy ounce.
		$oz = 31.1035;

		// Get metal current prices. Prices represent the price of 1 gram.
		$gold_price      = floatval( get_option( 'ign_gold_price', false ) );
		$silver_price    = floatval( get_option( 'ign_silver_price', false ) );
		$platinum_price  = floatval( get_option( 'ign_platinum_price', false ) );
		$palladium_price = floatval( get_option( 'ign_palladium_price', false ) );

		// Get metal price history (the last known price before the price changed).
		$last_gold      = floatval( get_option( 'ign_gold_price_history', false ) );
		$last_silver    = floatval( get_option( 'ign_silver_price_history', false ) );
		$last_platinum  = floatval( get_option( 'ign_platinum_price_history', false ) );
		$last_palladium = floatval( get_option( 'ign_palladium_price_history', false ) );

		$this->symbol = get_woocommerce_currency_symbol();

		if ( $gold_price < $last_gold ) {
			$price_went_up = false;
		} else {
			$price_went_up = true;
		}

		$gold    = $this->get_price_output( $gold_price, $price_went_up, 'gr' );
		$gold_oz = $this->get_price_output( $gold_price * $oz, $price_went_up, 'oz' );

		if ( $silver_price < $last_silver ) {
			$price_went_up = false;
		} else {
			$price_went_up = true;
		}

		$silver    = $this->get_price_output( $silver_price, $price_went_up, 'gr' );
		$silver_oz = $this->get_price_output( $silver_price * $oz, price_went_up, 'oz' );

		if ( $platinum_price < $last_platinum ) {
			$price_went_up = false;
		} else {
			$price_went_up = true;
		}

		$platinum    = $this->get_price_output( $platinum_price, $price_went_up, 'gr' );
		$platinum_oz = $this->get_price_output( $platinum_price * $oz, $price_went_up, 'oz' );

		if ( $palladium_price < $last_palladium ) {
			$price_went_up = false;
		} else {
			$price_went_up = true;
		}

		$palladium    = $this->get_price_output( $palladium_price, $price_went_up, 'gr' );
		$palladium_oz = $this->get_price_output( $palladium_price * $oz, $price_went_up, 'oz' );

		// The function current_datetime() only exists in WP 5.3 and newer.
		if ( function_exists( 'current_datetime' ) ) {
			$time_now          = current_datetime();
			$current_date_time = $time_now->getTimestamp() + $time_now->getOffset();
			$current_date_time = gmdate( 'Y-m-d H:i', $current_date_time );
		} else {
			$current_date_time = current_time( 'mysql', false );
		}

		$data = array(
			'goldPriceGram'       => $gold,
			'silverPriceGram'     => $silver,
			'platinumPriceGram'   => $platinum,
			'palladiumPriceGram'  => $palladium,
			'goldPriceOunce'      => $gold_oz,
			'silverPriceOunce'    => $silver_oz,
			'platinumPriceOunce'  => $platinum_oz,
			'palladiumPriceOunce' => $palladium_oz,
			'timeToUpdate'        => $next_update,
			'currentDate'         => $current_date_time,
		);

		// Set a transient with the metal JSON data to help avoid Ajax requests upon each page load.
		set_transient( 'metal_price_json', wp_json_encode( $data ), $this->update_interval * MINUTE_IN_SECONDS );

		// Send response and exit.
		wp_send_json( $data );
	}
}
new WC_Metals_Price_Display();
