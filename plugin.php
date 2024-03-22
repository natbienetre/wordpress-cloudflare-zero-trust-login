<?php
/*
 * Plugin Name: Cloudflare Zero Trust Login
 * Version:     0.0.1
 * Author:      Pierre PÉRONNET <pierre.peronnet@gmail.com>
 * Description: Configure login for zero trust users
 */

require 'autoload.php';

define( 'CF0TL_PLUGIN_FILE', __FILE__ );


CF0TLAdminPage::register_hooks();
CF0TLAuthentication::register_hooks();
CF0TLLoginPage::register_hooks();
CF0TLOptions::register_hooks();
CF0TLScheduler::register_hooks();
CF0TLUserProfilePage::register_hooks();
