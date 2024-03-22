<?php

class CFCertsKey {
    public string $kid;
    public string $kty;
    public string $alg;
    public string $use;
    public string $e;
    public string $n;

    public function __construct( array $options ) {
        $this->kid = $options['kid'];
        $this->kty = $options['kty'];
        $this->alg = $options['alg'];
        $this->use = $options['use'];
        $this->e   = $options['e'];
        $this->n   = $options['n'];
    }
}
