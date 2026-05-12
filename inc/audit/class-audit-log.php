<?php
/**
 * Bounded MCP audit log storage.
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Audit;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores compact, privacy-conscious MCP tool call audit events in an option.
 */
final class Audit_Log {
	private const OPTION_ENABLED     = 'webo_mcp_audit_log_enabled';
	private const OPTION_LOG         = 'webo_mcp_audit_log';
	private const OPTION_MAX_ENTRIES = 'webo_mcp_audit_log_max_entries';
	private const DEFAULT_MAX        = 200;
	private const HARD_MAX           = 1000;

	/**
	 * Whether audit logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '0' !== (string) get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Sanitizes the admin-configured max entry count.
	 *
	 * @param mixed $value Raw option value.
	 * @return int
	 */
	public static function sanitize_max_entries( $value ): int {
		$value = absint( $value );
		if ( $value < 25 ) {
			$value = 25;
		}
		if ( $value > self::HARD_MAX ) {
			$value = self::HARD_MAX;
		}

		return $value;
	}

	/**
	 * Returns the active maximum entry count.
	 *
	 * @return int
	 */
	public static function max_entries(): int {
		return self::sanitize_max_entries( get_option( self::OPTION_MAX_ENTRIES, self::DEFAULT_MAX ) );
	}

	/**
	 * Record an MCP tool call attempt/result.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $event   Event context.
	 * @return void
	 */
	public static function record( WP_REST_Request $request, array $event ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$arguments  = isset( $event['arguments'] ) && is_array( $event['arguments'] ) ? $event['arguments'] : array();
		$session_id = isset( $event['session_id'] ) ? sanitize_text_field( (string) $event['session_id'] ) : '';
		$user       = wp_get_current_user();
		$status     = isset( $event['status'] ) ? sanitize_key( (string) $event['status'] ) : 'unknown';

		$entry = array(
			'id'             => wp_generate_uuid4(),
			'timestamp_gmt'  => gmdate( 'c' ),
			'user_id'        => get_current_user_id(),
			'user_login'     => $user && $user->exists() ? sanitize_user( $user->user_login, true ) : '',
			'user_roles'     => $user && is_array( $user->roles ) ? array_values( array_map( 'sanitize_key', $user->roles ) ) : array(),
			'tool'           => isset( $event['tool_name'] ) ? sanitize_text_field( (string) $event['tool_name'] ) : '',
			'action'         => self::extract_action( $arguments ),
			'object_id'      => self::extract_object_id( $arguments, isset( $event['result'] ) ? $event['result'] : null ),
			'ip'             => self::get_anonymized_ip(),
			'session_hash'   => '' !== $session_id ? substr( hash( 'sha256', $session_id ), 0, 16 ) : '',
			'client'         => isset( $event['client'] ) ? sanitize_text_field( (string) $event['client'] ) : '',
			'status'         => $status,
			'result_summary' => self::summarize_result( isset( $event['result'] ) ? $event['result'] : null, $status ),
			'error_code'     => isset( $event['error_code'] ) ? sanitize_key( (string) $event['error_code'] ) : '',
			'error_message'  => isset( $event['error_message'] ) ? self::truncate( sanitize_text_field( (string) $event['error_message'] ), 220 ) : '',
		);

		$entries = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}

		array_unshift( $entries, $entry );
		$entries = array_slice( $entries, 0, self::max_entries() );

		update_option( self::OPTION_LOG, $entries, false );
	}

	/**
	 * Return recent entries.
	 *
	 * @param int $limit Max entries.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_entries( int $limit = 50 ): array {
		$entries = get_option( self::OPTION_LOG, array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$limit = max( 1, min( self::HARD_MAX, $limit ) );
		return array_slice( $entries, 0, $limit );
	}

	/**
	 * Clear all entries.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION_LOG );
	}

	/**
	 * Count stored entries.
	 *
	 * @return int
	 */
	public static function count(): int {
		$entries = get_option( self::OPTION_LOG, array() );
		return is_array( $entries ) ? count( $entries ) : 0;
	}

	/**
	 * Extract an action-like discriminator from tool arguments.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return string
	 */
	private static function extract_action( array $arguments ): string {
		foreach ( array( 'action', 'query', 'operation' ) as $key ) {
			if ( isset( $arguments[ $key ] ) && ! is_array( $arguments[ $key ] ) && ! is_object( $arguments[ $key ] ) ) {
				return self::truncate( sanitize_text_field( (string) $arguments[ $key ] ), 80 );
			}
		}

		return '';
	}

	/**
	 * Extract a likely object ID without storing full payloads.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @param mixed                $result    Tool result.
	 * @return int|string
	 */
	private static function extract_object_id( array $arguments, $result ) {
		foreach ( array( 'post_id', 'id', 'attachment_id', 'term_id', 'comment_id', 'menu_id', 'revision_id' ) as $key ) {
			if ( isset( $arguments[ $key ] ) && is_scalar( $arguments[ $key ] ) ) {
				$value = (int) $arguments[ $key ];
				if ( $value > 0 ) {
					return $value;
				}
			}
		}

		if ( is_array( $result ) && isset( $result['id'] ) && is_scalar( $result['id'] ) ) {
			$value = (int) $result['id'];
			if ( $value > 0 ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Build a compact result summary without large payloads or secrets.
	 *
	 * @param mixed  $result Tool result.
	 * @param string $status Event status.
	 * @return string
	 */
	private static function summarize_result( $result, string $status ): string {
		if ( $result instanceof WP_Error ) {
			return self::truncate( $result->get_error_code(), 80 );
		}

		if ( 'success' !== $status ) {
			return $status;
		}

		if ( is_array( $result ) ) {
			$keys = array_slice( array_map( 'strval', array_keys( $result ) ), 0, 6 );
			return self::truncate( 'ok: ' . implode( ', ', $keys ), 140 );
		}

		if ( is_scalar( $result ) ) {
			return self::truncate( 'ok: ' . sanitize_text_field( (string) $result ), 140 );
		}

		return 'ok';
	}

	/**
	 * Return anonymized remote IP if available.
	 *
	 * @return string
	 */
	private static function get_anonymized_ip(): string {
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		if ( '' === $ip ) {
			return '';
		}

		return function_exists( 'wp_privacy_anonymize_ip' ) ? wp_privacy_anonymize_ip( $ip ) : $ip;
	}

	/**
	 * Truncate a string to a storage-safe length.
	 *
	 * @param string $value  String.
	 * @param int    $length Max length.
	 * @return string
	 */
	private static function truncate( string $value, int $length ): string {
		$value = trim( $value );
		if ( strlen( $value ) <= $length ) {
			return $value;
		}

		return substr( $value, 0, max( 0, $length - 3 ) ) . '...';
	}
}
