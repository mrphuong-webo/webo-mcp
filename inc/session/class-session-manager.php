<?php

namespace WeboMCP\Core\Session;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SessionManager {

	/**
	 * Session TTL in seconds.
	 */
	private const SESSION_TTL = 43200;

	/**
	 * Creates a new MCP session and stores it in transient storage.
	 *
	 * @param array<string, mixed> $meta Session metadata.
	 * @return string
	 */
	public static function create( array $meta = array() ) {
		$session_id = wp_generate_uuid4();

		$payload = array(
			'session_id'  => $session_id,
			'created_at'  => time(),
			'user_id'     => get_current_user_id(),
			'client'      => isset( $meta['client'] ) ? sanitize_text_field( (string) $meta['client'] ) : '',
			'client_meta' => $meta,
		);

		set_transient( self::transient_key( $session_id ), $payload, self::SESSION_TTL );

		return $session_id;
	}

	/**
	 * Returns session payload by ID.
	 *
	 * @param string $session_id Session ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $session_id ) {
		$session_id = trim( $session_id );
		if ( '' === $session_id ) {
			return null;
		}

		$session = get_transient( self::transient_key( $session_id ) );
		if ( ! is_array( $session ) ) {
			return null;
		}

		return $session;
	}

	/**
	 * Validates a session ID.
	 *
	 * @param string $session_id Session ID.
	 * @return bool
	 */
	public static function validate( string $session_id ) {
		return null !== self::get( $session_id );
	}

	/**
	 * Destroys a session.
	 *
	 * @param string $session_id Session ID.
	 * @return void
	 */
	public static function destroy( string $session_id ) {
		$session_id = trim( $session_id );
		if ( '' === $session_id ) {
			return;
		}

		delete_transient( self::transient_key( $session_id ) );
	}

	/**
	 * Builds transient key.
	 *
	 * @param string $session_id Session ID.
	 * @return string
	 */
	private static function transient_key( string $session_id ) {
		return 'webo_mcp_session_' . md5( $session_id );
	}
}
