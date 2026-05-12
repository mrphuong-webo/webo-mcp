<?php

namespace WeboMCP\Core\Router;

use WeboMCP\Core\Audit\Audit_Log;
use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Session\SessionManager;
use WP_Error;
use WP_REST_Request;

require_once dirname( __DIR__ ) . '/audit/class-audit-log.php';
require_once __DIR__ . '/JsonRpcHelper.php';
require_once __DIR__ . '/SecurityHelper.php';
require_once __DIR__ . '/PolicyHelper.php';
require_once __DIR__ . '/AccessHelper.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class McpRouter {
	public static function register_rest_endpoint() {
		$router = new self();
		register_rest_route(
			'mcp/v1',
			'/router',
			array(
				'methods'             => 'POST',
				'callback'            => array( $router, 'handle_request' ),
				'permission_callback' => array( self::class, 'secure_permission_callback' ),
			)
		);
		register_rest_route(
			'mcp/v1',
			'/router',
			array(
				'methods'             => 'GET',
				'callback'            => array( $router, 'handle_sse_request' ),
				'permission_callback' => array( self::class, 'secure_permission_callback' ),
			)
		);
		register_rest_route(
			'mcp/v1',
			'/router/sse',
			array(
				'methods'             => 'GET',
				'callback'            => array( $router, 'handle_sse_request' ),
				'permission_callback' => array( self::class, 'secure_permission_callback' ),
			)
		);
		register_rest_route(
			'mcp',
			'/mcp-adapter-default-server',
			array(
				'methods'             => 'POST',
				'callback'            => array( $router, 'handle_request' ),
				'permission_callback' => array( self::class, 'secure_permission_callback' ),
			)
		);
		register_rest_route(
			'mcp',
			'/mcp-adapter-default-server',
			array(
				'methods'             => 'GET',
				'callback'            => array( $router, 'handle_sse_request' ),
				'permission_callback' => array( self::class, 'secure_permission_callback' ),
			)
		);
	}

	public function handle_request( WP_REST_Request $request ) {
		$raw_body = (string) $request->get_body();
		$payload  = json_decode( $raw_body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
			return JsonRpcHelper::error( -32700, 'Parse error', null );
		}
		$validation = JsonRpcHelper::validateEnvelope( $payload );
		if ( is_wp_error( $validation ) ) {
			$error_data = $validation->get_error_data();
			$code       = isset( $error_data['code'] ) ? (int) $error_data['code'] : -32600;
			$id         = isset( $payload['id'] ) ? $payload['id'] : null;
			return JsonRpcHelper::error( $code, $validation->get_error_message(), $id );
		}
		$method = (string) $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();
		$id     = isset( $payload['id'] ) ? $payload['id'] : null;
		switch ( $method ) {
			case 'initialize':
				return $this->handle_initialize( $params, $id );
			case 'tools/list':
				return $this->handle_tools_list( $request, $params, $id );
			case 'tools/call':
				return $this->handle_tools_call( $request, $params, $id );
			default:
				return JsonRpcHelper::error( -32601, 'Method not found', $id );
		}
	}

	public function handle_initialize( array $params, $id ) {
		$meta       = array(
			'client'  => isset( $params['client'] ) ? sanitize_text_field( (string) $params['client'] ) : '',
			'version' => isset( $params['version'] ) ? sanitize_text_field( (string) $params['version'] ) : '',
		);
		$session_id = SessionManager::create( $meta );
		return JsonRpcHelper::success(
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

	public function handle_tools_list( WP_REST_Request $request, array $params, $id ) {
		$include_internal           = PolicyHelper::is_internal_allowed( $request );
		$requested_include_internal = false;
		foreach ( array( 'include_internal', 'includeinternal', 'includeInternal' ) as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$requested_include_internal = filter_var( $params[ $key ], FILTER_VALIDATE_BOOLEAN );
				break;
			}
		}
		if ( $requested_include_internal && AccessHelper::current_user_can_use_mcp() ) {
			$include_internal = true;
		}
		$category = '';
		foreach ( array( 'category', 'Category' ) as $key ) {
			if ( array_key_exists( $key, $params ) && is_string( $params[ $key ] ) ) {
				$category = sanitize_text_field( $params[ $key ] );
				break;
			}
		}
		$payload          = ToolRegistry::list_tools( $include_internal, $category );
		$tools            = isset( $payload['tools'] ) && is_array( $payload['tools'] ) ? $payload['tools'] : array();
		$registered_total = isset( $payload['registered_total'] ) ? (int) $payload['registered_total'] : count( $tools );
		$tools            = array_values(
			array_filter(
				$tools,
				function ( $tool ) use ( $request, $params ) {
					return is_array( $tool ) && PolicyHelper::is_public_allowed( $tool, $request, $params );
				}
			)
		);
		return JsonRpcHelper::success(
			array(
				'tools' => $tools,
				'meta'  => array(
					'registered_total'     => $registered_total,
					'returned_total'       => count( $tools ),
					'include_internal'     => $include_internal,
					'category_filter'      => $category,
					'feature_support_hint' => $this->build_missing_feature_guidance_message(),
				),
			),
			$id
		);
	}

	public function handle_tools_call( WP_REST_Request $request, array $params, $id ) {
		$tool_name = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		$arguments = array();
		if ( isset( $params['arguments'] ) && is_array( $params['arguments'] ) ) {
			$arguments = $params['arguments'];
		}

		$session_id = $this->resolve_session_id( $request, $params );
		$audit_base = array(
			'tool_name'  => $tool_name,
			'arguments'  => $arguments,
			'session_id' => $session_id,
			'client'     => $this->resolve_session_client( $session_id ),
		);

		$security = SecurityHelper::validate( $request );
		if ( is_wp_error( $security ) ) {
			return $this->audit_and_error( $request, $audit_base, 'denied', -32001, $security->get_error_message(), $id, array( 'code' => $security->get_error_code() ) );
		}
		if ( $session_id === '' || ! SessionManager::validate( $session_id ) ) {
			return $this->audit_and_error( $request, $audit_base, 'denied', -32002, 'Invalid or missing session', $id, array( 'code' => 'webo_mcp_invalid_session' ) );
		}
		if ( $tool_name === '' ) {
			return $this->audit_and_error( $request, $audit_base, 'error', -32602, 'Invalid params: missing tool name', $id, array( 'code' => 'webo_mcp_missing_tool_name' ) );
		}
		$tool_definition = ToolRegistry::get( $tool_name );
		if ( ! $tool_definition ) {
			return $this->audit_and_error( $request, $audit_base, 'error', -32602, $this->build_missing_feature_guidance_message( $tool_name ), $id, array( 'code' => 'tool_not_found' ) );
		}
		if ( ToolRegistry::is_internal( $tool_name ) && ! PolicyHelper::is_internal_allowed( $request ) ) {
			return $this->audit_and_error( $request, $audit_base, 'denied', -32001, 'Internal tool is not available for this client', $id, array( 'code' => 'webo_mcp_internal_tool_denied' ) );
		}
		if ( ! PolicyHelper::is_public_allowed( $tool_definition, $request, $params ) ) {
			return $this->audit_and_error( $request, $audit_base, 'denied', -32001, 'Tool is not allowed by MCP policy', $id, array( 'code' => 'webo_mcp_tool_policy_denied' ) );
		}
		if ( isset( $params['arguments'] ) ) {
			if ( ! is_array( $params['arguments'] ) ) {
				return $this->audit_and_error( $request, $audit_base, 'error', -32602, 'Invalid params: arguments must be an object', $id, array( 'code' => 'webo_mcp_invalid_arguments' ) );
			}
			$arguments = $params['arguments'];
		}
		try {
			$result = ToolRegistry::call( $tool_name, $arguments );
		} catch ( \Exception $exception ) {
			return $this->audit_and_error( $request, $audit_base, 'error', -32003, $exception->getMessage(), $id, array( 'code' => 'webo_mcp_tool_exception' ) );
		}
		if ( is_wp_error( $result ) ) {
			$code    = $result->get_error_code();
			$data    = ( $code && $code !== 'webo_mcp_unknown' ) ? array( 'code' => $code ) : null;
			$message = $result->get_error_message();

			if ( $code === 'webo_mcp_unknown' || $code === 'tool_not_found' ) {
				$message = $this->build_missing_feature_guidance_message( $tool_name );
				$data    = array( 'code' => 'tool_not_found' );
			}

			return $this->audit_and_error( $request, $audit_base, 'error', -32003, $message, $id, is_array( $data ) ? $data : array() );
		}

		Audit_Log::record(
			$request,
			array_merge(
				$audit_base,
				array(
					'status' => 'success',
					'result' => $result,
				)
			)
		);

		return JsonRpcHelper::success( $result, $id );
	}

	/**
	 * Record a tool call failure and return a JSON-RPC error response.
	 *
	 * @param WP_REST_Request      $request    Request.
	 * @param array<string, mixed> $audit_base Base audit context.
	 * @param string               $status     Audit status.
	 * @param int                  $code       JSON-RPC error code.
	 * @param string               $message    Error message.
	 * @param mixed                $id         JSON-RPC ID.
	 * @param array<string, mixed> $data       JSON-RPC data.
	 * @return mixed
	 */
	private function audit_and_error( WP_REST_Request $request, array $audit_base, string $status, int $code, string $message, $id, array $data = array() ) {
		Audit_Log::record(
			$request,
			array_merge(
				$audit_base,
				array(
					'status'        => $status,
					'error_code'    => isset( $data['code'] ) ? (string) $data['code'] : '',
					'error_message' => $message,
				)
			)
		);

		return JsonRpcHelper::error( $code, $message, $id, empty( $data ) ? null : $data );
	}

	/**
	 * Build a consistent hint for missing-feature cases.
	 *
	 * @param string $tool_name Tool name if requested.
	 * @return string
	 */
	private function build_missing_feature_guidance_message( string $tool_name = '' ): string {
		$base  = __( 'Requested feature is not available on this site.', 'webo-mcp' );
		$guide = __( 'Run tools/list to see installed capabilities, then install a matching WEBO MCP addon if needed, or contact dev@webomcp.com for guidance.', 'webo-mcp' );

		if ( $tool_name === '' ) {
			return $base . ' ' . $guide;
		}

		/* translators: %s: tool name requested by MCP client. */
		$with_tool = sprintf( __( 'Requested feature "%s" is not available on this site.', 'webo-mcp' ), $tool_name );

		return $with_tool . ' ' . $guide;
	}

	/**
	 * SSE endpoint for MCP clients that prefer a streaming channel.
	 *
	 * Query args:
	 * - session_id: required; may also be provided via Mcp-Session-Id header.
	 * - wait: optional seconds to keep stream open for heartbeats (0-25, default 0).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return void|WP_Error
	 */
	public function handle_sse_request( WP_REST_Request $request ) {
		$session_id = $this->resolve_session_id( $request );
		if ( $session_id === '' || ! SessionManager::validate( $session_id ) ) {
			return new WP_Error(
				'webo_mcp_invalid_session',
				__( 'Missing or invalid session_id. Call initialize first and pass session_id (query) or Mcp-Session-Id header.', 'webo-mcp' ),
				array( 'status' => 400 )
			);
		}

		$wait = (int) $request->get_param( 'wait' );
		if ( $wait < 0 ) {
			$wait = 0;
		}
		if ( $wait > 25 ) {
			$wait = 25;
		}

		@ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( max( 30, $wait + 10 ) );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		header( 'Cache-Control: no-cache, no-transform' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		header( 'Connection: keep-alive' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		header( 'X-Accel-Buffering: no' );

		echo "retry: 3000\n";
		echo "event: ready\n";
		echo 'data: ' . wp_json_encode(
			array(
				'session_id'   => $session_id,
				'timestamp'    => time(),
				'capabilities' => array( 'tools/list', 'tools/call' ),
			)
		) . "\n\n";
		@ob_flush();
		@flush();

		$elapsed = 0;
		while ( $elapsed < $wait && ! connection_aborted() ) {
			sleep( 5 );
			$elapsed += 5;
			echo ': keepalive ' . $elapsed . "\n\n";
			@ob_flush();
			@flush();
		}

		echo "event: end\n";
		echo 'data: ' . wp_json_encode( array( 'ok' => true ) ) . "\n\n";
		@ob_flush();
		@flush();

		exit;
	}

	/**
	 * Resolve session ID from params/query/header.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $params  JSON-RPC params.
	 * @return string
	 */
	private function resolve_session_id( WP_REST_Request $request, array $params = array() ): string {
		$session_id = '';
		if ( isset( $params['session_id'] ) ) {
			$session_id = sanitize_text_field( (string) $params['session_id'] );
		}
		if ( $session_id === '' ) {
			$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
		}
		if ( $session_id === '' ) {
			$session_id = sanitize_text_field( (string) $request->get_header( 'Mcp-Session-Id' ) );
		}

		return $session_id;
	}

	/**
	 * Resolve the sanitized MCP client label from session metadata.
	 *
	 * @param string $session_id Session ID.
	 * @return string
	 */
	private function resolve_session_client( string $session_id ): string {
		$session = SessionManager::get( $session_id );
		if ( ! is_array( $session ) || ! isset( $session['client'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $session['client'] );
	}

	public static function secure_permission_callback( WP_REST_Request $request ) {
		if ( ! AccessHelper::try_authenticate_application_password( $request ) ) {
			return new WP_Error(
				'webo_mcp_permission_denied',
				__( 'Authenticate with a WordPress Application Password (HTTP Basic) or a logged-in session. API keys and HMAC do not replace login.', 'webo-mcp' ),
				array( 'status' => 401 )
			);
		}

		$secondary = AccessHelper::validate_secondary_credentials( $request );
		if ( is_wp_error( $secondary ) ) {
			return $secondary;
		}

		$gate = AccessHelper::require_mcp_access_or_error();
		return is_wp_error( $gate ) ? $gate : true;
	}
}
