<?php
/**
 * JSON-RPC 2.0 helpers for the MCP router.
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Router;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JsonRpcHelper {

	/**
	 * Builds a successful JSON-RPC response.
	 *
	 * @param mixed $result Result payload.
	 * @param mixed $id     Request id.
	 * @return array<string, mixed>
	 */
	public static function success( $result, $id ) {
		return array(
			'jsonrpc' => '2.0',
			'result'  => $result,
			'id'      => $id,
		);
	}

	/**
	 * Builds an error JSON-RPC response.
	 *
	 * @param int        $code    JSON-RPC error code.
	 * @param string     $message Error message.
	 * @param mixed|null $id      Request id.
	 * @param mixed|null $data    Optional error data.
	 * @return array<string, mixed>
	 */
	public static function error( $code, $message, $id = null, $data = null ) {
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
	 * Validates a JSON-RPC request envelope.
	 *
	 * @param array<string, mixed> $payload Decoded request body.
	 * @return true|WP_Error
	 */
	public static function validateEnvelope( array $payload ) {
		if ( ! isset( $payload['jsonrpc'] ) || '2.0' !== $payload['jsonrpc'] ) {
			return new WP_Error(
				'webo_mcp_invalid_jsonrpc_version',
				'Invalid Request: jsonrpc must be "2.0"',
				array( 'code' => -32600 )
			);
		}
		if ( ! isset( $payload['method'] ) || ! is_string( $payload['method'] ) || '' === trim( $payload['method'] ) ) {
			return new WP_Error(
				'webo_mcp_invalid_method',
				'Invalid Request: method is required',
				array( 'code' => -32600 )
			);
		}
		if ( isset( $payload['params'] ) && ! is_array( $payload['params'] ) ) {
			return new WP_Error(
				'webo_mcp_invalid_params',
				'Invalid Request: params must be an object',
				array( 'code' => -32600 )
			);
		}

		return true;
	}
}
