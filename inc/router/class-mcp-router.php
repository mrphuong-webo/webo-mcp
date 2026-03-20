<?php

namespace WeboMCP\Core\Router;

use WeboMCP\Core\Registry\ToolRegistry;
use WeboMCP\Core\Session\SessionManager;
use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/JsonRpcHelper.php';
require_once __DIR__ . '/SecurityHelper.php';
require_once __DIR__ . '/PolicyHelper.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class McpRouter {
	public static function register_rest_endpoint() {
		$router = new self();
		register_rest_route(
			'mcp/v1',
			'/router',
			[
				'methods' => 'POST',
				'callback' => [ $router, 'handle_request' ],
				'permission_callback' => [ self::class, 'secure_permission_callback' ],
			]
		);
		register_rest_route(
			'mcp',
			'/mcp-adapter-default-server',
			[
				'methods' => 'POST',
				'callback' => [ $router, 'handle_request' ],
				'permission_callback' => [ self::class, 'secure_permission_callback' ],
			]
		);
	}

	public function handle_request( WP_REST_Request $request ) {
		$raw_body = (string) $request->get_body();
		$payload = json_decode( $raw_body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
			return JsonRpcHelper::error( -32700, 'Parse error', null );
		}
		$validation = JsonRpcHelper::validateEnvelope( $payload );
		if ( is_wp_error( $validation ) ) {
			$error_data = $validation->get_error_data();
			$code = isset( $error_data['code'] ) ? (int) $error_data['code'] : -32600;
			$id = isset( $payload['id'] ) ? $payload['id'] : null;
			return JsonRpcHelper::error( $code, $validation->get_error_message(), $id );
		}
		$method = (string) $payload['method'];
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : [];
		$id = isset( $payload['id'] ) ? $payload['id'] : null;
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
		$meta = [
			'client' => isset( $params['client'] ) ? sanitize_text_field( (string) $params['client'] ) : '',
			'version' => isset( $params['version'] ) ? sanitize_text_field( (string) $params['version'] ) : '',
		];
		$session_id = SessionManager::create( $meta );
		return JsonRpcHelper::success([
			'session_id' => $session_id,
			'capabilities' => [
				'tools' => true,
				'methods' => [ 'initialize', 'tools/list', 'tools/call' ],
			],
		], $id );
	}

	public function handle_tools_list( WP_REST_Request $request, array $params, $id ) {
		$include_internal = PolicyHelper::is_internal_allowed( $request );
		$requested_include_internal = false;
		foreach ( [ 'include_internal', 'includeinternal', 'includeInternal' ] as $key ) {
			if ( array_key_exists( $key, $params ) ) {
				$requested_include_internal = filter_var( $params[$key], FILTER_VALIDATE_BOOLEAN );
				break;
			}
		}
		if ( $requested_include_internal && current_user_can( 'manage_options' ) ) {
			$include_internal = true;
		}
		$category = '';
		foreach ( [ 'category', 'Category' ] as $key ) {
			if ( array_key_exists( $key, $params ) && is_string( $params[ $key ] ) ) {
				$category = sanitize_text_field( $params[ $key ] );
				break;
			}
		}
		$all_payload = ToolRegistry::list_tools( true, '' );
		$payload = ToolRegistry::list_tools( $include_internal, $category );
		$all_tools = isset( $all_payload['tools'] ) && is_array( $all_payload['tools'] ) ? $all_payload['tools'] : [];
		$tools = isset( $payload['tools'] ) && is_array( $payload['tools'] ) ? $payload['tools'] : [];
		$tools = array_values( array_filter( $tools, function( $tool ) use ( $request ) {
			return is_array( $tool ) && PolicyHelper::is_public_allowed( $tool, $request );
		} ) );
		return JsonRpcHelper::success([
			'tools' => $tools,
			'meta' => [
				'registered_total' => count( $all_tools ),
				'returned_total' => count( $tools ),
				'include_internal' => $include_internal,
				'category_filter' => $category,
			],
		], $id );
	}

	public function handle_tools_call( WP_REST_Request $request, array $params, $id ) {
		$security = SecurityHelper::validate( $request );
		if ( is_wp_error( $security ) ) {
			return JsonRpcHelper::error( -32001, $security->get_error_message(), $id );
		}
		$session_id = '';
		if ( isset( $params['session_id'] ) ) {
			$session_id = sanitize_text_field( (string) $params['session_id'] );
		}
		if ( $session_id === '' ) {
			$session_id = sanitize_text_field( (string) $request->get_header( 'Mcp-Session-Id' ) );
		}
		if ( $session_id === '' || ! SessionManager::validate( $session_id ) ) {
			return JsonRpcHelper::error( -32002, 'Invalid or missing session', $id );
		}
		$tool_name = isset( $params['name'] ) ? sanitize_text_field( (string) $params['name'] ) : '';
		if ( $tool_name === '' ) {
			return JsonRpcHelper::error( -32602, 'Invalid params: missing tool name', $id );
		}
		$tool_definition = ToolRegistry::get( $tool_name );
		if ( ! $tool_definition ) {
			return JsonRpcHelper::error( -32602, 'Tool not found', $id );
		}
		if ( ToolRegistry::is_internal( $tool_name ) && ! PolicyHelper::is_internal_allowed( $request ) ) {
			return JsonRpcHelper::error( -32001, 'Internal tool is not available for this client', $id );
		}
		if ( ! PolicyHelper::is_public_allowed( $tool_definition, $request ) ) {
			return JsonRpcHelper::error( -32001, 'Tool is not allowed by public policy', $id );
		}
		$arguments = [];
		if ( isset( $params['arguments'] ) ) {
			if ( ! is_array( $params['arguments'] ) ) {
				return JsonRpcHelper::error( -32602, 'Invalid params: arguments must be an object', $id );
			}
			$arguments = $params['arguments'];
		}
		try {
			$result = ToolRegistry::call( $tool_name, $arguments );
		} catch ( \Exception $exception ) {
			return JsonRpcHelper::error( -32003, $exception->getMessage(), $id );
		}
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$data = ( $code && $code !== 'webo_mcp_unknown' ) ? array( 'code' => $code ) : null;
			return JsonRpcHelper::error( -32003, $result->get_error_message(), $id, $data );
		}
		return JsonRpcHelper::success( $result, $id );
	}

	public static function secure_permission_callback( WP_REST_Request $request ) {
		$maybe_set_admin_user = static function () {
			static $admin_id = null;
			if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
				if ( null === $admin_id ) {
					$admin = get_users(
						array(
							'role'    => 'administrator',
							'number'  => 1,
							'orderby' => 'ID',
						)
					);
					$admin_id = ! empty( $admin ) ? (int) $admin[0]->ID : 0;
				}
				if ( $admin_id > 0 ) {
					wp_set_current_user( $admin_id );
				}
			}
		};

		// Allow HMAC-signed requests through the REST permission callback.
		// The router will still enforce per-method security and tool policy.
		$configured_hmac_secret = (string) get_option( 'webo_mcp_hmac_secret', '' );
		$signature              = (string) $request->get_header( 'X-WEBO-SIGNATURE' );
		$timestamp              = (string) $request->get_header( 'X-WEBO-TIMESTAMP' );
		if ( $configured_hmac_secret !== '' && $signature !== '' && $timestamp !== '' && ctype_digit( $timestamp ) ) {
			$now           = time();
			$request_epoch = (int) $timestamp;
			if ( abs( $now - $request_epoch ) <= 300 ) {
				$payload            = $timestamp . '.' . (string) $request->get_body();
				$expected_signature = 'sha256=' . hash_hmac( 'sha256', $payload, $configured_hmac_secret );
				if ( hash_equals( $expected_signature, trim( $signature ) ) ) {
					$maybe_set_admin_user();
					return true;
				}
			}
		}

		$provided_api_key = (string) $request->get_header( 'X-WEBO-API-KEY' );
		if ( $provided_api_key !== '' ) {
			$configured_key = (string) get_option( 'webo_mcp_api_key', '' );
			if ( $configured_key !== '' && hash_equals( $configured_key, trim( $provided_api_key ) ) ) {
				// API key from Settings (option): set first admin as current user so capability checks pass.
				$maybe_set_admin_user();
			} else {
				$key = trim( $provided_api_key );
				$users = get_users(
					array(
						'meta_key'     => 'webo_mcp_api_key',
						'meta_value'   => $key,
						'meta_compare' => '=',
						'number'       => 1,
						'fields'       => 'ID',
					)
				);
				if ( ! empty( $users ) && isset( $users[0] ) ) {
					wp_set_current_user( (int) $users[0] );
				}
			}
		}

		// If still not authenticated, try Basic Auth (username + Application Password).
		if ( ! current_user_can( 'read' ) && function_exists( 'wp_authenticate_application_password' ) ) {
			$auth_header = (string) $request->get_header( 'Authorization' );
			if ( strpos( $auth_header, 'Basic ' ) === 0 ) {
				$encoded = trim( substr( $auth_header, 6 ) );
				if ( $encoded !== '' ) {
					$decoded = base64_decode( $encoded, true );
					if ( $decoded !== false ) {
						$colon = strpos( $decoded, ':' );
						if ( $colon !== false ) {
							$username = substr( $decoded, 0, $colon );
							$password = substr( $decoded, $colon + 1 );
							if ( $username !== '' && $password !== '' ) {
								$user = wp_authenticate_application_password( null, $username, $password );
								if ( $user instanceof \WP_User && ! is_wp_error( $user ) ) {
									wp_set_current_user( $user->ID );
								}
							}
						}
					}
				}
			}
		}

		if ( current_user_can( 'read' ) ) {
			return true;
		}
		return new WP_Error( 'webo_mcp_permission_denied', 'User does not have required capability.' );
	}
}
