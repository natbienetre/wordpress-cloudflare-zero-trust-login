<?php

class CFCertsCert {
    public string $kid;
    public string $cert;

    public function __construct( array $options ) {
        $this->kid  = $options['kid'];
        $this->cert = $options['cert'];
    }
}
