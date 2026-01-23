<?php
/**
 * Encryption utility class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encryption class.
 *
 * Handles encryption and decryption of sensitive data like API credentials.
 * Uses WordPress salts for key derivation.
 */
class SESH_Encryption {

	/**
	 * Encryption method.
	 *
	 * @var string
	 */
	const METHOD = 'aes-256-cbc';

	/**
	 * Get the encryption key.
	 *
	 * Uses WordPress AUTH_KEY and SECURE_AUTH_KEY salts.
	 *
	 * @return string
	 */
	private static function get_key() {
		$key = '';

		if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
			$key .= AUTH_KEY;
		}

		if ( defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ) {
			$key .= SECURE_AUTH_KEY;
		}

		// Fallback if salts not defined.
		if ( empty( $key ) ) {
			$key = 'sesh_default_key_' . md5( ABSPATH );
		}

		return hash( 'sha256', $key, true );
	}

	/**
	 * Encrypt a string.
	 *
	 * @param string $data Data to encrypt.
	 * @return string|false Encrypted data (base64 encoded) or false on failure.
	 */
	public static function encrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		// Check if OpenSSL is available.
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Return plain data with a marker if encryption not available.
			return 'plain:' . base64_encode( $data );
		}

		$key = self::get_key();
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::METHOD ) );

		$encrypted = openssl_encrypt( $data, self::METHOD, $key, 0, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		// Combine IV and encrypted data.
		return base64_encode( $iv . '::' . $encrypted );
	}

	/**
	 * Decrypt a string.
	 *
	 * @param string $data Encrypted data (base64 encoded).
	 * @return string|false Decrypted data or false on failure.
	 */
	public static function decrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		// Check for plain text marker (encryption not available).
		if ( 0 === strpos( $data, 'plain:' ) ) {
			return base64_decode( substr( $data, 6 ) );
		}

		// Check if OpenSSL is available.
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}

		$decoded = base64_decode( $data );
		if ( false === $decoded ) {
			// Data might be unencrypted (legacy).
			return $data;
		}

		$parts = explode( '::', $decoded, 2 );

		if ( count( $parts ) !== 2 ) {
			// Data might be unencrypted (legacy).
			return $data;
		}

		list( $iv, $encrypted ) = $parts;

		$key = self::get_key();

		$decrypted = openssl_decrypt( $encrypted, self::METHOD, $key, 0, $iv );

		return $decrypted;
	}

	/**
	 * Check if a value is encrypted.
	 *
	 * @param string $data Data to check.
	 * @return bool
	 */
	public static function is_encrypted( $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		// Check for plain text marker.
		if ( 0 === strpos( $data, 'plain:' ) ) {
			return true;
		}

		// Try to decode and check for our format.
		$decoded = base64_decode( $data, true );
		if ( false === $decoded ) {
			return false;
		}

		return false !== strpos( $decoded, '::' );
	}

	/**
	 * Mask a sensitive value for display.
	 *
	 * @param string $value     Value to mask.
	 * @param int    $show_chars Number of characters to show at start and end.
	 * @return string
	 */
	public static function mask( $value, $show_chars = 2 ) {
		if ( empty( $value ) ) {
			return '';
		}

		$length = strlen( $value );

		if ( $length <= $show_chars * 2 ) {
			return str_repeat( '*', $length );
		}

		$start = substr( $value, 0, $show_chars );
		$end   = substr( $value, -$show_chars );
		$mask  = str_repeat( '*', $length - ( $show_chars * 2 ) );

		return $start . $mask . $end;
	}
}
