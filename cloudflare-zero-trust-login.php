<?php
/*
 * Plugin Name: Cloudflare Zero Trust Login
 * Version:     0.0.1
 * Author:      Pierre PÉRONNET <pierre.peronnet@gmail.com>
 * Description: Configure login for Cloudflare Zero Trust users.
 * Funding URI: https://github.com/sponsors/holyhope
 * Text Domain: cf0tl
 */

require 'autoload.php';

define( 'CF0TL_PLUGIN_FILE', __FILE__ );


CF0TLAdminPage::register_hooks();
CF0TLAuthentication::register_hooks();
CF0TLLoginPage::register_hooks();
CF0TLOptions::register_hooks();
CF0TLScheduler::register_hooks();
CF0TLUser::register_hooks();
