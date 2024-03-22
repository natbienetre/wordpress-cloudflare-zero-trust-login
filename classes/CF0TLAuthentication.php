<?php
use Firebase\JWT\JWT;

class CF0TLAuthentication {
    // See https://developers.cloudflare.com/cloudflare-one/identity/authorization-cookie/validating-json/#verify-the-jwt-manually
    const CLOUDFLARE_AUTHORIZATION_HEADER = 'Cf-Access-Jwt-Assertion';
    const CLOUDFLARE_AUTHORIZATION_COOKIE = 'CF_Authorization';

    public static function register_hooks() {
        $instance = new self();

        add_action( 'login_init', array( $instance, 'login_init' ) );
        add_action( 'login_form_login', array( $instance, 'login_form_login' ) );
        add_filter( 'determine_current_user', array( $instance, 'determine_current_user' ) );
    }

    function login_init() {
        add_filter( 'authenticate', array( $this, 'authenticate' ), 30, 2 );
        add_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ) );
    }

    function login_form_login() {
        $opts = CF0TLOptions::load();

        if ( $opts->disable_wp_auth ) {
            remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
            remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
        }
    }

    function determine_current_user( int|false $user_id ): int|false {
        if ( $user_id ) {
            return $user_id;
        }

        $user = $this->_login();

        if ( is_wp_error( $user ) ) {
            return false;
        }

        return (int)$user->ID;
    }

    function wp_authenticate_user( WP_User | WP_Error $user ): WP_User | WP_Error {
        if ( is_wp_error( $user ) || CF0TLOptions::load()->require_zero_trust_auth ) {
            return $this->_login();
        }

        return $user;
    }

    function authenticate( WP_User | WP_Error | null $user, string $username ): WP_User | WP_Error | null {
        if ( is_null( $user ) || is_wp_error( $user ) ) {
            $opts = CF0TLOptions::load();

/*
            if ( $opts->disable_wp_auth ) {
                return $this->add_error( $user, 'cloudflare-authentication-failed', __( 'WordPress authentication required', 'cf0tl' ) );
            }
*/
            $cf_user = $this->_login();
            if ( is_wp_error( $cf_user ) ) {
                if ( is_wp_error( $user ) ) {
                    $cf_user->merge_from( $user );
                }

                return $cf_user;
            }

            if ( $opts->require_login_input && $cf_user->user_login !== $username ) {
                return new WP_Error( 'cloudflare-authentication-failed', __( 'WordPress user does not match cloudflare user', 'cf0tl' ) );
            }

            return $cf_user;
        }

        return $user;
    }

    private function _login(): WP_User | WP_Error {
        try {
            $email = $this->get_email_from_jwt();
        } catch ( Exception $e ) {
            return $this->add_error( null, 'cloudflare-authentication-failed', $e->getMessage() );
        }

        $cf_user = get_user_by( 'email', $email );
        if ( false === $cf_user ) {
            return $this->add_error( null, 'cloudflare-authentication-failed', __( 'User not found in WordPress', 'cf0tl' ) );
        }

        return $cf_user;
    }

    private function add_error( $error, int|string $code, string $message ) : WP_Error {
        if ( ! is_wp_error( $error ) ) {
            return new WP_Error( $code, $message );
        }

        $error->add( $code, $message );

        return $error;
    }

    private static function get_jwt_string(): string | null {
        $jwt = null;

        if ( ! empty( $_SERVER[ self::CLOUDFLARE_AUTHORIZATION_HEADER ] ) ) {
            $jwt = $_SERVER[ self::CLOUDFLARE_AUTHORIZATION_HEADER ];
        } elseif ( ! empty( $_COOKIE[ self::CLOUDFLARE_AUTHORIZATION_COOKIE ] ) ) {
            $jwt = $_COOKIE[ self::CLOUDFLARE_AUTHORIZATION_COOKIE ];
        }

        /**
         * Filter the JWT string.
         *
         * @param string|null $jwt The JWT string.
         */
        return apply_filters( 'cf0tl-jwt-string', $jwt );
    }

    public static function get_email_from_jwt(): String {
        $opts = CF0TLOptions::load();

        $jwt = self::get_jwt_string();

        if ( is_null( $jwt ) ) {
            throw new Exception( __( 'No Cloudflare JWT found', 'cf0tl' ) );
        }

        $keys = $opts->certs->get_keys( $opts->algo );

        $decoded = JWT::decode( $jwt, $keys );

        foreach ( $decoded->aud as $aud ) {
            if ( $aud === $opts->uad ) {
                return $decoded->email;
            }
        }

        throw new Exception( __( 'Invalid JWT, unexpected Application Audience (AUD) Tag', 'cf0tl' ) );
    }
}
