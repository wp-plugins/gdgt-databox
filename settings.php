<?php

if ( ! class_exists('GDGT_Base') )
	require_once ( dirname(__FILE__) . '/base.php' );

/**
 * Plugin installation and settings display in wp-admin
 *
 * @since 1.0
 */
class GDGT_Settings extends GDGT_Base {

	/**
	 * Tabs required to appear for each product.
	 * May not be overridden by individual publishers
	 *
	 * @since 1.0
	 * @var array
	 */
	public $required_tabs = array( 'specs', 'reviews', 'prices' );

	/**
	 * Track all created options.
	 * Useful for uninstalls
	 *
	 * @since 1.0
	 * @var array
	 */
	public static $all_options = array( 'gdgt_activation_run_once', 'gdgt_apikey', 'gdgt_min_disable_capability', 'gdgt_stop_tags', 'gdgt_max_products', 'gdgt_expand_products', 'gdgt_schema_org', 'gdgt_specs_tab', 'gdgt_reviews_tab', 'gdgt_prices_tab', 'gdgt_new_tabs', 'gdgt_content_filter_priority' );

	/**
	 * Attach settings hooks
	 *
	 * @since 1.0
	 */
	public function __construct() {
		// Customize plugins listing
		add_filter( 'plugin_action_links', array( &$this, 'plugin_action_links' ), 10, 2 );

		add_action( 'admin_menu', array( &$this, 'settings_menu_item' ) );
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
	}

	/**
	 * Define settings, sections, and fields
	 *
	 * @since 1.0
	 */
	public function admin_init() {
		// warn about no API key
		GDGT_Settings::api_key_reminder();

		if ( isset( $this->hook_suffix ) && is_string( $this->hook_suffix ) ) {
			add_action( 'admin_print_scripts-' . $this->hook_suffix, array( &$this, 'enqueue_scripts' ) );
			add_action( 'admin_print_styles-' . $this->hook_suffix, array( &$this, 'enqueue_styles' ) );
		}

		// API key
		add_settings_section( 'gdgt_access', __( 'API access', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'settings_page_access_section' ), GDGT_Settings::PLUGIN_SLUG );
		register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_apikey', array( &$this, 'sanitize_api_key' ) );
		add_settings_field( 'gdgt_apikey', __('API key', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_api_key' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_access' );

		// customize module
		add_settings_section( 'gdgt_data_box', __( 'Product display settings', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'settings_page_module_section' ), GDGT_Settings::PLUGIN_SLUG );
		foreach ( GDGT_Settings::tab_choices() as $id => $label ) {
			if ( empty($id) || empty($label) )
				continue;
			$shortname = substr( $id, 5 );
			$display_method = 'display_' . $shortname;
			if ( method_exists( $this, $display_method ) ) {
				register_setting( GDGT_Settings::PLUGIN_SLUG, $id, array( &$this, 'sanitize_' . $shortname ) );
				add_settings_field( $id, $label, array( &$this, $display_method ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_data_box' );
			}
			unset( $shortname );
			unset( $display_method );
		}

		register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_new_tabs', array( &$this, 'sanitize_new_tabs' ) );
		add_settings_field( 'gdgt_new_tabs', sprintf( __( 'Automatically display new tabs as %s releases them.', GDGT_Settings::PLUGIN_SLUG ), 'gdgt' ), array( &$this, 'display_new_tabs' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_data_box' );

		// multiple product display
		add_settings_section( 'gdgt_product_list', __( 'Databox display settings', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'settings_page_product_list_section' ), GDGT_Settings::PLUGIN_SLUG );
		register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_max_products', array( &$this, 'sanitize_max_products' ) );
		add_settings_field( 'gdgt_content_width', __( 'Content width', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_content_width' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_product_list' );
		register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_feed_include', array( &$this, 'sanitize_feed_include' ) );
		add_settings_field( 'gdgt_feed_include', __( 'Web feeds', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_feed_include' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_product_list' );
		add_settings_field( 'gdgt_max_products', __( 'Maximum products', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_max_products' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_product_list' );
		if ( absint( get_option( 'gdgt_max_products', 10 ) ) > 1 ) {
			register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_expand_products', array( &$this, 'sanitize_expand_products' ) );
			add_settings_field( 'gdgt_expand_products', __( 'Auto-expand', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_expand_products' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_product_list' );
			//add_action( 'update_option_gdgt_expand_products', array( &$this, 'update_option_expand_products' ), 10, 2 );
		}
		register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_schema_org', array( &$this, 'sanitize_schema_org' ) );
		add_settings_field( 'gdgt_schema_org', __( 'Schema.org microdata', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_schema_org' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_product_list' );
		register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_content_filter_priority', array( &$this, 'sanitize_filter_priority' ) );
		add_settings_field( 'gdgt_content_filter_priority', __( 'Content filter priority', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_filter_priority' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_product_list' );

		// overrides
		add_settings_section( 'gdgt_restrictions', __( 'Access and restrictions', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'settings_page_restrictions_section' ), GDGT_Settings::PLUGIN_SLUG );
		register_setting( GDGT_Settings::PLUGIN_SLUG , 'gdgt_min_disable_capability', array( &$this, 'sanitize_min_disable_capability' ) );
		add_settings_field( 'gdgt_min_disable_capability', __( 'Disable capability', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_min_disable_capability' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_restrictions' );
		register_setting( GDGT_Settings::PLUGIN_SLUG, 'gdgt_stop_tags', array( &$this, 'sanitize_stop_tags' ) );
		add_settings_field( 'gdgt_stop_tags', __( 'Stop-tags', GDGT_Settings::PLUGIN_SLUG ), array( &$this, 'display_stop_tags' ), GDGT_Settings::PLUGIN_SLUG, 'gdgt_restrictions' );
	}

	/**
	 * Customize tab display choices
	 *
	 * @since 1.0
	 * @return array associative array of option keys and labels
	 */
	public static function tab_choices() {
		return array(
			'gdgt_specs_tab' => __( 'Key specs', GDGT_Settings::PLUGIN_SLUG ),
			'gdgt_reviews_tab' => __( 'Reviews', GDGT_Settings::PLUGIN_SLUG ),
			'gdgt_prices_tab' => __( 'Prices', GDGT_Settings::PLUGIN_SLUG )
		);
	}

	/**
	 * Selectable custom permissions for product module disable
	 *
	 * @since 1.0
	 * @link http://codex.wordpress.org/Roles_and_Capabilities WordPress Codex: Roles and Capabilities
	 * @return array associative array of capability and description of capability + closest role
	 */
	public static function capability_choices() {
		return array(
			'manage_options' => __( 'Manage options (Administrator)', GDGT_Settings::PLUGIN_SLUG ),
			'publish_posts' => __( 'Publish post (Author)', GDGT_Settings::PLUGIN_SLUG ),
			'edit_posts' => __( 'Edit post (Contributor)', GDGT_Settings::PLUGIN_SLUG )
		);
	}

	/**
	 * If the current user is on the plugins administration page and has activated a plugin check if gdgt API key set
	 * Show a one-time admin notice if API key not set and activated plugin therefore will not have functionality beyond the settings page
	 *
	 * @since 1.0
	 */
	public static function api_key_reminder() {
		// are we on the plugin page after activation? can the current user manage plugin options?
		if ( basename( $_SERVER['SCRIPT_FILENAME'] ) !== 'plugins.php' || ! isset( $_GET['activate'] ) || $_GET['activate'] !== 'true' || ! current_user_can( 'manage_options' ) )
			return;

		$api_key = get_option( 'gdgt_apikey' );
		if ( empty( $api_key ) ) {
			$run_once = get_option( 'gdgt_activation_run_once', '0' );
			if ( $run_once !== '1' )
				add_action( 'admin_notices', array( 'GDGT_Settings', 'api_key_admin_notice' ) );
		}
	}

	/**
	 * Nag for gdgt API key if none stored
	 *
	 * @since 1.0
	 * @uses update_option()
	 */
	public static function api_key_admin_notice() {
		echo '<div id="gdgt-notice" class="updated fade"><p><strong>' . __( 'gdgt API key needed', GDGT_Settings::PLUGIN_SLUG ) . '</strong> ';
		echo sprintf( __('Please <a href="%s">enter a valid API key</a> to begin using the %s.'), 'options-general.php?page=' . GDGT_Settings::PLUGIN_SLUG, GDGT_Settings::PLUGIN_NAME );
		echo '</p></div>';
		update_option( 'gdgt_activation_run_once', '1' );
	}

	/**
	 * Link to settings from the plugin listing page
	 *
	 * @since 1.0
	 * @param array $links links displayed under the plugin
	 * @param string $file plugin main file path relative to plugin dir
	 * @return array links array passed in, possibly with our settings link added
	 */
    public function plugin_action_links( $links, $file ) {
    	if ( $file === plugin_basename( dirname(__FILE__) . '/plugin.php' ) )
			$links[] = '<a href="options-general.php?page=' . GDGT_Settings::PLUGIN_SLUG . '">' . __('Settings') . '</a>';
		return $links;
    }

	/**
	 * Add a gdgt submenu to the Settings menu
	 *
	 * @since 1.0
	 */
	public function settings_menu_item() {
		$this->hook_suffix = add_options_page( GDGT_Settings::PLUGIN_NAME, GDGT_Settings::PLUGIN_NAME, 'manage_options', GDGT_Settings::PLUGIN_SLUG, array( &$this, 'settings_page' ) );
		if ( $this->hook_suffix )
			add_action( 'load-' . $this->hook_suffix, array( &$this, 'settings_help' ) );
	}

	/**
	 * Add contextual help to settings page
	 *
	 * @since 1.23
	 */
	public function settings_help() {
		// check for WordPress 3.3 Screen API
		if ( ! class_exists( 'WP_Screen' ) || ! method_exists( 'WP_Screen', 'get' ) )
			return;
		$this->admin_screen = WP_Screen::get( $this->hook_suffix );
		if ( ! $this->admin_screen )
			return;

		// helpful links on the side
		// allow help URL override for publishers who prefer to display an internal help document
		$this->admin_screen->set_help_sidebar( '<p><strong>' . esc_html( __( 'More information:', GDGT_Settings::PLUGIN_SLUG ) ) . '</strong></p>' . '<ul><li><a href="http://gdgt.com/api/">' . esc_html( __( 'Manage your API key', GDGT_Settings::PLUGIN_SLUG ) ) . '</a></li><li><a href="' . esc_url( apply_filters( 'gdgt_help_url', 'http://help.gdgt.com/customer/portal/topics/171111-gdgt-databox-plugin-for-wordpress/articles' ) ) . '">' . esc_html( sprintf( __( '%s help', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</a></li></ul>' );

		$this->admin_screen->add_help_tab( array(
			'id' => 'apikey-help',
			'title' => __( 'API key', GDGT_Settings::PLUGIN_SLUG ),
			'content' => '<p>' . esc_html( sprintf( __( 'A %1$s API key uniquely identifies your account and its content to %1$s servers. An API key is required to enable %2$s functionality beyond basic settings.', GDGT_Settings::PLUGIN_SLUG ), 'gdgt', 'Databox' ) ) . '</p>'
		) );
		$this->admin_screen->add_help_tab( array(
			'id' => 'product-display-help',
			'title' => __( 'Product display', GDGT_Settings::PLUGIN_SLUG ),
			'content' => '<p>' . esc_html( sprintf( __( 'Future versions of %1$s may introduce new optional tabs. These tabs will be included in %1$s output by default. If you wish to manually control any new optional tabs please update your product display settings.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</p>'
		) );
		$this->admin_screen->add_help_tab( array(
			'id' => 'databox-display-help',
			'title' => __( 'Databox display', GDGT_Settings::PLUGIN_SLUG ),
			'content' => '<p>' . sprintf( __( 'The %1$s honors the %2$s <a href="%3$s" title="WordPress themes required hooks and navigation">required</a> global variable set by themes to communicate the pixel width of your post\'s main content column. This variable is typically set near the beginning of your theme\'s %4$s file.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME, '<var>content_width</var>', 'http://codex.wordpress.org/Theme_Review#Required_Hooks_and_Navigation', '<kbd>functions.php</kbd>' ) . '</p><p>' . esc_html( sprintf( __( 'A special version of the %1$s suitable for display in Google Reader (and other feed readers) appears in your site\'s RSS 2.0 and/or Atom Syndication Format feeds after the full content of your post. Disable this option if you prefer not to have the %1$s displayed in your feeds.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</p><p>' . esc_html( sprintf( __( 'A %1$s can display up to $2%u products (although you may choose to lower that limit). In the default settings, the first product in a %1$s appears expanded with subsequent products collapsed (but still instantly expandable by your visitors). You many choose to display all products in their fully expanded state.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME, 10 ) ) . '</p><p>' . sprintf( esc_html( __( '%1$s markup adds semantic meaning to %2$s content, helping indexers (such as %3$s) identify products and their reviews, pricing, and other structured data. %3$s may even use this data to display special %4$s content alongside your post in its search results, increasing your search conversion. (This feature requires %5$s which may break strict themes; try disabling it if you are having issues.)', GDGT_Settings::PLUGIN_SLUG ) ), '<a href="http://schema.org/">Schema.org</a>', GDGT_Settings::PLUGIN_NAME, 'Google', '<a href="http://support.google.com/webmasters/bin/answer.py?answer=99170">' . esc_html( _x( 'rich snippets', 'additional context summaries', GDGT_Settings::PLUGIN_SLUG ) ) . '</a>', '<a href="http://www.whatwg.org/specs/web-apps/current-work/multipage/microdata.html">' . esc_html( __( 'HTML5 microdata', GDGT_Settings::PLUGIN_SLUG ) ) . '</a>' ) . '</p><p>' . esc_html( sprintf( __( 'If you happen to have multiple plugins acting on the same WordPress content filters used to display plugin content (such as sharing buttons, related posts, etc.), you can increase or decrease the priority of the %s to ensure it displays before or after other content plugins accordingly.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</p>'
		) );
		$this->admin_screen->add_help_tab( array(
			'id' => 'restrictions-help',
			'title' => __( 'Restrictions', GDGT_Settings::PLUGIN_SLUG ),
			'content' => '<p>' . esc_html( sprintf( __( 'The %s may be disabled on individual posts by a WordPress user with sufficient permissions, or across all posts containing any of the specified stop-tags.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</p><p>' . esc_html( sprintf( __( 'Sites with editorial workflows that rely on specific user roles and capabilities may also want to limit the ability to disable the %s.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</p>' ) );
	}

	/**
	 * Include one or more scripts on the settings page
	 *
	 * @since 1.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( GDGT_Settings::PLUGIN_SLUG . '-settings-js', plugins_url( 'static/js/gdgt-settings.js', __FILE__ ), array( 'jquery' ), GDGT_Settings::PLUGIN_VERSION );
	}

	/**
	 * Include one or more styles on the settings page
	 *
	 * @since 1.23
	 */
	public function enqueue_styles() {
		wp_enqueue_style( GDGT_Settings::PLUGIN_SLUG . '-settings-css', plugins_url( 'static/css/settings.css', __FILE__ ), array(), GDGT_Settings::PLUGIN_VERSION );
	}

	/**
	 * Initialize the gdgt settings page
	 *
	 * @since 1.0
	 * @uses settings_fields()
	 * @uses settings_errors()
	 * @uses do_settings_sections()
	 */
	public function settings_page() {
		echo '<div class="wrap"><header><h2>' . esc_html( sprintf( __( '%s Settings', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</h2></header><form method="post" action="options.php">';
		settings_fields( GDGT_Settings::PLUGIN_SLUG );
		settings_errors();
		do_settings_sections( GDGT_Settings::PLUGIN_SLUG );
		echo '<p class="submit"><input type="submit" class="button-primary" value="' . esc_attr( __('Save Changes') ) . '" /></p></form></div>';
	}

	/**
	 * Setup the gdgt access settings section. API key etc.
	 *
	 * @since 1.0
	 */
	public function settings_page_access_section() {
		echo '<p>' . esc_html( sprintf( __( 'Your secret %s API key is required to enable Databox access.', GDGT_Settings::PLUGIN_SLUG ), 'gdgt' ) );
		echo '<br /><a href="http://help.gdgt.com/customer/portal/articles/372033" target="_blank">' . esc_html( __( 'Visit our API help page', GDGT_Settings::PLUGIN_SLUG ) ) . '</a> ' . esc_html( __( 'to learn more about gdgt API keys or to request a new key.', GDGT_Settings::PLUGIN_SLUG ) ) . '</p>';
	}

	/**
	 * Setup the gdgt restrictions section. Stop words, etc.
	 *
	 * @since 1.0
	 */
	public function settings_page_restrictions_section() {
		echo '<p>' .  esc_html( __( 'Configure how the Databox can be manually or automatically disabled.', GDGT_Settings::PLUGIN_SLUG ) ) . '</p>';
	}

	/**
	 * Setup the gdgt product list section.
	 * Customize number of gadgets displayed in HTML, feed, etc. and interactions between product modules
	 *
	 * @since 1.0
	 */
	public function settings_page_product_list_section() {
		echo '<p>'. esc_html( __( 'Customize Databox appearance and behavior.', GDGT_Settings::PLUGIN_SLUG ) ) . '</p>';
	}

	/**
	 * Setup the gdgt module section
	 * Customize tabs
	 *
	 * @since 1.0
	 */
	public function settings_page_module_section() {
		echo '<p>' . esc_html( __( 'Customize the tabs shown for each product module.', GDGT_Settings::PLUGIN_SLUG ) ) . '</p>';
	}

	/**
	 * Output HTML for display key input and label
	 *
	 * @since 1.0
	 */
	public function display_api_key() {
		$id = 'gdgt_apikey';
		$existing_value = get_option( $id );
		echo '<input type="text" name="' . $id . '" id="' . $id . '"';
		if ( ! empty( $existing_value ) )
			echo ' value="' . esc_attr( $existing_value ) . '"';
		echo ' maxlength="32" size="50" autocomplete="off" required />';
    }

	/**
	 * Test if the passed API key is valid
	 *
	 * @since 1.0
	 * @var string $api_key gdgt API key
	 * @return bool true if valid; else false
	 */
	public static function is_valid_api_key( $api_key ) {
		if ( ! is_string( $api_key ) || empty( $api_key ) || strlen( $api_key ) > 32 )
			return false;
		$args = GDGT_Settings::base_request_args();
		$args['body'] = json_encode( array( 'api_key' => $api_key ) );
		$response = wp_remote_post( GDGT_Settings::BASE_URL . 'v1/validate/', $args );
		unset( $args );

		if ( is_wp_error( $response ) )
			return false;
		if ( absint( wp_remote_retrieve_response_code( $response ) ) === 200 )
			return true;

		return false;
	}

	/**
	 * Sanitize a freeform text field that might contain an API key
	 *
	 * @since 1.0
	 * @param $key string API key
	 * @return string API key if valid else empty string
	 */
	public function sanitize_api_key( $key ) {
		if ( ! is_string( $key ) )
			return '';
		$key = trim( $key );
		if ( empty( $key ) )
			return '';

		// no need for API validation request if same key already stored.
		$id = 'gdgt_apikey';
		$old_value = get_option( $id );
		if ( ! empty( $old_value ) && $old_value === $key )
			return $key;
		else if ( GDGT_Settings::is_valid_api_key( $key ) ) {
			add_settings_error( $id, 'api-key-updated', __( 'API key updated!', GDGT_Settings::PLUGIN_SLUG ), 'updated' );
			return $key;
		}
		add_settings_error( $id, 'invalid-api-key', sprintf( __( 'Invalid API key entered. Please check your documentation and/or contact %s.', GDGT_Settings::PLUGIN_SLUG ), 'gdgt' ), 'error' );
		return '';
	}

	/**
	 * Display a select box with display capabilities
	 *
	 * @since 1.0
	 */
	public function display_min_disable_capability() {
		$id = 'gdgt_min_disable_capability';
		$default_value = 'edit_posts';
		$existing_value = get_option( $id, 'edit_posts' );
		$allowed_values = GDGT_Settings::capability_choices();
		if ( ! array_key_exists( $existing_value, $allowed_values ) )
			$exiting_value = $default_value;
		echo '<select name="' . $id . '" id="' . $id . '">';
		foreach( $allowed_values as $capability => $label ) {
			echo '<option value="' . esc_attr( $capability ) . '"';
			if ( $capability === $existing_value )
				echo ' selected';
			echo '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p><label for="' . $id . '">';
		echo esc_html( sprintf( __( 'The minimum capability needed to disable the %s on a single post.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) );
		echo '</label><br /><a href="http://codex.wordpress.org/Roles_and_Capabilities" target="_blank">' . esc_html( __( 'Further details on WordPress roles and capabilities', GDGT_Settings::PLUGIN_SLUG ) ) . '</a></p>';
	}

	/**
	 * Compare a submitted capability value against allowed capabilities for the setting.
	 * Display an updated message if value changed
	 *
	 * @since 1.0
	 * @see static::capability_choices()
	 * @param string $capability WordPress user capability
	 * @param string WordPress user capability
	 */
	public function sanitize_min_disable_capability( $capability ) {
		$capability = trim( $capability );
		$option_name = 'gdgt_min_disable_capability';
		$default_value = 'edit_posts';
		if ( ! array_key_exists( $capability, GDGT_Settings::capability_choices() ) )
			$capability = $default_value;
		$existing_value = get_option( $option_name, $default_value );
		if ( $capability != $existing_value )
			add_settings_error( $option_name, 'min-disable-capability-updated', sprintf( __( 'Permissions changed: only users with %s capability may disable the %s.', GDGT_Settings::PLUGIN_SLUG ), '<kbd>' . $capability . '</kbd>', GDGT_Settings::PLUGIN_NAME ), 'updated' );
		return $capability;
	}

	/**
	 * Enter tag values that may be used to exclude a post from gdgt produdct module submission and display
	 *
	 * @since 1.0
	 */
	public function display_stop_tags() {
		$id = 'gdgt_stop_tags';
		$existing_value = get_option( $id );
		echo '<div id="' . $id . '_div" class="tagchecklist"><input type="text" name="' . $id . '" id="' . $id . '" size="50"';
		if ( empty( $existing_value ) )
			echo ' placeholder="flowers,puppies"';
		else
			echo ' value="' . esc_attr( $existing_value ) . '"';
		echo ' autocomplete="off" />';
		echo '<p><label for="' . $id . '">' . esc_html( sprintf( __( 'Posts containing any of the specified stop-tags will not display the %s.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '<br />';
		echo esc_html( __( 'Enter one or more tags separated by a comma (,).', GDGT_Settings::PLUGIN_SLUG ) ) . '</label></p></div>';
		echo '<script type="text/javascript">gdgt.settings.labels.add=' . json_encode( __( 'Add' ) ) . '</script>'; // text should exist in WP translation: same text as post box edit
	}

	/**
	 * Clean up user-specified tags before storing
	 * No duplicates
	 * No leading or trailing spaces
	 *
	 * @since 1.0
	 * @param string user-entered tag string
	 * @return string CSV tag names
	 */
	public function sanitize_stop_tags( $tags ) {
		if ( ! is_string( $tags ) )
			return '';
		$tags = trim($tags);
		if ( empty( $tags ) )
			return '';

		$option_name = 'gdgt_stop_tags';
		$existing_stop_tags = get_option( $option_name );
		if ( empty( $existing_stop_tags ) )
			$existing_stop_tags = array();
		else
			$existing_stop_tags = explode( ',', $existing_stop_tags );

		$new_tags = array();
		$tag_array = array();
		foreach ( explode( ',', $tags ) as $tag ) {
			$tag = trim( strtolower( $tag ) );
			if ( empty( $tag ) || in_array( $tag, $tag_array, true ) )
				continue;
			$tag_array[] = $tag;
			if ( ! in_array( $tag, $existing_stop_tags ) )
				$new_tags[] = $tag;
		}
		foreach( $existing_stop_tags as $stop_tag ) {
			if ( ! in_array( $stop_tag, $tag_array, true ) )
				add_settings_error( $option_name, 'gdgt_stop_tag_' . md5( $stop_tag ), sprintf( __( '%s now enabled for any post tagged "%s."', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME, '<kbd>' . $stop_tag . '</kbd>' ), 'updated' );
		}
		// TODO: submit each matching post to gdgt for syndication removal
		unset( $existing_stop_tags );

		if ( ! empty( $tag_array ) ) {
			foreach( $new_tags as $stop_tag ) {
				add_settings_error( $option_name, 'gdgt_stop_tag_' . md5( $stop_tag ), sprintf( __( '%s now disabled for any post tagged "%s."', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME, '<kbd>' . $stop_tag . '</kbd>' ), 'updated' );
			}
			return implode( ',', $tag_array );
		}
		return '';
	}

	/**
	 * Display content_width based on active theme. Helps a publisher better understand what is happening on the frontend.
	 *
	 * @since 1.1
	 * @uses wp_get_theme()
	 */
	public function display_content_width() {
		global $content_width;

		// reference the theme and its child status
		$theme_label = '';
		if ( function_exists( 'wp_get_theme' ) ) { // WP 3.4+
			$theme = wp_get_theme();
			if ( $theme !== false ) {
				$theme_label = $theme->get( 'Name' );
				if ( ! empty( $theme_label ) ) {
					$theme_label = esc_html( trim( $theme_label ) );
					$theme_uri = $theme->get( 'ThemeURI' );
					if ( ! empty( $theme_uri ) )
						$theme_label = '<a href="' . esc_url( $theme_uri ) . '">' . $theme_label . '</a>';
					unset( $theme_uri );
					if ( $theme->is_child_theme )
						$theme_label .= ' ' . __( 'or parent', GDGT_Settings::PLUGIN_SLUG );
				}
			}
			unset( $theme );
		} else if ( function_exists( 'get_current_theme' ) ) {
			$theme_label = get_current_theme();
			if ( ! empty( $theme_label ) )
				$theme_label = esc_html( trim( $theme_label ) );
		}

		$databox_type = '';

		if ( ! isset( $content_width ) ) {
			echo esc_html( __( 'Not defined by theme', GDGT_Settings::PLUGIN_SLUG ) );
		} else {
			echo absint( $content_width ) . ' ' . __( 'pixels', GDGT_Settings::PLUGIN_SLUG ) . ', ' . __( 'defined by theme', GDGT_Settings::PLUGIN_SLUG );
			if ( ! class_exists( 'GDGT_Databox' ) )
				require_once( dirname( __FILE__ ) . '/databox.php' );
			$databox_type = GDGT_Databox::databox_type();
		}

		if ( ! empty( $theme_label ) )
			echo ' ' . $theme_label;

		echo '<p>';
		if ( isset( $content_width ) && $content_width < 550 ) {
			echo esc_html( sprintf( __( 'Databox not displayed. Minimum width of %d pixels required.', GDGT_Settings::PLUGIN_SLUG ), 550 ) );
		} else {
			$databox_width = $content_width;
			// cutoff at max width
			if ( $databox_width > 1000 )
				$databox_width = 1000;
			$databox_template_width = 650;
			if ( empty( $databox_type ) )
				$databox_type = 'default';
			else if ( $databox_type === 'mini' )
				$databox_template_width = 550;
			echo esc_html( sprintf( __( 'Displaying %1$s Databox: %2$u horizontal pixels fluid to %3$u.', GDGT_Settings::PLUGIN_SLUG ), $databox_type, $databox_template_width, $databox_width ) );
		}
		echo ' (<a href="http://help.gdgt.com/customer/portal/articles/409116" target="_blank">' . esc_html( _x( 'more info', 'more information', GDGT_Settings::PLUGIN_SLUG ) ) .  '</a>)</p>';
	}

	/**
	 * Allow a publisher to disable Databox display in feeds
	 *
	 * @since 1.1
	 */
	public function display_feed_include() {
		$id = 'gdgt_feed_include';
		$existing_value = (bool) get_option( $id, true );
		echo '<input type="checkbox" name="' . $id . '" id="' . $id . '" value="1"';
		if ( $existing_value === true )
			echo ' checked';
		echo ' /> <label for="' . $id . '">' . esc_html( sprintf( __( 'Include %s after RSS or Atom feed full content', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . '</label>';
	}

	/**
	 * Sanitize feed include checkbox value before saving
	 *
	 * @param string|bool checkbox value 1
	 * @return string 1 or 0
	 */
	public function sanitize_feed_include( $true_false ) {
		$option_name = 'gdgt_feed_include';
		$true_false = GDGT_Settings::sanitize_bool_preference( $true_false );
		$existing_value = get_option( $option_name, '1' );
		if ( $true_false != $existing_value ) {
			if ( $true_false )
				$message = sprintf( __( 'The %s will appear in feeds after full post content.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME );
				
			else
				$message = sprintf( __( 'The %s will no longer appear in feeds.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME );
			add_settings_error( $option_name, $option_name . '_confirm', $message, 'updated' );
		}
		return $true_false;
	}

	/**
	 * Display a select field to choose the maximum number of products to display in a post
	 *
	 * @since 1.0
	 */
	public function display_max_products() {
		$id = 'gdgt_max_products';
		$existing_value = absint( get_option( $id, 10 ) );
		echo '<select name="' . $id . '" id="' . $id . '">';
		for( $i=10; $i>0; $i-- ) {
			echo '<option';
			if ( $i === $existing_value )
				echo ' selected';
			echo '>' . $i . '</option>';
		}
		echo '</select> <label for="' . $id . '">' . esc_html( __( 'Maximum number of products per Databox', 'gdgt-databox' ) ) . '</label>';
	}

	/**
	 * Sanitize max products entry, making sure value is a number between 1 and 10.
	 *
	 * @since 1.0
	 * @param string $max_products settings field value
	 * @return int|string value converted to int or empty string if not valid
	 */
	public function sanitize_max_products( $max_products ) {
		$default = 10;
		if ( empty( $max_products ) )
			return $default;
		$max_products = absint( $max_products );
		$option_name = 'gdgt_max_products';
		if ( $max_products < 1 || $max_products > 10 )
			$max_products = $default;
		$existing_value = absint( get_option( $option_name, 10 ) );
		if ( $max_products != $existing_value )
			add_settings_error( $option_name, $option_name . '_updated', sprintf( __( 'The %s will display a maximum of %d %s.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME, $max_products, $max_products === 1 ? __( 'product', GDGT_Settings::PLUGIN_SLUG) : __( 'products', GDGT_Settings::PLUGIN_SLUG ) ), 'updated' );
		return $max_products;
	}

	/**
	 * Auto-expand products on display
	 *
	 * @since 1.0
	 */
	public function display_expand_products() {
		$id = 'gdgt_expand_products';
		$existing_value = (bool) get_option( $id, false );
		echo '<input type="checkbox" name="' . $id . '" id="' . $id . '" value="1"';
		if ( $existing_value === true )
			echo ' checked';
		echo ' /> <label for="' . $id . '">' . esc_html( __( 'Expand all products on initial page load', GDGT_Settings::PLUGIN_SLUG ) ) . '</label>';
	}

	/**
	 * Display an option checkbox to enable or disable Schema.org markup
	 *
	 * @since 1.0
	 */
	public function display_schema_org() {
		$id = 'gdgt_schema_org';
		$existing_value = (bool) get_option( $id, true );
		echo '<input type="checkbox" name="' . $id . '" id="' . $id . '" value="1"';
		if ( $existing_value === true )
			echo ' checked';
		echo ' /> <label for="' . $id . '">' . sprintf( esc_html( __( 'Include %s attributes in the Databox to express semantic meaning. (Recommended.)', GDGT_Settings::PLUGIN_SLUG ) ), '<a href="http://schema.org/" target="_blank">Schema.org</a> <a href="http://www.whatwg.org/specs/web-apps/current-work/multipage/microdata.html" target="_blank">HTML5 microdata</a>' ) . '</label>';
	}

	/**
	 * Sanitize schema.org preference to a bool string
	 * Include an updated message on change
	 *
	 * @since 1.0
	 * @param string $true_false checkbox value
	 * @return string '1' for true, '0' for false
	 */
	public function sanitize_schema_org( $true_false ) {
		$option_name = 'gdgt_schema_org';
		$true_false = GDGT_Settings::sanitize_bool_preference( $true_false );
		$existing_value = get_option( $option_name, '1' );
		if ( $true_false != $existing_value ) {
			if ( $true_false )
				$message = sprintf( __( 'The %s will now include Schema.org microdata for improved semantic meaning.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME );
			else
				$message = sprintf( __( 'The %s will not include Schema.org microdata.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME );
			add_settings_error( $option_name, $option_name . '_confirm', $message, 'updated' );
		}
		return $true_false;
	}

	/**
	 * Display a number input to set a custom filter priority
	 *
	 * @since 1.1
	 */
	public function display_filter_priority() {
		global $wp_filter;

		$option_name = 'gdgt_content_filter_priority';
		$existing_value = absint( get_option( $option_name, 1 ) );
		if ( $existing_value < 1 )
			$existing_value = 1;
		else if ( $existing_value > 100 )
			$existing_value = 100;
		echo '<input type="number" name="' . $option_name . '" id="' . $option_name .  '" value="' . $existing_value . '" min="1" max="100" />';
		echo '<p><label for="' . $option_name . '">' . esc_html( sprintf( __( 'Other plugins may also add content alongside the %s at the end of your posts.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME ) ) . ' ' . esc_html( sprintf( __( 'Enter a priority value between %1$d and %2$d to reorder where the %3$s appears.', GDGT_Settings::PLUGIN_SLUG ), 1, 100, GDGT_Settings::PLUGIN_NAME ) ) .'</label></p>';
		echo '<p><strong>' . esc_html( _x( 'Note:', 'take notice', GDGT_Settings::PLUGIN_SLUG ) ) .  '</strong> ' . esc_html( sprintf( __( 'the lower the number, the earlier its execution, meaning it will appear higher up the page. A priority of %1$d will show the %3$s above another plugin acting on the same content filter with a priority of %2$d.', GDGT_Settings::PLUGIN_SLUG ), 5, 17, GDGT_Settings::PLUGIN_NAME ) ) . '</p>';

		if ( ! class_exists( 'GDGT_Filter_Info' ) )
			include_once( dirname(__FILE__) . '/filter-info.php' );

		$filter_info = GDGT_Filter_Info::display_plugin_priorities_by_filter( 'the_content' );
		if ( ! empty( $filter_info ) )
			echo $filter_info;
	}

	/**
	 * Sanitize a filter priority to an integer between 1 and 20
	 *
	 * @since 1.1
	 * @param string|int $priority 
	 */
	public function sanitize_filter_priority( $priority ) {
		$priority = absint( $priority );
		if ( $priority < 1 )
			$priority = 1;
		else if ( $priority > 100 )
			$priority = 100;
		$option_name = 'gdgt_content_filter_priority';
		$existing_value = absint( get_option( $option_name, 1 ) );
		if ( $priority !== $existing_value ) {
			if ( $priority > $existing_value )
				$verb = _x( 'increased', 'positive numeric change: 1 to 2', GDGT_Settings::PLUGIN_SLUG );
			else
				$verb = _x( 'decreased', 'negative numeric change: 2 to 1', GDGT_Settings::PLUGIN_SLUG );
			add_settings_error( $option_name, $option_name . '_updated', sprintf( __( 'Content filter priority %1$s from %2$u to %3$u.', GDGT_Settings::PLUGIN_SLUG ), $verb, $existing_value, $priority ), 'updated' );
			unset( $verb );
		}
		return $priority;
	}

	/**
	 * Convert checked and other boolean-like values into a bool
	 *
	 * @since 1.0
	 * @param string $true_false 0 1
	 * @return string 0 1
	 */
	public static function sanitize_bool_preference( $true_false ) {
		if ( (bool) $true_false )
			return '1';
		else
			return '0';
	}

	/**
	 * Convert expand products to bool.
	 * Test if the value to be saved conflicts with another value already set while processing and possibly force the value
	 *
	 * @since 1.0
	 * @param string $true_false 0 1
	 * @return bool true if checked and not in conflict with another setting
	 */
	public function sanitize_expand_products( $true_false ) {
		$option_name = 'gdgt_expand_products';
		/*if ( isset( $this->force_expand ) && is_bool( $this->force_expand ) )
			return GDGT_Settings::sanitize_bool_preference( $this->force_expand ); */
		$true_false = GDGT_Settings::sanitize_bool_preference( $true_false );
		$existing_value = get_option( $option_name, '0' );
		if ( $true_false != $existing_value ) {
			if ( $true_false )
				$message = __( 'All products in the databox will now appear expanded on initial page load.', GDGT_Settings::PLUGIN_SLUG );
			else
				$message = __( 'The first product in the databox will appear expanded on the initial page load.', GDGT_Settings::PLUGIN_SLUG );
			add_settings_error( $option_name, $option_name . '_confirm', $message, 'updated' );
		}
		return $true_false;
	}

	/**
	 * Display a label and input checkbox for the given option / id.
	 *
	 * @since 1.0
	 * @param string $id option name
	 */
	private function display_tab( $id, $required=false ) {
		if ( ! is_string( $id ) || empty( $id ) )
			return;

		if ( $required === true )
			$existing_value = true;
		else
			$existing_value = (bool) get_option( $id, true );
		echo '<input type="checkbox" name="' . $id . '" id="' . $id . '" value="1"';
		if ( $required === true )
			echo ' checked disabled';
		else if ( $existing_value === true )
			echo ' checked';
		echo ' />';
	}

	/**
	 * Display the option to include or exclude the specifications tab from the gdgt product module
	 *
	 * @since 1.0
	 */
    public function display_specs_tab() {
    	$this->display_tab( 'gdgt_specs_tab', true );
    }

	/* Force specs tab display preference to true
	 *
	 * @since 1.0
	 * @param string $true_false checkbox POST
	 * @return string "1"
	 */
	public function sanitize_specs_tab( $true_false ) {
		return '1';
	}

	/**
	 * Display the option to include or exclude the reviews tab from the gdgt product module
	 *
	 * @since 1.0
	 */
	public function display_reviews_tab() {
		$this->display_tab( 'gdgt_reviews_tab', true );
	}

	/* Force reviews tab display preference to true
	 *
	 * @since 1.0
	 * @param string $true_false checkbox POST
	 * @return string "1"
	 */
	public function sanitize_reviews_tab( $true_false ) {
		return '1';
	}

	/**
	 * Display the option to include or exclude the prices tab from the gdgt product module
	 *
	 * @since 1.2
	 */
	public function display_prices_tab() {
		$this->display_tab( 'gdgt_prices_tab', true );
	}

	/* Force reviews tab display preference to true
	 *
	 * @since 1.2
	 * @param string $true_false checkbox POST
	 * @return string "1"
	 */
	public function sanitize_prices_tab( $true_false ) {
		return '1';
	}

	/**
	 * Display the option to automatically include new tabs released by gdgt
	 *
	 * @since 1.0
	 */
	public function display_new_tabs() {
		$this->display_tab( 'gdgt_new_tabs' );
	}

	/**
	 * Sanitize new tabs auto-add checkbox to boolean.
	 * Compare value against stored value or true if no stored value.
	 * If preference changed communicate the change in an updated message
	 *
	 * @since 1.0
	 * @param string $true_false input checkbox POST value
	 * @return string 1 for true, 0 for false
	 */
	public function sanitize_new_tabs( $true_false ) {
		$option_name = 'gdgt_new_tabs';
		$true_false = GDGT_Settings::sanitize_bool_preference( $true_false );
		$existing_value = get_option( $option_name, '1' );
		if ( $true_false != $existing_value ) {
			if ( $true_false )
				$message = sprintf( __( 'Future versions of the %s will automatically include new tab options.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME );
			else
				$message = sprintf( __( 'Auto-update of available tabs disabled. You will need to manually enable new tabs as they become available in future versions of the %s plugin.', GDGT_Settings::PLUGIN_SLUG ), GDGT_Settings::PLUGIN_NAME );
			add_settings_error( $option_name, $option_name . '_confirm', $message, 'updated' );
		}
		return $true_false;
	}
}
?>