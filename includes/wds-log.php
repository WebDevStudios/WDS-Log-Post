<?php
/**
 * WDS Log Post Wds Log
 *
 * @version 0.1.1
 * @package WDS Log Post
 *
 * Changelog
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
				// 'supports'          => array( 'title', 'editor', ),
				'supports'          => false,
				'menu_icon'         => 'dashicons-book-alt',
				'show_in_admin_bar' => false,
				'public'            => false,
				'hierarchical'      => false,
				'menu_position'     => 100,
				'capabilities' => array(
					'create_posts'  => is_multisite() ? 'do_not_allow' : false, // Removes support for the "Add New" function (use 'do_not_allow' instead of false for multisite set ups)
					'delete_posts'  => 'delete_posts',
				),
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
		// Alter edit list row actions
		add_action( 'post_row_actions', array( $this, 'alter_post_row_actions' ), 10, 2 );
		add_filter( "manage_{$this->post_type}_posts_columns", array( $this, 'add_log_type_column' ) );
		add_action( "manage_{$this->post_type}_posts_custom_column", array( $this, 'alter_post_row_titles' ), 10, 2 );
		add_filter( "bulk_actions-edit-{$this->post_type}", array( $this, 'remove_bulk_actions' ) );
		add_action( 'add_meta_boxes', array( $this, 'update_title_global' ) );
		add_action( 'edit_form_after_title', array( $this, 'output_title_content' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'screen_layout_columns', array( $this, 'adjust_view_for_single_cpt' ), 10, 3 );

		// Add custom taxonomy filter
		add_action( 'restrict_manage_posts', array( $this, 'add_taxonomy_filter' ) );
		add_action( 'parse_query', array( $this, 'filter_admin_list_taxonomy' ) );
	}

	public function enqueue_scripts() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || $this->post_type !== $screen->id ) {
			return;
		}

		wp_enqueue_style( 'media-views' );
	}

	function remove_bulk_actions( $actions ) {
		unset( $actions['edit'] );
		return $actions;
	}

	function adjust_view_for_single_cpt( $columns, $screen_id, $screen ) {
		if ( $this->post_type !== $screen_id ) {
			return $columns;
		}

		$columns = array(
			'max' => 1,
			'default' => 1,
		);
		$screen->add_option( 'layout_columns', $columns );

		remove_meta_box( 'submitdiv', $screen, 'side' );
		remove_meta_box( 'slugdiv', $this->post_type, 'normal' );

		return $columns;
	}

	/**
	 * Alter the edit page to remove just about everything
	 *
	 * @since 0.1.1
	 */
	public function progress_js( $progress_value, $aborted) {
		?>
<script>
jQuery( document ).ready( function( $ ) {

	<?php if ( $progress_value ) : ?>
		var jQprogress = $( '#wds_log_progress' );
		var percent = parseInt( <?php echo $progress_value; ?>, 10 );
		var aborted = <?php echo $aborted; ?>;
		var complete = function( aborted ) {
			$('#wds-log-progress-holder .spinner').remove();
			var dashClass = aborted ? 'dashicons-no' : 'dashicons-yes';
			var message = aborted ? '<?php _e( 'Process aborted', 'wds-log-post' ); ?>' : '<?php _e( 'Process complete!', 'wds-log-post' ); ?>';
			$('#wds-log-progress-label').text( message ).addClass('dashicons-before ' + dashClass);
			$(document).off( 'heartbeat-tick', tick );
		};

		var setStatus = function( percent, aborted ) {
			jQprogress.progressbar({value: percent }).attr( 'title', percent + '<?php _e( '% Complete', 'wds-log-post' ); ?>' );

			if ( percent >= 100 ) {
				complete( aborted ? true : false );
			} else if ( aborted ) {
				complete( true );
			}
		};

		var tick = function(e, data) {
			if ( data.wdslp_progress && data.wdslp_progress <= 100 ) {
				setStatus( parseInt( data.wdslp_progress, 10 ), data.wdslp_progress_aborted );
			}

			if ( data.wdslp_content ) {
				$('#wds-log-content' ).val( data.wdslp_content );
			}
		};

		$(document).on( 'heartbeat-tick', tick );

		setStatus( percent, aborted );

	<?php else: ?>
		$('#wds-log-progress-holder').remove();
	<?php endif; ?>
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
		if ( $this->post_type !== $post->post_type ) {
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

	public function update_title_global() {
		// Replaces the h1 title, which is normally $post_type_object->labels->edit_item
		$GLOBALS['title'] = the_title( '<h2>', '</h2>', false );
	}

	public function output_title_content( $post ) {
		if ( ! isset( $post->post_type ) || $this->post_type !== $post->post_type ) {
			return;
		}

		echo '<pre class="wp-editor-area wp-editor-container">'. $this->get_term_tag_html( $post->ID ) . '<hr/>';
		echo '<textarea id="wds-log-content" style="width:100%;min-height:500px" readonly="readonly">';
		print_r( $post->post_content );
		echo '</textarea></pre>';

		$progress_html = '';
		$progress_value = false;
		$aborted = 'false';

		if ( '' !== get_post_meta( $post->ID, '_wds_log_progress', true ) ) {
			$progress_value = absint( get_post_meta( $post->ID, '_wds_log_progress', true ) );
			$aborted = get_post_meta( $post->ID, '_wds_log_progress_aborted', true ) ? 'true' : 'false';
			$progress_html = implode( '', array(
				'<div id="wds-log-progress-holder">',
					'<div class="spinner" style="visibility:visible; float: left;"></div>',
					'<strong style="float:left; margin: 5px" id="wds-log-progress-label">Current Task Progress:</strong>',
					'<div style="float: right" class="media-progress-bar" id="wds_log_progress" title="' . sprintf( __( '%d%% Complete', 'wds-log-post' ), $progress_value ) . '"></div>',
				'</div>',
			));
		}

		echo $progress_html;

		// use sep. JS file, and move all the other JS there
		$this->progress_js( $progress_value, $aborted );
	}

	protected function get_term_tag_html( $post_id ) {
		$terms = get_the_terms( $post_id, $this->plugin->custom_taxonomy->taxonomy );
		$term_html = '';

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
