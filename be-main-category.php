<?php
/*
Plugin Name: BE - Main Category Selector
Plugin URI: http://blogestudio.com/plugins/be-main-category
Description: Main Category Selector for WordPress 2.5+.
Version: 2.1.1
Author: Alejandro Carravedo
Author URI: http://blogestudio.com/
Min WP Version: 2.3
License: MIT License - http://www.opensource.org/licenses/mit-license.php

*/


// Add Action to Create New Field
add_action('activate_be-main-category/be-main-category.php', 'add_maincategory_in_table_posts');

function add_maincategory_in_table_posts() {
	global $wpdb;
	
	include_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	
	maybe_add_column(
		$wpdb->posts,
		'post_maincategory',
		"ALTER TABLE ".$wpdb->posts." ADD post_maincategory BIGINT(20) NOT NULL DEFAULT '0'"
	);
}



// Add Action For Save & Edit Post
add_action('save_post', 'mcsbe_save_maincategory');
add_action('edit_post', 'mcsbe_save_maincategory');

function mcsbe_save_maincategory($post_ID) {
	global $wpdb;
	
	$post_maincategory = 0;
	
	if ( isset($_POST['post_maincategory']) && $_POST['post_maincategory'] > 0 ) {
		$post_maincategory = (int) $_POST['post_maincategory'];
	} else {
		// If there's only one category, then make it the main one
		$catArray = get_the_category($post_ID);
		if ( count($catArray) == 1 ) {
			$post_maincategory = (int) $catArray[0]->cat_ID;
		}
	}
	
	$wpdb->query("
		UPDATE $wpdb->posts
		SET post_maincategory = '" . $post_maincategory . "'
		WHERE ID = '$post_ID'
	");
	
}



// Add Selector to POST FORM

add_action('admin_menu', 'mcsbe_add_options_box');

function mcsbe_add_options_box() {
	if (function_exists('add_meta_box')) {
		add_meta_box( 'maincategorydiv', __('Main Category', 'mcsbe'), 'mcsbe_add_options_box_meta', 'post', 'normal' );
	} else {
		add_action('dbx_post_advanced', 'mcsbe_add_options_box_dbx');
	} 
}

function mcsbe_add_options_box_dbx() { // WP 2.3
	
	echo '<fieldset id="maincategorydiv" class="dbx-box">';
		echo '<h3 class="dbx-handle">'.__('Main Category', 'mcsbe').':</h3>';
		echo '<div class="dbx-content">';
			mcsbe_getCategorySelector();
		echo '</div>';
	echo '</fieldset>';
	
}

function mcsbe_add_options_box_meta() { // WP 2.5
	global $post, $wpdb;
	
	mcsbe_getCategorySelector();
}

function mcsbe_getCategorySelector($parent = 0) { // Category Selector
	global $post, $post_ID, $mode, $wpdb;
	
	$allCategories = $wpdb->get_results( "
		SELECT $wpdb->term_taxonomy.term_taxonomy_id AS cat_ID, $wpdb->terms.name AS cat_name
		FROM $wpdb->term_taxonomy LEFT JOIN $wpdb->terms ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id
		WHERE $wpdb->term_taxonomy.parent = $parent 
			AND $wpdb->term_taxonomy.taxonomy = 'category' 
			AND ( $wpdb->term_taxonomy.count = 0 OR $wpdb->term_taxonomy.count != 0 OR ( $wpdb->term_taxonomy.count = 0 AND $wpdb->term_taxonomy.count = 0 ) )
		ORDER BY $wpdb->terms.name ASC
	", ARRAY_A );
	
	if ( count( $allCategories ) > 0 ) {
		echo '<select name="post_maincategory" id="post_maincategory" class="postform">';
			echo '<option value="0">&#8211;'.__('Not Selected', 'mcsbe').'&#8211;</option>';
			while( list($k, $v) = each($allCategories) ) {
				echo '<option value="'.$v['cat_ID'].'" '.( ($post->post_maincategory == $v['cat_ID']) ? 'selected' : '' ).' >'.$v['cat_name'].'</option>';
			}
		echo '</select>';
	}
}



// Load JS Code for Selector in Admin Pages
add_filter('admin_footer', 'mcsbe_jscode');

function mcsbe_jscode() {
	if (strpos($_SERVER['REQUEST_URI'], 'post.php') 
		||  strpos($_SERVER['REQUEST_URI'], 'post-new.php')) { // post-new for WP 2.1
	?>
	<script language="JavaScript" type="text/javascript">
		<!--
		var categoryDiv = document.getElementById("categorydiv");
		if (categoryDiv) {
			var inputs = categoryDiv.getElementsByTagName("input");
			for (var i = 0; i < inputs.length; ++i) {
				var input = inputs[i];
				// Make sure it really is a category checkbox
				if (input.type == "checkbox"
					&& (   input.id ==    "category-"+input.value // up to WordPress 2.0.5
						|| input.id == "in-category-"+input.value)) { // WordPress 2.1+
					input.onclick = mcsbe_selectedcategory;
				}
			}
		}
		
		function mcsbe_selectedcategory() {
			
			if ( this.checked && this.form.post_maincategory.value == '0' ) {
				for (var i = 0; i < this.form.post_maincategory.length; ++i) {
					if (this.form.post_maincategory[i].value == this.value) {
						this.form.post_maincategory[i].selected = true;
						alert('<?php _e('Selected Main Category', 'mcsbe'); ?>');
						break;
					}
					
				}
			}else if ( !this.checked && this.form.post_maincategory.value == this.value ) {
				for (var i = 0; i < this.form.post_maincategory.length; ++i) {
					if (this.form.post_maincategory[i].value == 0) {
						this.form.post_maincategory[i].selected = true;
						alert('<?php _e('Main Category Has Been De-Selected', 'mcsbe'); ?>');
						break;
					}
				}
			}
		}
		//-->
	</script><?php
	}
}



// Template Function: Get main category from post.
function mcsbe_get_maincategory($postID) {
	global $post;
	
	$codeExit = '-';
	
	if ($post->post_maincategory > 0) {
		$codeExit = get_the_category_by_ID($post->post_maincategory);
	}
	
	return $codeExit;
}



// Function to Permalinks, always main category in permalink tag "%category%"
function mcsbe_maincategory_by_categories($content) {
	global $post;
	
	if ($post->post_maincategory > 0) {
		
		$permalink = get_option('permalink_structure');
		
		$category = '';
		if ( strstr($permalink, '%category%') ) {
			$cats = get_the_category($post->ID);
			$category = $cats[0]->category_nicename;
			if ( $parent=$cats[0]->category_parent )
				$category = get_category_parents($parent, FALSE, '/', TRUE) . $category;
		}
		
		if ($category != '') {
			$main_category = &get_category($post->post_maincategory);
			$content = str_replace($category, $main_category->category_nicename, $content);
		}
	}
	
	return $content;
}

add_filter('the_permalink', 'mcsbe_maincategory_by_categories');
add_filter('post_link', 'mcsbe_maincategory_by_categories');

?>