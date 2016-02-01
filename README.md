# WDS Log Post #
**Contributors:**      WebDevStudios
**Donate link:**       http://webdevstudios.com
**Tags:**			   logging
**Requires at least:** 4.3
**Tested up to:**      4.3
**Stable tag:**        0.1.2
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

### Slightly More Detailed Installation ###

1. Upload the entire `/wds-log-post` directory to the `/wp-content/plugins/` directory.
2. Activate WDS Log Post through the 'Plugins' menu in WordPress.

## Usage ##

Recording logs is pretty straightforward: 

```php
/**
 * Method signature
 * log_message( $title, $full_message = '', $term_slug = 'general' )
 */

if ( something_went_wrong ) {
	WDS_Log_Post::log_message( 'A short notice', '', 'error' );
} else if ( something_really_bad_happened ) {
	WDS_Log_Post::log_message( 'Something to write home', get_error_details(), 'error' );
} else {
	// No problems, but let's log that as a general log of success
	WDS_Log_Post::log_message( 'Daily content sync went off without a hitch!' );
}

// $term_slug can also be an array of types 
WDS_Log_Post::log_message( 'This is a general error, for whatever reason', '', array( 'general' , 'error' ) );
```

### New in 0.2.x ###

The 0.2.x release sees the addition of updatable logs.

```php
// Create a log and record it's ID
$my_new_log = WDS_Log_Post::log_message( 'This is beginning', '', 'general' );

// Update it by passing the generated ID to the log_message method
WDS_Log_Post::log_message( 'Title update', 'content update', '', $my_new_log );

if ( $we_are_done ) {
	// Complete a log by passing the final parameter
	WDS_Log_Post::log_message( 'The final update', 'We are done', '', $my_new_log, true );
} else {
	// We can also abort logs now!
	WDS_Log_Post::abort_progress( $my_new_log );
}
```

Updatable logs also include the ability to dispaly progress. The progress bar will be updated via the WP Heartbeat API
if you update the same log ID while the post is being viewed.

```php
// Update progress to 50%
WDS_Log_Post::log_progress( $my_new_log, 50 );
```

## Tweaking ##

### Access Control ###

By default, only super admins have access to the logging post type. To add other user roles, you can filter
the eligibility with the `wds_log_post_user_can_see` role:

```php
// Allow editors to see logs
add_filter( 'wds_log_post_user_can_see', function( $user_can_see ) ) {
	return current_user_can( 'editor' );
} );
```

The `$user_can_see` is the current value of whether the logs are visible, in case you need to add several
different checks for roles or capabilities and want to know if the logs have been dis/allowed so far.

### Term Styling ###

**Deprecated** This is moving to using standard CSS, and in the future inline styles will not be supported (likely
in the next major release).

__Preferred CSS Method__

CSS classes should be `.wds-log-tag.<slug>` where `<slug>` is your custom term's slug.

```css
.wds-log-tag.warning {
	background: #ffff99;
}
```

__The Old Way (inline CSS)__

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

### 0.2.3 ###
* Now supports updating existing log posts.
* Progress bar is enabled for updated posts.
* Adds ability to abort or complete in-progress logs.
* Deprecated inlince CSS for tag styles.

### 0.1.2 ###
* Fix a bug with calling `wp_users` instead of `$wpdb->users`
* Fix a fatal error when `get_current_screen()` hasn't been defined.

### 0.1.0 ###
* First release
