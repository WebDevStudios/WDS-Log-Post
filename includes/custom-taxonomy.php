<?php
/**
 * WDS Log Post Custom Taxonomy
 * @version 0.1.0
 * @package WDS Log Post
 */

class WDSLP_Custom_Taxonomy {
	/**
	 * Parent plugin class
	 *
	 * @var WDS_Log_Post
	 * @since  0.1.0
	 */
	protected $plugin = null;

	public $taxonomy = 'wds_log_type';
	public $taxonomy_ready = false;

	/**
	 * Constructor
	 *
	 * @since 0.1.0
	 *
	 * @param WDS_Log_Post $plugin The main plugin object.
	 */
	public function __construct( WDS_Log_Post $plugin ) {
		$this->plugin = $plugin;
		$this->hooks();
	}

	/**
	 * Initiate our hooks
	 *
	 * @since 0.1.0
	 * @return  null
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_custom_taxonomy' ) );
	}

	public function register_custom_taxonomy() {
		$args = array(
			'label'             => __( 'Log Type' ),
			'public'            => false,
			'show_ui'           => false,
			'show_in_menu'      => false,
			'show_in_nav_menu'  => false,
			'show_admin_column' => false,
		);

		/**
		 * Filter the taxonomy arguments for our custom taxonomy.
		 *
		 * @since 0.3.0
		 * @author Jeremy Pry
		 *
		 * @param array $args The arguments used to register the taxonomy.
		 */
		$args = apply_filters( 'wds_log_post_taxonomy_args', $args );

		register_taxonomy(
			$this->taxonomy,
			$this->plugin->key,
			$args
		);

		$this->register_terms();
	}

	protected function register_terms() {
		// Setup a point for adding terms
		$terms = array(
			'General' => array(
				'slug'        => 'general',
				'description' => 'background-color:#00ee00;',
			),
			'Error' => array(
				'slug'        => 'error',
				'description' => 'background-color:#ff0000;',
			),
			'Debug' => array(
				'slug'        => 'debug',
				'description' => '',
			),
		);

		/**
		 * Filter the taxonomy terms that should be registered.
		 *
		 * @since 0.1.0
		 *
		 * @param array $terms The array of terms and their data. See default terms above for the format.
		 */
		$terms = apply_filters( 'wds_log_post_log_types', $terms );

		if ( count( $terms ) ) {
			foreach ( $terms as $term_label => $term_args ) {
				$term_slug = isset( $term_args['slug'] ) ? $term_args['slug'] : strtolower( $term_label );
				if ( false !== get_term_by( 'slug', $term_slug, $this->taxonomy ) ) {
					continue;
				}

				$new_term = wp_insert_term( $term_label, $this->taxonomy, $term_args );
			}
		}

		$this->taxonomy_ready = true;
	}
}
