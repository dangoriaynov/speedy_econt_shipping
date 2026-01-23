<?php
/**
 * Database migration class.
 *
 * @package Speedy_Econt_Shipping
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Database Migrator class.
 *
 * Handles version-based database migrations with rollback support.
 */
class SESH_DB_Migrator {

	/**
	 * Current database version.
	 *
	 * @var string
	 */
	const CURRENT_VERSION = '2.0.0';

	/**
	 * Option name for DB version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'sesh_db_version';

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Database handler instance.
	 *
	 * @var SESH_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param SESH_Database $database Database handler.
	 */
	public function __construct( $database = null ) {
		global $wpdb;
		$this->wpdb     = $wpdb;
		$this->database = $database;
	}

	/**
	 * Run migrations if needed.
	 *
	 * @return bool True if migrations were run.
	 */
	public function maybe_migrate() {
		$installed_version = get_option( self::VERSION_OPTION, '0.0.0' );

		// No migration needed if already at current version.
		if ( version_compare( $installed_version, self::CURRENT_VERSION, '>=' ) ) {
			return false;
		}

		// Run migrations in order.
		$migrations_run = false;

		if ( version_compare( $installed_version, '1.3.0', '<' ) ) {
			$this->migrate_to_1_3_0();
			$migrations_run = true;
		}

		if ( version_compare( $installed_version, '2.0.0', '<' ) ) {
			$this->migrate_to_2_0_0();
			$migrations_run = true;
		}

		// Update version.
		update_option( self::VERSION_OPTION, self::CURRENT_VERSION );

		/**
		 * Fires after database migrations are complete.
		 *
		 * @since 2.0.0
		 * @param string $from_version Previous version.
		 * @param string $to_version   New version.
		 */
		do_action( 'sesh_db_migrated', $installed_version, self::CURRENT_VERSION );

		return $migrations_run;
	}

	/**
	 * Migrate to version 1.3.0.
	 *
	 * This handles legacy installations that may not have proper primary keys.
	 */
	private function migrate_to_1_3_0() {
		$tables = array(
			$this->wpdb->prefix . 'speedy_sites',
			$this->wpdb->prefix . 'speedy_offices',
			$this->wpdb->prefix . 'econt_sites',
			$this->wpdb->prefix . 'econt_offices',
		);

		foreach ( $tables as $table ) {
			// Check if table exists.
			$table_exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table
				)
			);

			if ( ! $table_exists ) {
				continue;
			}

			// Check if primary key exists.
			$pk_exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
					WHERE CONSTRAINT_TYPE = 'PRIMARY KEY'
					AND TABLE_SCHEMA = %s
					AND TABLE_NAME = %s",
					DB_NAME,
					$table
				)
			);

			if ( ! $pk_exists ) {
				// Add primary key to id column.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->wpdb->query( "ALTER TABLE {$table} ADD PRIMARY KEY (id)" );
			}
		}
	}

	/**
	 * Migrate to version 2.0.0.
	 *
	 * Adds new columns to existing tables and creates new tables.
	 */
	private function migrate_to_2_0_0() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		// Add new columns to speedy_sites if they don't exist.
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_sites',
			'post_code',
			'VARCHAR(10) DEFAULT \'\''
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_sites',
			'lat',
			'DECIMAL(10,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_sites',
			'lng',
			'DECIMAL(11,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_sites',
			'updated_at',
			'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		);

		// Add new columns to speedy_offices.
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'lat',
			'DECIMAL(10,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'lng',
			'DECIMAL(11,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'working_hours',
			'TEXT DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'phone',
			'VARCHAR(50) DEFAULT \'\''
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'is_open',
			'TINYINT(1) DEFAULT 1'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'updated_at',
			'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		);

		// Add new columns to econt_sites.
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_sites',
			'post_code',
			'VARCHAR(10) DEFAULT \'\''
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_sites',
			'lat',
			'DECIMAL(10,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_sites',
			'lng',
			'DECIMAL(11,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_sites',
			'updated_at',
			'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		);

		// Add new columns to econt_offices.
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'lat',
			'DECIMAL(10,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'lng',
			'DECIMAL(11,8) DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'working_hours',
			'TEXT DEFAULT NULL'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'phone',
			'VARCHAR(50) DEFAULT \'\''
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'is_active',
			'TINYINT(1) DEFAULT 1'
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'partner_code',
			'VARCHAR(20) DEFAULT \'\''
		);
		$this->add_column_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'updated_at',
			'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		);

		// Add indexes if they don't exist.
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'speedy_sites',
			'idx_region',
			'region(100)'
		);
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'speedy_sites',
			'idx_is_prod',
			'is_prod'
		);
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'idx_city',
			'city(100)'
		);
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'speedy_offices',
			'idx_is_prod',
			'is_prod'
		);
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'econt_sites',
			'idx_region',
			'region(100)'
		);
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'econt_sites',
			'idx_is_prod',
			'is_prod'
		);
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'idx_city',
			'city(100)'
		);
		$this->add_index_if_not_exists(
			$this->wpdb->prefix . 'econt_offices',
			'idx_is_prod',
			'is_prod'
		);

		// Create new tables (api_cache and shipping_labels).
		$this->create_cache_table( $charset_collate );
		$this->create_labels_table( $charset_collate );
	}

	/**
	 * Add column if it doesn't exist.
	 *
	 * @param string $table      Table name.
	 * @param string $column     Column name.
	 * @param string $definition Column definition.
	 * @return bool
	 */
	private function add_column_if_not_exists( $table, $column, $definition ) {
		$column_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				$column
			)
		);

		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return false !== $this->wpdb->query(
				"ALTER TABLE {$table} ADD COLUMN {$column} {$definition}"
			);
		}

		return false;
	}

	/**
	 * Add index if it doesn't exist.
	 *
	 * @param string $table      Table name.
	 * @param string $index_name Index name.
	 * @param string $columns    Columns to index.
	 * @return bool
	 */
	private function add_index_if_not_exists( $table, $index_name, $columns ) {
		$index_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.STATISTICS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND INDEX_NAME = %s',
				DB_NAME,
				$table,
				$index_name
			)
		);

		if ( ! $index_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return false !== $this->wpdb->query(
				"ALTER TABLE {$table} ADD INDEX {$index_name} ({$columns})"
			);
		}

		return false;
	}

	/**
	 * Create API cache table.
	 *
	 * @param string $charset_collate Charset collation.
	 */
	private function create_cache_table( $charset_collate ) {
		$table = $this->wpdb->prefix . 'sesh_api_cache';

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
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
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create shipping labels table.
	 *
	 * @param string $charset_collate Charset collation.
	 */
	private function create_labels_table( $charset_collate ) {
		$table = $this->wpdb->prefix . 'sesh_shipping_labels';

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
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
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Get current database version.
	 *
	 * @return string
	 */
	public static function get_version() {
		return get_option( self::VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Check if database is up to date.
	 *
	 * @return bool
	 */
	public static function is_up_to_date() {
		return version_compare( self::get_version(), self::CURRENT_VERSION, '>=' );
	}

	/**
	 * Get migration status.
	 *
	 * @return array
	 */
	public function get_status() {
		$current_version = self::get_version();

		return array(
			'current_version'   => $current_version,
			'target_version'    => self::CURRENT_VERSION,
			'is_up_to_date'     => self::is_up_to_date(),
			'needs_migration'   => version_compare( $current_version, self::CURRENT_VERSION, '<' ),
			'tables_exist'      => $this->check_tables_exist(),
			'new_columns_exist' => $this->check_new_columns_exist(),
		);
	}

	/**
	 * Check if all tables exist.
	 *
	 * @return array
	 */
	private function check_tables_exist() {
		$tables = array(
			'speedy_sites'        => $this->wpdb->prefix . 'speedy_sites',
			'speedy_offices'      => $this->wpdb->prefix . 'speedy_offices',
			'econt_sites'         => $this->wpdb->prefix . 'econt_sites',
			'econt_offices'       => $this->wpdb->prefix . 'econt_offices',
			'sesh_api_cache'      => $this->wpdb->prefix . 'sesh_api_cache',
			'sesh_shipping_labels' => $this->wpdb->prefix . 'sesh_shipping_labels',
		);

		$result = array();
		foreach ( $tables as $key => $table ) {
			$result[ $key ] = (bool) $this->wpdb->get_var(
				$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);
		}

		return $result;
	}

	/**
	 * Check if new columns exist in sites/offices tables.
	 *
	 * @return bool
	 */
	private function check_new_columns_exist() {
		$table = $this->wpdb->prefix . 'speedy_sites';

		$lat_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				'lat'
			)
		);

		return (bool) $lat_exists;
	}
}
