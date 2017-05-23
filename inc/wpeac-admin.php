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
		$smarter_cache_enabled = $options['smarter_cache_enabled'];
		?>
		<div class="wrap">
			<h2>Cache Options</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpengine-advanced-cache-control' );?>
				<?php do_settings_sections( 'wpengine-advanced-cache-control' ); ?>
				<table class="form-table">
					<p>Increasing the Cache time on the site will make the site faster, while improving backend performance.
						<br> <br>Increasing the cache times on the server will allow more users to see Cached copies of your pages. Anytime a user is served a cached copy of your page, they don't have to go through WordPress to get it, which saves the user time, and the server resources.
						<br> <br>The cache is purged in most functions that update post content, so often times it's best to set limits as high as possible. If you're seeing issues with content taking some time to update on your posts regularly, it may be best to reduce these limits. If the changes are one-offs, the purge cache button should update the content for your visitors.</p>
						<?php
						// Run through and build all the forms for each post type
						foreach ( $options['sanitized_post_types'] as $post_type ) {
							$this->cache_menu_settings_page_options( $post_type );
						}
						?>
						<!-- Give an option to turn off the "Smarter Cache" -->
					</table>
					<h3>Smarter Cache</h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">Smarter Cache</th>
							<p>This option will allow your posts and pages to be cached for longer if they haven't been modified in a while.
								<br> <br>
								If your posts and pages have gone more than 4 weeks without being updated, this option will allow you to cache them for up to 6 months by default. As posts pass 4 weeks without being updated, the cache header will be updated to 6 months.<p>
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
									<p>Last modified headers will encourage bots and users to use local cache instead of pulling content from the server each time. This will speed up their responses, and decrease the impact a heavy bot crawl could have on your site.
										<br> <br>
										Last-Modified headers are updated on specific posts based on the last time they were modified, and the most recent comment. They are also updated Globally on Theme change, or Menu updates. If a major change is made, the global Last-Modified headers can be updated using the option below. The Last-Modified headers sent from the server are always the most recent of those options.
										<p>
											<td>
												<select id="last_modified_enabled" name ="<?php echo esc_attr( WPEAC_Core::CONFIG_OPTION . '[last_modified_enabled]' ); ?>">
													<?php $last_modified_enabled = WPEAC_Core::get( 'last_modified_enabled' ) ?>
													<option value='1' <?php selected( $last_modified_enabled, 1 ); ?> >On</option>
													<option value='0' <?php selected( $last_modified_enabled, 0 ); ?> >Off</option>
													<option value='2' <?php selected( $last_modified_enabled, 2 ); ?> >Only Enabled for Posts and Pages</option>
												</select>
											</td>
										</tr>
									</table>
									<?php submit_button(); ?>
								</form>
								<table class="form-table">
									<tr valign="top">
										<th scope="row">Reset Global Last Modified</th>
									</tr>
								</table>
								<p>Unless you're seeing major issues with stale content in a number of user's browsers, we recommend not using this option.
									<br> <br>
									This option will allow you to update the global Last-Modified headers on the site. This will force bots and browser that respect those headers to download a new version of each page. Doing so may cause a load during bot crawls, so it's recommended to avoid updating this if at all possible, especially on large sites.
									<p>
										<div id="results2"><?php echo 'Global Last-Modified Header currently set to ' . gmdate( 'D, d M Y H:i:s 	T', WPEAC_Core::get( 'wpe_ac_global_last_modified' ) )?></div>
										<br>
										<button class="button-primary" id="reset_global_last_modified" style="float:left">Reset Last-Modified</button>
										<br>
										<br>
										<br>
									</table>
									<!-- Add a button to purge specific posts from cache -->
									<?php
									if ( isset( $_SERVER['IS_WPE'] ) ) {
										?>
										<table class="form-table">
											<tr valign="top">
												<th scope="row">Purge Single Post</th>
												<td>
													<input id="purge_varnish_post_id_input">
													Accepts Post ID Number
												</td>
											</tr>
										</table>
										<div id="results"><br></div>
										<br>
										<button class="button-primary" id="purge_varnish_post_id" style="float:left">Purge Post</button>
									</div>
									<?php
									}
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
		$current_cache_time = WPEAC_Core::get( $post_type . '_cache_expires_value' );
		?>
		<tr valign="top">
			<th scope="row"> <?php echo esc_html( ucfirst( $post_type ) ) ?> Cache Length</th>
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
			?>
			<option value=<?php echo $seconds;
			?> <?php echo selected( $current_cache_time, $seconds ); ;
			?> ><?php echo $human_readable;
			?></option>
			<?php

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
		echo 'Global Last-Modified Header updated to ' . gmdate( 'D, d M Y H:i:s T', WPEAC_Core::get( 'wpe_ac_global_last_modified' ) );
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
	 * @see get_sanitized_post_types, cache_control_settings_register,  default_cache_control_to_hour
	 * @uses register_setting
	 * @return null
	 */
	function register_settings() {
		register_setting( 'wpengine-advanced-cache-control', WPEAC_Core::CONFIG_OPTION, array( $this, 'validate_cache_control_settings' ) );
		foreach ( self::get_sanitized_post_types() as $post_type ) {
			$this->init_cache_control_settings( $post_type );
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
			 * Update global last modified variable.
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
	 * Register Cache options for each post_type
	 *
	 * Actually goes through and registers each setting for the valid post types being passed.
	 *
	 * @since 0.1.1
	 * @see validate_cache_control_settings(
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
	 * Validate Cache values
	 *
	 * Validate the cache options we're setting are valid, and are part of our global array.
	 * Defaults to 1 hour if the option being set isn't valid.
	 *
	 * @since 0.1.2
	 * @global array VALID_CACHE_CONTROL_OPTIONS {
	 *         Array of valid cache control times from the global variable
	 * @return int $input Cache control option
	 */
	function validate_cache_control_settings( $options ) {
		$current = WPEAC_Core::get();
		if ( ! is_array( $options ) ) {
			return $current;
		}

		$validations = array(
			'sanitized_post_types' => array(
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_FORCE_ARRAY,
			),
			'sanitized_builtin_post_types' => array(
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_FORCE_ARRAY,
			),
			'smarter_cache_enabled'        => FILTER_VALIDATE_INT,
			'last_modified_enabled'        => FILTER_VALIDATE_INT,
			'wpe_ac_global_last_modified'  => FILTER_SANITIZE_STRING,
		);
		if( isset( $current['sanitized_post_types'] ) ) {
			foreach( $current['sanitized_post_types'] as $post_type ) {
				$validations[$post_type.'_cache_expires_value'] = FILTER_SANITIZE_STRING;
			}
		}
		// $options = filter_var_array( $options, $validations );
		// echo '<pre>';var_dump($options);die();
		return $options;
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
	 */
	public static function purge_cache_by_post_id( $post_id ) {
		if ( ! class_exists( WpeCommon ) ) {
			$purge_response = 'This function only works on a WP Engine installation.';
		} elseif ( 1 == $post_id ) {
			$purge_response = "Post ID #1 Will purge the entire cache, I'd recommend using the Purge All Caches button to get this done";
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
}
