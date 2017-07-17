=== Haystack ===
Contributors: francoisppeloquin
Donate link: https://haystack.menu/
Tags: haystack, search, elasticsearch, mellenger, navigation, third-party integration
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://haystack.menu/

Haystack enables your search bar to become a super-charged, auto-completing web for finding content.


== Description ==

Modern, mobile-friendly web design simplifies navigation but makes it harder to locate specific content. By adding Haystack to your site, your search bar becomes a super-charged, auto-completing web utility that helps people quickly find what they’re looking for.


== Installation ==

1. Go to [Haystack.menu](https://haystack.menu/) to get your *license* for the Haystack search
1. Upload the plugin files to the `/wp-content/plugins/haystack-wordpress` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Use the Haystack screen and configure the plugin
1. Set the optional search field ID tag in HTML (i.e. #search_bar) or allow Haystack to create a default styled footer bar
1. Allow time for the indexer to run the first crawl of the site
1. Go to the home page and try it out 


== Frequently Asked Questions ==

= How do I obtain a license for Haystack? =

Got to [Haystack.menu](https://haystack.menu/) and follow the simple steps in order to create your account and receive access.

= Are there ways to override the content sent up to the server? Such as images or content so that I can make my searches more customizable? =

Haystack has built in filters that allow you to change some defined variables that represent your web page. Currently the filters `haystack_set_type`, `haystack_set_image`, `haystack_set_body`, and `haystack_set_tags` have been enabled. These functions are passed the ID of the post, and the returned data will be sent to elasticsearch. 

Here are two examples set in a user's theme folder in their functions.php file:

	//We want type here to show much more data than the average
	function _haystack_set_type($id) {
	  //Adding post type as the first of the tags to pass
	  $post_type = get_post_type($id);
	  $post_type = get_post_type_object($post_type);
	  $tags[] = array($post_type->labels->singular_name);

	  //Adding the post author as the second part of the tags array to pass
	  $post_auth = get_post($id);
	  $post_auth = $post_auth->post_author;
	  $tags[] = get_the_author_meta('nicename',$post_auth);
	  
	  //Adding categories to the array
	  $post_cat = wp_get_post_categories($id);
	  foreach ($post_cat as $c) {
	    $cat = get_category($c);
	    $tags[] = $cat->name;
	  }

	  //Adding tags associated with the post, if they exist
	  $post_tags = wp_get_post_tags($id);
	  foreach ($post_tags as $key => $val) {
	    $tags[] = $val->name;
	  }

	  //Return in <cat> tags
	  return (!empty($tags) ? '<cat>'.implode('</cat><cat>',$tags).'</cat>' : '');
	}
	//Apply the filter
	add_filter('haystack_set_type','_haystack_set_type');

The second example here hooks in and replaces all images with a static one:

	//Add a sample image to each Haystack post item. Image could alternately be fetched from the ID
	function _haystack_set_image($id) {
	  return 'http://www.gallaghersmash.com/wp-content/uploads/2015/04/gallagher.jpg';
	}
	//Apply the filter
	add_filter('haystack_set_image','_haystack_set_image');


== Screenshots ==

1. Go to https://haystack.menu/ to get your *license* for the Haystack search


== Changelog ==

= 1.0 =
* The first version of the plugin has been created.


== Upgrade Notice ==

= 1.0 =
The first version of the plugin has been created.
