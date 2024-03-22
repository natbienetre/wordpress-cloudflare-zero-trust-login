<?php

class CFCerts {
    public array $keys;

    public array $public_cert;

    public array $public_certs;

    public function __construct( array $options ) {
        $options = wp_parse_args( $options, self::defaults() );

        $this->keys = $options['keys'];
        $this->public_cert  = $options['public_cert'];
        $this->public_certs = $options['public_certs'];
    }

    public static function defaults(): array {
        return array(
            'keys'         => array(),
            'public_cert'  => array(),
            'public_certs' => array(),
        );
    }

    /**
     * @return array<string,Firebase\JWT\Key> | WP_Error
     */
    function get_keys( $algo ): array | WP_Error {
        $keys = array();

        foreach ( $this->keys as $key ) {
            $keys[ $key['kid'] ] = Firebase\JWT\JWK::parseKey( $key, $algo );
        }

        return $keys;
    }
}
