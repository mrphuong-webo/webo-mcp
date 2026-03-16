<?php

namespace WeboMCP\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecurityHelper {
	public static function validate( \WP_REST_Request $request ) {
		if ( is_user_logged_in() && current_user_can( 'read' ) ) {
			return true;
		}
		$configured_api_key = (string) get_option( 'webo_wordpress_mcp_api_key', '' );
		$provided_api_key   = (string) $request->get_header( 'X-WEBO-API-KEY' );
		if ( $configured_api_key !== '' && $provided_api_key !== '' && hash_equals( $configured_api_key, trim( $provided_api_key ) ) ) {
			return true;
		}
		$configured_hmac_secret = (string) get_option( 'webo_wordpress_mcp_hmac_secret', '' );
		$signature              = (string) $request->get_header( 'X-WEBO-SIGNATURE' );
		$timestamp              = (string) $request->get_header( 'X-WEBO-TIMESTAMP' );
		if ( $configured_hmac_secret !== '' && $signature !== '' && $timestamp !== '' && ctype_digit( $timestamp ) ) {
			$now           = time();
			$request_epoch = (int) $timestamp;
			if ( abs( $now - $request_epoch ) <= 300 ) {
				$payload           = $timestamp . '.' . (string) $request->get_body();
				$expected_signature = 'sha256=' . hash_hmac( 'sha256', $payload, $configured_hmac_secret );
				if ( hash_equals( $expected_signature, trim( $signature ) ) ) {
					return true;
				}
			}
		}
		return new \WP_Error( 'webo_mcp_unauthorized', 'Unauthorized request for tools/call' );
	}
}
