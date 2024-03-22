<?php

class CF0TLOptions {
    const OPTION_NAME = 'cf0tl_options';

    const ALGO_RS256 = 'RS256';

    /**
     * @var bool Enforce zero trust authentication
     */
    public bool $require_zero_trust_auth;

    /**
     * @var bool Require login input from the wordpress login form
     */
    public bool $require_login_input;

    /**
     * @var bool Require login authentication AND zero trust authentication
     */
    public bool $disable_wp_auth;

    /**
     * @var bool Disable logout
     */
    public bool $disable_logout;

    public string $algo;
    public string $team;
    public string $uad;

    public CFCerts $certs;

    public function __construct( array $options = array() ) {
        $options = wp_parse_args( $options, self::defaults() );

        $this->require_zero_trust_auth = $options['require_zero_trust_auth'];
        $this->require_login_input     = $options['require_login_input'];
        $this->disable_wp_auth         = $options['disable_wp_auth'];
        $this->disable_logout          = $options['disable_logout'];
        $this->algo                    = $options['algo'];
        $this->team                    = $options['team'];
        $this->uad                     = $options['uad'];

        $this->set_certs( $options['certs'] );
    }

    public static function defaults(): array {
        return array(
            'require_zero_trust_auth' => false,
            'require_login_input'     => false,
            'disable_wp_auth'         => false,
            'disable_logout'          => false,
            'algo'                    => self::ALGO_RS256,
            'team'                    => '',
            'uad'                     => '',
            'certs'                   => array(
                'keys'         => array(),
                'public_cert'  => array(),
                'public_certs' => array(),
            ),
        );
    }

    public static function load(): CF0TLOptions {
        return new self( (array) get_option( self::OPTION_NAME, self::defaults() ) );
    }

    public function save(): bool {
        return update_option( self::OPTION_NAME, array(
            'require_zero_trust_auth' => $this->require_zero_trust_auth,
            'require_login_input'     => $this->require_login_input,
            'disable_wp_auth'         => $this->disable_wp_auth,
            'disable_logout'          => $this->disable_logout,
            'algo'                    => $this->algo,
            'team'                    => $this->team,
            'uad'                     => $this->uad,
            'certs'                   => array(
                'keys'        => $this->certs->keys,
                'public_cert' => $this->certs->public_cert,
                'public_certs'=> $this->certs->public_certs,
            ),
        ) );
    }

    public function add_options() {
        add_option( self::OPTION_NAME, self::defaults() );
    }

    public static function register_hooks() {
        $instance = new self();

        register_activation_hook( CF0TL_PLUGIN_FILE, array( $instance, 'add_options' ) );
    }

    public function set_certs( array $certs ) {
        $this->certs = new CFCerts( $certs );
    }

    public function jwks_uri(): string {
        return apply_filters( 'cf0tl-jwks-uri', "https://$this->team.cloudflareaccess.com/cdn-cgi/access/certs" );
    }

    public static function load_certificates( string $team ): array | WP_Error {
        if ( empty( $team ) ) return throw new InvalidArgumentException('team is required');

        $resp = wp_remote_get( "https://$team.cloudflareaccess.com/cdn-cgi/access/certs" );
        if ( is_wp_error( $resp ) ) {
            return $resp;
        }

        if ( $resp['http_response']->get_status() != 200 ) {
            return new WP_Error( 'invalid_response', 'Invalid response', $resp );
        }

        $certs = json_decode( $resp['body'], true );

        return $certs;
    }
}
