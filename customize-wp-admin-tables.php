<?php
/*
Plugin Name: Custom WP-Admin Tables
Plugin URI: http://9seeds.com/
Description: Examples to Customize WP-Admin Tables
Version: 1.0
Author: justin@9seeds.com
Author URI: http://9seeds.com/
*/

/**
 * This example uses a custom field called 'nsfw' and it's assumed to
 * be set to 'yes' or 'no'.  You can set this yourself on any (or all)
 * posts to see it in action
 */
class CustomizeWPAdminTables {

	private $quickEditActions = array();
	
	function onInit() {
		// the word 'post' in most of these actions & filters can be
		// replaced with a different post-type (ex. use
		// manage_page_posts_columns to add a column to 'All Pages')
		
		// Example #1 Add a custom column to a WP-Table 
		add_filter( 'manage_post_posts_columns', array( $this, 'addColumns' ) );

		// Example #2 Add custom data to the new column
		add_action( 'manage_post_posts_custom_column', array( $this, 'renderColumns' ), 10, 2 );

		// Example #3 Make the column sortable, and tell WP how to sort it
		add_filter( 'manage_edit-post_sortable_columns', array( $this, 'addSortableColumns' ) );
		add_filter( 'request', array( $this, 'sortColumnRequest' ) );

		// Example #4 Add a filter to the column
		add_action( 'restrict_manage_posts', array( $this, 'addColumnFilter') );
		add_action( 'parse_query', array( $this, 'columnFilterRequest' ) );

		// Example #5 Add a quickedit dropdown
		add_action( 'admin_menu', array( $this, 'onAdminMenu' ) );
		add_action( 'quick_edit_custom_box',  array( $this, 'quickEdit' ), 10, 2);
		add_action( 'admin_footer', array( $this, 'quickEditJs' ));
		add_filter( 'post_row_actions', array( $this, 'expandQuickEditLink' ), 10, 2);
		add_filter( 'wp_insert_post_data' , array( $this, 'postSave' ), 10, 2 );
	}

	//*
	// Filter for Example #1
	// Add a custom column
	function addColumns( $columns ) {
		$columns['not_safe_for_work'] = 'Not Safe For Work?';

		//other fun things to do:

		//1) remove a column programatically
		//unset($columns['tags']);

		//2) put a column in a specific spot (third from the left in this case)
		//$second_half = array_splice( $columns, 3 );
		//$columns['not_safe_for_work'] = 'Not Safe For Work?';
		//$columns = array_merge( $columns, $second_half );

		return $columns;
	}
	//*/

	//*
	// Action for Example #2	
	// Add data to the custom column
	// $column_index is the index set in Example #1 (not_safe_for_work)
	// $post_id is the posts numeric ID
	function renderColumns( $column_index, $post_id ) {
		if ( $column_index == 'not_safe_for_work' )
			echo get_post_meta( $post_id, 'nsfw', true );
	}
	//*/
	

	//*
	// Filters for Example #3
	// Tell WP which custom columns are sortable
	function addSortableColumns( $columns ) {
		$columns['not_safe_for_work'] = 'not_safe_for_work';		
		return $columns;
	}

	// Tell WP how to sort the custom column
	// 
	// See http://codex.wordpress.org/Function_Reference/WP_Query
	// for all the other options you can change here
	function sortColumnRequest( $query ) {
		if ( isset( $query['orderby'] ) && $query['orderby'] == 'not_safe_for_work' ) {
			$query = array_merge( $query,
				array(
					'orderby' => 'meta_value', //order by a meta value
					'meta_key' => 'nsfw'       //use the meta value who's key is 'nsfw'
			) );
		}
 
		return $query;
	}
	//*/
	
	//used for both Examples #4 & #5
	function printNSFWSelect( $name = 'nsfw_list', $selected_id = '', $first_label = '' ) {
		$values = array( '' => $first_label,
						 'yes' => 'yes',
						 'no' => 'no' );
			
		echo "<select name='{$name}' id='{$name}'>\n";
		foreach ( $values as $value => $label ) {
			$selected = $value == $selected_id ? " selected='selected'" : '';
			echo "<option value='{$value}'{$selected}>{$label}</option>\n";
		}
		echo "</select>\n";
	}

	//*
	// Actions for Example #4
	// Add a dropdown in the filter section
	function addColumnFilter() {
		$screen = get_current_screen();

		if ( $screen->post_type == 'post' ) {
			$selected_id = isset( $_GET['nsfw_filter'] ) ? $_GET['nsfw_filter'] : NULL;
			$this->printNSFWSelect( 'nsfw_filter', $selected_id, 'All NSFW Statuses' );
		}
	}

	// Make WP only retrieve certain posts when the filter button is pressed
	function columnFilterRequest( &$query ) {
		if ( !function_exists( 'get_current_screen' ) )
			return;
			
		$screen = get_current_screen();
		
		$qv = &$query->query_vars;

		if ( $screen->id == 'edit-post'
			 && isset( $qv['post_type'] ) && $qv['post_type'] == 'post'
			 && isset( $_GET['nsfw_filter'] ) && $_GET['nsfw_filter'] ) {
			$qv['meta_value'] = $_GET['nsfw_filter'];
		}
	}
	//*/

	//*
	// Actions & Filters for Example #5
	// Add a dropdown for NSFW to quick edit 
	function quickEdit( $column_name, $post_type ) {
		if ( $post_type != 'post' )
			return;		

		if ( $column_name == 'not_safe_for_work' ):	
		?>
		<fieldset class="inline-edit-col-left">
			<div class="inline-edit-col">
			<span class="title">NSFW?:</span>
			<?php $this->printNSFWSelect(); ?>
			</div>
		</fieldset>
		<?php
		endif;
	}

	function onAdminMenu() {
		wp_enqueue_script( 'jquery' );
	}
	
	// Make the quick edit link set the NSFW dropdown correctly
	function expandQuickEditLink( $actions, $post ) {
		$current_screen = get_current_screen();
		if ( $current_screen->id != 'edit-post' || $current_screen->post_type != 'post' )
			return $actions;

		$selected_id = get_post_meta( $post->ID, 'nsfw', true );

		$this->quickEditActions[] = "
jQuery('#post-{$post->ID} .editinline').click(function() {
    set_inline_nsfw_info('{$selected_id}');
});
";
		
		return $actions;
	}

	// Add a JavaScript function to the page for setting the quick edit dropdown
	function quickEditJs() {
		$current_screen = get_current_screen();
		if ( ( $current_screen->id != 'edit-post' ) || ( $current_screen->post_type !=  'post' ) )
			return;
		?>
<script type="text/javascript">
<!--
function set_inline_nsfw_info(nsfw_id) {
	// revert Quick Edit menu so that it refreshes properly
	inlineEditPost.revert();

	var nsfwList = jQuery('select[name="nsfw_list"]');
	jQuery('option:selected', nsfwList).removeAttr('selected');
	jQuery('option[value="' + nsfw_id + '"]', nsfwList).attr('selected', 'selected');	
}
<?php foreach ( $this->quickEditActions as $js ) echo $js; ?>
//-->
</script>
		<?php
	}
	
	// Save when a quick edit update is made
	function postSave( $data , $postarr ) {
		if ( isset( $postarr['nsfw_list'] ) ) {
			update_post_meta( $postarr['ID'], 'nsfw', $postarr['nsfw_list'] );
		}
		return $data;
	}
	//*/
}

$custom_tables_plugin = new CustomizeWPAdminTables();
add_action( 'init', array( $custom_tables_plugin, 'onInit' ) );
