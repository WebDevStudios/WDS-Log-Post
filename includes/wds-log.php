<?php
/**
 * WDS Log Post Wds Log
 *
 * @version 0.2.0
 * @package WDS Log Post
 *
 * Changelog
 * 0.2.0
 * - Begin moving to CSS classes over term description.
 * - Move 99% of JS to separate script.
 * 0.1.1
 * - Restrict admin UI
 */

require_once dirname(__FILE__) . '/../vendor/cpt-core/CPT_Core.php';

class WDSLP_Wds_Log extends CPT_Core {
	/**
	 * Parent plugin class
	 *
	 * @var class
	 * @since  0.1.0
	 */
	protected $plugin = null;

	public $post_type = 'wdslp-wds-log';

	protected $filter_key = 'wdslp_type_filter';

	/**
	 * Constructor
	 * Register Custom Post Types. See documentation in CPT_Core, and in wp-includes/post.php
	 *
	 * @since 0.1.0
	 * @return  null
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->hooks();

		// Register this cpt
		// First parameter should be an array with Singular, Plural, and Registered name
		parent::__construct(
			array( __( 'WDS Log', 'wds-log-post' ), __( 'WDS Logs', 'wds-log-post' ), $this->post_type ),
			array(
				'supports'          => array( 'title', 'editor', ),
				'menu_icon'         => 'dashicons-book-alt',
				'show_in_admin_bar' => false,
				'public'            => false,
				'hierarchical'      => false,
				'menu_position'     => 100,
			)
		);
	}

	/**
	 * Initiate our hooks
	 *
	 * @since 0.1.0
	 * @return  null
	 */
	public function hooks() {
		// Remove "Add New"...
		add_action( 'admin_menu', array( $this, 'remove_add_menu' ) ); // From sidebar menu
		add_action( 'admin_head-edit.php', array( $this, 'alter_list_view' ) ); // From list view
		// Failsafe against actually getting to the Add New page
		add_action( 'load-post-new.php', array( $this, 'prevent_create_post' ) );

		// Remove meta boxes
		add_action( 'admin_head-post.php', array( $this, 'alter_post_view' ) );

		// Alter edit list row actions
		add_action( 'post_row_actions', array( $this, 'alter_post_row_actions' ), 10, 2 );
		add_filter( 'manage_wdslp-wds-log_posts_columns', array( $this, 'add_log_type_column' ) );
		add_action( 'manage_wdslp-wds-log_posts_custom_column', array( $this, 'alter_post_row_titles' ), 10, 2 );

		// Add custom taxonomy filter
		add_action( 'restrict_manage_posts', array( $this, 'add_taxonomy_filter' ) );
		add_action( 'parse_query', array( $this, 'filter_admin_list_taxonomy' ) );
	}

	/**
	 * Remove the submenu for adding a new log post
	 *
	 * @since 0.1.0
	 */
	public function remove_add_menu() {
		global $submenu;
		unset( $submenu[ 'edit.php?post_type=' . $this->post_type ][10] );
	}

	/**
	 * Removes the 'Add New' button from the list view screen and also removes
	 * the "Edit" bulk action
	 *
	 * @since 0.1.0
	 */
	public function alter_list_view() {
		$screen = get_current_screen();

		if ( null === $screen || $this->post_type !== $screen->post_type ) {
			return;
		}
	}

	/**
	 * No one should ever be able to create a new Log Post through the UI
	 *
	 * @since 0.1.0
	 */
	public function prevent_create_post() {
		$screen = get_current_screen();

		if ( 'add' === $screen->action && 'post' === $screen->base && $this->post_type === $screen->post_type ) {
			wp_die( __( 'This post type is read-only' ) );
		}
	}

	/**
	 * Alter the edit page to remove just about everything
	 *
	 * @since 0.1.1
	 */
	public function alter_post_view() {
		global $post;
		global $wp_meta_boxes;

		$screen = get_current_screen();

		if ( null === $screen || $this->post_type !== $screen->post_type ) {
			return;
		}

		$wp_meta_boxes[ $this->post_type ] = array();

		wp_localize_script( $this->plugin->key . '_admin_js', 'wds_log_post', array(
			'tax_info'         => $this->get_term_tag_html( $post->ID ),
			'progress_value'   => get_post_meta( $post->ID, '_wds_log_progress', true ),
			'progress_aborted' => get_post_meta( $post->ID, '_wds_log_progress_aborted', true ) ? 'true' : 'false',
			'messages'         => array(
				'complete'              => __( 'Complete', 'wds-log-post' ),
				'current_task_progress' => __( 'Current Task Progress', 'wds-log-post' ),
				'process_aborted'       => __( 'Process Aborted', 'wds-log-post' ),
				'process_complete'      => __( 'Process Complete', 'wds-log-post' ),
			),
		) );

		// Elements to remove from the page.
		$remove_elements = implode( ',', array(
			'#screen-options-link-wrap',
			'#edit-slug-box',
			'#ed_toolbar',
			'#wp-content-editor-tools',
			'#wp-word-count',
			'a.page-title-action',
			'#wpbody-content .wrap h1',
		) );
		?>
<style>
/**
 * Hide these elements with CSS first.
 */
<?php echo $remove_elements; ?> {
	display: none;
	visibility: hidden;
}
</style>
<script>
jQuery( document ).ready( function( $ ) {
	// Really remove everything.
	$('<?php echo $remove_elements; ?>').remove();
});
</script>
<?php
	}

	/**
	 * Changes a lot of the post row actions like quick edit etc
	 *
	 * @since 0.1.1
	 *
	 * @return array
	 */
	public function alter_post_row_actions( $actions, $post ) {
		if ( ! $this->post_type === $post->post_type ) {
			return $actions;
		}

		// Change "Edit" to "View"
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = strtr( $actions['edit'], array( 'Edit' => 'View Details' ) );
		}

		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['view'] );
		unset( $actions['clone'] );
		unset( $actions['edit_as_new_draft'] );

		return $actions;
	}

	public function add_log_type_column( $columns ) {
		unset( $columns['title'] );
		$type_column = array( 'custom_title' => 'Title' );
		$orig_columns = array_splice( $columns, 0, 1 ); // After 'cb' column
		return array_merge( $orig_columns, $type_column, $columns );
	}

	public function alter_post_row_titles( $column, $post_id ) {
		if ( 'custom_title' === $column ) {
			$edit_link = get_edit_post_link( $post_id );
			echo sprintf( '%s <a href="%s">%s</a>', $this->get_term_tag_html( $post_id ), $edit_link, get_the_title() );
		}
	}

	protected function get_term_tag_html( $post_id ) {
		$terms = wp_get_object_terms( $post_id, $this->plugin->custom_taxonomy->taxonomy );
		$term_html = '';

		// @deprecated Style from the description field is going away in favor of CSS classes.
		if ( count( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_html .= sprintf( '<span class="wds-log-tag %s" style="%s">%s</span>', $term->slug, $term->description, $term->name );
			}
		}

		return $term_html;
	}

	public function add_taxonomy_filter() {
		if ( ! $this->edit_screen_check() ) {
			return;
		}

		$options = array( 'all' => 'All' );
		$terms = get_terms( $this->plugin->custom_taxonomy->taxonomy );

		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}

		echo "<select name='{$this->filter_key}'>";

		foreach ( $options as $option_value => $option_label ) {
			$selected = isset( $_GET[ $this->filter_key ] ) && $_GET[ $this->filter_key ] === $option_value ? 'selected="selected"' : '';
			echo sprintf( '<option value="%s" %s>%s</option>', $option_value, $selected, $option_label );
		}

		echo '</select>';
	}

	public function filter_admin_list_taxonomy( $query ) {
		if ( ! $this->edit_screen_check()  || ! isset( $_GET[ $this->filter_key ] ) ) {
			return;
		}

		if ( 'all' === $_GET[ $this->filter_key ] ) {
			return;
		}

		$tax_query = array( array(
			'taxonomy' => $this->plugin->custom_taxonomy->taxonomy,
			'field'    => 'slug',
			'terms'    => $_GET[ $this->filter_key ],
		) );

		$query->set( 'tax_query', $tax_query );

		return $query;
	}

	protected function edit_screen_check() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		return null !== $screen && 'edit' === $screen->base && $this->post_type === $screen->post_type;
	}
}
