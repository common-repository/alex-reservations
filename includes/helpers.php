<?php


function alexr_get_translations($use_all = false) {
	return evavel_load_language_files($use_all);
}

function alexr_get_config() {

	$autofill = alexr_get_setting('widget_autofill_reservation_form');
	$current_user = wp_get_current_user();

	return [
		'ajaxurl' => evavel_ajaxurl(),
		'languages' => evavel_languages_as_options(),
		'widget_autofill_reservation_form' => ($autofill == 'true' || $autofill === true) ? 'true' : 'false',
		'is_logged_in' => is_user_logged_in() ? 'true' : 'false',
		'current_user' => [
			'first_name' => $current_user->first_name,
			'last_name' => $current_user->last_name,
			'email' => $current_user->user_email
		]
	];
}



/**
 * Allow only specific hook
 * @param $tag
 * @param $hook_allowed
 *
 * @return void
 */
function alexr_limit_hooks_to_one($tag = 'wp_footer', $hooks_allowed = ['wp_print_footer_scripts', 'wp_enqueue_global_styles'])
{
	global $wp_filter;
	//echo '<pre>'; print_r($wp_filter[$tag]->callbacks);  echo '</pre>';

	foreach($wp_filter[$tag]->callbacks as $key => $list){
		foreach($list as $hook_name => $value) {
			if ( !in_array($hook_name, $hooks_allowed)) {
				unset($wp_filter[$tag]->callbacks[$key][$hook_name]);
			}
		}
	}
	//echo '<pre>'; print_r($wp_filter[$tag]->callbacks);  echo '</pre>';
}
