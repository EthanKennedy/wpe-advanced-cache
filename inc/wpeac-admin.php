<?php
/**
 * WP Engine Advanced Cache Admin Page and Settings
 */
class WPEAC_Admin {
	/**
	 * Cache option array
	 *
	 * @since 0.1.2
	 * @var array VALID_CACHE_CONTROL_OPTIONS This lists out the second time, and human readable times we can set for cache headers
	 */
	const VALID_CACHE_CONTROL_OPTIONS = array(
		'10 Minutes' => 600,
		'1 Hour'     => 3600,
		'4 Hours'    => 14400,
		'12 Hours'   => 43200,
		'1 Day'      => 86400,
		'3 Days'     => 259200,
		'7 Days'     => 604800,
		'4 Weeks'    => 2419200,
	);

	function __construct() {
		add_action( 'admin_menu', array( $this, 'cache_menu_settings' ) );
		// Enqueue JS for Ajax calls on admin menu pages
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		// Ajax callbacks
		add_action( 'wp_ajax_purge_varnish_post_id', array( $this, 'purge_varnish_post_id_callback' ) );
		add_action( 'wp_ajax_reset_global_last_modified', array( $this, 'reset_global_last_modified_callback' ) );
		add_action( 'wp_ajax_purge_varnish_path', array( $this, 'purge_varnish_path_callback' ) );
		// Register/confirm plugin options on admin load
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Update the global last modified if the theme is changed, since that will probably change how the site looks
		add_action( 'after_switch_theme', array( $this, 'update_global_last_modified' ) );
		// Set the new last modified global variable to prevent things from stale menus on pages that haven't been changed
		add_action( 'edited_nav_menu', array( $this, 'update_global_last_modified' ) );
	}
	/**
	 * Register Admin Menu
	 *
	 * Registers the settings and pages to generate the admin menu.
	 *
	 * @since 0.1.1
	 * @action admin_menu
	 * @uses add_menu_page
	 * @return null
	 */
	function cache_menu_settings() {
		$this->settings_hook = add_options_page( 'Cache Settings', 'Cache Settings', 'administrator', 'cache-settings', array( $this, 'cache_menu_settings_page' ) );
	}
	/**
	 * Enqueue JS
	 *
	 * Enqueues the JS for the ajax calls on the menu pages
	 *
	 * @since 0.3.7
	 * @action admin_enqueue_scripts
	 * @uses wp_enqueue_script
	 * @return null
	 */
	function admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_cache-settings' != $hook ) {
			return;
		}
		wp_enqueue_script( 'wpe-advanced-cache', plugins_url( 'js/wpe-advanced-cache.js', dirname( __FILE__ ) ), array( 'jquery' ), 1, true );
	}
	/**
	 * Build Menu Page
	 *
	 * Builds out HTML of the admin menu page.
	 *
	 * @since 0.1.1
	 * @action admin_menu
	 * @see get_sanitized_post_types
	 * @see cache_menu_settings_page_options
	 * @uses settings_fields, do_settings_sections, gmdate, class_exists
	 * @return HTML of admin page
	 */
	function cache_menu_settings_page() {
		$options = WPEAC_Core::get();
		?>
		<div class="wrap">
			<h2>Cache Options</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpengine-advanced-cache-control' );?>
				<?php do_settings_sections( 'wpengine-advanced-cache-control' ); ?>
				<p>Increasing the cache times on the server will allow more users to see Cached copies of your pages. Cached copies of pages are served from outside of WordPress, which conserves server resources and saves time for your users.
					<br> <br>The cache is purged in most functions that update post content, so oftentimes it's best to set limits as high as possible. If you regularly update content and notice your posts take a while to update, it may be best to reduce these limits. If you are making a one-off change, the purge cache button will update the content for your visitors.</p>
				<h3>Post Types</h3>
				<p>Use the below options to alter the cache expiry times on the public post types on your site</p>
				<table class="form-table">
						<?php
						// Run through and build all the forms for each post type
						foreach ( $options['sanitized_post_types'] as $post_type ) {
							$this->cache_menu_settings_page_options( $post_type );
						}
					?>
				</table>
				<?php if ( function_exists( 'rest_get_server' ) ) { ?>
				<table class="form-table">
					<h3>Rest API Namespaces</h3>
					<p>Use the below options to alter the cache expiry times on Rest API end-points your site</p>
						<?php
						// Run through and build all the forms for each post type
						foreach ( $options['namespaces'] as $namespace ) {
							$this->cache_menu_settings_page_options( $namespace );
						}
					?>
				</table>
			<?php } // Conditional to prevent UI displaying for Rest API ?>
				<!-- Give an option to turn off the "Smarter Cache" -->
				<h3>Smarter Cache</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Smarter Cache</th>
						<p>
							This option will allow your posts and pages to be cached for longer if they haven't been modified in a while.
							<br> <br>
							If your posts and pages have gone more than 4 weeks without being updated, this option will allow you to cache them for up to 6 months by default. As posts pass 4 weeks without being updated, the cache header will be updated to 6 months.
						</p>
						<td>
							<select id="smarter_cache_enabled" name ="<?php echo esc_attr( WPEAC_Core::CONFIG_OPTION . '[smarter_cache_enabled]' ); ?>">
							<?php $smarter_cache_enabled = $options['smarter_cache_enabled'] ?>
							<option value="1" <?php selected( $smarter_cache_enabled, 1 ); ?> >On</option>
							<option value="0" <?php selected( $smarter_cache_enabled, 0 ); ?> >Off</option>
							</select>
						</td>
					</tr>
				</table>
				<!-- Give an option to turn off the last-modified headers -->
				<h3>Last-Modified Headers</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Last-Modified Headers</th>
						<p>
							Last modified headers will encourage bots and users to use local cache instead of pulling content from the server each time. This will speed up their responses, and decrease the impact a heavy bot crawl could have on your site.
							<br> <br>
							Last-Modified headers are updated on specific posts based on the last time they were modified, and the most recent comment. They are also updated Globally on Theme change, or Menu updates. If a major change is made, the global Last-Modified headers can be updated using the option below. The Last-Modified headers sent from the server are always the most recent of those options.
						</p>
						<td>
							<select id="last_modified_enabled" name ="<?php echo esc_attr( WPEAC_Core::CONFIG_OPTION . '[last_modified_enabled]' ); ?>">
								<?php $last_modified_enabled = $options['last_modified_enabled'] ?>
								<option value='1' <?php selected( $last_modified_enabled, 1 ); ?> >On</option>
								<option value='0' <?php selected( $last_modified_enabled, 0 ); ?> >Off</option>
								<option value='2' <?php selected( $last_modified_enabled, 2 ); ?> >Only Enabled for Posts and Pages</option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
				<input type="hidden" id="wpe_ac_global_last_modified" name="<?php echo esc_attr( WPEAC_Core::CONFIG_OPTION . '[wpe_ac_global_last_modified]' ); ?>" value="<?php echo WPEAC_Core::get( 'wpe_ac_global_last_modified' )?>"/>
			</form>
			<hr>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Reset Global Last Modified</th>
				</tr>
			</table>
			<p>
				Unless you're seeing major issues with stale content in a number of user's browsers, we recommend not using this option.
				<br> <br>
				This option will allow you to update the global Last-Modified headers on the site. This will force bots and browser that respect those headers to download a new version of each page. Doing so may cause a load during bot crawls, so it's recommended to avoid updating this if at all possible, especially on large sites.
			</p>
			<div id="wpe_ac_global_last_modified_text"><?php echo esc_html( 'Global Last-Modified Header currently set to ' . gmdate( 'D, d M Y H:i:s 	T',  WPEAC_Core::get( 'wpe_ac_global_last_modified' ) ) )?></div>
			<br>
			<button class="button-primary" id="reset_global_last_modified" style="float:left">Reset Last-Modified</button>
			<br>
			<br>
			<br>
			<hr>
			<!-- Add a button to purge specific posts from cache -->
			<!-- only add the button on a WP ENGINE site -->
			<?php if ( isset( $_SERVER['IS_WPE'] ) ) { ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Purge Single Post</th>
						<p>
							This option purges the post, homepage, feed and API endpoints for a single post based on post ID.
						</p>
						<td>
							<input id="purge_varnish_post_id_input">
							<p class="description">Accepts Post ID</p>
						</td>
					</tr>
				</table>
				<div id="purge_results_text"><br></div>
				<br>
				<button class="button-primary" id="purge_varnish_post_id" style="float:left">Purge Post</button>
				<br>
				<br>
				<br>
				<hr>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">Purge Path</th>
						<p>
							Use the URL as it appears in browser to purge content that does not have an associated post ID.
						<td>
							<input id="purge_varnish_path_input" class="regular-text">
								<button class="button-primary" id="purge_varnish_url_verify">Verify URL</button>
							<p id="purge_varnish_url_description" class="description">Accepts Path</p>
						</td>
					</tr>
				</table>
				<div id="purge_path_results_text"><br></div>
				<br>
				<button class="button-primary" id="purge_varnish_path" style="float:left" disabled>Purge Path</button>
		</div><!-- .wrap -->
		<?php }
	}
	/**
	 * Build cache selection drop down
	 *
	 * Builds out the html drop down for each public post type.
	 *
	 * @since 0.1.1
	 * @see build_cache_menu
	 * @param string $post_type the post_type of the post we're working to add to the menu
	 * @return HMTL Drop down element and descriptor for each post type
	 */
	function cache_menu_settings_page_options( $post_type ) {
		$current_cache_time = WPEAC_Core::get( $post_type . '_cache_expires_value' ); ?>
		<tr valign="top">
			<th scope="row"> <?php echo esc_html( ucfirst( $post_type ) ); ?>
				Cache Length</th>
			<td>
				<select name ="<?php echo esc_attr( WPEAC_Core::CONFIG_OPTION . '[' . $post_type . '_cache_expires_value]' ); ?>">
					<?php $this->build_cache_menu( $current_cache_time ); ?>
				</select>
			</td>
		</tr>
		<?php

	}
	/**
	 * Fill out cache selection drop down
	 *
	 * Pulls in the values from the $this->VALID_CACHE_CONTROL_OPTIONS global to fill out drop down menus for each post type.
	 *
	 * @since 0.1.1
	 * @global array $this->VALID_CACHE_CONTROL_OPTIONS {
	 *       Array of valid cache times and their Human Readable equivalents
	 *
	 *       @type string $human_readable Human readable cache time options.
	 *
	 *       @type int $seconds The equivalent cache time in seconds instead of human readable. Used to actual set the option and used in the headers
	 * }
	 * @param string $current_cache_time The currently set cache time for that post type
	 * @return Content's of HTML drop down.
	 */
	function build_cache_menu( $current_cache_time ) {
		foreach ( self::VALID_CACHE_CONTROL_OPTIONS as $human_readable => $seconds ) {
			 echo '<option value="' . esc_attr( $seconds ) . '" ' . selected( $current_cache_time, $seconds ) . '>';
			 echo esc_html( $human_readable );
			 echo '</option>';
		}
	}
	/**
	 * Execute cache purged passed by JS
	 *
	 * Executes the php function that goes in and purges the cache.
	 *
	 * @since 0.2.0
	 * @action wp_ajax_purge_varnish_post_id
	 * @see purge_cache_by_post_id
	 * @return null
	 */
	function purge_varnish_post_id_callback() {
		$this->purge_cache_by_post_id( $_POST['your_post_id'] );
		wp_die(); // this is required to terminate immediately and return a proper response
	}
	/**
	 * Executes global last modified update
	 *
	 * Actually executes the global last modified update php code using the JS Admin ajax call.
	 *
	 * @since 0.3.2
	 * @action wp_ajax_reset_global_last_modified
	 * @see $this->update_last_modified
	 * @return current global last modified date.
	 */
	function reset_global_last_modified_callback() {
		$this->update_global_last_modified();
		echo WPEAC_Core::get( 'wpe_ac_global_last_modified' );
		wp_die(); // this is required to terminate immediately and return a proper response
	}
	/**
	 * Execute cache purged passed by JS
	 *
	 * Executes the php function that goes in and purges the cache.
	 *
	 * @since 1.3.0
	 * @action wp_ajax_purge_varnish_path
	 * @see purge_cache_by_path
	 * @return null
	 */
	function purge_varnish_path_callback() {
		$this->purge_cache_by_path( $_POST['your_path'] );
		wp_die(); // this is required to terminate immediately and return a proper response
	}
	//__________________________________________________________________________________________________________________
	/**
	 * Register Plugin Options
	 *
	 * Runs the code to register the appropriate settings for each post type and set their defaults.
	 * Also registers option for Smarter Cache and Last Modified on or off.
	 *
	 * @since 0.1.1
	 * @action admin_init
	 * @see get_sanitized_post_types, cache_control_settings_register,
	 * @uses register_setting
	 * @return null
	 */
	function register_settings() {
		register_setting( 'wpengine-advanced-cache-control', WPEAC_Core::CONFIG_OPTION, array( $this, 'validate_cache_control_settings' ) );
		foreach ( self::get_sanitized_post_types() as $post_type ) {
			$this->init_cache_control_settings( $post_type );
		}
		if ( function_exists( 'rest_get_server' ) ) {
			foreach ( self::get_rest_api_namespaces() as $namespace ) {
				$this->init_cache_control_settings( $namespace );
			}
			WPEAC_Core::update( 'namespaces', self::get_rest_api_namespaces() );
		}
		WPEAC_Core::update( 'sanitized_post_types', self::get_sanitized_post_types() );
		WPEAC_Core::update( 'sanitized_builtin_post_types', self::get_sanitized_post_types( true ) );
	}
	/**
	 * Update global last modified
	 *
	 * Updates the global last modified option to the current timestamp
	 *
	 * @since 0.3.2
	 * @uses update_option
	 * @action after_switch_theme
	 * @action edited_nav_menu
	 * @return null
	 */
	function update_global_last_modified() {
		$current_global_variable = date( 'U' );
		/**
		 * Filter global last mod var
		 *
		 * Allows the customization of global last_modified variable.
		 *
		 * @since 0.3.6
		 *
		 * @param string $current_global_variable the current value of the global last modified in epoch format.
		 */
		$current_global_variable = apply_filters( 'wpe_ac_global_last_modified_variable', $current_global_variable );
		WPEAC_Core::update( 'wpe_ac_global_last_modified', (int) $current_global_variable );
	}
	/**
	 * Set Default Cache Times
	 *
	 * Works with other function to set cache times when none are present.
	 *
	 * @TODO This function seems largely useless and can be condensed with default_cache_control_to_hour or vice versa
	 *
	 * @since 0.1.1
	 * @uses register_setting
	 * @param string $post_type The post type that we're registering settings against
	 * @return null
	 */
	function init_cache_control_settings( $post_type ) {
		$key = $post_type . '_cache_expires_value';
		if ( empty( WPEAC_Core::get( $key ) ) ) {
			$this->default_cache_control_to_hour( $post_type );
		}
	}
	/**
	 * Defaults cache time to 1 hour
	 *
	 * Sets the defaults for the cache headers on each post_type to 1 hour.
	 * This may be a little aggressive, but it'll have a huge impact out of the gate.
	 *
	 * @since 0.1.2
	 * @uses update_option
	 * @param string $post_type The post type that we're verifying
	 * @return null
	 */
	function default_cache_control_to_hour( $post_type ) {
		WPEAC_Core::update( $post_type . '_cache_expires_value', 3600 );
	}

	/**
	 * Validate all options
	 *
	 * Validates all options being passed in as part of our option blob on form submit
	 *
	 * @since 0.1.2
	 * @param array $options the options blob being saved to the database
	 * @see return_validations_array
	 * @uses filter_var_array
	 * @return array $options Validated options array
	 */
	function validate_cache_control_settings( $options ) {
		$current = WPEAC_Core::get();
		if ( ! is_array( $options ) ) {
			return $current;
		}
		$validations = $this->return_validations_array();
		$options = filter_var_array( $options, $validations );
		return $options;
	}
	/**
	 * Return Validation Array
	 *
	 * Returns the array of validators to compare to the options passed in when saving the values to the database
	 *
	 * @since 0.4.1
	 * @see validate_cache_control_settings
	 * @return array $validations
	 */
	public function return_validations_array() {
		$sanitized_post_types = $this->get_sanitized_post_types();
		$validations = array(
			'sanitized_post_types' => array(
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_FORCE_ARRAY,
			),
			'sanitized_builtin_post_types' => array(
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_FORCE_ARRAY,
			),
			'smarter_cache_enabled'        => FILTER_SANITIZE_STRING,
			'last_modified_enabled'        => FILTER_SANITIZE_STRING,
			'wpe_ac_global_last_modified'  => FILTER_SANITIZE_STRING,
		);
		foreach ( $sanitized_post_types as $post_type ) {
			$validations[ $post_type . '_cache_expires_value' ] = FILTER_SANITIZE_STRING;
		}
		if ( function_exists( 'rest_get_server' ) ) {
			$namespaces = $this->get_rest_api_namespaces();
			$validations['namespaces'] = array(
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_FORCE_ARRAY,
			);
			foreach ( $namespaces as $namespace ) {
				$validations[ $namespace . '_cache_expires_value' ] = FILTER_SANITIZE_STRING;
			}
		}
		return $validations;
	}
	/**
	 * Purges cache based on post number
	 *
	 * Executes our cache purge based on the ajax call in the admin menu.
	 * Validates input to make sure it's being passed valid information.
	 *
	 * @since 0.2.1
	 * @see WpeCommon::purge_varnish_cache, get_sanitized_post_types
	 * @uses get_post_type, get_the_title
	 * @param string $post_id This should be the number to a valid post, human input.
	 * @return string $purge_response Purge response based on input
	 *
	 * @TODO Maybe we can have this work using post titles? Maybe not, since people typo a lot?
	 */
	public static function purge_cache_by_post_id( $post_id ) {
		if ( ! class_exists( WpeCommon ) ) {
			$purge_response = 'This function only works on a WP Engine installation.';
		} elseif ( 1 == $post_id ) {
			$purge_response = "Post ID #1 Will purge the entire cache, I'd recommend using the Purge All Caches button to get this done";
		} elseif ( method_exists( WpeCommon, purge_varnish_cache ) != true ) {
			//If we mess with the purge_varnish_cache function, I don't want to hard error anyone.
			$purge_response = 'There may be something wrong with the WP Engine Function that this feature uses. Use the standard purge cache instead.';
		} elseif ( in_array( get_post_type( $post_id ), WPEAC_Core::get( 'sanitized_post_types' ) ) ) {
			WpeCommon::purge_varnish_cache( (int) $post_id );
			$post_title = get_the_title( $post_id );
			$purge_response = "Purged post ID $post_id, $post_title,  from cache";
		} elseif ( ! is_numeric( $post_id ) ) {
			$purge_response = 'This field only accepts digits';
		} else {
			$purge_response = "$post_id is not a valid public post ID";
		}
		echo $purge_response;
	}
	/**
	 * Returns post_types to manage
	 *
	 * Returns the post_types after filtering some out, optionally only returns the builtins I say are okay  (posts and pages).
	 *
	 * @since 0.1.1
	 * @uses get_post_types
	 * @param boolean $builtin Default = False. Used only if we want to just use Posts and Pages.
	 * @return array Array of the post_types we want to mess with in our code
	 */
	function get_sanitized_post_types( $builtin = false ) {
		$args = array(
			'public' => true,
		);
		if ( true === $builtin ) {
			$args['_builtin'] = true;
		}
		$post_types = get_post_types( $args, 'names' );
		//who cares about cache times on nav_menu_items and attachments
		$post_types = array_diff( $post_types, array( 'revision', 'nav_menu_item', 'attachment' ) );
		/**
		 * Update sanitized post types array
		 *
		 * Allows customization of the returned post types.
		 *
		 * @since 0.3.6
		 *
		 * @param string $post_types the returned post_types for use in other functions.
		 */
		return apply_filters( 'wpe_ac_get_sanitized_post_types', $post_types );
	}
	/**
	 * Return Registered Namespaces
	 *
	 * Returns the registered namespaces on the rest server
	 *
	 * @since 1.2.0
	 * @uses get_rest_server, get_namespaces
	 * @return array Array of namespaces
	 *
	 */
	function get_rest_api_namespaces() {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return;
		}
		$server = rest_get_server();
		return $server->get_namespaces();
	}
	/**
	 * Purge Varnish By Path
	 *
	 * purges varnish on WP Engine installations by path instead of just post ID
	 *
	 * @since 1.3.0
	 * @see WpeCommon::purge_varnish_cache
	 * @param string $path the path to be purged based on user input
	 * @return string $purge_path_response purge response based on input
	 *
	 */
	var $path_to_purge;

	function purge_cache_by_path( $path ) {
		$url = parse_url( $path, PHP_URL_PATH );
		$this->path_to_purge = $url;
		if ( isset( $_SERVER['IS_WPE'] ) ) {
			add_filter( 'wpe_purge_varnish_cache_paths', array( $this, 'set_path' ) );
			WpeCommon::purge_varnish_cache( 1 );
			remove_filter( 'wpe_purge_varnish_cache_paths', array( $this, 'set_path' ) );
			echo ( "Purged $url from cache." );
		} else {
			echo 'This function only works on WP Engine installations.';
		}
	}

	function set_path() {
		$url = array( $this->path_to_purge );
		return $url;
	}
}
