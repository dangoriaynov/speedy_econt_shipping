<?php
/**
 * Database handler class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Database class.
 *
 * Handles all database operations including table creation,
 * data queries, and migration.
 */
class SESH_Database {

	/**
	 * Database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '2.0.0';

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table names.
	 *
	 * @var array
	 */
	private $tables;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		$this->tables = array(
			'speedy_sites'   => $wpdb->prefix . 'speedy_sites',
			'speedy_offices' => $wpdb->prefix . 'speedy_offices',
			'econt_sites'    => $wpdb->prefix . 'econt_sites',
			'econt_offices'  => $wpdb->prefix . 'econt_offices',
			'api_cache'      => $wpdb->prefix . 'sesh_api_cache',
			'labels'         => $wpdb->prefix . 'sesh_shipping_labels',
		);
	}

	/**
	 * Get table name.
	 *
	 * @param string $table Table key.
	 * @return string
	 */
	public function get_table_name( $table ) {
		return isset( $this->tables[ $table ] ) ? $this->tables[ $table ] : '';
	}

	/**
	 * Create database tables.
	 */
	public function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		// Speedy sites table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->tables['speedy_sites']} (
			id int(15) NOT NULL,
			name text NOT NULL,
			region text NOT NULL,
			municipality text NOT NULL,
			post_code varchar(10) DEFAULT '',
			lat decimal(10,8) DEFAULT NULL,
			lng decimal(11,8) DEFAULT NULL,
			is_prod tinyint(1) DEFAULT 0,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_region (region(100)),
			KEY idx_is_prod (is_prod)
		) $charset_collate;";
		dbDelta( $sql );

		// Speedy offices table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->tables['speedy_offices']} (
			id int(15) NOT NULL,
			name text NOT NULL,
			city text NOT NULL,
			address text NOT NULL,
			lat decimal(10,8) DEFAULT NULL,
			lng decimal(11,8) DEFAULT NULL,
			working_hours text DEFAULT NULL,
			phone varchar(50) DEFAULT '',
			is_open tinyint(1) DEFAULT 1,
			is_prod tinyint(1) DEFAULT 0,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_city (city(100)),
			KEY idx_is_prod (is_prod)
		) $charset_collate;";
		dbDelta( $sql );

		// Econt sites table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->tables['econt_sites']} (
			id int(15) NOT NULL,
			name text NOT NULL,
			region text NOT NULL,
			municipality text NOT NULL,
			post_code varchar(10) DEFAULT '',
			lat decimal(10,8) DEFAULT NULL,
			lng decimal(11,8) DEFAULT NULL,
			is_prod tinyint(1) DEFAULT 0,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_region (region(100)),
			KEY idx_is_prod (is_prod)
		) $charset_collate;";
		dbDelta( $sql );

		// Econt offices table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->tables['econt_offices']} (
			id int(15) NOT NULL,
			name text NOT NULL,
			city text NOT NULL,
			address text NOT NULL,
			lat decimal(10,8) DEFAULT NULL,
			lng decimal(11,8) DEFAULT NULL,
			working_hours text DEFAULT NULL,
			phone varchar(50) DEFAULT '',
			is_active tinyint(1) DEFAULT 1,
			partner_code varchar(20) DEFAULT '',
			is_prod tinyint(1) DEFAULT 0,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_city (city(100)),
			KEY idx_is_prod (is_prod)
		) $charset_collate;";
		dbDelta( $sql );

		// API cache table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->tables['api_cache']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			cache_key varchar(255) NOT NULL,
			cache_value longtext NOT NULL,
			carrier varchar(20) NOT NULL,
			request_type varchar(50) NOT NULL,
			expires_at datetime NOT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_cache_key (cache_key),
			KEY idx_expires (expires_at),
			KEY idx_carrier (carrier)
		) $charset_collate;";
		dbDelta( $sql );

		// Shipping labels table.
		$sql = "CREATE TABLE IF NOT EXISTS {$this->tables['labels']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id bigint(20) UNSIGNED NOT NULL,
			carrier varchar(20) NOT NULL,
			tracking_number varchar(100) DEFAULT '',
			label_data longtext DEFAULT NULL,
			label_format varchar(10) DEFAULT 'pdf',
			status varchar(20) DEFAULT 'pending',
			api_response text DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_order (order_id),
			KEY idx_tracking (tracking_number),
			KEY idx_carrier (carrier),
			KEY idx_status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// Update version.
		update_option( 'sesh_db_version', self::DB_VERSION );
	}

	/**
	 * Drop all tables.
	 */
	public function drop_tables() {
		foreach ( $this->tables as $table ) {
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		delete_option( 'sesh_db_version' );
	}

	/**
	 * Get sites for a carrier.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @param string $order_by Order by column.
	 * @return array
	 */
	public function get_sites( $carrier, $order_by = 'name' ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return array();
		}

		$allowed_order = array( 'name', 'region', 'id' );
		$order_by      = in_array( $order_by, $allowed_order, true ) ? $order_by : 'name';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_prod = 1 ORDER BY {$order_by}"
		);
	}

	/**
	 * Get offices for a carrier.
	 *
	 * @param string $carrier  Carrier (speedy or econt).
	 * @param string $order_by Order by column.
	 * @return array
	 */
	public function get_offices( $carrier, $order_by = 'name' ) {
		$table = $this->get_table_name( $carrier . '_offices' );
		if ( empty( $table ) ) {
			return array();
		}

		$allowed_order = array( 'name', 'city', 'id' );
		$order_by      = in_array( $order_by, $allowed_order, true ) ? $order_by : 'name';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_prod = 1 ORDER BY {$order_by}"
		);
	}

	/**
	 * Get offices for a city.
	 *
	 * @param string $carrier   Carrier (speedy or econt).
	 * @param string $city_name City name.
	 * @return array
	 */
	public function get_offices_by_city( $carrier, $city_name ) {
		$table = $this->get_table_name( $carrier . '_offices' );
		if ( empty( $table ) ) {
			return array();
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_prod = 1 AND city = %s ORDER BY name", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$city_name
			)
		);
	}

	/**
	 * Get regions for a carrier.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return array
	 */
	public function get_regions( $carrier ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $this->wpdb->get_col(
			"SELECT DISTINCT region FROM {$table} WHERE is_prod = 1 ORDER BY region"
		);

		$regions = array();
		foreach ( $results as $region ) {
			$regions[ $region ] = $region;
		}

		return $regions;
	}

	/**
	 * Get cities for a region.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @param string $region  Region name.
	 * @return array
	 */
	public function get_cities_by_region( $carrier, $region ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return array();
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_prod = 1 AND region = %s ORDER BY name", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$region
			)
		);
	}

	/**
	 * Insert site.
	 *
	 * @param string $carrier Carrier.
	 * @param array  $data    Site data.
	 * @return int|false
	 */
	public function insert_site( $carrier, $data ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return false;
		}

		$result = $this->wpdb->replace(
			$table,
			array(
				'id'           => $data['id'],
				'name'         => $data['name'],
				'region'       => $data['region'],
				'municipality' => isset( $data['municipality'] ) ? $data['municipality'] : '',
				'post_code'    => isset( $data['post_code'] ) ? $data['post_code'] : '',
				'lat'          => isset( $data['lat'] ) ? $data['lat'] : null,
				'lng'          => isset( $data['lng'] ) ? $data['lng'] : null,
				'is_prod'      => isset( $data['is_prod'] ) ? $data['is_prod'] : 0,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%d' )
		);

		return false !== $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Insert office.
	 *
	 * @param string $carrier Carrier.
	 * @param array  $data    Office data.
	 * @return int|false
	 */
	public function insert_office( $carrier, $data ) {
		$table = $this->get_table_name( $carrier . '_offices' );
		if ( empty( $table ) ) {
			return false;
		}

		$result = $this->wpdb->replace(
			$table,
			array(
				'id'            => $data['id'],
				'name'          => $data['name'],
				'city'          => $data['city'],
				'address'       => $data['address'],
				'lat'           => isset( $data['lat'] ) ? $data['lat'] : null,
				'lng'           => isset( $data['lng'] ) ? $data['lng'] : null,
				'working_hours' => isset( $data['working_hours'] ) ? $data['working_hours'] : null,
				'phone'         => isset( $data['phone'] ) ? $data['phone'] : '',
				'is_prod'       => isset( $data['is_prod'] ) ? $data['is_prod'] : 0,
			),
			array( '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d' )
		);

		return false !== $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Truncate carrier tables.
	 *
	 * @param string $carrier Carrier.
	 * @param bool   $is_prod Whether to delete prod data.
	 */
	public function truncate_carrier_tables( $carrier, $is_prod = false ) {
		$sites_table   = $this->get_table_name( $carrier . '_sites' );
		$offices_table = $this->get_table_name( $carrier . '_offices' );

		$is_prod_value = $is_prod ? 1 : 0;

		if ( $sites_table ) {
			$this->wpdb->delete( $sites_table, array( 'is_prod' => $is_prod_value ), array( '%d' ) );
		}

		if ( $offices_table ) {
			$this->wpdb->delete( $offices_table, array( 'is_prod' => $is_prod_value ), array( '%d' ) );
		}
	}

	/**
	 * Mark data as production.
	 *
	 * @param string $carrier Carrier.
	 */
	public function mark_data_as_prod( $carrier ) {
		$sites_table   = $this->get_table_name( $carrier . '_sites' );
		$offices_table = $this->get_table_name( $carrier . '_offices' );

		if ( $sites_table ) {
			$this->wpdb->update( $sites_table, array( 'is_prod' => 1 ), array( 'is_prod' => 0 ) );
		}

		if ( $offices_table ) {
			$this->wpdb->update( $offices_table, array( 'is_prod' => 1 ), array( 'is_prod' => 0 ) );
		}
	}

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false
	 */
	public function get_cache( $key ) {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT cache_value FROM {$this->tables['api_cache']} WHERE cache_key = %s AND expires_at > NOW()",
				$key
			)
		);

		if ( ! $result ) {
			return false;
		}

		return maybe_unserialize( $result->cache_value );
	}

	/**
	 * Set cached value.
	 *
	 * @param string $key          Cache key.
	 * @param mixed  $value        Value to cache.
	 * @param string $carrier      Carrier.
	 * @param string $request_type Request type.
	 * @param int    $ttl          Time to live in seconds.
	 * @return bool
	 */
	public function set_cache( $key, $value, $carrier, $request_type, $ttl = 3600 ) {
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $ttl );

		$result = $this->wpdb->replace(
			$this->tables['api_cache'],
			array(
				'cache_key'    => $key,
				'cache_value'  => maybe_serialize( $value ),
				'carrier'      => $carrier,
				'request_type' => $request_type,
				'expires_at'   => $expires_at,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Delete expired cache entries.
	 *
	 * @return int Number of deleted entries.
	 */
	public function cleanup_cache() {
		return $this->wpdb->query(
			"DELETE FROM {$this->tables['api_cache']} WHERE expires_at < NOW()"
		);
	}

	/**
	 * Invalidate cache by key pattern.
	 *
	 * @param string $pattern Key pattern (supports % wildcard).
	 * @return int Number of deleted entries.
	 */
	public function invalidate_cache_by_pattern( $pattern ) {
		return $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->tables['api_cache']} WHERE cache_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$pattern
			)
		);
	}

	/**
	 * Invalidate cache by carrier.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return int Number of deleted entries.
	 */
	public function invalidate_cache_by_carrier( $carrier ) {
		return $this->wpdb->delete(
			$this->tables['api_cache'],
			array( 'carrier' => $carrier ),
			array( '%s' )
		);
	}

	/**
	 * Invalidate cache by request type.
	 *
	 * @param string $request_type Request type.
	 * @return int Number of deleted entries.
	 */
	public function invalidate_cache_by_type( $request_type ) {
		return $this->wpdb->delete(
			$this->tables['api_cache'],
			array( 'request_type' => $request_type ),
			array( '%s' )
		);
	}

	/**
	 * Invalidate all cache.
	 *
	 * @return int Number of deleted entries.
	 */
	public function invalidate_all_cache() {
		return $this->wpdb->query( "TRUNCATE TABLE {$this->tables['api_cache']}" );
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array
	 */
	public function get_cache_stats() {
		$total = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->tables['api_cache']}"
		);

		$expired = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->tables['api_cache']} WHERE expires_at < NOW()"
		);

		$by_carrier = $this->wpdb->get_results(
			"SELECT carrier, COUNT(*) as count FROM {$this->tables['api_cache']} GROUP BY carrier",
			ARRAY_A
		);

		return array(
			'total'      => (int) $total,
			'expired'    => (int) $expired,
			'active'     => (int) $total - (int) $expired,
			'by_carrier' => $by_carrier,
		);
	}

	// =========================================================================
	// Shipping Labels Methods
	// =========================================================================

	/**
	 * Insert a shipping label.
	 *
	 * @param array $data Label data.
	 * @return int|false Insert ID or false on failure.
	 */
	public function insert_label( $data ) {
		$defaults = array(
			'order_id'        => 0,
			'carrier'         => '',
			'tracking_number' => '',
			'label_data'      => null,
			'label_format'    => 'pdf',
			'status'          => 'pending',
			'api_response'    => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $this->wpdb->insert(
			$this->tables['labels'],
			array(
				'order_id'        => $data['order_id'],
				'carrier'         => $data['carrier'],
				'tracking_number' => $data['tracking_number'],
				'label_data'      => $data['label_data'],
				'label_format'    => $data['label_format'],
				'status'          => $data['status'],
				'api_response'    => is_array( $data['api_response'] ) ? wp_json_encode( $data['api_response'] ) : $data['api_response'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get label by ID.
	 *
	 * @param int $label_id Label ID.
	 * @return object|null
	 */
	public function get_label( $label_id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tables['labels']} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$label_id
			)
		);
	}

	/**
	 * Get labels for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function get_labels_by_order( $order_id ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tables['labels']} WHERE order_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);
	}

	/**
	 * Get label by tracking number.
	 *
	 * @param string $tracking_number Tracking number.
	 * @return object|null
	 */
	public function get_label_by_tracking( $tracking_number ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tables['labels']} WHERE tracking_number = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$tracking_number
			)
		);
	}

	/**
	 * Update label status.
	 *
	 * @param int    $label_id Label ID.
	 * @param string $status   New status (pending, generated, printed, cancelled).
	 * @return bool
	 */
	public function update_label_status( $label_id, $status ) {
		$allowed_statuses = array( 'pending', 'generated', 'printed', 'cancelled' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		return false !== $this->wpdb->update(
			$this->tables['labels'],
			array( 'status' => $status ),
			array( 'id' => $label_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update label data.
	 *
	 * @param int   $label_id Label ID.
	 * @param array $data     Data to update.
	 * @return bool
	 */
	public function update_label( $label_id, $data ) {
		$allowed_fields = array(
			'tracking_number',
			'label_data',
			'label_format',
			'status',
			'api_response',
		);

		$update_data   = array();
		$update_format = array();

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$value = $data[ $field ];

				if ( 'api_response' === $field && is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}

				$update_data[ $field ] = $value;
				$update_format[]       = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		return false !== $this->wpdb->update(
			$this->tables['labels'],
			$update_data,
			array( 'id' => $label_id ),
			$update_format,
			array( '%d' )
		);
	}

	/**
	 * Delete label.
	 *
	 * @param int $label_id Label ID.
	 * @return bool
	 */
	public function delete_label( $label_id ) {
		return false !== $this->wpdb->delete(
			$this->tables['labels'],
			array( 'id' => $label_id ),
			array( '%d' )
		);
	}

	/**
	 * Get labels by status.
	 *
	 * @param string $status Status filter.
	 * @param int    $limit  Limit results.
	 * @return array
	 */
	public function get_labels_by_status( $status, $limit = 100 ) {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->tables['labels']} WHERE status = %s ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				$limit
			)
		);
	}

	// =========================================================================
	// Additional Query Methods
	// =========================================================================

	/**
	 * Get site by ID.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @param int    $site_id Site ID.
	 * @return object|null
	 */
	public function get_site_by_id( $carrier, $site_id ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return null;
		}

		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND is_prod = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$site_id
			)
		);
	}

	/**
	 * Get office by ID.
	 *
	 * @param string $carrier   Carrier (speedy or econt).
	 * @param int    $office_id Office ID.
	 * @return object|null
	 */
	public function get_office_by_id( $carrier, $office_id ) {
		$table = $this->get_table_name( $carrier . '_offices' );
		if ( empty( $table ) ) {
			return null;
		}

		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND is_prod = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$office_id
			)
		);
	}

	/**
	 * Search sites by name.
	 *
	 * @param string $carrier     Carrier (speedy or econt).
	 * @param string $search_term Search term.
	 * @param int    $limit       Limit results.
	 * @return array
	 */
	public function search_sites( $carrier, $search_term, $limit = 20 ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return array();
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_prod = 1 AND name LIKE %s ORDER BY name LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $this->wpdb->esc_like( $search_term ) . '%',
				$limit
			)
		);
	}

	/**
	 * Search cities (sites) by name for autocomplete.
	 *
	 * Prioritizes exact matches and starts-with matches.
	 *
	 * @param string $carrier     Carrier (speedy or econt).
	 * @param string $search_term Search term.
	 * @param int    $limit       Limit results.
	 * @return array
	 */
	public function search_cities( $carrier, $search_term, $limit = 20 ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return array();
		}

		$like_term        = $this->wpdb->esc_like( $search_term );
		$starts_with_term = $like_term . '%';
		$contains_term    = '%' . $like_term . '%';

		// Use CASE to prioritize: exact match first, then starts-with, then contains.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT *,
					CASE
						WHEN name = %s THEN 1
						WHEN name LIKE %s THEN 2
						ELSE 3
					END AS match_priority
				FROM {$table}
				WHERE is_prod = 1 AND name LIKE %s
				ORDER BY match_priority ASC, name ASC
				LIMIT %d",
				$search_term,
				$starts_with_term,
				$contains_term,
				$limit
			)
		);
	}

	/**
	 * Get offices by city ID.
	 *
	 * Joins with sites table to get offices for a specific city ID.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @param int    $city_id City ID from sites table.
	 * @return array
	 */
	public function get_offices_by_city_id( $carrier, $city_id ) {
		$offices_table = $this->get_table_name( $carrier . '_offices' );
		$sites_table   = $this->get_table_name( $carrier . '_sites' );

		if ( empty( $offices_table ) || empty( $sites_table ) ) {
			return array();
		}

		// First get the city name from the sites table.
		$city = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT name FROM {$sites_table} WHERE id = %d AND is_prod = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$city_id
			)
		);

		if ( ! $city ) {
			return array();
		}

		// Get offices for that city name.
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$offices_table} WHERE is_prod = 1 AND city = %s ORDER BY name", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$city->name
			)
		);
	}

	/**
	 * Search offices by name or address.
	 *
	 * @param string $carrier     Carrier (speedy or econt).
	 * @param string $search_term Search term.
	 * @param int    $limit       Limit results.
	 * @return array
	 */
	public function search_offices( $carrier, $search_term, $limit = 20 ) {
		$table = $this->get_table_name( $carrier . '_offices' );
		if ( empty( $table ) ) {
			return array();
		}

		$like_term = '%' . $this->wpdb->esc_like( $search_term ) . '%';

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE is_prod = 1 AND (name LIKE %s OR address LIKE %s OR city LIKE %s) ORDER BY name LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$like_term,
				$like_term,
				$like_term,
				$limit
			)
		);
	}

	/**
	 * Get count of sites/offices.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @param string $type    Type (sites or offices).
	 * @return int
	 */
	public function get_count( $carrier, $type ) {
		$table = $this->get_table_name( $carrier . '_' . $type );
		if ( empty( $table ) ) {
			return 0;
		}

		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE is_prod = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Get last update time for carrier data.
	 *
	 * @param string $carrier Carrier (speedy or econt).
	 * @return string|null
	 */
	public function get_last_update_time( $carrier ) {
		$table = $this->get_table_name( $carrier . '_sites' );
		if ( empty( $table ) ) {
			return null;
		}

		return $this->wpdb->get_var(
			"SELECT MAX(updated_at) FROM {$table} WHERE is_prod = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Check if tables need data refresh.
	 *
	 * @param string $carrier Carrier.
	 * @param int    $max_age Maximum age in seconds (default 24 hours).
	 * @return bool
	 */
	public function needs_refresh( $carrier, $max_age = 86400 ) {
		$last_update = $this->get_last_update_time( $carrier );

		if ( ! $last_update ) {
			return true;
		}

		$last_update_time = strtotime( $last_update );
		return ( time() - $last_update_time ) > $max_age;
	}
}
