<?php
/**
 * MCP HTTP Transport for WordPress - MCP 2025-06-18 Compliant
 *
 * @package McpAdapter
 */
declare( strict_types=1 );

namespace WP\MCP\Transport\Infrastructure;

/**
 * A simple data carrier for HTTP request context.
 * Properties are public for easy access but should be treated as read-only after construction.
 */
class HttpRequestContext {

	/**
	 * The original WordPress REST request object.
	 *
	 * @var \WP_REST_Request<array<string, mixed>>
	 */
	public \WP_REST_Request $request;

	/**
	 * The HTTP method of the request.
	 *
	 * @var string
	 */
	public string $method;


	/**
	 * The Mcp-Session-Id header from the request.
	 *
	 * @var string|null
	 */
	public ?string $session_id;

	/**
	 * The JSON-decoded body of the request.
	 *
	 * @var array|null
	 */
	public ?array $body;

	/**
	 * The Accept header from the request.
	 *
	 * @var string|null
	 */
	public ?string $accept_header;

	/**
	 * Constructor.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request The original request object.
	 */
	public function __construct( \WP_REST_Request $request ) {
		$this->request       = $request;
		$this->method        = $request->get_method();
		$this->accept_header = $request->get_header( 'accept' );
		$this->body          = 'POST' === $this->method ? $request->get_json_params() : null;

		$header_session_id = $request->get_header( 'Mcp-Session-Id' );
		if ( is_string( $header_session_id ) && '' !== trim( $header_session_id ) ) {
			$this->session_id = trim( $header_session_id );
			return;
		}

		/*
		 * Compatibility fallback: some MCP clients send session_id inside JSON-RPC
		 * params (tools/call, resources/read, prompts/get) instead of HTTP header.
		 */
		$body_params = is_array( $this->body ) && isset( $this->body['params'] ) && is_array( $this->body['params'] )
			? $this->body['params']
			: array();

		if ( isset( $body_params['session_id'] ) && is_string( $body_params['session_id'] ) && '' !== trim( $body_params['session_id'] ) ) {
			$this->session_id = trim( $body_params['session_id'] );
			return;
		}

		$this->session_id = null;
	}
}
