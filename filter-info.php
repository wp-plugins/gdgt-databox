<?php

/**
 * Display information about WordPress filters
 *
 * @since 1.1
 */
class GDGT_Filter_Info {

	/**
	 * List all active plugins
	 *
	 * @since 1.1
	 * @return array associative array. plugin file, plugin data
	 */
	public static function active_plugins() {
		$active_plugins = array();
		foreach ( (array) apply_filters( 'all_plugins', get_plugins() ) as $plugin_file => $plugin_data ) {
			if ( is_plugin_active( $plugin_file ) )
				$active_plugins[$plugin_file] = $plugin_data;
		}
		return $active_plugins;
	}

	/**
	 * Convert a filter argument an array with information about the function, its containing file, and location within the file
	 *
	 * @since 1.1
	 * @param mixed $function function argument passed to add_filter
	 * @return array associative array containing a string representation of the filter function, its containing filename, and the function's start line
	 */
	public static function filter_function_name( $function ) {
		if ( is_string( $function ) ) {
			$r = new ReflectionFunction( $function );
			return array( 'filename' => $r->getFileName(), 'startline' => $r->getStartLine(), 'function'=> $function );
		}

		if ( is_object( $function ) ) {
			// Closures are currently implemented as objects
			$function = array( $function, '' );
		} else {
			$function = (array) $function;
		}

		if ( is_object( $function[0] ) || is_string( $function[0] ) ) {
			$r = new ReflectionMethod( $function[0], $function[1] );
			$info = array( 'filename' => $r->getFileName(), 'startline' => $r->getStartLine() );
			if ( isset( $function[0]->wp_filter_id ) && is_string( $function[0]->wp_filter_id ) )
				$info['function'] = $function[0]->wp_filter_id;
			else
				$info['function'] = get_class( $function[0] ) . '->' . $function[1];
			return $info;
		}
	}

	/**
	 * HTML label for a plugin function
	 *
	 * @since 1.1
	 * @param array $function_info associative array with information about the function filtering the_content. keys: filename, function
	 * @param array $active_plugins associative array of active plugins for the current site. Key is the plugin file path and the value is an array of the plugin data.
	 * @return string HTML string
	 */
	public static function plugin_list_item_html( array $function_info, array $active_plugins ) {
		if ( ! empty( $active_plugins ) ) {
			// extract just the path to file relative to the plugin directory
			$plugin_filename = plugin_basename( $function_info['filename'] );
			if ( $plugin_filename !== $function_info['filename'] ) {
				// try to identify the plugin slug for comparison against active plugins
				// a function may exist in plugin-slug/settings/users.php and we want to compare to plugin-slug/plugin.php
				$first_directory_separator = strpos( $plugin_filename, DIRECTORY_SEPARATOR );
				if ( $first_directory_separator !== false ) {
					$plugin_top_directory = substr( $plugin_filename, 0, $first_directory_separator + 1 );
					if ( $plugin_top_directory !== false ) {
						$plugin_top_directory_length = strlen( $plugin_top_directory );
						$active_plugins_paths = array_keys( $active_plugins );
						foreach ( $active_plugins_paths as $active_plugin_path ) {
							// does active plugin path begin with our plugin file's top directory?
							if ( substr_compare( $plugin_top_directory, $active_plugin_path, 0, $plugin_top_directory_length ) === 0 && array_key_exists( 'Name', $active_plugins[$active_plugin_path] ) && ! empty( $active_plugins[$active_plugin_path]['Name'] ) ) {
								$line = '<td>';
								if ( array_key_exists( 'PluginURI', $active_plugins[$active_plugin_path] ) && ! empty( $active_plugins[$active_plugin_path]['PluginURI'] ) ) {
									$line .= '<a href="' . esc_url( $active_plugins[$active_plugin_path]['PluginURI'] ) . '"';
									if ( array_key_exists( 'Description', $active_plugins[$active_plugin_path] ) && ! empty( $active_plugins[$active_plugin_path]['Description'] ) )
										$line .= ' title="' . esc_attr( strip_tags( $active_plugins[$active_plugin_path]['Description'] ) ) . '"';
									$line .= '>' . esc_html( $active_plugins[$active_plugin_path]['Name'] ) . '</a>';
								} else {
									$line .= esc_html( $active_plugins[$active_plugin_path]['Name'] );
								}
								$line .= '</td><th scope="row">';
								if ( array_key_exists( 'Version', $active_plugins[$active_plugin_path] ) && ! empty( $active_plugins[$active_plugin_path]['Version'] ) ) {
									$line .= '<a href="';
									$line .= esc_url( 'http://plugins.trac.wordpress.org/browser/' . $plugin_top_directory . 'tags/' . $active_plugins[$active_plugin_path]['Version'] . '/' . substr( $plugin_filename, $first_directory_separator + 1 ) . '#L' . absint( $function_info['startline'] ), array( 'http', 'https' ) );
									$line .= '" title="' . esc_attr( __( 'View source code in WordPress plugin repository (this sometimes works)', 'gdgt-databox' ) ) . '">' . esc_html( $function_info['function'] ) . '</a>';
								} else {
									$line .= esc_html( $function_info['function'] );
								}
								$line .= '</th>';
								return $line;
							}
						}
						unset( $plugin_top_directory_length );
						unset( $active_plugins_paths );
					}
					unset( $plugin_top_directory );
				}
				unset( $first_directory_separator );
			}
		}
		return '<td class="no-content">' . __( 'Unknown' ) .'</td><th>' . esc_html( $function_info['function'] ) . '</th>';
	}

	/**
	 * Display helper / introductory text before the list
	 *
	 * @return string HTML paragraphs explaining the filter display
	 */
	public static function helper_text() {
		return '<p>' . esc_html( sprintf( __( 'We found other active plugins that may change your existing post content in-place or add content to the end of your post alongside the %s.', 'gdgt-databox' ), 'gdgt Databox' ) ) . ' ' . esc_html( __( 'A non-exhaustive list for reference:', 'gdgt-databox' ) ) . '</p>';
	}

	/**
	 * Show a list of plugins and their functions acting on a particular filter so a site user may choose a custom filter priority
	 *
	 * @since 1.1
	 * @param string filter The name of the filter
	 * @return string HTML display of all plugins acting on filter. or empty string if none found
	 */
	public static function display_plugin_priorities_by_filter( $filter ) {
		global $wp_filter;

		if ( is_string( $filter ) && ! empty( $filter ) && isset( $wp_filter ) && is_array( $wp_filter ) && array_key_exists( $filter, $wp_filter ) && ! empty( $wp_filter[$filter] ) ) {
			$content_filters = $wp_filter[$filter];

			$rows = array();
			$wp_includes_directory = ABSPATH . WPINC;
			$wp_includes_directory_length = strlen( $wp_includes_directory );
			$active_plugins = GDGT_Filter_Info::active_plugins();

			foreach ( $content_filters as $priority => $idx_list ) {
				$priority = (int) $priority;
				$priority_key = (string) $priority;
				if ( is_array( $idx_list ) ) {
					foreach ( $idx_list as $idx => $fn_args ) {
						if ( ! array_key_exists( 'function', $fn_args ) )
							continue;
						try {
							$function_info = GDGT_Filter_Info::filter_function_name( $fn_args['function'] );
							if ( ! empty( $function_info ) ) {
								if ( strlen( $function_info['filename'] ) > $wp_includes_directory_length && substr_compare( $wp_includes_directory, $function_info['filename'], 0, $wp_includes_directory_length ) === 0 )
									continue;
								$cells = GDGT_Filter_Info::plugin_list_item_html( $function_info, $active_plugins );
								if ( ! empty( $cells ) ) {
									if ( array_key_exists( $priority_key, $rows ) )
										$rows[$priority_key][] = $cells;
									else
										$rows[$priority_key] = array( $cells );
								}
								unset( $cells );
							}
							unset( $function_info );
						} catch ( Exception $e ) {}
					}
				}
				unset( $priority_key );
			}
			unset( $content_filters );
			unset( $wp_includes_directory );
			unset( $wp_includes_directory_length );
			unset( $active_plugins );
			if ( ! empty( $rows ) ) {
				$wp_default = '<td>WordPress</td><th scope="row">' . esc_html( __( 'default' ) ) . '</th>';
				if ( array_key_exists( '10', $rows ) )
					array_unshift( $rows['10'], $wp_default );
				else
					$rows['10'] = $wp_default;
				unset( $wp_default );
				$html = GDGT_Filter_Info::helper_text() . '<table id="' . esc_attr( str_replace( '_', '-', $filter ) ) . '-plugin-priorites"><thead><tr><th class="priority">' . esc_html( __( 'Priority' ) ) . '</th><th>' . esc_html( __( 'Plugin' ) ) . '</th><th>' . esc_html( __( 'Function' ) ) . '</th></tr></thead><tbody>';
				ksort( $rows, SORT_NUMERIC );
				foreach ( $rows as $priority => $plugins ) {
					foreach ( $plugins as $plugin_cells ) {
						$html .= '<tr><td class="priority">' . $priority . '</td>' . $plugin_cells . '</tr>';
					}
				}
				$html .= '</tbody></table>';
				return $html;
			}
			unset( $rows );
		}

		return '';
	}
}

?>