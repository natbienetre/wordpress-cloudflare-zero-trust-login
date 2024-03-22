<?php

class CF0TLScheduler {
    const HOOK_NAME = 'cft0l_sync_certificates';
    const ACTION_NAME = 'cft0l_sync_certificates';

    public string $recurrence = 'hourly';

    public static function register_hooks() {
        $instance = new self();

        register_activation_hook( CF0TL_PLUGIN_FILE, array( $instance, 'schedule_sync_certificates' ) );
        register_deactivation_hook( CF0TL_PLUGIN_FILE, array( $instance, 'clear_scheduled_hooks' ) );

        add_action( self::ACTION_NAME, array( $instance, 'sync_certificates' ) );
    }

    function schedule_sync_certificates() {
        $args = array();
        if (! wp_next_scheduled ( self::ACTION_NAME, $args )) {
            wp_schedule_event( time(), $this->recurrence, self::ACTION_NAME, $args );
        }
    }

    function clear_scheduled_hooks() {
        wp_clear_scheduled_hook( self::ACTION_NAME );
    }

    function sync_certificates() {
        $options = CF0TLOptions::load();
        $certificates = CF0TLOptions::load_certificates( $options->team );
        if ( is_wp_error( $certificates ) ) {
            throw new Exception( $certificates->get_error_message() );
        }

        $options->set_certs( $certificates );
        if ( ! $options->save() ) {
            throw new Exception('Failed to save certificates');
        }
    }
}
