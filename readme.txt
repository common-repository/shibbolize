=== Shibbolize ===
Contributors: cjmaio
Tags: shibboleth, single sign on, saml, login, authentication
Requires at least: 3.3
Tested up to: 3.5.1
Stable Tag: 1.1.0

Allows WordPress to pass user authentication, account creation, and permissions
to a Shibboleth Service Provider / Shibboleth Identity Provider.

== Description ==

This plugin is designed to support integrating Wordpress sites into your existing
identity management infrastructure using a [Shibboleth] Service Provider / Identity 
Provider.

This plugin was initially based off of the [Shibboleth Plugin] by Will Norris, but
updated to support network-based installations of WordPress, as well as the ability
to update permissions on a per-site basis.

WordPress can be configured so that all standard login requests will be sent to
your configured Identity Provider. Once authenticated, a WordPress account will be
created if one does not exist. User attributes can be synchronized with your enterprise's
system.

In addition, the user's role within each WordPress site, as well as the role of Super Admin,
 can be set and updated based on any attribute Shibboleth provides. 
 
[Shibboleth]: http://shibboleth.internet2.edu/
[Shibboleth Plugin]: http://wordpress.org/plugins/shibboleth/

== Installation ==

= Pre-Requisites =

This plugin assumes that you already have your Shibboleth Service Provider properly 
installed and working using "lazy sessions"

The plugin will attempt to set the .htaccess directives automatically.  If it is 
unable to, add this manually either to your .htaccess file, or Apache configuration:
	`AuthType Shibboleth
	Require Shibboleth
	ShibRequireSession Off`

= Uploading the Plugin =

Upload the `shibbolize` folder to your WordPress plugins folder. By default, this is:
`/wp-content/plugins` Afterwards, activate it through the WordPress admin panel. Configure it
through the `Shibbolize` settings page.

== Changelog ==

= version 1.1.0 (2013-07-05) =
 - fixed issue with jquery
 - added in username overrides for @ _ - and .
 
= version 1.0.6 (2013-06-10) =
 - replaced deprecated function used (oops!)

= version 1.0.5 (2013-06-04) =
 - tested for compatibility with WordPress 3.5.1
 - initial public release of modified 'Shibboleth' plugin changed to work properly with WordPress network installations.