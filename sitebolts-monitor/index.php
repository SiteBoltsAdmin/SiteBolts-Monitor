<?php
/*
* Plugin Name: SiteBolts Monitor
* Description: A site health plugin that helps your web manager keep your site updated, online, and free of spam.
* Version: 4.0
* Author: SiteBolts
* Author URI: https://sitebolts.com/
*/

function sbmon_get_plugin_version()
{
	return '4';
}

function sbmon_echo_json_and_die($data, $status_code)
{
	http_response_code($status_code);
	echo json_encode($data, JSON_PRETTY_PRINT);
	die();
}

function sbmon_add_wp_admin_menu()
{
	add_menu_page( 
					'SiteBolts Monitor - Overview', 
					'SiteBolts Monitor', 
					'administrator', 
					'sitebolts_monitor_overview',
					'sbmon_generate_overview_page', 
					'dashicons-admin-generic'
				);
}

add_action('admin_menu', 'sbmon_add_wp_admin_menu');

function sbmon_generate_overview_page()
{
	if (current_user_can('administrator') === false)
	{
		echo '<p>You must be logged in as an administrator to view this page.</p>';
		wp_die();
	}
	
	$metrics = sbmon_get_site_metrics();
	
	?>
	<style>
	.site-metric-data
	{
		background-color: #fafafa;
		border: 1px solid #999999;
		padding: 10px;
		overflow-y: scroll;
		max-height: 300px;
		margin: 0;
	}
	
	.sitebolts-monitor-token-feedback .error-message
	{
		color: #dd0000;
	}
	
	.sitebolts-monitor-token-feedback .success-message
	{
		color: #009900;
	}
	</style>
	<?php
	
	echo '<div class="wrap">';
	echo '<h1 class="wp-heading-inline">SiteBolts Monitor - Overview</h1><hr class="wp-header-end">';
	echo '</div>'; //.wrap
	echo '<div>';
	echo '<p class="plugin-description">SiteBolts Monitor is a lightweight plugin that helps keep your site fully updated and free of spam.</p>';
	echo '</div>';
	echo '<div class="wrap">';
	echo '<h3>Debugging data</h3>';
	echo '<pre class="site-metric-data">' . htmlspecialchars(var_export($metrics, true)) . '</pre>';
	echo '</div>';
	echo '<div class="wrap">';
	echo '<h3>API token</h3>';
	echo '<p>This token grants secure access to your site\'s debugging data via a REST URL. The token is stored as a hashed value in your database and only one token can be active at any time.</p>';
	echo '<div>';
	echo '<input type="text" class="sitebolts-monitor-token-input" value="" size="48">';
	echo '<input type="button" class="sitebolts-monitor-generate-token-button" value="Generate">';
	echo '<input type="button" class="sitebolts-monitor-save-token-button" value="Save">';
	echo '<div class="sitebolts-monitor-token-feedback"></div>';
	echo '</div>';
	echo '</div>'; //.wrap
}


//This function is a simplified reimplementation of wp_get_update_data that skips the current_user_can checks
//This allows it to work with the REST API via our token rather than going through the standard WordPress authentication process
//IMPORTANT: This function returns sensitive site data, so it should not be called until after a valid token has been provided
function sbmon_wp_get_update_data()
{
	//This path is required to make sbmon_wp_get_update_data() work with the REST API
	require_once ABSPATH . 'wp-admin/includes/update.php';
	
	$counts =	[
					'plugins'      => 0,
					'themes'       => 0,
					'wordpress'    => 0,
					'translations' => 0,
				];

	$update_plugins = get_site_transient('update_plugins');

	if (!empty($update_plugins->response))
	{
		$counts['plugins'] = count($update_plugins->response);
	}

	$update_themes = get_site_transient('update_themes');

	if (!empty( $update_themes->response))
	{
		$counts['themes'] = count($update_themes->response);
	}

	$update_wordpress = get_core_updates(array('dismissed' => false));

	if (!empty($update_wordpress) && !in_array($update_wordpress[0]->response, array('development', 'latest'), true))
	{
		$counts['wordpress'] = 1;
	}

	if (wp_get_translation_updates())
	{
		$counts['translations'] = 1;
	}

	$counts['total'] = $counts['plugins'] + $counts['themes'] + $counts['wordpress'] + $counts['translations'];


	$update_data =	[
						'counts' => $counts,
					];
	
	return $update_data;
}



//IMPORTANT: This function assumes that the user has already authenticated themselves via a token prior to calling this function
//TODO: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
//TODO: https://stackoverflow.com/questions/42381521/
function sbmon_get_site_metrics()
{
	//This path is required to make get_plugins() work with the REST API
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
	
	$metrics = [];
	
	$cf7_active = (is_plugin_active('contact-form-7/wp-contact-form-7.php') === true);
	
	$plugin_version = sbmon_get_plugin_version();
	$php_version = phpversion();
	//$update_data = wp_get_update_data();
	$update_data = sbmon_wp_get_update_data();
	$comments_require_manual_approval = get_option('comment_moderation');
	$max_links_to_hold_comment = get_option('comment_max_links');
	$site_is_using_child_theme = is_child_theme();
	$noindex_disabled = get_option('blog_public');
	$num_pending_comments = get_option('get_pending_comments_num');
	$xmlrpc_enabled = 'Unknown'; //TODO
	$plugins = get_plugins();
	$auto_update_plugins = get_option('auto_update_plugins');
	$auto_update_themes = get_option('auto_update_themes');
	$auto_update_core_major = get_option('auto_update_core_major');
	$auto_update_core_minor = get_option('auto_update_core_minor');
	$auto_update_core_dev = get_option('auto_update_core_dev');
	$home_url_is_using_https = str_starts_with(get_home_url(), 'https://');
	$site_url_is_using_https = str_starts_with(get_site_url(), 'https://');
	$child_theme_configurator_active = is_plugin_active('child-theme-configurator/child-theme-configurator.php');
	$cf7_missing_flamingo = ($cf7_active && (is_plugin_active('flamingo/flamingo.php') === false));
	$cf7_missing_honeypot = ($cf7_active && (is_plugin_active('contact-form-7-honeypot/honeypot.php') === false));
	$cf7_missing_recaptcha = $cf7_active && empty(maybe_unserialize(get_option('wpcf7'))['recaptcha'] ?? null);
	
	$metrics['plugin_version'] = $plugin_version;
	$metrics['php_version'] = $php_version;
	$metrics['num_plugin_updates'] = $update_data['counts']['plugins'] ?? null; //Should ideally be 0
	$metrics['num_theme_updates'] = $update_data['counts']['themes'] ?? null; //Should ideally be 0
	$metrics['num_core_updates'] = $update_data['counts']['wordpress'] ?? null; //Should ideally be 0
	$metrics['num_translation_updates'] = $update_data['counts']['translations'] ?? null; //Should ideally be 0
	$metrics['total_num_updates'] = $update_data['counts']['total'] ?? null; //Should ideally be 0
	$metrics['comments_require_manual_approval'] = $comments_require_manual_approval; //Should ideally be '1'
	$metrics['max_links_to_hold_comment'] = $max_links_to_hold_comment; //Should ideally be '0'
	$metrics['site_is_using_child_theme'] = $site_is_using_child_theme; //Should ideally be '0'
	$metrics['noindex_disabled'] = $noindex_disabled; //Should ideally be '1'
	$metrics['num_pending_comments'] = $num_pending_comments;
	$metrics['xmlrpc_enabled'] = $xmlrpc_enabled; //TODO
	$metrics['plugins'] = $plugins;
	$metrics['auto_update_plugins'] = $auto_update_plugins;
	$metrics['auto_update_themes'] = $auto_update_plugins;
	$metrics['auto_update_core_major'] = $auto_update_core_major; //Should ideally be 'enabled'
	$metrics['auto_update_core_minor'] = $auto_update_core_minor; //Should ideally be 'enabled'
	$metrics['auto_update_core_dev'] = $auto_update_core_dev; //Should ideally be 'enabled'
	$metrics['home_url_is_using_https'] = $home_url_is_using_https; //Should ideally be true
	$metrics['site_url_is_using_https'] = $site_url_is_using_https; //Should ideally be true
	$metrics['child_theme_configurator_active'] = $child_theme_configurator_active; //Should ideally be false
	$metrics['cf7_missing_flamingo'] = $cf7_missing_flamingo; //Should ideally be false
	$metrics['cf7_missing_honeypot'] = $cf7_missing_honeypot; //Should ideally be false
	$metrics['cf7_missing_recaptcha'] = $cf7_missing_recaptcha; //Should ideally be false
	
	$metrics['TEMP_CUC_UP'] = current_user_can('update_plugins'); //This is the problem. The REST API returns false for this, which breaks wp_get_update_data
	$metrics['TEMP_GST_UP'] = get_site_transient('update_plugins');
	$metrics['TEMP_GST_UT'] = get_site_transient('update_themes');
	$metrics['TEMP_GCU'] = get_core_updates(array('dismissed' => false));
	$metrics['TEMP_WPGTU'] = wp_get_translation_updates();
	
	return $metrics;
}


function sbmon_get_site_metrics_rest(WP_REST_Request $request)
{
	$provided_token = $_REQUEST['token'] ?? null;
	$hashed_token = get_option('sbmon_hashed_token');
	
	if (empty($provided_token) || empty($hashed_token) || (wp_check_password($provided_token, $hashed_token) !== true))
	{
		return	[
					'status'		=> 'error',
					'text_message'	=> 'Invalid token.',
					'html_message'	=> '<p class="error-message">Invalid token.</p>',
					'metrics'		=> null,
				];
	}
	
	$metrics = sbmon_get_site_metrics();
	
	return	[
				'status'		=> 'success',
				'text_message'	=> 'Successfully retrieved the site\'s metrics.',
				'html_message'	=> '<p class="success-message">Successfully retrieved the site\'s metrics.</p>',
				'metrics'		=> $metrics,
			];
}

//Endpoint example: http://example.com/wp-json/sitebolts-monitor/v1/sbmon_get_site_metrics_rest
add_action('rest_api_init', function()
{
	register_rest_route('sitebolts-monitor/v1', '/sbmon_get_site_metrics_rest',
	[
		'methods' 				=> 'GET, POST, PUT, PATCH, DELETE',
		'callback' 				=> 'sbmon_get_site_metrics_rest',
		'permission_callback'	=> '__return_true',
	]);
});

function sbmon_localize_script($script_handle)
{
	wp_localize_script($script_handle, 'sbmon_globals',	[
															'plugin_version'	=> sbmon_get_plugin_version(),
															'ajax_url' 			=> admin_url('admin-ajax.php'),
														]);
}

function sbmon_enqueue_scripts()
{
	$plugin_version = sbmon_get_plugin_version();
	
	wp_enqueue_script('sitebolts-monitor-script', plugins_url('script.js', __FILE__ ), [], $plugin_version, false);
	sbmon_localize_script('sitebolts-monitor-script');
}

add_action('admin_enqueue_scripts', 'sbmon_enqueue_scripts');


function sbmon_save_new_token_ajax()
{
	if (current_user_can('administrator') !== true)
	{
		sbmon_echo_json_and_die([
									'status'		=> 'error',
									'text_message'	=> 'You must be logged in as an administrator to perform this action.',
									'html_message'	=> '<p class="error-message">You must be logged in as an administrator to perform this action.</p>',
								], 200);
	}
	
	$token = $_POST['token'] ?? null;
	
	if (empty($token) === true)
	{
		sbmon_echo_json_and_die([
									'status'		=> 'error',
									'text_message'	=> 'The provided token was empty.',
									'html_message'	=> '<p class="error-message">The provided token was empty.</p>',
								], 200);
	}
	
	$hashed_token = wp_hash_password($token);
	update_option('sbmon_hashed_token', $hashed_token);
	
	sbmon_echo_json_and_die([
									'status'		=> 'success',
									'text_message'	=> 'The new token was successfully saved and the previous token has been revoked.',
									'html_message'	=> '<p class="success-message">The new token was successfully saved and the previous token has been revoked.</p>',
								], 200);
}

add_action('wp_ajax_sbmon_save_new_token_ajax', 'sbmon_save_new_token_ajax');
add_action('wp_ajax_nopriv_sbmon_save_new_token_ajax', 'sbmon_save_new_token_ajax');