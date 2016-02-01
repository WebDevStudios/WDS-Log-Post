<?php
/**
 * Plugin Name: WDS Log Post
 * Plugin URI:  http://webdevstudios.com
 * Description: A Log custom post type for logging all the things!
 * Version:     0.2.3
 * Author:      WebDevStudios
 * Author URI:  http://webdevstudios.com
 * Donate link: http://webdevstudios.com
 * License:     GPLv2
 * Text Domain: wds-log-post
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2015 WebDevStudios (email : contact@webdevstudios.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using generator-plugin-wp
 */


/**
 * Autoloads files with classes when needed
 *
 * @since  0.1.0
 * @param  string $class_name Name of the class being requested
 * @return  null
 */
function wds_log_post_autoload_classes( $class_name ) {
	if ( 0 !== strpos( $class_name, 'WDSLP_' ) ) {
		return;
	}

	$filename = strtolower( str_replace(
		'_', '-',
		substr( $class_name, strlen( 'WDSLP_' ) )
	) );

	WDS_Log_Post::include_file( $filename );
}
spl_autoload_register( 'wds_log_post_autoload_classes' );


/**
 * Main initiation class
 *
 * @since  0.1.0
 * @var  string $version  Plugin version
 * @var  string $basename Plugin basename
 * @var  string $url      Plugin URL
 * @var  string $path     Plugin Path
 */
class WDS_Log_Post {

	/**
	 * Current version
	 *
	 * @var  string
	 * @since  0.1.0
	 */
	const VERSION = '0.2.3';

	/**
	 * URL of plugin directory
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $path = '';

	/**
	 * Plugin basename
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $basename = '';

	/**
	 * Singleton instance of plugin
	 *
	 * @var WDS_Log_Post
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	protected $key = 'wds_log_post';

	/**
	 * Configurable settings.
	 *
	 * @var array
	 * @since 0.2.1
	 */
	protected static $config = array(
		'show_timestamps' => false,
		'append_logs'     => true,
	);

	/**
	 * Separator for updating log files.
	 *
	 * @var string
	 * @sicne 0.2.0
	 */
	const SEPARATOR = "\n------------------------------------------------\n";

	/**
	 * Separator for updating log files.
	 *
	 * @var string
	 * @sicne 0.2.0
	 */
	const TITLESEPARATOR = "\n---------\n";

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since  0.1.0
	 * @return WDS_Log_Post A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin
	 *
	 * @since  0.1.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );
		$this->plugin_classes();
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 *
	 * @since 0.1.0
	 * @return  null
	 */
	public function plugin_classes() {
		if ( ! class_exists( 'WDSLP_Wds_Log' ) ) {
			self::include_file( 'wds-log' );
		}

		if ( ! class_exists( 'WDSLP_Custom_Taxonomy' ) ) {
			self::include_files( 'custom-taxonomy' );
		}

		$this->cpt             = new WDSLP_Wds_Log( $this );
		$this->custom_taxonomy = new WDSLP_Custom_Taxonomy( $this );
	}

	/**
	 * Add hooks and filters
	 *
	 * @since 0.1.0
	 * @return null
	 */
	public function hooks() {
		register_activation_hook(   __FILE__, array( $this, '_activate' ) );

		add_action( 'init', array( $this, 'init' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'register_enqueue_scripts_styles' ) );
		add_action( 'admin_head', array( $this, 'maybe_enqueue_scripts_styles' ) );

		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
	}

	function heartbeat_received( $response, $data ) {
		if ( ! isset( $data['wp-refresh-post-lock']['post_id'] ) ) {
			return $response;
		}

		$post_id = absint( $data['wp-refresh-post-lock']['post_id'] );
		$post = get_post( $post_id );

		if ( empty( $post ) || $this->cpt->post_type !== $post->post_type ) {
			return $response;
		}

		$progress = get_post_meta( $post_id, '_wds_log_progress', 1 );

		if ( ! empty( $progress ) ) {
			$response['wdslp_progress'] = get_post_meta( $post_id, '_wds_log_progress', 1 );
			$response['wdslp_progress_aborted'] = (bool) get_post_meta( $post_id, '_wds_log_progress_aborted', 1 );
		}

		// Update post content.
		$response['wdslp_content'] = $post->post_content;

		return $response;
	}

	public function register_enqueue_scripts_styles() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_style( $this->key . '_admin_css', self::url( "assets/css/wds-log-post{$min}.css" ), $this->version );
		wp_register_script( $this->key . '_admin_js', self::url( 'assets/js/main.min.js' ), array( 'jquery' ), self::VERSION );
	}

	public function maybe_enqueue_scripts_styles() {
		$screen = get_current_screen();

		if ( ! $this->cpt->post_type === $screen->post_type ) {
			return;
		}

		wp_enqueue_style( $this->key . '_admin_css' );

		// Enqueue progress bar.
		wp_enqueue_script( 'jquery-ui-progressbar' );
		wp_enqueue_script( 'heartbeat' );
		wp_enqueue_script( $this->key . '_admin_js' );
	}

	/**
	 * Activate the plugin
	 *
	 * @since  0.1.0
	 * @return null
	 */
	function _activate() {
		// Make sure any rewrite functionality has been loaded
		flush_rewrite_rules();
	}

	/**
	 * Init hooks
	 *
	 * @since  0.1.0
	 * @return null
	 */
	public function init() {
		$user_can_see = is_super_admin();

		/**
		 * Filter to hook in to check if the current user can see the log post type
		 *
		 * @param bool $user_can_see Defaults to whether the current user "can" "administrator"
		 */
		$user_can_see = apply_filters( 'wds_log_post_user_can_see', $user_can_see );

		if ( ! $user_can_see ) {
			global $wp_post_types;

			if ( isset( $wp_post_types[ $this->cpt->post_type ] ) ) {
				unset( $wp_post_types[ $this->cpt->post_type ] );
			}
		}
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.1.0
	 * @param string $field
	 * @throws Exception Throws an exception if the field is invalid.
	 * @return mixed
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'basename':
			case 'url':
			case 'path':
			case 'key':
				return $this->$field;
			default:
				throw new Exception( 'Invalid '. __CLASS__ .' property: ' . $field );
		}
	}

	/**
	 * Include a file from the includes directory
	 *
	 * @since  0.1.0
	 * @param  string  $filename Name of the file to be included
	 * @return bool    Result of include call.
	 */
	public static function include_file( $filename ) {
		$file = self::dir( 'includes/'. $filename .'.php' );
		if ( file_exists( $file ) ) {
			return include_once( $file );
		}
		return false;
	}

	/**
	 * This plugin's directory
	 *
	 * @since  0.1.0
	 * @param  string $path (optional) appended path
	 * @return string       Directory and path
	 */
	public static function dir( $path = '' ) {
		static $dir;
		$dir = $dir ? $dir : trailingslashit( dirname( __FILE__ ) );
		return $dir . $path;
	}

	/**
	 * This plugin's url
	 *
	 * @since  0.1.0
	 * @param  string $path (optional) appended path
	 * @return string       URL and path
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );
		return $url . $path;
	}

	/**
	 * Creates a new log entry
	 *
	 * @param string $message               The message for the error. Should be concise, as it will be the log entry title.
	 * @param string $full_message[='']     A longer message, if desired. This can include more detail.
	 * @param mixed  $term_slug[='general'] A string or array of log types to assign to this entry.
	 * @param int    $log_post_id[=null]    An option ID of the log message to update.
	 * @param bool   $completed[=false]     If updating, specifcy whether this update is the last one.
	 *
	 * @return int|WP_Error
	 */
	public static function update_message( $log_post_id, $full_message = '', $title = '' ) {
		$log_post = get_post( $log_post_id );

		// If no post object or no update values, bail
		if ( empty( $log_post ) || ! ( strlen( $title ) || strlen( $full_message ) ) ) {
			return false;
		}

		$log_post_arr = array(
			'post_status'  => 'publish',
			'ID' => $log_post_id,
		);

		if ( strlen( $title ) ) {
			$log_post_arr['post_title'] = $title;
		}

		if ( strlen( $full_message ) ) {
			$log_post_arr['post_content'] = $full_message;
		}

		if ( self::get_option( 'append_logs' ) ) {
			$title_time = '';

			if ( self::get_option( 'show_timestamps' ) ) {
				$title_time = '[' . date( 'Y-m-d H:i:s' ) . ']';
			}

			if ( strlen( $title ) ) {
				$title = $title_time . $log_post_arr['post_title'] . self::TITLESEPARATOR;
			}

			$log_post_arr['post_content'] = $log_post->post_content . self::SEPARATOR . $title . $log_post_arr['post_content'];
		}

		return wp_update_post( $log_post_arr );
	}

	/**
	 * Creates a new log entry
	 *
	 * @param string $message               The message for the error. Should be concise, as it will be the log entry title.
	 * @param string $full_message[='']     A longer message, if desired. This can include more detail.
	 * @param mixed  $term_slug[='general'] A string or array of log types to assign to this entry.
	 * @param int    $log_post_id[=null]    An option ID of the log message to update.
	 * @param bool   $completed[=false]     If updating, specifcy whether this update is the last one.
	 *
	 * @return int|WP_Error
	 */
	public static function log_message( $title, $full_message = '', $term_slug = 'general', $log_post_id = null, $completed = false ) {
		$self = self::get_instance();
		if ( ! $self->custom_taxonomy->taxonomy_ready ) {
			$self->custom_taxonomy->register_custom_taxonomy();
		}

		$log_post_arr = array(
			'post_title'   => $title,
			'post_status'  => 'publish',
		);

		if ( null !== $log_post_id ) {
			$log_post = get_post( $log_post_id );
			$log_post_arr['ID'] = $log_post_id;

			// Setup the title.
			$log_post_arr['post_title'] = ( $completed ? '[Complete] ' : '[In Progress] ' ) . $log_post_arr['post_title'];

			// Setup post content.
			$log_post_arr['post_content'] = $full_message;

			if ( self::get_option( 'append_logs' ) ) {
				$title_time = '';

				if ( self::get_option( 'show_timestamps' ) ) {
					$title_time = '[' . date( 'Y-m-d H:i:s' ) . ']';
				}

				$log_post_arr['post_content'] = $log_post->post_content . self::SEPARATOR . $title_time . $log_post_arr['post_title'] . self::TITLESEPARATOR . $log_post_arr['post_content'];
			}

			$log_post_id = wp_update_post( $log_post_arr );
		} else {
			$log_post_arr['post_type']    = $self->cpt->post_type;
			$log_post_arr['post_content'] = $full_message;
			$log_post_arr['post_author']  = self::get_lowest_user_id();
			$log_post_id = wp_insert_post( $log_post_arr );
		}

		if ( ! is_wp_error( $log_post_id ) ) {
			if ( ! is_array( $term_slug ) ) {
				$term_slug = array( $term_slug );
			}

			$terms = array();

			foreach ( $term_slug as $term_lookup ) {
				$term = get_term_by( 'slug', $term_lookup, $self->custom_taxonomy->taxonomy );

				if ( false === $term ) {
					throw new Exception( sprintf( __( 'Could not find term %s for post_type %s' ), $term_lookup, $self->cpt->post_type ) );
				}

				$terms[] = $term->term_id;

			}

			wp_set_object_terms( $log_post_id, $terms, $self->custom_taxonomy->taxonomy );
		}

		return $log_post_id;
	}

	protected static function get_lowest_user_id() {
		global $wpdb;

		return absint( $wpdb->get_var( "SELECT `ID` FROM {$wpdb->users} ORDER BY `ID` ASC LIMIT 0,1;" ) );
	}

	public static function set_option( $option, $value = false ) {
		if ( in_array( $option, self::$config ) ) {
			self::$config[ $option ] = $value;
		}
	}

	public static function get_option( $option ) {
		return in_array( $option, self::$config ) ? self::$config[ $option ] : null;
	}

	/**
	 * Add progress bar updates under the log message.
	 *
	 * @param int $log_post_id The ID of the Log Post to apply progress to.
	 * @param int $progress    The amount of progress (between 0-100).
	 */
	public static function log_progress( $log_post_id, $progress ) {
		return update_post_meta( $log_post_id, '_wds_log_progress', abs( $progress ) );
	}

	/**
	 * Abort progress, but leave progress bar status.
	 *
	 * @param int $log_post_id The ID of the Log Post to apply progress to.
	 */
	public static function abort_progress( $log_post_id ) {
		update_post_meta( $log_post_id, '_wds_log_progress_aborted', true );
	}
}

/**
 * Grab the WDS_Log_Post object and return it.
 * Wrapper for WDS_Log_Post::get_instance()
 *
 * @since  0.1.0
 * @return WDS_Log_Post  Singleton instance of plugin class.
 */
function wds_log_post() {
	return WDS_Log_Post::get_instance();
}

// Kick it off
if ( apply_filters( 'wds_log_post_site_check', 'is_main_site' ) ) {
	add_action( 'plugins_loaded', array( wds_log_post(), 'hooks' ) );
}
