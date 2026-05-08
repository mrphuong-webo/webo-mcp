<?php
/**
 * Strips accidental UTF-8 BOM / invisible prefix from MCP REST JSON responses.
 *
 * Some installs echo a BOM before JSON (UTF-8-with-BOM PHP files, mu-plugins, BOM in wp-config).
 * MCP clients then fail with "Unexpected token" on parse.
 *
 * @package WeboMCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this REST request should run the BOM sanitizer on output.
 *
 * @param \WP_REST_Request|null $request Request object.
 */
function webo_mcp_rest_request_needs_bom_guard( $request ) {
	if ( ! $request instanceof \WP_REST_Request ) {
		return false;
	}
	$route = $request->get_route();
	if ( ! is_string( $route ) || $route === '' ) {
		return false;
	}
	$r = strtolower( $route );

	// MCP adapter default transport, WEBO router alias, diagnostics.
	if ( strpos( $r, 'mcp' ) !== false ) {
		return true;
	}

	if ( strpos( $r, 'webo-mcp' ) !== false ) {
		return true;
	}

	return (bool) apply_filters( 'webo_mcp_rest_bom_guard_for_route', false, $route, $request );
}

/**
 * Remove leading UTF-8 BOM (and stray U+FEFF) from a buffer.
 *
 * @param string $buffer Output buffer.
 * @return string
 */
function webo_mcp_rest_strip_leading_bom( $buffer ) {
	if ( ! is_string( $buffer ) || $buffer === '' ) {
		return $buffer;
	}
	// Repeated UTF-8 BOM.
	$out = preg_replace( '/^\xEF\xBB\xBF+/s', '', $buffer );
	// BOM as Unicode (some broken stacks).
	$out = preg_replace( '/^\x{FEFF}/u', '', $out );
	return $out;
}

/**
 * OB handler: strip BOM once when the inner buffer is flushed into this one.
 *
 * @param string $buffer Buffer chunk.
 * @param int    $phase  OB phase flags.
 * @return string
 */
function webo_mcp_rest_ob_strip_handler( $buffer, $phase = 0 ) {
	if ( ! is_string( $buffer ) || $buffer === '' ) {
		return $buffer;
	}
	return webo_mcp_rest_strip_leading_bom( $buffer );
}

/**
 * Start a nested output buffer for MCP routes so BOM is stripped before the client sees JSON.
 *
 * @param mixed               $result  Prior result (unused).
 * @param \WP_REST_Server     $server  REST server.
 * @param \WP_REST_Request    $request Request.
 * @return mixed Unchanged.
 */
function webo_mcp_rest_bom_guard_pre_dispatch( $result, $server, $request ) {
	unset( $server );
	if ( null !== $result ) {
		return $result;
	}
	if ( ! webo_mcp_rest_request_needs_bom_guard( $request ) ) {
		return null;
	}
	static $started = false;
	if ( $started ) {
		return null;
	}
	$started = true;
	if ( function_exists( 'ob_start' ) ) {
		$ob_flags = defined( 'PHP_OUTPUT_HANDLER_STDFLAGS' ) ? PHP_OUTPUT_HANDLER_STDFLAGS : 0;
		// Handler runs when this buffer is flushed so the client receives JSON without a leading BOM.
		ob_start( 'webo_mcp_rest_ob_strip_handler', 0, $ob_flags );
	}
	return null;
}

add_filter( 'rest_pre_dispatch', 'webo_mcp_rest_bom_guard_pre_dispatch', PHP_INT_MIN, 3 );
