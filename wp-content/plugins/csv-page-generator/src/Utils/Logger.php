<?php
/**
 * Logger Utility Class
 *
 * @package ReasonDigital\CSVPageGenerator\Utils
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Utils;

/**
 * Logger class for handling plugin logging functionality.
 *
 * Provides structured logging with different levels and automatic
 * log rotation to prevent excessive disk usage.
 */
class Logger {

	/**
	 * Log levels.
	 */
	const EMERGENCY = 'emergency';
	const ALERT     = 'alert';
	const CRITICAL  = 'critical';
	const ERROR     = 'error';
	const WARNING   = 'warning';
	const NOTICE    = 'notice';
	const INFO      = 'info';
	const DEBUG     = 'debug';

	/**
	 * Log level priorities for filtering.
	 *
	 * @var array
	 */
	private static $level_priorities = array(
		self::EMERGENCY => 0,
		self::ALERT     => 1,
		self::CRITICAL  => 2,
		self::ERROR     => 3,
		self::WARNING   => 4,
		self::NOTICE    => 5,
		self::INFO      => 6,
		self::DEBUG     => 7,
	);

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Log file path.
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'csv_page_generator_settings', array() );
		$this->setup_log_file();
	}

	/**
	 * Set up the log file path.
	 */
	private function setup_log_file() {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/csv-imports/logs';

		// Ensure log directory exists
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$this->log_file = $log_dir . '/csv-page-generator-' . date( 'Y-m-d' ) . '.log';
	}

	/**
	 * Log an emergency message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function emergency( $message, array $context = array() ) {
		$this->log( self::EMERGENCY, $message, $context );
	}

	/**
	 * Log an alert message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function alert( $message, array $context = array() ) {
		$this->log( self::ALERT, $message, $context );
	}

	/**
	 * Log a critical message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function critical( $message, array $context = array() ) {
		$this->log( self::CRITICAL, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function error( $message, array $context = array() ) {
		$this->log( self::ERROR, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function warning( $message, array $context = array() ) {
		$this->log( self::WARNING, $message, $context );
	}

	/**
	 * Log a notice message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function notice( $message, array $context = array() ) {
		$this->log( self::NOTICE, $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function info( $message, array $context = array() ) {
		$this->log( self::INFO, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function debug( $message, array $context = array() ) {
		$this->log( self::DEBUG, $message, $context );
	}

	/**
	 * Log a message with the given level.
	 *
	 * @param string $level   The log level.
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 */
	public function log( $level, $message, array $context = array() ) {
		// Check if logging is enabled
		if ( empty( $this->settings['enable_logging'] ) ) {
			return;
		}

		// Check if this level should be logged
		if ( ! $this->should_log_level( $level ) ) {
			return;
		}

		// Format the log entry
		$log_entry = $this->format_log_entry( $level, $message, $context );

		// Write to file
		$this->write_to_file( $log_entry );

		// Also log to WordPress debug.log if WP_DEBUG_LOG is enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[CSV Page Generator] ' . $log_entry );
		}
	}

	/**
	 * Check if the given level should be logged based on settings.
	 *
	 * @param string $level The log level to check.
	 * @return bool True if the level should be logged.
	 */
	private function should_log_level( $level ) {
		$configured_level = $this->settings['log_level'] ?? self::INFO;

		if ( ! isset( self::$level_priorities[ $level ] ) || ! isset( self::$level_priorities[ $configured_level ] ) ) {
			return true; // Log if we can't determine the level
		}

		return self::$level_priorities[ $level ] <= self::$level_priorities[ $configured_level ];
	}

	/**
	 * Format a log entry.
	 *
	 * @param string $level   The log level.
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 * @return string The formatted log entry.
	 */
	private function format_log_entry( $level, $message, array $context = array() ) {
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$level     = strtoupper( $level );

		// Replace placeholders in message with context values
		$message = $this->interpolate( $message, $context );

		$log_entry = "[{$timestamp}] {$level}: {$message}";

		// Add context data if present
		if ( ! empty( $context ) ) {
			$context_string = wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
			$log_entry     .= ' Context: ' . $context_string;
		}

		// Add memory usage and execution time for debugging
		if ( $level === 'DEBUG' ) {
			$memory_usage = memory_get_usage( true );
			$memory_peak  = memory_get_peak_usage( true );
			$log_entry   .= sprintf( ' [Memory: %s / Peak: %s]', size_format( $memory_usage ), size_format( $memory_peak ) );
		}

		return $log_entry;
	}

	/**
	 * Interpolate context values into the message placeholders.
	 *
	 * @param string $message The message with placeholders.
	 * @param array  $context The context data.
	 * @return string The interpolated message.
	 */
	private function interpolate( $message, array $context = array() ) {
		// Build a replacement array with braces around the context keys
		$replace = array();
		foreach ( $context as $key => $val ) {
			// Check that the value can be cast to string
			if ( ! is_array( $val ) && ( ! is_object( $val ) || method_exists( $val, '__toString' ) ) ) {
				$replace[ '{' . $key . '}' ] = $val;
			}
		}

		// Interpolate replacement values into the message and return
		return strtr( $message, $replace );
	}

	/**
	 * Write log entry to file.
	 *
	 * @param string $log_entry The formatted log entry.
	 */
	private function write_to_file( $log_entry ) {
		// Ensure log file is writable
		if ( ! is_writable( dirname( $this->log_file ) ) ) {
			return;
		}

		// Rotate log file if it's too large
		$this->rotate_log_if_needed();

		// Write to file
		file_put_contents( $this->log_file, $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Rotate log file if it exceeds size limit.
	 */
	private function rotate_log_if_needed() {
		$max_size = 10 * 1024 * 1024; // 10MB

		if ( file_exists( $this->log_file ) && filesize( $this->log_file ) > $max_size ) {
			$backup_file = $this->log_file . '.backup';

			// Remove old backup if exists
			if ( file_exists( $backup_file ) ) {
				unlink( $backup_file );
			}

			// Move current log to backup
			rename( $this->log_file, $backup_file );
		}
	}

	/**
	 * Get recent log entries.
	 *
	 * @param int $lines Number of lines to retrieve.
	 * @return array Array of log entries.
	 */
	public function get_recent_logs( $lines = 100 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}

		$file_lines = file( $this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( ! $file_lines ) {
			return array();
		}

		return array_slice( $file_lines, -$lines );
	}

	/**
	 * Clear all log files.
	 */
	public function clear_logs() {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/csv-imports/logs';

		if ( is_dir( $log_dir ) ) {
			$files = glob( $log_dir . '/*.log*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}

		$this->info( 'Log files cleared by user.' );
	}

	/**
	 * Get log file size.
	 *
	 * @return int File size in bytes.
	 */
	public function get_log_size() {
		if ( ! file_exists( $this->log_file ) ) {
			return 0;
		}

		return filesize( $this->log_file );
	}
}
