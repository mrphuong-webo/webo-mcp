<?php
/**
 * Focused compatibility tests for WordPress 7.0/Core-aware bridge logic.
 *
 * This intentionally uses tiny WordPress stubs so it can run without a full
 * database-backed WordPress install or PHPUnit.
 *
 * @package WeboMCP
 */

namespace {
define( 'ABSPATH', __DIR__ . '/../' );

$GLOBALS['webo_mcp_test_options'] = array();
$GLOBALS['webo_mcp_test_filters'] = array();
$GLOBALS['webo_mcp_test_caps']    = array( 'read' => true );
$GLOBALS['webo_mcp_test_abilities'] = array();

function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $text;
}

function sanitize_key( $key ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
}

function sanitize_text_field( $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return trim( (string) $value );
}

function get_option( $name, $default = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return array_key_exists( $name, $GLOBALS['webo_mcp_test_options'] ) ? $GLOBALS['webo_mcp_test_options'][ $name ] : $default;
}

function apply_filters( $hook_name, $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return array_key_exists( $hook_name, $GLOBALS['webo_mcp_test_filters'] ) ? $GLOBALS['webo_mcp_test_filters'][ $hook_name ] : $value;
}

function current_user_can( $capability ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return ! empty( $GLOBALS['webo_mcp_test_caps'][ $capability ] );
}

function is_wp_error( $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $value instanceof WP_Error;
}

function wp_get_abilities() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return $GLOBALS['webo_mcp_test_abilities'];
}

function wp_get_ability( $name ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return isset( $GLOBALS['webo_mcp_test_abilities'][ $name ] ) ? $GLOBALS['webo_mcp_test_abilities'][ $name ] : null;
}

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = (string) $code;
		$this->message = (string) $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class WP_REST_Request {}

class Webo_MCP_Test_Ability {
	private $name;
	private $meta;
	private $permission;

	public function __construct( $name, array $meta, $permission = true ) {
		$this->name       = $name;
		$this->meta       = $meta;
		$this->permission = $permission;
	}

	public function get_name() {
		return $this->name;
	}

	public function get_label() {
		return $this->name;
	}

	public function get_description() {
		return 'Test ability';
	}

	public function get_category() {
		return 'test';
	}

	public function get_input_schema() {
		return array();
	}

	public function get_output_schema() {
		return array();
	}

	public function get_meta() {
		return $this->meta;
	}

	public function check_permissions( $input = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return $this->permission;
	}

	public function execute( $input = null ) {
		return array( 'executed' => $this->name );
	}
}
}

namespace WeboMCP\Core\Router {
	class PolicyHelper {
		public static function is_public_allowed( array $tool, \WP_REST_Request $request, array $params = array() ) {
			return empty( $GLOBALS['webo_mcp_test_policy_denied'] );
		}
	}

	class ClientToolPolicy {
		public static function is_enabled() {
			return ! empty( $GLOBALS['webo_mcp_test_allowlist_enabled'] );
		}

		public static function is_tool_allowed( array $tool, \WP_REST_Request $request, array $params = array() ) {
			return ! empty( $GLOBALS['webo_mcp_test_allowlist_allowed'] );
		}
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/inc/bridge/class-ability-bridge.php';

	use WeboMCP\Core\Bridge\Ability_Bridge;

	function webo_mcp_test_assert( $condition, $message ) {
		if ( ! $condition ) {
			fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
			exit( 1 );
		}
		echo 'PASS: ' . $message . PHP_EOL;
	}

	$GLOBALS['webo_mcp_test_options']['webo_mcp_bridge_mode'] = 'full';
	webo_mcp_test_assert( Ability_Bridge::get_mode() === 'full', 'option controls bridge mode when no filter/constant exists' );

	$GLOBALS['webo_mcp_test_filters']['webo_mcp_bridge_mode'] = 'off';
	webo_mcp_test_assert( Ability_Bridge::get_mode() === 'off', 'filter overrides option' );

	define( 'WEBO_MCP_BRIDGE_MODE', 'layered' );
	$GLOBALS['webo_mcp_test_filters']['webo_mcp_bridge_mode'] = 'off';
	webo_mcp_test_assert( Ability_Bridge::get_mode() === 'layered', 'constant overrides filter and option' );

	$GLOBALS['webo_mcp_test_abilities'] = array(
		'public/read'   => new Webo_MCP_Test_Ability(
			'public/read',
			array(
				'mcp'      => array( 'public' => true ),
				'webo_mcp' => array(
					'scope' => 'read',
					'risk'  => 'low',
				),
			)
		),
		'private/read'  => new Webo_MCP_Test_Ability(
			'private/read',
			array(
				'mcp' => array( 'public' => false ),
			)
		),
		'public/admin'  => new Webo_MCP_Test_Ability(
			'public/admin',
			array(
				'mcp'      => array( 'public' => true ),
				'webo_mcp' => array(
					'scope' => 'admin',
					'risk'  => 'high',
				),
			)
		),
		'public/denied' => new Webo_MCP_Test_Ability(
			'public/denied',
			array(
				'mcp'      => array( 'public' => true ),
				'webo_mcp' => array(
					'scope' => 'read',
					'risk'  => 'low',
				),
			),
			false
		),
	);

	$list = Ability_Bridge::query( array( 'action' => 'list' ) );
	webo_mcp_test_assert( count( $list['abilities'] ) === 3, 'query lists only meta.mcp.public abilities' );

	$private = Ability_Bridge::authorize_execution( 'private/read', array(), new WP_REST_Request(), array() );
	webo_mcp_test_assert( is_wp_error( $private ) && $private->get_error_code() === 'webo_mcp_ability_not_public', 'private ability execution is denied' );

	$denied = Ability_Bridge::authorize_execution( 'public/denied', array(), new WP_REST_Request(), array() );
	webo_mcp_test_assert( is_wp_error( $denied ), 'ability permission callback denial blocks execution' );

	$admin = Ability_Bridge::authorize_execution( 'public/admin', array(), new WP_REST_Request(), array() );
	webo_mcp_test_assert( is_wp_error( $admin ) && $admin->get_error_code() === 'webo_mcp_ability_scope_risk_denied', 'admin/high-risk ability requires admin or explicit allowlist' );

	$GLOBALS['webo_mcp_test_caps']['manage_options'] = true;
	$admin_allowed = Ability_Bridge::authorize_execution( 'public/admin', array(), new WP_REST_Request(), array() );
	webo_mcp_test_assert( true === $admin_allowed, 'administrator may execute public admin/high-risk ability after permission check' );
}
