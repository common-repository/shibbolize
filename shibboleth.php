<?php
/*
 Plugin Name: Shibbolize
 Plugin URI: http://wordpress.org/plugins/shibbolize/
 Description: Allows WordPress to pass user authentication, account creation, and permissions to a Shibboleth Service Provider / Shibboleth Identity Provider.
 Requires: Wordpress 3.3 or above
 Author: cjmaio
 Version: 1.1.0
 */

define('SHIBBOLETH2_PLUGIN_VERSION', '1.1.0');

// Initialize plugin
shibboleth_initialize_plugin();

// Reactivate plugin on version change
shibboleth_check_version();

// Enable Wordpress Multisite Installation
shibboleth_enable_multisite();






/***********************************
 ***** BEGIN Multisite Roles   *****
 ***********************************/
 
function shibboleth_get_sites()
{
	global $wpdb;
	return $wpdb->get_results("SELECT blog_id AS id, CONCAT(domain, path) AS site FROM wp_blogs");
}

function shibboleth_get_roles($site_id)
{
	return (array) shibboleth_get_option('shibboleth_roles_blog_' . $site_id);
}

function shibboleth_add_roles($site_id, $roles)
{
	shibboleth_add_option('shibboleth_roles_blog_' . $site_id, $roles);
}

function shibboleth_update_roles($site_id, $roles)
{
	shibboleth_update_option('shibboleth_roles_blog_' . $site_id, $roles);
}

/***********************************
 ***** END Multisite Roles     *****
 ***********************************/






/***********************************
 ***** BEGIN Plugin Activation *****
 ***********************************/
 
/**
 * Activate the plugin.  This registers default values for all of the 
 * Shibboleth options and attempts to add the appropriate mod_rewrite rules to 
 * WordPress's .htaccess file.
 */
function shibboleth_activate_plugin() 
{
	if (function_exists('switch_to_blog')) switch_to_blog($GLOBALS['current_site']->blog_id);
	
	shibboleth_add_option('shibboleth_service_name', 'Shibboleth');
	shibboleth_add_option('shibboleth_login_url', get_option('home') . '/Shibboleth.sso/Login');
	shibboleth_add_option('shibboleth_default_login', false);
	shibboleth_add_option('shibboleth_logout_url', get_option('home') . '/Shibboleth.sso/Logout');
	shibboleth_add_option('shibboleth_convert_wp_users', '1');
	shibboleth_add_option('shibboleth_headers', array(
		'username' => array( 'name' => 'eppn', 'managed' => false),
		'first_name' => array( 'name' => 'givenName', 'managed' => true),
		'last_name' => array( 'name' => 'sn', 'managed' => true),
		'nickname' => array( 'name' => 'eppn', 'managed' => true),
		'display_name' => array( 'name' => 'displayName', 'managed' => true),
		'email' => array( 'name' => 'mail', 'managed' => true),
	));

	// Since this supports multi-site, we need to create these roles
	// for each site that is currently installed.
	$blog_list = shibboleth_get_sites();
	foreach ($blog_list AS $blogs)
	{
		shibboleth_add_option('shibboleth_roles_blog_' . $blogs->id, array(
			'administrator' => array(
				'header' => 'entitlement',
				'value' => 'urn:mace:example.edu:entitlement:wordpress:admin',
			),
			'author' => array(
				'header' => 'affiliation',
				'value' => 'faculty',
			),
			'default' => 'subscriber',
		));
	}

	shibboleth_add_option('shibboleth_update_roles', true);
	shibboleth_add_option('shibboleth_allow_sitewide_redirects', false);
	shibboleth_add_option('shibboleth_common_email_domain', '');

	shibboleth_insert_htaccess();

	shibboleth_update_option('shibboleth2_plugin_version', SHIBBOLETH2_PLUGIN_VERSION);

	if (function_exists('restore_current_blog')) restore_current_blog();
}

/**
 * Cleanup certain plugins options on deactivation.
 */
function shibboleth_deactivate_plugin() 
{
	shibboleth_remove_htaccess();
}

/* Localization, I do not like the way this is done from the previous
 * developer, but I haven't gotten to changing it yet.
 * TODO: Implement a more fully implemented localization plugin
 */
function shibboleth_textdomain() 
{
	load_plugin_textdomain('shibboleth', null, 'shibboleth/localization');
}

/**
 * Load Shibboleth admin hooks only on admin page loads. 
 */
function shibboleth_admin_hooks() 
{
	if (defined('WP_ADMIN') && WP_ADMIN === true) 
	{
		require_once dirname(__FILE__) . '/options-admin.php';
		require_once dirname(__FILE__) . '/options-user.php';
	}
}

/* Initializes the plugin by calling all the hooks and such. This is
 * called by this file every time its loaded
 */
function shibboleth_initialize_plugin()
{
	// Register actions and hooks
	register_activation_hook('shibboleth/shibboleth.php', 'shibboleth_activate_plugin');
	register_deactivation_hook('shibboleth/shibboleth.php', 'shibboleth_deactivate_plugin');
	add_action('init', 'shibboleth_admin_hooks');
	add_action('init', 'shibboleth_textdomain'); // localization
	add_action('login_form_shibboleth', 'shibboleth_login_form_shibboleth');
	add_action('retrieve_password', 'shibboleth_retrieve_password');
	add_filter('login_url', 'shibboleth_login_url');
	add_action('wp_logout', 'shibboleth_logout', 20);
	add_action('login_form', 'shibboleth_login_form');
	
	add_filter('wpmu_validate_user_signup', 'shibboleth_validate_username');
	add_filter('sanitize_user', 'shibboleth_sanitize_username', 10, 3);
}

/**
 * Adds in the required code to enable this plugin
 * for multisite installs
 */
function shibboleth_enable_multisite()
{
	$redirects = shibboleth_get_option('shibboleth_allow_sitewide_redirects');
	if ($redirects)
	{
		add_filter('allowed_redirect_hosts', 'shibboleth_allow_redirect_to_subdomain', 10);
		function shibboleth_allow_redirect_to_subdomain($allowed_hosts)
		{
			if (empty($_REQUEST['redirect_to'])) return $allowed_hosts;
		
			$redirect_url = parse_url($_REQUEST['redirect_to']);
			$network_home_url = parse_url(network_home_url());
		
			if ($redirect_url['host'] === $network_home_url['host']) return $allowed_hosts;
		
			$pos = strpos($redirect_url['host'], '.');
			if ($pos !== false && substr($redirect_url['host'], $pos+1) === $network_home_url['host']) $allowed_hosts[] = $redirect_url['host'];
		
			return $allowed_hosts;
		}
	}
}

/** 
 * Check the version of this plugin. If it does not match
 * we want to re-activate the plugin in case of changes
 */
function shibboleth_check_version()
{
	$current_version = shibboleth_get_option('shibboleth2_plugin_version');
	if ($current_version === false || SHIBBOLETH2_PLUGIN_VERSION != $current_version)
	{
		add_action('admin_init', 'shibboleth_activate_plugin');
	}
}
/***********************************
 ***** END Plugin Activation   *****
 ***********************************/






/***********************************
 ***** BEGIN Login and URLs    *****
 ***********************************/

/**
 * Check if Shibboleth session is active from the IDP
 */
function shibboleth_session_active() 
{ 
	return apply_filters('shibboleth_session_active', isset($_SERVER['Shib-Session-ID']));
}

/**
 * Authenticate the user using Shibboleth.  If a Shibboleth session is active, 
 * use the data provided by Shibboleth to log the user in.  If a Shibboleth 
 * session is not active, redirect the user to the Shibboleth Session Initiator 
 * URL to initiate the session.
 */
function shibboleth_authenticate($user, $username, $password) 
{
	if (shibboleth_session_active()) return shibboleth_authenticate_user();
	else wp_redirect(shibboleth_session_initiator_url($_REQUEST['redirect_to']));
}

/**
 * When wp-login.php is loaded with 'action=shibboleth', hook Shibboleth 
 * into the WordPress authentication flow.
 */
function shibboleth_login_form_shibboleth() 
{
	add_filter('authenticate', 'shibboleth_authenticate', 10, 3);
}

/**
 * If a Shibboleth user requests a password reset, and the Shibboleth password 
 * reset URL is set, redirect the user there.
 */
function shibboleth_retrieve_password($user_login) 
{
	$password_reset_url = shibboleth_get_option('shibboleth_password_reset_url');
	$user = get_userdatabylogin($user_login);
	
	if (!empty($password_reset_url) && $user && get_usermeta($user->ID, 'shibboleth_account')) wp_redirect($password_reset_url);
}

/**
 * If Shibboleth is the default login method, add 'action=shibboleth' to the 
 * WordPress login URL.
 */
function shibboleth_login_url($login_url) 
{
	if (shibboleth_get_option('shibboleth_default_login')) return add_query_arg('action', 'shibboleth', remove_query_arg('reauth', $login_url));
	else return $login_url;
}

/**
 * If the Shibboleth logout URL is set and the user has an active Shibboleth 
 * session, log the user out of Shibboleth after logging them out of WordPress.
 */
function shibboleth_logout() 
{
	$logout_url = shibboleth_get_option('shibboleth_logout_url');
	if (!empty($logout_url) && shibboleth_session_active()) wp_redirect($logout_url);
}

/**
 * Add a "Login with [SERVICE]" link to the WordPress login form.  This link 
 * will be wrapped in a <p> with an id value of "shibboleth_login" so that 
 * deployers can style this however they choose.
 *
 * The [SERVICE] variable can be set from the plugin admin panel
 */
function shibboleth_login_form() 
{
	$login_url = add_query_arg('action', 'shibboleth');
	
	$service = shibboleth_get_option('shibboleth_service_name');
	echo '<p id="shibboleth_login"><div style="width: 262px; height: 40px;"><a href="' . $login_url . '"><span class="button button-primary button-large" style="width: 100%; text-align: center;">Sign in with ' . $service . '</span>' . '</a></div></p>';
}

/**
 * Generate the URL to initiate Shibboleth login.
 */
function shibboleth_session_initiator_url($redirect = null) 
{
	$allow_sitewide_redirects = shibboleth_get_option('shibboleth_allow_sitewide_redirects');
	if ($allow_sitewide_redirects) 
	{
		$target = network_home_url('wp-login.php');
		if ($redirect === '/') $redirect = get_home_url(null) . '/';
	} 
	else 
	{
		if (function_exists('switch_to_blog')) switch_to_blog($GLOBALS['current_site']->blog_id);
		$target = site_url('wp-login.php');
		if (function_exists('restore_current_blog')) restore_current_blog();
	}

	$target = add_query_arg('action', 'shibboleth', $target);
	if (!empty($redirect)) $target = add_query_arg('redirect_to', urlencode($redirect), $target);

	$initiator_url = shibboleth_get_option('shibboleth_login_url');
	$initiator_url = add_query_arg('target', urlencode($target), $initiator_url);
	$initiator_url = apply_filters('shibboleth_session_initiator_url', $initiator_url);

	return $initiator_url;
}

/***********************************
 ***** END Login and URLs      *****
 ***********************************/






/***********************************
 ***** BEGIN WP User Management*****
 ***********************************/

/**
 * Authenticate the user based on the current Shibboleth headers.
 *
 * If the data available does not map to a WordPress role (based on the
 * configured role-mapping), the user will not be allowed to login.
 *
 * If this is the first time we've seen this user (based on the username
 * attribute), a new account will be created.
 *
 * Known users will have their profile data updated based on the Shibboleth
 * data present if the plugin is configured to do so.
 */
function shibboleth_authenticate_user() 
{
	$shib_headers = shibboleth_get_option('shibboleth_headers');

	// Get the roles we have to map. 
	$user_roles = shibboleth_get_user_roles();
	
	$username = $_SERVER[$shib_headers['username']['name']];
	$user = new WP_User($username);
	
	// If the user account doesn't exist, just create it.
	if (!$user->ID) $user = shibboleth_create_new_user($username);
	if (!$user->ID) return new WP_Error('missing_data', __('Unable to create account based on data provided.'));

	// If the user already exists, depending on our settings, deny access if its not 
	// a shibboleth user
	if ($user->ID && !get_usermeta($user->ID, 'shibboleth_account') && shibboleth_get_option('shibboleth_convert_wp_users') === false) 
	{
		return new WP_Error('invalid_username', __('Account already exists by this name.'));
	}

	// Update user data with our metadata
	update_usermeta($user->ID, 'shibboleth_account', true);
	shibboleth_update_user_data($user->ID);
	
	// If we are set to update the roles, lets do so. Currently, this only supports
	// adding in new roles. If groups are removed, roles are not removed in Wordpress
	// TODO: This feature may be added in at a later time.
	if (shibboleth_get_option('shibboleth_update_roles')) 
	{
		if (is_array($user_roles))
		{
			foreach ($user_roles as $id => $role)
			{
				if ($id === 'superadmin') // super admin
				{
					if ($role == true) 
					{
						include(ABSPATH . 'wp-admin/includes/ms.php');
						grant_super_admin($user->ID);
					}
				}
				else
				{
					$users_blogs = is_user_member_of_blog($user->ID, $id);
					if ($is_user_of_blog)
					{
						switch_to_blog($id);
						$user->set_role($role);
					}
					else
					{
						add_user_to_blog($id, $user->ID, $role);
					}
				}
			}
			do_action('shibboleth_set_user_roles', $user);
		}
	}

	return $user;
}

/**
 * Create a new WordPress user account, and mark it as a Shibboleth account.
 *
 * @param string $user_login login name for the new user
 * @return object WP_User object for newly created user
 */
function shibboleth_create_new_user($user_login) 
{
	require_once( ABSPATH . WPINC . '/registration.php' );
	if (empty($user_login)) return null;
	
	$user_data = array('user_login' => $user_login, 'nickname' => $user_login);
	
	// Set the email address, based on the common email domain
	$common_email_domain = shibboleth_get_option('shibboleth_common_email_domain');
	if ($common_email_domain) $user_data['user_email'] = $user_login.'@'.$common_email_domain;

	// Add the user to Wordpress
	$user_id = wp_insert_user($user_data);
	$user = new WP_User($user_id);
	
	// Update account and set it as a shibboleth account
	update_usermeta($user->ID, 'shibboleth_account', true);

	// User is created, however, is not assigned to any roles.
	shibboleth_update_user_data($user->ID, true);
	do_action('shibboleth_set_user_roles', $user);

	return $user;
}

/**
 * Get roles that can be made available for this user. This function supports multi-site,
 * and will set permissions (and access to sites) based on what role mapping is 
 * configured for this plugin, and the Shibboleth headers present at the time of login
 */
function shibboleth_get_user_roles() {
	global $wp_roles;
	if (!$wp_roles) $wp_roles = new WP_Roles();

	$user_roles['superadmin'] = false; // default to not a super admin
	$sites = shibboleth_get_sites();
	foreach ($sites as $site)
	{
		$shib_roles = shibboleth_get_option('shibboleth_roles_blog_' . $site->id);

		foreach ($wp_roles->role_names as $key => $name) 
		{
			$role_header = $shib_roles[$key]['header'];
			$role_value = $shib_roles[$key]['value'];

			if (empty($role_header) || empty($role_value)) continue;

			$values = split(';', $_SERVER[$role_header]);
			if (in_array($role_value, $values)) 
			{
				$user_roles[$site->id] = $key;
				break;
			}
		}
	}
	
	// Support for setting super admin status via Shibboleth
	$option = shibboleth_get_option('shibboleth_superadmin');
	$role_value = $option['value'];
	$values = split(';', $_SERVER[$option['header']]);
	if (in_array($role_value, $values)) $user_roles['superadmin'] = true;
	
	return isset($user_roles) ? $user_roles : false;
}

/**
 * Get the user fields that are managed by Shibboleth.
 */
function shibboleth_get_managed_user_fields() 
{
	$headers = shibboleth_get_option('shibboleth_headers');
	$managed = array();

	foreach ($headers as $name => $value) 
	{
		if ($value['managed']) $managed[] = $name;
	}

	return $managed;
}

/**
 * Update the user data for the specified user based on the current Shibboleth headers.  Unless 
 * the 'force_update' parameter is true, only the user fields marked as 'managed' fields will be 
 * updated.
 */
function shibboleth_update_user_data($user_id, $force_update = false)
{
	require_once( ABSPATH . WPINC . '/registration.php' );

	$shib_headers = shibboleth_get_option('shibboleth_headers');

	$user_fields = array(
		'user_login' => 'username',
		'user_nicename' => 'username',
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'nickname' => 'nickname',
		'display_name' => 'display_name',
		'user_email' => 'email'
	);

	$user_data = array('ID' => $user_id);
	
	foreach ($user_fields as $field => $header) 
	{
		if ( $force_update || $shib_headers[$header]['managed'] ) 
		{
			$filter = 'shibboleth_' . ( strpos($field, 'user_') === 0 ? '' : 'user_' ) . $field;
			$user_data[$field] = apply_filters($filter, $_SERVER[$shib_headers[$header]['name']]);
		}
	}

	$common_email_domain = shibboleth_get_option('shibboleth_common_email_domain');
	if ($common_email_domain && empty($user_data['user_email'])) 
	{
		$user_data['user_email'] = $user_login.'@' . $common_email_domain;
	}

	wp_update_user($user_data);
}

/***********************************
 ***** END WP User Management  *****
 ***********************************/






/***********************************
 ***** BEGIN htaccess rules    *****
 ***********************************/

/**
 * Helper function to get the htaccess path
 */
function shibboleth_htaccess_path()
{
	return get_home_path() . '.htaccess';
}

/**
 * Insert directives into .htaccess file to enable Shibboleth Lazy Sessions.
 */
function shibboleth_insert_htaccess() 
{
	if (got_mod_rewrite()) insert_with_markers(shibboleth_htaccess_path(), 'Shibboleth', array('AuthType Shibboleth', 'Require Shibboleth', 'ShibRequireSession Off', 'ShibRequestSetting isPassive Off'));
}

/**
 * Remove directives from .htaccess file to enable Shibboleth Lazy Sessions.
 */
function shibboleth_remove_htaccess() 
{
	if (got_mod_rewrite()) insert_with_markers(shibboleth_htaccess_path(), 'Shibboleth', array());
}

/***********************************
 ***** END htaccess rules    *****
 ***********************************/






/***********************************
 ***** BEGIN Shib site_options *****
 ***********************************/

function shibboleth_get_option($key, $default = false ) 
{
	return function_exists('get_site_option') ? get_site_option($key, $default) : get_option($key, $default);
}

function shibboleth_add_option($key, $value, $autoload = 'yes') 
{
	return function_exists('add_site_option') ? add_site_option($key, $value) : add_option($key, $value, '', $autoload);
}

function shibboleth_update_option($key, $value) 
{
	return function_exists('update_site_option') ? update_site_option($key, $value) : update_option($key, $value);
}

function shibboleth_delete_option($key) 
{
	return function_exists('delete_site_option') ? delete_site_option($key) : delete_option($key);
}

/***********************************
 ***** END Shib site_options   *****
 ***********************************/
 
 
 
 
 
 /***********************************
 ***** BEGIN Username Overrides *****
 ***********************************/
 
 /**
 * Allow email addresses to have characters like '+' which is
 * generally not allowed
 */
function shibboleth_sanitize_username($username, $raw_username, $strict) 
{
	if (is_email($raw_username)) $username = $raw_username;
	return $username;
}

/**
 * Remove a lot of built-in WordPress restrictions on usernames
 */
function shibboleth_validate_username($result) 
{
	// If there are no errors, we need not be involved.
	if (!is_wp_error($result['errors'])) return $result;
	
	// The user we will be working with
	$username = $result['user_name'];
	
	// Compile a list of new errors, after filtering out the ones
	// we don't care about at all
	$new_errors = new WP_Error();

	$errors = $result['errors'];
	$codes = $errors->get_error_codes();
	foreach ($codes as $code) 
	{
		$messages = $errors->get_error_messages($code);

		if ($code == 'user_name') 
		{
			foreach ($messages as $message) 
			{
				if ($message == __('Only lowercase letters (a-z) and numbers are allowed.')) 
				{
					// We allow emails by default, lets also allow some other types of characters that may be found
					// in an Active Directory or Shibboleth username
					if (!is_email($username)) 
					{
						preg_match('/[-_.A-Za-z0-9]+/', $username, $maybe);

						if ($username != $maybe[0]) {
							$new_errors->add($code, $message);
						}
					}
				}
				else if ($message == __('Sorry, usernames may not contain the character &#8220;_&#8221;!')) 
				{
					// Let these pass
				}
				else if ($message == __('Username must be at least 4 characters.')) 
				{
					// Let this pass
				}
				else 
				{
					$new_errors->add($code, $message);
				}
			}
		}
		else 
		{
			foreach ($messages as $message) 
			{
				$new_errors->add($code, $message);
			}
		}
	}
	
	$result['errors'] = $new_errors;
	return $result;
}

/***********************************
 ***** END Username overrides  *****
 ***********************************/