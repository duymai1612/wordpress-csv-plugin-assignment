<?php
/**
 * Nonce Manager Class
 *
 * @package ReasonDigital\CSVPageGenerator\Security
 * @author  Reason Digital Developer
 * @license GPL-2.0-or-later
 * @link    https://github.com/reason-digital/wordpress-csv-plugin
 */

namespace ReasonDigital\CSVPageGenerator\Security;

/**
 * Manages WordPress nonces for CSRF protection.
 *
 * Provides a centralized way to create, verify, and manage nonces
 * throughout the plugin with consistent naming and security practices.
 */
class NonceManager {

	/**
	 * Nonce action prefix.
	 *
	 * @var string
	 */
	private $prefix = 'csv_page_generator_';

	/**
	 * Default nonce lifetime in seconds.
	 *
	 * @var int
	 */
	private $lifetime = DAY_IN_SECONDS;

	/**
	 * Create a nonce for a specific action.
	 *
	 * @param string $action The action for which to create the nonce.
	 * @param int    $user_id Optional. User ID for user-specific nonces.
	 * @return string The nonce value.
	 */
	public function create_nonce( $action, $user_id = null ) {
		$action = $this->get_prefixed_action( $action );

		if ( $user_id ) {
			$action .= '_user_' . $user_id;
		}

		return wp_create_nonce( $action );
	}

	/**
	 * Verify a nonce for a specific action.
	 *
	 * @param string $nonce The nonce to verify.
	 * @param string $action The action to verify against.
	 * @param int    $user_id Optional. User ID for user-specific nonces.
	 * @return bool|int False if invalid, 1 if valid and generated within 12 hours, 2 if valid and generated within 24 hours.
	 */
	public function verify_nonce( $nonce, $action, $user_id = null ) {
		$action = $this->get_prefixed_action( $action );

		if ( $user_id ) {
			$action .= '_user_' . $user_id;
		}

		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Create a nonce field for forms.
	 *
	 * @param string $action The action for which to create the nonce.
	 * @param string $name Optional. The name attribute for the nonce field.
	 * @param bool   $referer Optional. Whether to include a referer field.
	 * @param bool   $echo Optional. Whether to echo the field or return it.
	 * @return string The nonce field HTML if $echo is false.
	 */
	public function create_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) {
		$action = $this->get_prefixed_action( $action );
		return wp_nonce_field( $action, $name, $referer, $echo );
	}

	/**
	 * Verify a nonce from a form submission.
	 *
	 * @param string $action The action to verify against.
	 * @param string $name Optional. The name of the nonce field.
	 * @return bool|int False if invalid, 1 if valid and generated within 12 hours, 2 if valid and generated within 24 hours.
	 */
	public function verify_nonce_field( $action, $name = '_wpnonce' ) {
		$action = $this->get_prefixed_action( $action );

		if ( ! isset( $_POST[ $name ] ) ) {
			return false;
		}

		return wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ), $action );
	}

	/**
	 * Create a nonce URL.
	 *
	 * @param string $actionurl The URL to add the nonce to.
	 * @param string $action The action for which to create the nonce.
	 * @param string $name Optional. The name of the nonce parameter.
	 * @return string The URL with the nonce added.
	 */
	public function create_nonce_url( $actionurl, $action, $name = '_wpnonce' ) {
		$action = $this->get_prefixed_action( $action );
		return wp_nonce_url( $actionurl, $action, $name );
	}

	/**
	 * Verify a nonce from a URL parameter.
	 *
	 * @param string $action The action to verify against.
	 * @param string $name Optional. The name of the nonce parameter.
	 * @return bool|int False if invalid, 1 if valid and generated within 12 hours, 2 if valid and generated within 24 hours.
	 */
	public function verify_nonce_url( $action, $name = '_wpnonce' ) {
		$action = $this->get_prefixed_action( $action );

		if ( ! isset( $_GET[ $name ] ) ) {
			return false;
		}

		return wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ $name ] ) ), $action );
	}

	/**
	 * Create an admin referrer nonce field.
	 *
	 * @param string $action The action for which to create the nonce.
	 * @param bool   $echo Optional. Whether to echo the field or return it.
	 * @return string The nonce field HTML if $echo is false.
	 */
	public function create_admin_referer_field( $action, $echo = true ) {
		$action = $this->get_prefixed_action( $action );
		return wp_referer_field( $echo ) . wp_nonce_field( $action, '_wpnonce', false, $echo );
	}

	/**
	 * Verify an admin referrer and nonce.
	 *
	 * @param string $action The action to verify against.
	 * @param string $query_arg Optional. The query argument to check.
	 * @return bool|int False if invalid, 1 if valid and generated within 12 hours, 2 if valid and generated within 24 hours.
	 */
	public function verify_admin_referer( $action, $query_arg = '_wpnonce' ) {
		$action = $this->get_prefixed_action( $action );
		return check_admin_referer( $action, $query_arg );
	}

	/**
	 * Create a nonce for AJAX requests.
	 *
	 * @param string $action The action for which to create the nonce.
	 * @return string The nonce value.
	 */
	public function create_ajax_nonce( $action ) {
		$action = $this->get_prefixed_action( $action );
		return wp_create_nonce( $action );
	}

	/**
	 * Verify a nonce from an AJAX request.
	 *
	 * @param string $action The action to verify against.
	 * @param string $query_arg Optional. The query argument or POST field to check.
	 * @param bool   $die Optional. Whether to die if verification fails.
	 * @return bool|int False if invalid, 1 if valid and generated within 12 hours, 2 if valid and generated within 24 hours.
	 */
	public function verify_ajax_nonce( $action, $query_arg = false, $die = true ) {
		$action = $this->get_prefixed_action( $action );

		if ( $die ) {
			return check_ajax_referer( $action, $query_arg, $die );
		}

		// Manual verification without dying
		$nonce = '';

		if ( $query_arg && isset( $_REQUEST[ $query_arg ] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $query_arg ] ) );
		} elseif ( isset( $_REQUEST['_ajax_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}

		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Get the prefixed action name.
	 *
	 * @param string $action The action name.
	 * @return string The prefixed action name.
	 */
	private function get_prefixed_action( $action ) {
		// Remove prefix if already present to avoid double prefixing
		if ( strpos( $action, $this->prefix ) === 0 ) {
			return $action;
		}

		return $this->prefix . $action;
	}

	/**
	 * Set a custom nonce lifetime.
	 *
	 * @param int $lifetime The lifetime in seconds.
	 */
	public function set_nonce_lifetime( $lifetime ) {
		$this->lifetime = absint( $lifetime );

		// Use WordPress filter to modify nonce lifetime
		add_filter( 'nonce_life', array( $this, 'filter_nonce_life' ) );
	}

	/**
	 * Filter callback for nonce lifetime.
	 *
	 * @return int The nonce lifetime.
	 */
	public function filter_nonce_life() {
		return $this->lifetime;
	}

	/**
	 * Generate a secure random token for additional security.
	 *
	 * @param int $length The length of the token.
	 * @return string The random token.
	 */
	public function generate_secure_token( $length = 32 ) {
		if ( function_exists( 'random_bytes' ) ) {
			try {
				return bin2hex( random_bytes( $length / 2 ) );
			} catch ( Exception $e ) {
				// Fall back to wp_generate_password
			}
		}

		return wp_generate_password( $length, false );
	}

	/**
	 * Create a time-limited token for specific operations.
	 *
	 * @param string $action The action for the token.
	 * @param int    $expiry_time Optional. Expiry time in seconds from now.
	 * @return string The token.
	 */
	public function create_time_limited_token( $action, $expiry_time = 3600 ) {
		$data = array(
			'action'  => $action,
			'expiry'  => time() + $expiry_time,
			'user_id' => get_current_user_id(),
			'random'  => $this->generate_secure_token( 16 ),
		);

		$token = base64_encode( wp_json_encode( $data ) );
		$hash  = hash_hmac( 'sha256', $token, wp_salt( 'nonce' ) );

		return $token . '.' . $hash;
	}

	/**
	 * Verify a time-limited token.
	 *
	 * @param string $token The token to verify.
	 * @param string $action The expected action.
	 * @return bool True if valid, false otherwise.
	 */
	public function verify_time_limited_token( $token, $action ) {
		$parts = explode( '.', $token );

		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $data_token, $hash ) = $parts;

		// Verify hash
		$expected_hash = hash_hmac( 'sha256', $data_token, wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected_hash, $hash ) ) {
			return false;
		}

		// Decode and verify data
		$data = json_decode( base64_decode( $data_token ), true );

		if ( ! $data || ! is_array( $data ) ) {
			return false;
		}

		// Check required fields
		if ( ! isset( $data['action'], $data['expiry'], $data['user_id'] ) ) {
			return false;
		}

		// Check action
		if ( $data['action'] !== $action ) {
			return false;
		}

		// Check expiry
		if ( time() > $data['expiry'] ) {
			return false;
		}

		// Check user
		if ( $data['user_id'] !== get_current_user_id() ) {
			return false;
		}

		return true;
	}
}
