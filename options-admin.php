<?php

add_action('admin_menu', 'shibboleth_admin_panels');
add_action('network_admin_menu', 'shibboleth_admin_panels');

/**
 * Setup admin menus for Shibboleth options.
 **/
function shibboleth_admin_panels() 
{
	if (function_exists('is_site_admin')) $hookname = add_submenu_page('settings.php', __('Shibbolize Options', 'shibboleth'),__('Shibbolize', 'shibboleth'), 8, 'shibboleth-options', 'shibboleth_options_page' );
	else $hookname = add_options_page(__('Shibbolize Options', 'shibboleth'), __('Shibbolize', 'shibboleth'), 8, 'shibboleth-options', 'shibboleth_options_page' );
}

/**
 * WordPress options page to configure the Shibboleth plugin.
 */
function shibboleth_options_page() 
{
	global $wp_roles;

	if (isset($_POST['submit'])) 
	{
		check_admin_referer('shibboleth_update_options');

		$shib_headers = (array) shibboleth_get_option('shibboleth_headers');
		$shib_headers = array_merge($shib_headers, $_POST['headers']);
		$shib_headers = apply_filters( 'shibboleth_form_submit_headers', $shib_headers );
		shibboleth_update_option('shibboleth_headers', $shib_headers);

		$sites = shibboleth_get_sites();
		foreach ($sites as $site)
		{
			$shib_roles = shibboleth_get_roles($site->id);
			$shib_roles = array_merge($shib_roles, $_POST['shibboleth_roles_' . $site->id]);
			$shib_roles = apply_filters('shibboleth_form_submit_roles', $shib_roles);
			shibboleth_update_roles($site->id, $shib_roles);
		}
		
		shibboleth_update_option('shibboleth_convert_wp_users', $_POST['shibboleth_convert_wp_users']);
		shibboleth_update_option('shibboleth_superadmin', $_POST['shibboleth_superadmin']);
		shibboleth_update_option('shibboleth_service_name', $_POST['service_name']);
		shibboleth_update_option('shibboleth_login_url', $_POST['login_url']);
		shibboleth_update_option('shibboleth_logout_url', $_POST['logout_url']);
		shibboleth_update_option('shibboleth_password_change_url', $_POST['password_change_url']);
		shibboleth_update_option('shibboleth_password_reset_url', $_POST['password_reset_url']);
		shibboleth_update_option('shibboleth_default_login', (boolean) $_POST['default_login']);
		shibboleth_update_option('shibboleth_update_users', (boolean) $_POST['update_users']);
		shibboleth_update_option('shibboleth_update_roles', (boolean) $_POST['update_roles']);
		shibboleth_update_option('shibboleth_allow_sitewide_redirects', (boolean) $_POST['allow_sitewide_redirects']);
		shibboleth_update_option('shibboleth_common_email_domain', $_POST['common_email_domain']);

		do_action( 'shibboleth_form_submit' );
	}

	$shib_headers = shibboleth_get_option('shibboleth_headers');
	$shibboleth_plugin_path = apply_filters('shibboleth_plugin_path', plugins_url('shibbolize'));

	screen_icon('shibbolize');
	?>
	
	<style type="text/css">
		#icon-shibbolize 
		{ 
			background: url("<?php echo $shibboleth_plugin_path . '/assets/shibboleth.jpg' ?>") no-repeat; 
			height: 61px; 
			width: 50px; 
		}
	</style>

	<div class="wrap">
		<form method="post">
			<h2><?php _e('Shibbolize Options', 'shibboleth') ?></h2>
			<br /><br />
			<h3><?php _e('IDP Configuration', 'shibboleth') ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="service_name"><?php _e('Shibboleth Service Name', 'shibboleth') ?></label></th>
					<td>
						<input type="text" id="service_name" name="service_name" value="<?php echo shibboleth_get_option('shibboleth_service_name') ?>" size="50" /><br />
						<?php _e('The name that will be displayed when you login at the WordPress login page.', 'shibboleth'); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="login_url"><?php _e('Session Initiator URL', 'shibboleth') ?></label></th>
					<td>
						<input type="text" id="login_url" name="login_url" value="<?php echo shibboleth_get_option('shibboleth_login_url') ?>" size="50" /><br />
						<?php _e('This URL is constructed from values found in your main Shibboleth SP configuration file: your site hostname, the Sessions handlerURL, and the SessionInitiator Location.', 'shibboleth'); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="logout_url"><?php _e('Logout URL', 'shibboleth') ?></label></th>
					<td>
						<input type="text" id="logout_url" name="logout_url" value="<?php echo shibboleth_get_option('shibboleth_logout_url') ?>" size="50" /><br />
						<?php _e('This URL is constructed from values found in your main Shibboleth SP configuration file: your site hostname, the Sessions handlerURL, and the LogoutInitiator Location.', 'shibboleth'); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="password_change_url"><?php _e('Password Change URL', 'shibboleth') ?></label></th>
					<td>
						<input type="text" id="password_change_url" name="password_change_url" value="<?php echo shibboleth_get_option('shibboleth_password_change_url') ?>" size="50" /><br />
						<?php _e('If this option is set, Shibboleth users will see a "change password" link on their profile page directing them to this URL.', 'shibboleth') ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="password_reset_url"><?php _e('Password Reset URL', 'shibboleth') ?></label></th>
					<td>
						<input type="text" id="password_reset_url" name="password_reset_url" value="<?php echo shibboleth_get_option('shibboleth_password_reset_url') ?>" size="50" /><br />
						<?php _e('If this option is set, Shibboleth users who try to reset their forgotten password using WordPress will be redirected to this URL.', 'shibboleth') ?>
					</td>
				</tr>
				<tr>
				<th scope="row"><label for="default_login"><?php _e('Shibboleth is default login', 'shibboleth') ?></label></th>
					<td>
						<input type="checkbox" id="default_login" name="default_login" <?php echo shibboleth_get_option('shibboleth_default_login') ? ' checked="checked"' : '' ?> />
						<label for="default_login"><?php _e('Use Shibboleth as the default login method for users.', 'shibboleth'); ?></label>

						<p><?php _e('If set, this will cause all standard WordPress login links to initiate Shibboleth login instead of local WordPress authentication. Append <code>?no_shib</code> to your URL to bypass Shibboleth.', 'shibboleth'); ?></p>
					</td>
				</tr>
				<tr>
				<th scope="row"><label for="allow_sitewide_redirects"><?php _e('Allow sitewide redirects', 'shibboleth') ?></label></th>
					<td>
						<input type="checkbox" id="allow_sitewide_redirects" name="allow_sitewide_redirects" <?php echo shibboleth_get_option('shibboleth_allow_sitewide_redirects') ? ' checked="checked"' : '' ?> />
						<label for="allow_sitewide_redirects"><?php _e('Allow redirects to any subdomain on this Wordpress site.', 'shibboleth'); ?></label>

						<p><?php _e('This is required for login redirects to work in a Network installation.', 'shibboleth'); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="common_email_domain"><?php _e('Common email domain', 'shibboleth') ?></label></th>
					<td>
						<input type="text" id="common_email_domain" name="common_email_domain" value="<?php echo shibboleth_get_option('shibboleth_common_email_domain') ?>" size="50" /><br />
						<?php _e('Automatically set user email address for new users by appending this domain to the login name.', 'shibboleth') ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="shibboleth_convert_wp_users"><?php _e('Convert existing Wordpress users', 'shibboleth') ?></label></th>
					<td>
						<input type="text" id="shibboleth_convert_wp_users" name="shibboleth_convert_wp_users" value="<?php echo shibboleth_get_option('shibboleth_convert_wp_users') ?>" size="50" /><br />
						<?php _e('If a WordPress user exists with the same username and email as one authenticating with Shibboleth, merge the two users together if checked.', 'shibboleth') ?>
					</td>
				</tr>
			</table>
			
			<p class="submit"><input type="submit" name="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
			<hr />
			
			<?php do_action('shibboleth_options_table', $shib_headers, $shib_roles); ?>

			<h3><?php _e('User Profile Data', 'shibboleth') ?></h3>
			<p><?php _e('Define the Shibboleth headers which should be mapped to each user profile attribute.  These header names are configured in <code>attribute-map.xml</code>.', 'shibboleth') ?></p>
			<p><?php _e('<em>Managed</em> profile fields are updated each time the user logs in using the current data provided by Shibboleth.  Additionally, users will be prevented from manually updating these fields from within WordPress.  Note that Shibboleth data is always used to populate the user profile during initial account creation.', 'shibboleth'); ?></p>
			<table class="form-table optiontable editform" cellspacing="2" cellpadding="5">
				<tr valign="top">
					<th scope="row"><label for="username"><?php _e('Username') ?></label></th>
					<td><input type="text" id="username" name="headers[username][name]" value="<?php echo 
						$shib_headers['username']['name'] ?>" /></td>
					<td width="60%"><input type="checkbox" id="username_managed" name="headers[username][managed]" checked disabled /> <?php _e('Managed', 'shibboleth') ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="first_name"><?php _e('First name') ?></label></th>
					<td><input type="text" id="first_name" name="headers[first_name][name]" value="<?php echo 
						$shib_headers['first_name']['name'] ?>" /></td>
					<td><input type="checkbox" id="first_name_managed" name="headers[first_name][managed]" <?php 
						checked($shib_headers['first_name']['managed'], 'on') ?> /> <?php _e('Managed', 'shibboleth') ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="last_name"><?php _e('Last name') ?></label></th>
					<td><input type="text" id="last_name" name="headers[last_name][name]" value="<?php echo 
						$shib_headers['last_name']['name'] ?>" /></td>
					<td><input type="checkbox" id="last_name_managed" name="headers[last_name][managed]" <?php 
						checked($shib_headers['last_name']['managed'], 'on') ?> /> <?php _e('Managed', 'shibboleth') ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="nickname"><?php _e('Nickname') ?></label></th>
					<td><input type="text" id="nickname" name="headers[nickname][name]" value="<?php echo 
						$shib_headers['nickname']['name'] ?>" /></td>
					<td><input type="checkbox" id="nickname_managed" name="headers[nickname][managed]" <?php 
						checked($shib_headers['nickname']['managed'], 'on') ?> /> <?php _e('Managed', 'shibboleth') ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="_display_name"><?php _e('Display name', 'shibboleth') ?></label></th>
					<td><input type="text" id="_display_name" name="headers[display_name][name]" value="<?php echo 
						$shib_headers['display_name']['name'] ?>" /></td>
					<td><input type="checkbox" id="display_name_managed" name="headers[display_name][managed]" <?php 
						checked($shib_headers['display_name']['managed'], 'on') ?> /> <?php _e('Managed', 'shibboleth') ?></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="email"><?php _e('Email Address', 'shibboleth') ?></label></th>
					<td><input type="text" id="email" name="headers[email][name]" value="<?php echo 
						$shib_headers['email']['name'] ?>" /></td>
					<td><input type="checkbox" id="email_managed" name="headers[email][managed]" <?php 
						checked($shib_headers['email']['managed'], 'on') ?> /> <?php _e('Managed', 'shibboleth') ?></td>
				</tr>
			</table>
			
			<p class="submit"><input type="submit" name="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
			<hr />

			<h3><?php _e('User Role Mappings', 'shibboleth') ?></h3>

			<p><?php _e('Users can be placed into internal WordPress roles based on any'
				. ' attribute.  For example, you could define a special eduPersonEntitlement value'
				. ' that designates the user as a WordPress Administrator.  Or you could automatically'
				. ' place all users with an eduPersonAffiliation of "faculty" in the Author role.', 'shibboleth'); ?></p>

			<p><?php _e('<strong>Roles are mapped</strong> on a per-site basis, in order to support'
				. ' WordPress Network installs. Additionally, Super Admins can be mapped to a specific'
				. ' value, though it is discouraged to do so.', 'shibboleth'); ?></p>

			<style type="text/css">
				#role_mappings 
				{ 
					padding: 0; 
				}
				#role_mappings thead th 
				{ 
					padding: 5px 10px; 
				}
				#role_mappings td, #role_mappings th 
				{ 
					border-bottom: 0px; 
				}
				.container
				{
  					width: 100%;
  					margin:10px;
  					background: #FFFFFF;
				}
				.header
				{
				  	background: url('<?php echo $shibboleth_plugin_path . '/assets/arrow_up_alt1-20.png' ?>') no-repeat;
				  	background-position: left 0px;
				  	padding-left: 25px;
				  	cursor: pointer;
				}
				.collapsed .header
				{
  					background-image:url('<?php echo $shibboleth_plugin_path . '/assets/arrow_right_alt1-20.png' ?>');
				}
				.content 
				{
  					height:260px;
  					overflow:hidden;
  					transition: height .5s .2s ease-in-out;
  					-o-transition: height .5s .2s ease-in-out;
  					-webkit-transition: height .5s .2s ease-in-out;
  					-moz-transition: height .5s .2s ease-in-out;
				}
				.content2
				{
  					height:100px;
  					overflow:hidden;
  					transition: height .5s .2s ease-in-out;
  					-o-transition: height .5s .2s ease-in-out;
  					-webkit-transition: height .5s .2s ease-in-out;
  					-moz-transition: height .5s .2s ease-in-out;
				}
				.collapsed .content2
				{
  					height:0px;
				}
				.collapsed .content
				{
  					height:0px;
				}
			</style>

			<script src="<?php echo $shibboleth_plugin_path . '/assets/jquery1-10-0.js'?>"></script>
			<script>
				$(function() {
  					$('.header').click(function() {
    					$(this).closest('.container').toggleClass('collapsed');
  					});
				});
			</script>

			<table class="form-table optiontable editform" cellspacing="2" cellpadding="5" width="100%">
				<tr>
					<th scope="row"><label for="update_roles"><?php _e('Update User Roles', 'shibboleth') ?></label></th>
					<td>
						<input type="checkbox" id="update_roles" name="update_roles" <?php echo shibboleth_get_option('shibboleth_update_roles') ? ' checked="checked"' : '' ?> />
						<label for="update_roles"><?php _e('Use Shibboleth data to update user role mappings each time the user logs in.', 'shibboleth') ?></label>
						<p><?php _e('Be aware that this is a <strong>one-way</strong> transfer of information currently, and will only populate roles, not remove.', 'shibboleth') ?></p>

					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Role Mappings', 'shibboleth') ?></th>
					<td id="role_mappings">
						<div class="container collapsed">
							<div class="header"><h3>General</h3></div>
							<div class="content2">
								<table id="">
									<col width="10%"></col>
									<col></col>
									<col></col>
									<thead>
										<tr>
											<th></th>
											<th scope="column"><?php _e('Header Name', 'shibboleth') ?></th>
											<th scope="column"><?php _e('Header Value', 'shibboleth') ?></th>
										</tr>
									</thead>
									<tbody>
										<tr valign="top">
											<th scope="row">Super Admin</th>
											<? $shib_super_admin = shibboleth_get_option('shibboleth_superadmin'); ?>
											<td><input type="text" id="shibboleth_superadmin_header" name="shibboleth_superadmin[header]" value="<?= $shib_super_admin['header'] ?>" style="width: 100%" /></td>
											<td><input type="text" id="shibboleth_superadmin_value" name="shibboleth_superadmin[value]" value="<?= $shib_super_admin['value'] ?>" style="width: 100%" /></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						<?php
						$sites = shibboleth_get_sites();
						foreach ($sites as $site)
						{
							?>
							<div class="container collapsed">
								<div class="header"><h3>Site: <?=$site->site?></h3></div>
								<div class="content">
									<table id="">
										<col width="10%"></col>
										<col></col>
										<col></col>
										<thead>
											<tr>
												<th></th>
												<th scope="column"><?php _e('Header Name', 'shibboleth') ?></th>
												<th scope="column"><?php _e('Header Value', 'shibboleth') ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											$shib_roles = shibboleth_get_roles($site->id);
											foreach ($wp_roles->role_names as $key => $name) 
											{
												echo'
												<tr valign="top">
													<th scope="row">' . _c($name) . '</th>
													<td><input type="text" id="role_'.$key.'_header" name="shibboleth_roles_' . $site->id . '['.$key.'][header]" value="' . @$shib_roles[$key]['header'] . '" style="width: 100%" /></td>
													<td><input type="text" id="role_'.$key.'_value" name="shibboleth_roles_' . $site->id . '['.$key.'][value]" value="' . @$shib_roles[$key]['value'] . '" style="width: 100%" /></td>
												</tr>';
											}
											?>
										</tbody>
									</table>
								</div>
							</div>
						<?php
						}?>
					</td>
				</tr>
			</table>

			<?php wp_nonce_field('shibboleth_update_options') ?>
			<p class="submit"><input type="submit" name="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
		</form>
	</div>

<?php
}
