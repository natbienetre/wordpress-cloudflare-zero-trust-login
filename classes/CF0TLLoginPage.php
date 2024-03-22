<?php
class CF0TLLoginPage {
    public static function register_hooks() {
        $instance = new self();

        add_action( 'login_enqueue_scripts', array( $instance, 'login_enqueue_scripts' ) );
        add_action( 'login_init', array( $instance, 'login_init' ) );
    }

    function login_init() {
        add_filter( 'login_form_defaults', array( $this, 'login_form_defaults' ) );
    }

    function login_form_defaults( array $defaults ) {
        if ( CF0TLOptions::load()->require_login_input ) {
            return $defaults;
        }

        try {
            $email = CF0TLAuthentication::get_email_from_jwt();
        } catch ( Exception $e ) {
            return $defaults;
        }

        return array_merge( $defaults, array(
            'value_username' => $email,
        ) );
    }

    function login_enqueue_scripts() {
        wp_register_style( 'cf0tl-only-cloudflare-login', plugin_dir_url( CF0TL_PLUGIN_FILE ) . 'styles/only-cloudflare-login.css', array(), '0.0.1');
        wp_register_style( 'cf0tl-login-no-password', plugin_dir_url( CF0TL_PLUGIN_FILE ) . 'styles/login-no-password.css', array(), '0.0.1');

        $opts = CF0TLOptions::load();
        if ( $opts->disable_wp_auth ) {
            wp_enqueue_style( 'cf0tl-only-cloudflare-login' );
        } else if ( $opts->require_login_input ) {
            wp_enqueue_style( 'cf0tl-login-no-password' );
        }
    }
}
