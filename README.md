# WDS Log Post #

**Contributors:**      WebDevStudios  
**Donate link:**       http://webdevstudios.com  
**Tags:**			   logging  
**Requires at least:** 4.3  
**Tested up to:**      4.6  
**Stable tag:**        0.4.0  
**License:**           GPLv2  
**License URI:**       http://www.gnu.org/licenses/gpl-2.0.html  

## Description ##

A Log custom post type for logging all the things!

Creates a logging post type. This post type is read-only aside from the ability to trash and delete log posts. The post
type also includes a taxonomy (with a filter for adding your own log types on top of the defaults) that can be used to
filter log posts in the admin screen.

**N.B.** By default, only Super Admins can see the WDS Log post type in the UI. See how to filter that below.

![Typical Log](/../screenshots/typical-log.png?raw=true)

## Installation ##

* Place the plugin folder in the usual place and activate it from the plugins screen
* Add a filter for your own custom log type.

```php
function add_my_post_type_to_logs( $posttypes ) {
	$posttypes['my-log-cpt'] = 'My Log Type';
	return $posttypes;
}
add_filter( 'wds_log_post_types', 'add_my_post_type_to_logs' );1
```

### Slightly More Detailed Installation ###

1. Upload the entire `/wds-log-post` directory to the `/wp-content/plugins/` directory.
2. Add the `wds_log_post_types` filter somewhere in your code to define a log type.
3. Activate WDS Log Post through the 'Plugins' menu in WordPress.

## Usage ##

Recording logs is pretty straightforward:

```php
/**
 * Method signature
 * log_message( $posttype, $title, $full_message = '', $term_slug = 'general', $log_post_id = null, $completed = false )
 */

if ( something_went_wrong ) {
	WDS_Log_Post::log_message( 'my-log-cpt', 'A short notice', '', 'error' );
} else if ( something_really_bad_happened ) {
	WDS_Log_Post::log_message( 'my-log-cpt', 'Something to write home', get_error_details(), 'error' );
} else {
	// No problems, but let's log that as a general log of success
	WDS_Log_Post::log_message( 'my-log-cpt', 'Daily content sync went off without a hitch!' );
}

// $term_slug can also be an array of types
WDS_Log_Post::log_message( 'my-log-cpt', 'This is a general error, for whatever reason', '', array( 'general' , 'error' ) );
```

## Tweaking ##

### Access Control ###

By default, only super admins have access to the logging post type. To add other user roles, you can filter
the eligibility with the `wds_log_post_user_can_see` role:

```php
// Allow editors to see logs
// Modified in 0.4.0 to use the log type
add_filter( 'wds_log_my_log_cpt_user_can_see', function( $user_can_see ) ) {
	return current_user_can( 'editor' );
} );

// Pre 0.4.0
add_filter( 'wds_log_post_user_can_see', function( $user_can_see ) ) {
	return current_user_can( 'editor' );
} );
```

The `$user_can_see` is the current value of whether the logs are visible, in case you need to add several
different checks for roles or capabilities and want to know if the logs have been dis/allowed so far.

### Term Styling ###

This post type uses a hidden taxonomy, `wds_log_type`, whose terms are the different log types. This plugin comes with
two built-in types: 'General' and 'Error.' You can add more with the `wds_log_post_log_types` filter:

```php
// Add a custom "Warning" log type:
add_filter( 'wds_log_post_log_types', function ( $terms ) {
	if ( ! isset( $terms['Warning'] ) ) {
		$terms['Warning'] = array(
			'slug'        => 'warning',
			'description' => 'background-color: #ffff99',
		);
	}

	return $terms;
} );
```

## Changelog ##

### 0.4.0 ###
* Logs now **must** fit a post type defined by the user via the `wds_log_post_types` filter.

### 0.3.1 ###
* Generate post slugs internally to avoid WP looking for slugs on it's own.

### 0.3.0 ###
* Added a filter to the term lookup code to determine whether pre-defined terms are required
* Added a filter to the taxonomy registration arguments.
* Update some docblocks

### 0.1.2 ###
* Fix a bug with calling `wp_users` instead of `$wpdb->users`
* Fix a fatal error when `get_current_screen()` hasn't been defined.

### 0.1.0 ###
* First release
