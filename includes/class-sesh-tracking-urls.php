<?php
/**
 * Tracking URLs helper class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tracking URLs class.
 *
 * Generates tracking URLs for Speedy and Econt carriers.
 */
class SESH_Tracking_URLs {

	/**
	 * Speedy tracking URL template.
	 *
	 * @var string
	 */
	const SPEEDY_TRACK_URL = 'https://www.speedy.bg/bg/track-shipment?shipmentNumber=';

	/**
	 * Econt tracking URL template.
	 *
	 * @var string
	 */
	const ECONT_TRACK_URL = 'https://www.econt.com/services/track-shipment/?shipmentNumber=';

	/**
	 * Get tracking URL for a carrier and tracking number.
	 *
	 * @param string $carrier        Carrier name (speedy or econt).
	 * @param string $tracking_number Tracking number.
	 * @return string Tracking URL or empty string if carrier is invalid.
	 */
	public static function get_tracking_url( $carrier, $tracking_number ) {
		if ( empty( $carrier ) || empty( $tracking_number ) ) {
			return '';
		}

		$carrier = strtolower( $carrier );

		switch ( $carrier ) {
			case 'speedy':
				return self::SPEEDY_TRACK_URL . urlencode( $tracking_number );

			case 'econt':
				return self::ECONT_TRACK_URL . urlencode( $tracking_number );

			default:
				return '';
		}
	}

	/**
	 * Get carrier logo URL.
	 *
	 * @param string $carrier Carrier name (speedy or econt).
	 * @return string Logo URL or empty string.
	 */
	public static function get_carrier_logo_url( $carrier ) {
		if ( empty( $carrier ) ) {
			return '';
		}

		$carrier = strtolower( $carrier );

		switch ( $carrier ) {
			case 'speedy':
				return SESH_PLUGIN_URL . 'assets/images/speedy-logo.png';

			case 'econt':
				return SESH_PLUGIN_URL . 'assets/images/econt-logo.png';

			default:
				return '';
		}
	}

	/**
	 * Get carrier display name.
	 *
	 * @param string $carrier Carrier name (speedy or econt).
	 * @return string Display name.
	 */
	public static function get_carrier_name( $carrier ) {
		if ( empty( $carrier ) ) {
			return '';
		}

		$carrier = strtolower( $carrier );

		switch ( $carrier ) {
			case 'speedy':
				return __( 'Speedy', 'speedy_econt_shipping' );

			case 'econt':
				return __( 'Econt', 'speedy_econt_shipping' );

			default:
				return ucfirst( $carrier );
		}
	}
}
