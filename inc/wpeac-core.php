<?php
/**
 * Methods used on every call to populate required information for each request
 */

class WPEAC_Core {
	const CONFIG_OPTION = 'wpeac_config';
	/**
	 * Update an option
	 */
	static function update( $key, $value ) {
		$options = self::get();
		$options[ $key ] = $value;
		update_option( self::CONFIG_OPTION, $options );
	}
	/**
	 * Get a single option, or all options as an array.
	 *
	 * @return string|array|null
	 */
	static function get( $opt = null ) {
		$options = get_option( self::CONFIG_OPTION );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options = wp_parse_args( $options, array(
			'sanitized_post_types'         => array(),
			'sanitized_builtin_post_types' => array(),
			'smarter_cache_enabled'        => 1,
			'last_modified_enabled'        => 1,
			'wpe_ac_global_last_modified'  => 1262304000,
		) );
		if ( isset( $opt ) ) {
			$option = isset( $options[ $opt ] ) ? $options[ $opt ] : null;
			return $option;
		} else {
			return $options;
		}
	}
	function __construct() {
		add_filter( 'wpe_ac_last_modified_header', array( $this, 'global_last_modified_compare' ), 10, 1 );
		add_filter( 'wpe_ac_last_modified_header', array( $this, 'compare_comments_to_true_last_modified' ), 11, 2 );
	}
	/**
	 * Set last modified headers
	 *
	 * Sets and builds headers based on our various configuration options.
	 * Sets last-modified headers, and checks for smarter cache.
	 *
	 * @since 0.1.2
	 * @see most_recent_comment_timestamp, get_sanitized_post_types
	 * @uses get_option, is_user_logged_in, apply_filters
	 * @param string $post_type Post type of the requests
	 * @param string $last_modified Epoch of get_the_modified_date of the requests
	 * @param string $post_id Id of the specific post being checked
	 * @return last modified headers
	 */
	function send_header_last_modified( $last_modified, $post_id, $post_type ) {
		if ( is_user_logged_in() ) {
			return;
		}
		$last_modified_toggle = get_option( 'last_modified_enabled' );
		//serve last modified headers if they're turned on, or if it's set to only builtins and we're on a builtin
		if ( '1' == $last_modified_toggle && in_array( $post_type , self::get( 'sanitized_post_types' ) ) ||
			 ( '2' == $last_modified_toggle && in_array( $post_type , self::get( 'sanitized_builtin_post_types' ) ) )
			 ) {
			 /**
				* Last Modified Header configs
				*
				* Allows the customization of last_modified headers on requests.
				*
				* @since 0.3.6
				*
				* @param string $last_modified the output of get_the_modified_date on the post request
				* @param string $post_id the ID of the post we're working with
				*/
				$last_modified = apply_filters( 'wpe_ac_last_modified_header', $last_modified, $post_id );
				//convert our last modified to the timestamp that all the browsers recognize
				$last_modified_header = gmdate( 'D, d M Y H:i:s T', $last_modified );
				header( "Last-Modified: $last_modified_header" );
		}
	}
	/**
	 * Set cache control headers
	 *
	 * Sets and builds headers based on our various configuration options.
	 * Sets cache-control headers, and checks for smarter cache.
	 *
	 * @since 0.3.6
	 * @see most_recent_comment_timestamp, get_sanitized_post_types
	 * @uses get_option, is_user_logged_in, comments_open, apply_filters
	 * @param string $post_type Post type of the requests
	 * @param string $last_modified Epoch of get_the_modified_date of the requests
	 * @param string $post_id Id of the specific post being checked
	 * @return cache control headers
	 */
	function send_header_cache_control_length( $last_modified, $post_id, $post_type ) {
		$last_mod_seconds = time() - $last_modified;
		if ( self::get( 'smarter_cache_enabled' ) &&
			//Let's only "Smarter cache" builtins
			in_array( $post_type, self::get( 'sanitized_builtin_post_types' ) ) && $last_mod_seconds > ( 60 * 60 * 24 * 7 * 4 ) ) { // 4 Weeks
			$cache_length = ( 60 * 60 * 24 * 30 * 6 ); // 6 Months
			$cache_length = apply_filters( 'wpe_ac_smarter_cache_length', $cache_length );
		} else {
			$cache_length = self::get( $post_type . '_cache_expires_value' );
		}
		//@TODO if we update how our cache works, change this header to respect the tight varnish options
		header( "Cache-Control: max-age=$cache_length, must-revalidate" );
	}
	/**
	 * Get Most recent comment timestamp
	 *
	 * Pulls the most recent comment timestamp in utc for use in last-modified headers
	 *
	 * @since 0.3.2
	 * @uses get_comments
	 * @param string $post_id ID of post we're looking up
	 * @return string Timestamp of most recent comment.
	 */
	function most_recent_comment_timestamp( $post_id ) {
		// Arguments used in lookup of comment, to make sure we get what we need
		$args = array(
			'orderby' => 'comment_date_gmt',
			'number'  => 1,
			'post_id' => $post_id,
		);
		$comment_info = get_comments( $args );
		// This comes out in an array, so lets break it down to just the single comment object
		$comment_info_object = array_shift( $comment_info );
		$most_recent_comment_timestamp = strtotime( $comment_info_object->comment_date_gmt );
		/**
		 * Update comment last modified variable.
		 *
		 * Allows the customization of comment last_modified variable.
		 *
		 * @since 0.3.6
		 *
		 * @param string $most_recent_comment_timestamp the current comment last modified variable. In epoch format.
		 */
		$most_recent_comment_timestamp = apply_filters( 'wpe_ac_most_recent_comment_timestamp', $most_recent_comment_timestamp );
		return $most_recent_comment_timestamp;
	}
	/**
	 * Compare last modified to comments
	 *
	 * Compares the last modified passed with the most recent comment timestamp.
	 *
	 * @since 0.3.6
	 * @uses get_option
	 * @param string $last_modified variable used to compare, and return.
	 * @param string $post_id post id used to pull comment data
	 * @return string $true_last_modified timestamp to proceed with
	 */
	function compare_comments_to_true_last_modified( $last_modified, $post_id ) {
		if ( ! $post_id || ! comments_open( $post_id ) ) {
			return $last_modified;
		}
		$comment_time_stamp = $this->most_recent_comment_timestamp( $post_id );
		return ( $comment_time_stamp > $last_modified ) ? $comment_time_stamp : $last_modified;
	}
	/**
	 * Compare last modified to global
	 *
	 * Compares the last modified passed in with the global value and returns the larger option
	 *
	 * @since 0.3.6
	 * @uses get_option
	 * @param string $last_modified get_the_modified_date last time the post was modified
	 * @return string $true_last_modified timestamp to proceed with
	 */
	function global_last_modified_compare( $last_modified ) {
		//get the global last modified as a baseline
		$global_last_modified = get_option( 'wpe_ac_global_last_modified' );
		//compare the global to the last_modified time passed as part of the request
		return ( $global_last_modified > $last_modified ? $global_last_modified : $last_modified );
	}
}
