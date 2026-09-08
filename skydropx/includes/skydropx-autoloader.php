<?php
/**
 * PSR-like autoloader for plugin classes and traits.
 *
 * Resolves fully qualified class names under Skydropx into file paths
 * using a simple convention: class names map to 'class-<lowercased-name>.php'
 * inside a folder structure matching the namespace.
 *
 * @package   Skydropx
 * @since     1.0.0
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $fqcn ) {
		// Only handle classes within the Skydropx namespace.
		if ( strpos( $fqcn, 'Skydropx' ) !== 0 ) {
			return;
		}

		// Convert the namespace into a file path format.
		$class_path = str_replace( '\\', '/', $fqcn );

		// Explode the class path into parts.
		$path_parts = explode( '/', $class_path );
		$classname  = array_pop( $path_parts );  // Extract the class or trait name.

		// Ensure the class name is not empty or undefined.
		if ( empty( $classname ) ) {
			return;
		}

		// Format the classname to remove any leading dashes or underscore issues.
		$classname = strtolower( $classname ); // Convert to lowercase.
		$classname = str_replace( '_', '-', $classname );

		// Create the dynamic directory path based on namespace.
		$folder_path = strtolower( implode( '/', $path_parts ) );

		// Handling empty folder path cases.
		if ( empty( $folder_path ) ) {
			$folder_path = ''; // No need to append an empty path, just use base plugin dir.
		} else {
			// Avoid double 'skydropx' by ensuring it's not already in the path.
			$plugin_slug_underscore = str_replace( '-', '_', 'skydropx' );
			if ( strpos( $folder_path, strtolower( $plugin_slug_underscore ) ) === 0 ) {
				// Remove the leading 'skydropx' if it's already included.
				$folder_path = str_replace( strtolower( $plugin_slug_underscore ) . '/', '', $folder_path );
			}
		}

		// Construct the full file path dynamically based on the namespace.
		$filename  = 'class-' . $classname . '.php';
		$file_path = rtrim( plugin_dir_path( __DIR__ ), '/' ) . '/' . trim( $folder_path, '/' ) . '/' . $filename;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);
