<?php

namespace WeboMCP\Core\Router;

use Exception;
use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Session\SessionManager;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class McpRouter {

	/**
	 * Registers MCP router endpoint.
	 *
	 * @return void
	 */
	public static function register_rest_endpoint() {
		$router = new self();

		register_rest_route(
			'mcp/v1',
			'/router',
			array(
				'methods'             => 'POST',
				'callback'            => array( $router, 'handle_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handles JSON-RPC request and routes supported methods.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return array<string, mixed>
	 */
	public function handle_request( WP_REST_Request $request ) {
		$raw_body = (string) $request->get_body();
		$payload  = json_decode( $raw_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
			return $this->jsonrpc_error( -32700, 'Parse error', null );
		}

		$validation = $this->validate_jsonrpc( $payload );
		if ( is_wp_error( $validation ) ) {
			$error_data = $validation->get_error_data();
			$code       = isset( $error_data['code'] ) ? (int) $error_data['code'] : -32600;
			$id         = isset( $payload['id'] ) ? $payload['id'] : null;
			return $this->jsonrpc_error( $code, $validation->get_error_message(), $id );
		}

		$method = (string) $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();
		$id     = isset( $payload['id'] ) ? $payload['id'] : null;

		switch ( $method ) {
			case 'initialize':
				return $this->handle_initialize( $params, $id );

			case 'tools/list':
				return $this->handle_tools_list( $request, $id );

			case 'tools/call':
				return $this->handle_tools_call( $request, $params, $id );

			default:
				return $this->jsonrpc_error( -32601, 'Method not found', $id );
		}
	}

	/**
	 * Handles initialize method.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @param int|string|null      $id Request ID.
	 * @return array<string, mixed>
	 */
	public function handle_initialize( array $params, $id ) {
		$meta = array(
			'client'  => isset( $params['client'] ) ? sanitize_text_field( (string) $params['client'] ) : '',
			'version' => isset( $params['version'] ) ? sanitize_text_field( (string) $params['version'] ) : '',
		);

		$session_id = SessionManager::create( $meta );

		return $this->jsonrpc_success(
			array(
				'session_id'   => $session_id,
				'capabilities' => array(
					'tools'   => true,
					'methods' => array( 'initialize', 'tools/list', 'tools/call' ),
				),
			),
			$id
		);
	}

	/**
	 * Handles tools/list method.
	 *
	 * @param int|string|null $id Request ID.
	 * @return array<string, mixed>
	 */
	public function handle_tools_list( WP_REST_Request $request, $id ) {
		$include_internal = $this->is_internal_tools_allowed( $request );
		$payload          = ToolRegistry::list_tools( $include_internal );
		$tools            = isset( $payload['tools'] ) && is_array( $payload['tools'] ) ? $payload['tools'] : array();

		$tools = array_values(
			array_filter(
				$tools,
				function ( $tool ) use ( $request ) {
					return is_array( $tool ) && $this->is_public_tool_allowed( $tool, $request );
				}
			)
		);

		return $this->jsonrpc_success( array( 'tools' => $tools ), $id );
	}

	/**
	 * Handles tools/call method.
	 *
	 * @param WP_REST_Request      $request Incoming request.
	 * @param array<string, mixed> $params Request params.
	 * @param int|string|null      $id Request ID.
	 * @return array<string, mixed>
	 */
	public function handle_tools_call( WP_REST_Request $request, array $params, $id ) {
		$security = $this->validate_security( $request );
		if ( is_wp_error( $security ) ) {
			return $this->jsonrpc_error( -32001, $security->get_error_message(), $id );
		}

		$session_id = '';
		if ( isset( $params['session_id'] ) ) {
			$session_id = sanitize_text_field( (string) $params['session_id'] );
		}

		if ( '' === $session_id ) {
			$session_id = sanitize_text_field( (string) $request->get_header( 'Mcp-Session-Id' ) );
		}

		if ( '' === $session_id || ! SessionManager::validate( $session_id ) ) {
			return $this->jsonrpc_error( -32002, 'Invalid or missing session', $id );
		}

		$tool_name = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		if ( '' === $tool_name ) {
			return $this->jsonrpc_error( -32602, 'Invalid params: missing tool name', $id );
		}

		$tool_definition = ToolRegistry::get( $tool_name );
		if ( ! $tool_definition ) {
			return $this->jsonrpc_error( -32602, 'Tool not found', $id );
		}

		if ( ToolRegistry::is_internal( $tool_name ) && ! $this->is_internal_tools_allowed( $request ) ) {
			return $this->jsonrpc_error( -32001, 'Internal tool is not available for this client', $id );
		}

		if ( ! $this->is_public_tool_allowed( $tool_definition, $request ) ) {
			return $this->jsonrpc_error( -32001, 'Tool is not allowed by public policy', $id );
		}

		$arguments = array();
		if ( isset( $params['arguments'] ) ) {
			if ( ! is_array( $params['arguments'] ) ) {
				return $this->jsonrpc_error( -32602, 'Invalid params: arguments must be an object', $id );
			}
			$arguments = $params['arguments'];
		}

		try {
			$result = ToolRegistry::call( $tool_name, $arguments );
		} catch ( Exception $exception ) {
			return $this->jsonrpc_error( -32003, $exception->getMessage(), $id );
		}

		if ( is_wp_error( $result ) ) {
			return $this->jsonrpc_error( -32003, $result->get_error_message(), $id );
		}

		return $this->jsonrpc_success( $result, $id );
	}

	/**
	 * Validates JSON-RPC 2.0 envelope.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return true|WP_Error
	 */
	public function validate_jsonrpc( array $payload ) {
		if ( ! isset( $payload['jsonrpc'] ) || '2.0' !== $payload['jsonrpc'] ) {
			return new WP_Error( 'webo_mcp_invalid_jsonrpc_version', 'Invalid Request: jsonrpc must be "2.0"', array( 'code' => -32600 ) );
		}

		if ( ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === trim( $payload['method'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_method', 'Invalid Request: method is required', array( 'code' => -32600 ) );
		}

		if ( isset( $payload['params'] ) && ! is_array( $payload['params'] ) ) {
			return new WP_Error( 'webo_mcp_invalid_params', 'Invalid Request: params must be an object', array( 'code' => -32600 ) );
		}

		return true;
	}

	/**
	 * Builds JSON-RPC success response.
	 *
	 * @param mixed           $result Response result.
	 * @param int|string|null $id Request ID.
	 * @return array<string, mixed>
	 */
	public function jsonrpc_success( $result, $id ) {
		return array(
			'jsonrpc' => '2.0',
			'result'  => $result,
			'id'      => $id,
		);
	}

	/**
	 * Builds JSON-RPC error response.
	 *
	 * @param int             $code JSON-RPC code.
	 * @param string          $message Error message.
	 * @param int|string|null $id Request ID.
	 * @param mixed           $data Optional error data.
	 * @return array<string, mixed>
	 */
	public function jsonrpc_error( int $code, string $message, $id = null, $data = null ) {
		$error = array(
			'code'    => $code,
			'message' => $message,
		);

		if ( null !== $data ) {
			$error['data'] = $data;
		}

		return array(
			'jsonrpc' => '2.0',
			'error'   => $error,
			'id'      => $id,
		);
	}

	/**
	 * Validates request security for tool execution.
	 *
	 * Supports:
	 * - WordPress authenticated user with "read" capability
	 * - API key header: X-WEBO-API-KEY
	 * - HMAC headers: X-WEBO-TIMESTAMP + X-WEBO-SIGNATURE
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	private function validate_security( WP_REST_Request $request ) {
		if ( is_user_logged_in() && current_user_can( 'read' ) ) {
			return true;
		}

		$configured_api_key = (string) get_option( 'webo_wordpress_mcp_api_key', get_option( 'webo_mcp_core_api_key', '' ) );
		$provided_api_key   = (string) $request->get_header( 'X-WEBO-API-KEY' );

		if ( '' !== $configured_api_key && '' !== $provided_api_key && hash_equals( $configured_api_key, trim( $provided_api_key ) ) ) {
			return true;
		}

		$configured_hmac_secret = (string) get_option( 'webo_wordpress_mcp_hmac_secret', get_option( 'webo_mcp_core_hmac_secret', '' ) );
		$signature              = (string) $request->get_header( 'X-WEBO-SIGNATURE' );
		$timestamp              = (string) $request->get_header( 'X-WEBO-TIMESTAMP' );

		if ( '' !== $configured_hmac_secret && '' !== $signature && '' !== $timestamp && ctype_digit( $timestamp ) ) {
			$now           = time();
			$request_epoch = (int) $timestamp;

			if ( abs( $now - $request_epoch ) <= 300 ) {
				$payload            = $timestamp . '.' . (string) $request->get_body();
				$expected_signature = 'sha256=' . hash_hmac( 'sha256', $payload, $configured_hmac_secret );

				if ( hash_equals( $expected_signature, trim( $signature ) ) ) {
					return true;
				}
			}
		}

		return new WP_Error( 'webo_mcp_unauthorized', 'Unauthorized request for tools/call' );
	}

	/**
	 * Whether internal tools are available for current request context.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	private function is_internal_tools_allowed( WP_REST_Request $request ) {
		$default_allowed = is_user_logged_in() && current_user_can( 'manage_options' );
		$allowed         = (bool) apply_filters( 'webo_wordpress_mcp_allow_internal_tools', $default_allowed, $request );

		return (bool) apply_filters( 'webo_mcp_core_allow_internal_tools', $allowed, $request );
	}

	/**
	 * Validates whether a tool is allowed by public policy.
	 *
	 * Default policy only allows category "wordpress" tools.
	 *
	 * @param array<string, mixed> $tool Tool definition.
	 * @param WP_REST_Request       $request Request object.
	 * @return bool
	 */
	private function is_public_tool_allowed( array $tool, WP_REST_Request $request ) {
		$visibility = isset( $tool['visibility'] ) ? (string) $tool['visibility'] : 'public';

		if ( 'internal' === $visibility && ! $this->is_internal_tools_allowed( $request ) ) {
			return false;
		}

		$allowed_categories = apply_filters( 'webo_wordpress_mcp_public_categories', array( 'wordpress' ), $request, $tool );
		$allowed_categories = apply_filters( 'webo_mcp_core_public_categories', $allowed_categories, $request, $tool );
		if ( ! is_array( $allowed_categories ) ) {
			$allowed_categories = array( 'wordpress' );
		}

		$allowed_categories = array_values( array_filter( array_map( 'strval', $allowed_categories ) ) );
		$tool_category      = isset( $tool['category'] ) ? (string) $tool['category'] : '';

		if ( ! empty( $allowed_categories ) && ! in_array( $tool_category, $allowed_categories, true ) ) {
			return false;
		}

		$allowed_names = apply_filters( 'webo_wordpress_mcp_public_tool_allowlist', array(), $request, $tool );
		$allowed_names = apply_filters( 'webo_mcp_core_public_tool_allowlist', $allowed_names, $request, $tool );
		if ( ! is_array( $allowed_names ) ) {
			$allowed_names = array();
		}

		$allowed_names = array_values( array_filter( array_map( 'strval', $allowed_names ) ) );
		if ( ! empty( $allowed_names ) ) {
			$tool_name = isset( $tool['name'] ) ? (string) $tool['name'] : '';
			if ( ! in_array( $tool_name, $allowed_names, true ) ) {
				return false;
			}
		}

		return true;
	}
}
