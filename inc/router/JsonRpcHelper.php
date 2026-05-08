<?php

namespace WeboMCP\Core\Router;

class JsonRpcHelper {
	public static function success($result, $id) {
		return [
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $id,
		];
	}

	public static function error($code, $message, $id = null, $data = null) {
		$error = [
			'code' => $code,
			'message' => $message,
		];
		if ($data !== null) {
			$error['data'] = $data;
		}
		return [
			'jsonrpc' => '2.0',
			'error' => $error,
			'id' => $id,
		];
	}

	public static function validateEnvelope(array $payload) {
		if (!isset($payload['jsonrpc']) || $payload['jsonrpc'] !== '2.0') {
			return new \WP_Error('webo_mcp_invalid_jsonrpc_version', 'Invalid Request: jsonrpc must be "2.0"', ['code' => -32600]);
		}
		if (!isset($payload['method']) || !is_string($payload['method']) || trim($payload['method']) === '') {
			return new \WP_Error('webo_mcp_invalid_method', 'Invalid Request: method is required', ['code' => -32600]);
		}
		if (isset($payload['params']) && !is_array($payload['params'])) {
			return new \WP_Error('webo_mcp_invalid_params', 'Invalid Request: params must be an object', ['code' => -32600]);
		}
		return true;
	}
}
