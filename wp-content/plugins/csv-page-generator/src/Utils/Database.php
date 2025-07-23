<?php
/**
 * Database Utility Class
 *
 * @package ReasonDigital\CSVPageGenerator\Utils
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Utils;

/**
 * Database operations for CSV import tracking.
 *
 * Handles all database operations related to import tracking,
 * progress monitoring, and history management.
 */
class Database {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Import table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		global $wpdb;
		
		$this->logger = $logger;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'csv_page_generator_imports';
	}

	/**
	 * Create a new import record.
	 *
	 * @param array $data Import data.
	 * @return int Import ID.
	 * @throws \Exception If creation fails.
	 */
	public function create_import_record( array $data ) {
		$defaults = array(
			'user_id'           => get_current_user_id(),
			'filename'          => '',
			'original_filename' => '',
			'file_size'         => 0,
			'total_rows'        => 0,
			'processed_rows'    => 0,
			'successful_rows'   => 0,
			'failed_rows'       => 0,
			'status'            => 'pending',
			'error_log'         => '',
			'created_pages'     => '',
			'started_at'        => current_time( 'mysql' ),
			'completed_at'      => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $this->wpdb->insert(
			$this->table_name,
			$data,
			array(
				'%d', // user_id
				'%s', // filename
				'%s', // original_filename
				'%d', // file_size
				'%d', // total_rows
				'%d', // processed_rows
				'%d', // successful_rows
				'%d', // failed_rows
				'%s', // status
				'%s', // error_log
				'%s', // created_pages
				'%s', // started_at
				'%s', // completed_at
			)
		);

		if ( false === $result ) {
			throw new \Exception( 
				sprintf( 
					/* translators: %s: database error */
					__( 'Failed to create import record: %s', 'csv-page-generator' ), 
					$this->wpdb->last_error 
				) 
			);
		}

		$import_id = $this->wpdb->insert_id;

		$this->logger->info( 'Import record created', array(
			'import_id' => $import_id,
			'filename'  => $data['filename'],
		) );

		return $import_id;
	}

	/**
	 * Update an import record.
	 *
	 * @param int   $import_id Import ID.
	 * @param array $data Data to update.
	 * @return bool Success status.
	 */
	public function update_import_record( $import_id, array $data ) {
		$allowed_fields = array(
			'total_rows',
			'processed_rows',
			'successful_rows',
			'failed_rows',
			'status',
			'error_log',
			'created_pages',
			'completed_at',
		);

		// Filter data to only allowed fields
		$filtered_data = array_intersect_key( $data, array_flip( $allowed_fields ) );

		if ( empty( $filtered_data ) ) {
			return false;
		}

		// Prepare format array
		$format = array();
		foreach ( $filtered_data as $key => $value ) {
			switch ( $key ) {
				case 'total_rows':
				case 'processed_rows':
				case 'successful_rows':
				case 'failed_rows':
					$format[] = '%d';
					break;
				default:
					$format[] = '%s';
					break;
			}
		}

		$result = $this->wpdb->update(
			$this->table_name,
			$filtered_data,
			array( 'id' => $import_id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			$this->logger->error( 'Failed to update import record', array(
				'import_id' => $import_id,
				'error'     => $this->wpdb->last_error,
			) );
			return false;
		}

		return true;
	}

	/**
	 * Get an import record by ID.
	 *
	 * @param int $import_id Import ID.
	 * @return array|null Import record or null if not found.
	 */
	public function get_import_record( $import_id ) {
		$record = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$import_id
			),
			ARRAY_A
		);

		if ( $record ) {
			// Decode JSON fields
			$record['created_pages'] = json_decode( $record['created_pages'], true ) ?: array();
			$record['error_log'] = json_decode( $record['error_log'], true ) ?: array();
		}

		return $record;
	}

	/**
	 * Get import records with pagination and filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array Import records and pagination info.
	 */
	public function get_import_records( array $args = array() ) {
		$defaults = array(
			'user_id'    => null,
			'status'     => null,
			'limit'      => 20,
			'offset'     => 0,
			'order_by'   => 'started_at',
			'order'      => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause
		$where_conditions = array( '1=1' );
		$where_values = array();

		if ( ! is_null( $args['user_id'] ) ) {
			$where_conditions[] = 'user_id = %d';
			$where_values[] = $args['user_id'];
		}

		if ( ! is_null( $args['status'] ) ) {
			$where_conditions[] = 'status = %s';
			$where_values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Build ORDER BY clause
		$allowed_order_by = array( 'id', 'started_at', 'completed_at', 'status', 'filename' );
		$order_by = in_array( $args['order_by'], $allowed_order_by, true ) ? $args['order_by'] : 'started_at';
		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Get total count
		$count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
		if ( ! empty( $where_values ) ) {
			$count_query = $this->wpdb->prepare( $count_query, $where_values );
		}
		$total_records = $this->wpdb->get_var( $count_query );

		// Get records
		$query = "SELECT * FROM {$this->table_name} 
				  WHERE {$where_clause} 
				  ORDER BY {$order_by} {$order} 
				  LIMIT %d OFFSET %d";

		$query_values = array_merge( $where_values, array( $args['limit'], $args['offset'] ) );
		$records = $this->wpdb->get_results(
			$this->wpdb->prepare( $query, $query_values ),
			ARRAY_A
		);

		// Decode JSON fields for each record
		foreach ( $records as &$record ) {
			$record['created_pages'] = json_decode( $record['created_pages'], true ) ?: array();
			$record['error_log'] = json_decode( $record['error_log'], true ) ?: array();
		}

		return array(
			'records'       => $records,
			'total'         => (int) $total_records,
			'limit'         => $args['limit'],
			'offset'        => $args['offset'],
			'total_pages'   => ceil( $total_records / $args['limit'] ),
			'current_page'  => floor( $args['offset'] / $args['limit'] ) + 1,
		);
	}

	/**
	 * Delete an import record.
	 *
	 * @param int $import_id Import ID.
	 * @return bool Success status.
	 */
	public function delete_import_record( $import_id ) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => $import_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			$this->logger->error( 'Failed to delete import record', array(
				'import_id' => $import_id,
				'error'     => $this->wpdb->last_error,
			) );
			return false;
		}

		$this->logger->info( 'Import record deleted', array( 'import_id' => $import_id ) );
		return true;
	}

	/**
	 * Get import statistics.
	 *
	 * @param array $args Filter arguments.
	 * @return array Statistics.
	 */
	public function get_import_statistics( array $args = array() ) {
		$defaults = array(
			'user_id'    => null,
			'date_from'  => null,
			'date_to'    => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause
		$where_conditions = array( '1=1' );
		$where_values = array();

		if ( ! is_null( $args['user_id'] ) ) {
			$where_conditions[] = 'user_id = %d';
			$where_values[] = $args['user_id'];
		}

		if ( ! is_null( $args['date_from'] ) ) {
			$where_conditions[] = 'started_at >= %s';
			$where_values[] = $args['date_from'];
		}

		if ( ! is_null( $args['date_to'] ) ) {
			$where_conditions[] = 'started_at <= %s';
			$where_values[] = $args['date_to'];
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$query = "SELECT 
					COUNT(*) as total_imports,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_imports,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_imports,
					SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_imports,
					SUM(total_rows) as total_rows_processed,
					SUM(successful_rows) as total_pages_created,
					SUM(failed_rows) as total_failed_rows,
					AVG(file_size) as avg_file_size
				  FROM {$this->table_name} 
				  WHERE {$where_clause}";

		if ( ! empty( $where_values ) ) {
			$query = $this->wpdb->prepare( $query, $where_values );
		}

		$stats = $this->wpdb->get_row( $query, ARRAY_A );

		// Convert to integers and handle nulls
		foreach ( $stats as $key => $value ) {
			$stats[ $key ] = is_null( $value ) ? 0 : (int) $value;
		}

		return $stats;
	}

	/**
	 * Clean up old import records.
	 *
	 * @param int $days_old Number of days old to consider for cleanup.
	 * @return int Number of records deleted.
	 */
	public function cleanup_old_records( $days_old = 30 ) {
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table_name} 
				 WHERE started_at < %s 
				 AND status IN ('completed', 'failed', 'cancelled')",
				$cutoff_date
			)
		);

		if ( $deleted > 0 ) {
			$this->logger->info( 'Old import records cleaned up', array(
				'deleted_records' => $deleted,
				'cutoff_date'     => $cutoff_date,
			) );
		}

		return $deleted;
	}

	/**
	 * Get recent import activity.
	 *
	 * @param int $limit Number of recent records to get.
	 * @return array Recent import records.
	 */
	public function get_recent_activity( $limit = 10 ) {
		$records = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, filename, status, successful_rows, failed_rows, started_at, completed_at 
				 FROM {$this->table_name} 
				 ORDER BY started_at DESC 
				 LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $records;
	}

	/**
	 * Check if table exists.
	 *
	 * @return bool True if table exists.
	 */
	public function table_exists() {
		$table_name = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->table_name
			)
		);

		return $table_name === $this->table_name;
	}
}
