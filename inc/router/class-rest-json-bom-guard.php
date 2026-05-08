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
 * Heuristic: current HTTP request URI targets REST/JSON API (and thus needs BOM sanitization).
 *
 * Works before {@see WP_REST_Server} builds a route (BOM often prints before routes run).
 *
 * @return bool
 */
function webo_mcp_rest_uri_maybe_mcp() {
	$uri_raw = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
	$uri     = strtolower( $uri_raw );

	/**
	 * Guard all REST/JSON-style API URLs (pretty permalinks and index.php/rest_route fallback).
	 * MCP transports and @automattic/mcp-wordpress-remote parse JSON bodies with JSON.parse(),
	 * which fails on leading UTF-8 BOM; BOM can appear anywhere the stack echoes before JSON,
	 * not only on MCP-prefixed routes.
	 *
	 * @param bool   $activate Default true.
	 * @param string $uri_raw  Raw REQUEST_URI value.
	 */
	$guard_json_api_requests = apply_filters( 'webo_mcp_rest_bom_guard_json_api_requests', true, $uri_raw );

	if ( $guard_json_api_requests && strpos( $uri, '/wp-json/' ) !== false ) {
		return true;
	}

	if ( $guard_json_api_requests && strpos( $uri, 'wp-json.php' ) !== false ) {
		return true;
	}

	if ( $guard_json_api_requests && isset( $_GET['rest_route'] ) ) {
		$rr_low = strtolower( wp_unslash( (string) $_GET['rest_route'] ) );
		if ( $rr_low !== '' ) {
			return true;
		}
	}

	return (bool) apply_filters( 'webo_mcp_rest_bom_guard_for_request_uri', false, $uri_raw );
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

	if ( strpos( $r, 'wp-abilities' ) !== false ) {
		return true;
	}

	return (bool) apply_filters( 'webo_mcp_rest_bom_guard_for_route', false, $route, $request );
}

/**
 * Combine URI probe + route probe.
 *
 * @param \WP_REST_Request|null $request Optional request (after routing).
 */
function webo_mcp_rest_should_activate_bom_guard( $request = null ) {
	if ( webo_mcp_rest_uri_maybe_mcp() ) {
		return true;
	}
	if ( webo_mcp_rest_request_needs_bom_guard( $request ) ) {
		return true;
	}

	return false;
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
	$out = $buffer;
	do {
		$prev = $out;
		// Repeat the 3-byte UTF-8 BOM sequence; \xEF\xBB\xBF+ would wrongly repeat only 0xBF.
		$out = preg_replace( '/^(?:\xEF\xBB\xBF)+/s', '', $out );
		$out = preg_replace( '/^\x{FEFF}+/u', '', $out );
	} while ( $out !== $prev && $out !== '' );

	return $out;
}

/**
 * OB handler: strip BOM once when this buffer flushes into the outer layer.
 *
 * @param string $buffer Buffer chunk.
 * @param int    $phase  OB phase flags.
 * @return string
 */
function webo_mcp_rest_ob_strip_handler( $buffer, $phase = 0 ) {
	unset( $phase );
	if ( ! is_string( $buffer ) || $buffer === '' ) {
		return $buffer;
	}
	return webo_mcp_rest_strip_leading_bom( $buffer );
}

/**
 * Starts one BOM-stripping output buffer (nested, idempotent).
 */
function webo_mcp_rest_try_start_output_buffer() {
	static $started = false;
	if ( $started ) {
		return;
	}
	/**
	 * Last chance to disable the guard (unlikely).
	 *
	 * @param bool $enable Default true.
	 */
	if ( ! apply_filters( 'webo_mcp_rest_bom_guard_enabled', true ) ) {
		return;
	}
	$started = true;
	if ( ! function_exists( 'ob_start' ) ) {
		return;
	}
	$flags = defined( 'PHP_OUTPUT_HANDLER_STDFLAGS' ) ? PHP_OUTPUT_HANDLER_STDFLAGS : 0;
	// Handler receives the buffered body when flushed (normal REST JSON output path).
	ob_start( 'webo_mcp_rest_ob_strip_handler', 0, $flags );
}

/**
 * Start buffering as soon as REST API boots when the URL looks like REST/JSON API — covers BOM echoed before routing.
 *
 * @return void
 */
function webo_mcp_rest_bootstrap_bom_guard_on_rest_load() {
	if ( ! webo_mcp_rest_should_activate_bom_guard( null ) ) {
		return;
	}
	webo_mcp_rest_try_start_output_buffer();
}

add_action( 'rest_api_init', 'webo_mcp_rest_bootstrap_bom_guard_on_rest_load', 0 );

/**
 * Start buffering very early for MCP-looking requests.
 *
 * Some stacks echo a BOM during plugin bootstrap or before {@see 'rest_api_init'} runs; a single
 * {@see 'rest_api_init'} hook can be too late for the outer output buffer to capture that noise.
 *
 * @return void
 */
function webo_mcp_rest_bom_guard_plugins_loaded() {
	if ( ! webo_mcp_rest_uri_maybe_mcp() ) {
		return;
	}
	webo_mcp_rest_try_start_output_buffer();
}

add_action( 'plugins_loaded', 'webo_mcp_rest_bom_guard_plugins_loaded', -999999 );

/**
 * Second early gate: covers code that prints between {@see 'plugins_loaded'} and REST bootstrap.
 *
 * @return void
 */
function webo_mcp_rest_bom_guard_init() {
	if ( ! webo_mcp_rest_uri_maybe_mcp() ) {
		return;
	}
	webo_mcp_rest_try_start_output_buffer();
}

add_action( 'init', 'webo_mcp_rest_bom_guard_init', -999999 );

/**
 * Fallback: activate by matched route once the request exists.
 *
 * @param mixed            $result  Prior result (unused).
 * @param \WP_REST_Server  $server  REST server.
 * @param \WP_REST_Request $request Request.
 * @return mixed Unchanged.
 */
function webo_mcp_rest_bom_guard_pre_dispatch( $result, $server, $request ) {
	unset( $server );
	if ( null !== $result ) {
		return $result;
	}
	if ( webo_mcp_rest_should_activate_bom_guard( $request ) ) {
		webo_mcp_rest_try_start_output_buffer();
	}

	return null;
}

add_filter( 'rest_pre_dispatch', 'webo_mcp_rest_bom_guard_pre_dispatch', PHP_INT_MIN, 3 );
