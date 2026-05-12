<?php
/**
 * Optional per-user/role/client MCP tool allowlist policy.
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Router;

use WeboMCP\Core\Session\SessionManager;
use WP_REST_Request;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies disabled-by-default allowlist rules to MCP tools/list and tools/call.
 */
final class ClientToolPolicy {
	private const OPTION_ENABLED = 'webo_mcp_tool_allowlist_enabled';
	private const OPTION_RULES   = 'webo_mcp_tool_allowlist_rules';

	/**
	 * Application Password context captured by WordPress during this request.
	 *
	 * @var array<string, string>
	 */
	private static $application_password = array();

	/**
	 * Capture authenticated Application Password metadata without storing secrets.
	 *
	 * @param WP_User              $user Application Password user.
	 * @param array<string, mixed> $item Application Password item.
	 * @return void
	 */
	public static function capture_application_password( WP_User $user, array $item ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		self::$application_password = array(
			'uuid'   => isset( $item['uuid'] ) ? sanitize_text_field( (string) $item['uuid'] ) : '',
			'app_id' => isset( $item['app_id'] ) ? sanitize_text_field( (string) $item['app_id'] ) : '',
			'name'   => isset( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : '',
		);
	}

	/**
	 * Whether the allowlist layer is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '0' );
	}

	/**
	 * Sanitize allowlist settings from the admin textarea fields.
	 *
	 * @param mixed $value Raw option value.
	 * @return array<string, string>
	 */
	public static function sanitize_rules_option( $value ): array {
		$value = is_array( $value ) ? $value : array();

		return array(
			'users'   => isset( $value['users'] ) && is_scalar( $value['users'] ) ? sanitize_textarea_field( wp_unslash( (string) $value['users'] ) ) : '',
			'roles'   => isset( $value['roles'] ) && is_scalar( $value['roles'] ) ? sanitize_textarea_field( wp_unslash( (string) $value['roles'] ) ) : '',
			'clients' => isset( $value['clients'] ) && is_scalar( $value['clients'] ) ? sanitize_textarea_field( wp_unslash( (string) $value['clients'] ) ) : '',
		);
	}

	/**
	 * Return raw stored rules.
	 *
	 * @return array<string, string>
	 */
	public static function get_rules_option(): array {
		$rules = get_option( self::OPTION_RULES, array() );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		return array(
			'users'   => isset( $rules['users'] ) ? (string) $rules['users'] : '',
			'roles'   => isset( $rules['roles'] ) ? (string) $rules['roles'] : '',
			'clients' => isset( $rules['clients'] ) ? (string) $rules['clients'] : '',
		);
	}

	/**
	 * Check a tool against active allowlist rules.
	 *
	 * @param array<string, mixed>  $tool    Tool definition or tools/list item.
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $params  JSON-RPC params.
	 * @return bool
	 */
	public static function is_tool_allowed( array $tool, WP_REST_Request $request, array $params = array() ): bool {
		if ( ! self::is_enabled() ) {
			return true;
		}

		$tool_name = isset( $tool['name'] ) ? (string) $tool['name'] : '';
		if ( '' === $tool_name ) {
			return false;
		}

		$allowed_tools = self::matching_allowed_tools( $request, $params );
		$allowed       = in_array( '*', $allowed_tools, true ) || in_array( $tool_name, $allowed_tools, true );

		return (bool) apply_filters( 'webo_mcp_tool_allowlist_allowed', $allowed, $tool_name, $request, $params, $allowed_tools );
	}

	/**
	 * Return matched allowlist tools for the current identity/client.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $params  JSON-RPC params.
	 * @return array<int, string>
	 */
	private static function matching_allowed_tools( WP_REST_Request $request, array $params ): array {
		$rules   = self::get_rules_option();
		$allowed = array();

		$user_rules = self::parse_rules_text( $rules['users'] );
		$user       = wp_get_current_user();
		if ( $user && $user->exists() ) {
			foreach ( array( (string) $user->ID, strtolower( $user->user_login ) ) as $identifier ) {
				if ( isset( $user_rules[ $identifier ] ) ) {
					$allowed = array_merge( $allowed, $user_rules[ $identifier ] );
				}
			}
		}

		$role_rules = self::parse_rules_text( $rules['roles'] );
		if ( $user && is_array( $user->roles ) ) {
			foreach ( $user->roles as $role ) {
				$role = sanitize_key( $role );
				if ( isset( $role_rules[ $role ] ) ) {
					$allowed = array_merge( $allowed, $role_rules[ $role ] );
				}
			}
		}

		$client_rules = self::parse_rules_text( $rules['clients'] );
		foreach ( self::current_client_identifiers( $request, $params ) as $identifier ) {
			if ( isset( $client_rules[ $identifier ] ) ) {
				$allowed = array_merge( $allowed, $client_rules[ $identifier ] );
			}
		}

		return array_values( array_unique( array_filter( $allowed ) ) );
	}

	/**
	 * Parse "identifier = tool, tool" text into a map.
	 *
	 * @param string $text Textarea value.
	 * @return array<string, array<int, string>>
	 */
	private static function parse_rules_text( string $text ): array {
		$rules = array();
		$lines = preg_split( '/\R/', $text );
		if ( ! is_array( $lines ) ) {
			return $rules;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			$parts = preg_split( '/\s*[=:]\s*/', $line, 2 );
			if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
				continue;
			}

			$identifier = self::normalize_identifier( $parts[0] );
			if ( '' === $identifier ) {
				continue;
			}

			$tools = array();
			foreach ( explode( ',', $parts[1] ) as $tool ) {
				$tool = trim( sanitize_text_field( $tool ) );
				if ( '' !== $tool ) {
					$tools[] = $tool;
				}
			}

			if ( ! empty( $tools ) ) {
				$rules[ $identifier ] = $tools;
			}
		}

		return $rules;
	}

	/**
	 * Return current client identifiers from Application Password and MCP session metadata.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $params  JSON-RPC params.
	 * @return array<int, string>
	 */
	private static function current_client_identifiers( WP_REST_Request $request, array $params ): array {
		$identifiers = array();

		foreach ( self::$application_password as $value ) {
			$value = self::normalize_identifier( $value );
			if ( '' !== $value ) {
				$identifiers[] = $value;
			}
		}

		$session_id = '';
		if ( isset( $params['session_id'] ) && is_scalar( $params['session_id'] ) ) {
			$session_id = sanitize_text_field( (string) $params['session_id'] );
		}
		if ( '' === $session_id ) {
			$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
		}
		if ( '' === $session_id ) {
			$session_id = sanitize_text_field( (string) $request->get_header( 'Mcp-Session-Id' ) );
		}

		if ( '' !== $session_id ) {
			$session = SessionManager::get( $session_id );
			if ( is_array( $session ) && isset( $session['client'] ) ) {
				$client = self::normalize_identifier( (string) $session['client'] );
				if ( '' !== $client ) {
					$identifiers[] = $client;
				}
			}
		}

		return array_values( array_unique( $identifiers ) );
	}

	/**
	 * Normalize a user/role/client identifier.
	 *
	 * @param string $value Raw identifier.
	 * @return string
	 */
	private static function normalize_identifier( string $value ): string {
		$value = strtolower( trim( sanitize_text_field( $value ) ) );
		if ( 0 === strpos( $value, 'user:' ) || 0 === strpos( $value, 'role:' ) || 0 === strpos( $value, 'client:' ) || 0 === strpos( $value, 'app:' ) ) {
			$value = trim( substr( $value, strpos( $value, ':' ) + 1 ) );
		}

		return $value;
	}
}
