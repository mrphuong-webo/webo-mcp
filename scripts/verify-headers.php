<?php
/**
 * Standalone: replicate WP 6.9 get_file_data + _cleanup_header_comment.
 */
define( 'KB_IN_BYTES', 1024 );

function _cleanup_header_comment( $str ) {
	return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $str ) );
}

function get_file_data( $file, $default_headers, $context = '' ) {
	$file_data = file_get_contents( $file, false, null, 0, 8 * KB_IN_BYTES );
	if ( false === $file_data ) {
		$file_data = '';
	}
	$file_data     = str_replace( "\r", "\n", $file_data );
	$extra_headers = array(); // skip filters in CLI
	$all_headers   = $default_headers;
	foreach ( $all_headers as $field => $regex ) {
		$pattern = '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi';
		if ( preg_match( $pattern, $file_data, $match ) && $match[1] ) {
			$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
		} else {
			$all_headers[ $field ] = '';
		}
	}
	return $all_headers;
}

$file            = dirname( __DIR__ ) . '/webo-mcp.php';
$default_headers = array(
	'Name'            => 'Plugin Name',
	'PluginURI'       => 'Plugin URI',
	'Version'         => 'Version',
	'Description'     => 'Description',
	'Author'          => 'Author',
	'AuthorURI'       => 'Author URI',
	'TextDomain'      => 'Text Domain',
	'DomainPath'      => 'Domain Path',
	'Network'         => 'Network',
	'RequiresWP'      => 'Requires at least',
	'RequiresPHP'     => 'Requires PHP',
	'UpdateURI'       => 'Update URI',
	'RequiresPlugins' => 'Requires Plugins',
	'License'         => 'License',
	'LicenseURI'      => 'License URI',
);
$data            = get_file_data( $file, $default_headers, 'plugin' );
foreach ( array( 'Name', 'Description', 'Version', 'License' ) as $k ) {
	echo "$k => " . var_export( $data[ $k ], true ) . PHP_EOL;
}
