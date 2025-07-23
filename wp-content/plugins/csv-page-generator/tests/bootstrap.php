<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for the CSV Page Generator plugin.
 * This file is loaded before any tests are run.
 *
 * @package ReasonDigital\CSVPageGenerator\Tests
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/' );
}

// Define plugin constants for testing
if ( ! defined( 'CSV_PAGE_GENERATOR_PLUGIN_DIR' ) ) {
	define( 'CSV_PAGE_GENERATOR_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'CSV_PAGE_GENERATOR_PLUGIN_URL' ) ) {
	define( 'CSV_PAGE_GENERATOR_PLUGIN_URL', 'http://localhost/wp-content/plugins/csv-page-generator/' );
}
if ( ! defined( 'CSV_PAGE_GENERATOR_VERSION' ) ) {
	define( 'CSV_PAGE_GENERATOR_VERSION', '1.0.0' );
}

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Initialize Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Mock WordPress functions commonly used in the plugin
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new Exception( $message );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return strip_tags( $data, '<p><br><strong><em><a><ul><ol><li><h1><h2><h3><h4><h5><h6>' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return true; // For testing purposes, assume user has all capabilities
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return true; // For testing purposes, assume nonces are valid
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'test_nonce_' . md5( $action );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		static $options = array();
		return isset( $options[ $option ] ) ? $options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		static $options = array();
		$options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		static $options = array();
		unset( $options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'path'    => '/tmp/uploads',
			'url'     => 'http://localhost/wp-content/uploads',
			'subdir'  => '',
			'basedir' => '/tmp/uploads',
			'baseurl' => 'http://localhost/wp-content/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return wp_mkdir_p_real( $target );
	}
}

if ( ! function_exists( 'wp_mkdir_p_real' ) ) {
	function wp_mkdir_p_real( $target ) {
		if ( ! is_dir( $target ) ) {
			return mkdir( $target, 0755, true );
		}
		return true;
	}
}

if ( ! function_exists( 'size_format' ) ) {
	function size_format( $bytes, $decimals = 0 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		
		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}
		
		return round( $bytes, $decimals ) . ' ' . $units[ $i ];
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://localhost/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

// Mock global $wpdb object
global $wpdb;
if ( ! isset( $wpdb ) ) {
	$wpdb = new stdClass();
	$wpdb->prefix = 'wp_';
	$wpdb->prepare = function( $query, ...$args ) {
		return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
	};
	$wpdb->get_results = function( $query ) {
		return array();
	};
	$wpdb->get_var = function( $query ) {
		return null;
	};
	$wpdb->query = function( $query ) {
		return true;
	};
	$wpdb->insert = function( $table, $data, $format = null ) {
		return true;
	};
	$wpdb->update = function( $table, $data, $where, $format = null, $where_format = null ) {
		return true;
	};
	$wpdb->delete = function( $table, $where, $where_format = null ) {
		return true;
	};
}

// Set up test environment
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

// Clean up after tests
register_shutdown_function( function() {
	\Brain\Monkey\tearDown();
} );

// Note: Plugin main file not loaded to avoid WordPress dependency issues in unit tests
// For integration tests, consider loading WordPress test environment
