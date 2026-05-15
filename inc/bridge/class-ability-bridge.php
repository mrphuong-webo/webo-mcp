<?php
/**
 * Compact WordPress Abilities bridge for WEBO MCP.
 *
 * @package WeboMCP
 */

namespace WeboMCP\Core\Bridge;

use WeboMCP\Core\Router\ClientToolPolicy;
use WeboMCP\Core\Router\PolicyHelper;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges public WordPress abilities through compact WEBO MCP tools.
 */
final class Ability_Bridge {
	const MODE_OFF     = 'off';
	const MODE_LAYERED = 'layered';
	const MODE_FULL    = 'full';

	/**
	 * Get active bridge mode.
	 *
	 * Precedence: constant > filter > option > default.
	 *
	 * @return string
	 */
	public static function get_mode() {
		if ( defined( 'WEBO_MCP_BRIDGE_MODE' ) ) {
			$mode = WEBO_MCP_BRIDGE_MODE;
		} else {
			$mode = apply_filters( 'webo_mcp_bridge_mode', get_option( 'webo_mcp_bridge_mode', self::MODE_LAYERED ) );
		}
		$mode = is_scalar( $mode ) ? sanitize_key( (string) $mode ) : self::MODE_LAYERED;

		return in_array( $mode, array( self::MODE_OFF, self::MODE_LAYERED, self::MODE_FULL ), true ) ? $mode : self::MODE_LAYERED;
	}

	/**
	 * Sanitize stored bridge mode.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_mode( $value ) {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : self::MODE_LAYERED;
		return in_array( $value, array( self::MODE_OFF, self::MODE_LAYERED, self::MODE_FULL ), true ) ? $value : self::MODE_LAYERED;
	}

	/**
	 * Whether the bridge should run.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return self::MODE_OFF !== self::get_mode() && (bool) apply_filters( 'webo_mcp_auto_bridge_abilities', true );
	}

	/**
	 * Return all registered abilities.
	 *
	 * @return array<string, object>
	 */
	public static function get_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = wp_get_abilities();
		return is_array( $abilities ) ? $abilities : array();
	}

	/**
	 * Query public abilities through a compact tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function query( array $arguments ) {
		$action = isset( $arguments['action'] ) ? sanitize_key( (string) $arguments['action'] ) : 'list';

		switch ( $action ) {
			case 'list':
				return array(
					'mode'      => self::get_mode(),
					'abilities' => array_values( array_map( array( __CLASS__, 'summarize_ability' ), self::public_abilities() ) ),
				);

			case 'get':
			case 'schema':
				$ability_name = isset( $arguments['ability'] ) ? sanitize_text_field( (string) $arguments['ability'] ) : '';
				$ability      = self::get_public_ability( $ability_name );
				if ( is_wp_error( $ability ) ) {
					return $ability;
				}

				$summary = self::summarize_ability( $ability );
				if ( 'schema' === $action ) {
					return array(
						'ability'       => $summary['name'],
						'input_schema'  => method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array(),
						'output_schema' => method_exists( $ability, 'get_output_schema' ) ? $ability->get_output_schema() : array(),
						'meta'          => self::public_meta( $ability ),
					);
				}

				return array_merge(
					$summary,
					array(
						'input_schema'  => method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array(),
						'output_schema' => method_exists( $ability, 'get_output_schema' ) ? $ability->get_output_schema() : array(),
						'meta'          => self::public_meta( $ability ),
					)
				);

			case 'categories':
				return array(
					'categories' => self::categories(),
				);
		}

		return new WP_Error( 'webo_mcp_invalid_ability_query', __( 'Invalid ability query action.', 'webo-mcp' ) );
	}

	/**
	 * Execute a public ability through the compact tool.
	 *
	 * @param array<string, mixed> $arguments Tool arguments.
	 * @return mixed|WP_Error
	 */
	public static function execute( array $arguments ) {
		$ability_name = isset( $arguments['ability'] ) ? sanitize_text_field( (string) $arguments['ability'] ) : '';
		$input        = isset( $arguments['input'] ) && is_array( $arguments['input'] ) ? $arguments['input'] : array();
		$dry_run      = ! empty( $arguments['dry_run'] );
		$reason       = isset( $arguments['reason'] ) ? sanitize_text_field( (string) $arguments['reason'] ) : '';

		$ability = self::get_public_ability( $ability_name );
		if ( is_wp_error( $ability ) ) {
			return $ability;
		}

		if ( $dry_run ) {
			return array(
				'dry_run' => true,
				'allowed' => true,
				'ability' => self::summarize_ability( $ability ),
				'reason'  => $reason,
			);
		}

		return method_exists( $ability, 'execute' ) ? $ability->execute( $input ) : new WP_Error( 'webo_mcp_ability_not_executable', __( 'Ability is not executable.', 'webo-mcp' ) );
	}

	/**
	 * Authorize a bridged ability execution before ToolRegistry invokes it.
	 *
	 * @param string          $ability_name Ability name.
	 * @param array           $input        Ability input.
	 * @param WP_REST_Request $request      REST request.
	 * @param array           $params       JSON-RPC params.
	 * @param string          $tool_name    MCP tool name.
	 * @return true|WP_Error
	 */
	public static function authorize_execution( $ability_name, array $input, WP_REST_Request $request, array $params, $tool_name = 'webo/ability-execute' ) {
		$ability = self::get_public_ability( $ability_name );
		if ( is_wp_error( $ability ) ) {
			return $ability;
		}

		if ( ! PolicyHelper::is_public_allowed( array( 'name' => $tool_name, 'category' => 'wordpress', 'visibility' => 'public' ), $request, $params ) ) {
			return new WP_Error( 'webo_mcp_ability_tool_policy_denied', __( 'Ability tool is not allowed by MCP policy.', 'webo-mcp' ) );
		}

		$permission = method_exists( $ability, 'check_permissions' ) ? $ability->check_permissions( $input ) : true;
		if ( true !== $permission ) {
			return is_wp_error( $permission ) ? $permission : new WP_Error( 'webo_mcp_ability_permission_denied', __( 'Ability permission callback denied execution.', 'webo-mcp' ) );
		}

		if ( ! self::scope_risk_allowed( $ability, $request, $params, $tool_name ) ) {
			return new WP_Error( 'webo_mcp_ability_scope_risk_denied', __( 'Ability scope or risk requires explicit allowlist or administrator configuration.', 'webo-mcp' ) );
		}

		return true;
	}

	/**
	 * Public abilities only.
	 *
	 * @return array<string, object>
	 */
	public static function public_abilities() {
		$public = array();
		foreach ( self::get_abilities() as $ability ) {
			if ( is_object( $ability ) && self::is_public_for_mcp( $ability ) ) {
				$name = method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '';
				if ( '' !== $name && self::should_bridge_ability( $name ) ) {
					$public[ $name ] = $ability;
				}
			}
		}

		return $public;
	}

	/**
	 * Count public and hidden abilities.
	 *
	 * @return array<string, int>
	 */
	public static function counts() {
		$public = count( self::public_abilities() );
		$total  = 0;
		foreach ( self::get_abilities() as $ability ) {
			if ( is_object( $ability ) ) {
				++$total;
			}
		}

		return array(
			'public' => $public,
			'hidden' => max( 0, $total - $public ),
		);
	}

	/**
	 * Is an ability explicitly public for MCP.
	 *
	 * @param object $ability Ability instance.
	 * @return bool
	 */
	public static function is_public_for_mcp( $ability ) {
		$meta = self::meta( $ability );
		return isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && isset( $meta['mcp']['public'] ) && true === (bool) $meta['mcp']['public'];
	}

	/**
	 * Whether an ability should be bridged.
	 *
	 * @param string $ability_name Ability name.
	 * @return bool
	 */
	public static function should_bridge_ability( $ability_name ) {
		$ability_name = trim( (string) $ability_name );
		if ( '' === $ability_name ) {
			return false;
		}

		$deny_patterns = apply_filters(
			'webo_mcp_bridge_deny_patterns',
			array(
				'bulk',
				'plugins/',
				'themes/',
				'multisite/',
			)
		);

		$denied = false;
		if ( is_array( $deny_patterns ) ) {
			foreach ( $deny_patterns as $pattern ) {
				$pattern = trim( (string) $pattern );
				if ( '' !== $pattern && false !== strpos( $ability_name, $pattern ) ) {
					$denied = true;
					break;
				}
			}
		}

		return (bool) apply_filters( 'webo_mcp_should_bridge_ability', ! $denied, $ability_name );
	}

	/**
	 * Get one public ability.
	 *
	 * @param string $ability_name Ability name.
	 * @return object|WP_Error
	 */
	private static function get_public_ability( $ability_name ) {
		if ( '' === $ability_name || ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error( 'webo_mcp_ability_not_found', __( 'Ability not found.', 'webo-mcp' ) );
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! is_object( $ability ) ) {
			return new WP_Error( 'webo_mcp_ability_not_found', sprintf( __( 'Ability not found: %s', 'webo-mcp' ), $ability_name ) );
		}

		if ( ! self::is_public_for_mcp( $ability ) || ! self::should_bridge_ability( $ability_name ) ) {
			return new WP_Error( 'webo_mcp_ability_not_public', __( 'Ability is not public for MCP use.', 'webo-mcp' ) );
		}

		return $ability;
	}

	/**
	 * Summarize one ability.
	 *
	 * @param object $ability Ability instance.
	 * @return array<string, mixed>
	 */
	private static function summarize_ability( $ability ) {
		$meta      = self::meta( $ability );
		$webo_meta = isset( $meta['webo_mcp'] ) && is_array( $meta['webo_mcp'] ) ? $meta['webo_mcp'] : array();

		return array(
			'name'        => method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '',
			'label'       => method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : '',
			'description' => method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '',
			'category'    => method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : '',
			'scope'       => isset( $webo_meta['scope'] ) ? sanitize_key( (string) $webo_meta['scope'] ) : self::infer_scope( $ability ),
			'risk'        => isset( $webo_meta['risk'] ) ? sanitize_key( (string) $webo_meta['risk'] ) : self::infer_risk( $ability ),
		);
	}

	/**
	 * Return public metadata only.
	 *
	 * @param object $ability Ability instance.
	 * @return array<string, mixed>
	 */
	private static function public_meta( $ability ) {
		$meta = self::meta( $ability );
		return array(
			'mcp'      => isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) ? $meta['mcp'] : array(),
			'webo_mcp' => isset( $meta['webo_mcp'] ) && is_array( $meta['webo_mcp'] ) ? $meta['webo_mcp'] : array(),
		);
	}

	/**
	 * Get ability metadata.
	 *
	 * @param object $ability Ability instance.
	 * @return array<string, mixed>
	 */
	private static function meta( $ability ) {
		return method_exists( $ability, 'get_meta' ) && is_array( $ability->get_meta() ) ? $ability->get_meta() : array();
	}

	/**
	 * Build category counts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function categories() {
		$categories = array();
		foreach ( self::public_abilities() as $ability ) {
			$category = method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : 'wordpress';
			if ( ! isset( $categories[ $category ] ) ) {
				$categories[ $category ] = array(
					'name'  => $category,
					'count' => 0,
				);
			}
			++$categories[ $category ]['count'];
		}

		return array_values( $categories );
	}

	/**
	 * Infer scope from metadata annotations.
	 *
	 * @param object $ability Ability instance.
	 * @return string
	 */
	private static function infer_scope( $ability ) {
		$meta        = self::meta( $ability );
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
		if ( isset( $annotations['readonly'] ) && true === (bool) $annotations['readonly'] ) {
			return 'read';
		}

		return 'write';
	}

	/**
	 * Infer risk from metadata annotations.
	 *
	 * @param object $ability Ability instance.
	 * @return string
	 */
	private static function infer_risk( $ability ) {
		$meta        = self::meta( $ability );
		$annotations = isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ? $meta['annotations'] : array();
		if ( ! empty( $annotations['destructive'] ) ) {
			return 'high';
		}

		return 'low';
	}

	/**
	 * Scope/risk policy for bridged ability execution.
	 *
	 * @param object          $ability   Ability instance.
	 * @param WP_REST_Request $request   REST request.
	 * @param array           $params    JSON-RPC params.
	 * @param string          $tool_name Tool name.
	 * @return bool
	 */
	private static function scope_risk_allowed( $ability, WP_REST_Request $request, array $params, $tool_name ) {
		$summary = self::summarize_ability( $ability );
		$scope   = isset( $summary['scope'] ) ? (string) $summary['scope'] : 'read';
		$risk    = isset( $summary['risk'] ) ? (string) $summary['risk'] : 'low';

		$read_only = 'read' === $scope && in_array( $risk, array( 'low', 'medium' ), true );
		$allowed   = $read_only && current_user_can( 'read' );

		if ( ! $allowed ) {
			$allowed = current_user_can( 'manage_options' ) || current_user_can( 'manage_network_options' );
		}

		if ( ! $allowed && ClientToolPolicy::is_enabled() ) {
			$allowed = ClientToolPolicy::is_tool_allowed( array( 'name' => $tool_name ), $request, $params )
				|| ClientToolPolicy::is_tool_allowed( array( 'name' => (string) $summary['name'] ), $request, $params );
		}

		return (bool) apply_filters( 'webo_mcp_ability_scope_risk_allowed', $allowed, $summary, $request, $params );
	}
}
