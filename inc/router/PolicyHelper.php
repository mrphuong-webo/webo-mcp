<?php

namespace WeboMCP\Core\Router;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AccessHelper.php';

class PolicyHelper {
	public static function is_internal_allowed( WP_REST_Request $request ) {
		$default_allowed = AccessHelper::current_user_can_use_mcp();
		return (bool) apply_filters( 'webo_mcp_allow_internal_tools', $default_allowed, $request );
	}

	public static function is_public_allowed( array $tool, WP_REST_Request $request ) {
		$visibility = isset( $tool['visibility'] ) ? (string) $tool['visibility'] : 'public';
		if ( $visibility === 'internal' && ! self::is_internal_allowed( $request ) ) {
			return false;
		}
		if ( $visibility === 'internal' ) {
			return true;
		}

		return true;
	}
}
