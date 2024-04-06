<?php
class CF0TLAdminPage {
    const OPTIONS_PAGE_ID = 'cf0tl-options';

    const GENERAL_SECTION = 'general';
    const CERTS_SECTION   = 'certs';

    private string $load_hook_suffix;

    public static function register_hooks() {
        $instance = new self();

        add_action( 'admin_menu', array( $instance, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $instance, 'settings_init' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( CF0TL_PLUGIN_FILE ), array( $instance, 'plugin_settings' ), 10, 4 );
    }

    function plugin_settings( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( $this->get_url() ) . '&amp;sub=options">' . __( 'Settings', 'cf0tl' ) . '</a>' );
		return $links;
	}

    public function get_url( array $params = array() ): string {
        $params['page'] = 'password-sync-to-cloudflare';

        return admin_url( 'options-general.php?' . http_build_query( $params ) );
    }

    function add_admin_menu() {
        $this->load_hook_suffix = add_options_page(
            __( 'Cloudflare Zero Trust Login', 'cf0tl' ),
            __( 'Cloudflare Zero Trust Login', 'cf0tl' ),
            'manage_options',
            'cloudflare-zero-trust-login',
            array( $this, 'options_page' ),
        );
    }

    function options_page() {
        ?>
            <h1><? esc_html_e( 'Cloudflare Zero Trust Login', 'cf0tl' ); ?></h1>

            <div class="wrap" id="cf0tl-content">
                <form action="options.php" method="post" data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
                    <?php
                        settings_fields( CF0TLOptions::OPTION_NAME );
                        do_settings_sections( self::OPTIONS_PAGE_ID );
                        submit_button();
                    ?>
                </form>
            </div>
        <?php
    }

    public function sanitize_setting( array $value ): array {
        $sanitized = (array) CF0TLOptions::load();

        if ( empty( $value['team'] ) ) {
            add_settings_error( CF0TLOptions::OPTION_NAME, 'empty_team', __( 'An empty team is not allowed', 'cf0tl' ) );
            $sanitized['certs'] = array();
        } else {
            $certs = CF0TLOptions::load_certificates( $value['team'] );
            if ( is_wp_error( $certs ) ) {
                add_settings_error( CF0TLOptions::OPTION_NAME, 'invalid_team', __( 'The team is invalid', 'cf0tl' ) );
                $sanitized['certs'] = array();
            } else {
                $sanitized['certs'] = $certs;
            }
        }

        $sanitized['algo'] = isset( $value['algo_auto'] ) && $value['algo_auto'] == 'on' ? false : $value['algo'];
        $sanitized['uad'] = trim( $value['uad'] );
        $sanitized['team'] = $value['team'];
        $sanitized['require_zero_trust_auth'] = isset( $value['require_zero_trust_auth'] ) && $value['require_zero_trust_auth'] == 'on';
        $sanitized['require_login_input'] = isset( $value['require_login_input'] ) && $value['require_login_input'] == 'on';
        $sanitized['disable_wp_auth'] = isset( $value['disable_wp_auth'] ) && $value['disable_wp_auth'] == 'on';
        $sanitized['disable_logout'] = isset( $value['disable_logout'] ) && $value['disable_logout'] == 'on';
        $sanitized['disable_self_email_edit'] = isset( $value['disable_self_email_edit'] ) && $value['disable_self_email_edit'] == 'on';
        $sanitized['use_custom_email_field'] = isset( $value['use_custom_email_field'] ) && $value['use_custom_email_field'] == 'on';
        $sanitized['auto_user_creation'] = isset( $value['auto_user_creation'] ) && $value['auto_user_creation'] == 'on';

        return $sanitized;
    }

    protected function label_for( string $id, string $label ): string {
        return '<label for="' . esc_attr( $id ) . '">' . $label . '</label>';
    }

    function settings_init() {
        register_setting( CF0TLOptions::OPTION_NAME, CF0TLOptions::OPTION_NAME, array(
            'type'              => 'array',
            'default'           => CF0TLOptions::defaults(),
            'sanitize_callback' => array( $this, 'sanitize_setting' ),
        ) );

        add_settings_section(
            self::GENERAL_SECTION,
            esc_html_x( 'General', 'Header for the setting section', 'cf0tl' ),
            array( $this, 'general_section_callback' ),
            self::OPTIONS_PAGE_ID,
        );

        add_settings_field(
            'require_zero_trust_auth_render',
            $this->label_for( 'cf0tl-require-zero-trust-auth', esc_html_x( 'Check to require zero trust authentication', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'require_zero_trust_auth_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'require_login_input_render',
            $this->label_for( 'cf0tl-require-login-input', esc_html_x( 'Check to require username on login', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'require_login_input_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'disable_wp_auth_render',
            $this->label_for( 'cf0tl-disable-wp-auth', esc_html_x( 'Check to disable WordPress authentication', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'disable_wp_auth_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'disable_logout',
            $this->label_for( 'cf0tl-disable-logout', esc_html_x( 'Check to disable logout', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'disable_logout_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'disable_self_email_edit',
            $this->label_for( 'cf0tl-disable-self-email-edit', esc_html_x( 'Check to disable self email edition', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'disable_self_email_edit_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'use_custom_email_field',
            $this->label_for( 'cf0tl-use-custom-email-field', esc_html_x( 'Check to use custom email field', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'use_custom_email_field_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'auto_user_creation',
            $this->label_for( 'cf0tl-auto-user-creation', esc_html_x( 'Automatically create user if not found', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'auto_user_creation_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'team',
            $this->label_for( 'cf0tl-team', esc_html_x( 'Cloudflare Zero Trust team', 'Label for the setting field', 'cf0tl' ) . wp_required_field_indicator() ),
            array( $this, 'team_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'algo',
            $this->label_for( 'cf0tl-algo', esc_html_x( 'Algorithm', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'algo_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        add_settings_field(
            'uad',
            $this->label_for( 'cf0tl-uad', esc_html_x( 'Application Audience (AUD) Tag', 'Label for the setting field', 'cf0tl' ) ),
            array( $this, 'uad_render' ),
            self::OPTIONS_PAGE_ID,
            self::GENERAL_SECTION,
        );

        if ( ! empty( CF0TLOptions::load()->team ) ) {
            add_settings_section(
                self::CERTS_SECTION,
                esc_html_x( 'Cloudflare', 'Header for the setting section', 'cf0tl' ),
                array( $this, 'certs_section_callback' ),
                self::OPTIONS_PAGE_ID,
            );

            add_settings_field(
                'certs_key',
                $this->label_for( 'cf0tl-certs-key', esc_html_x( 'Certificates key', 'Label for the setting field', 'cf0tl' ) . wp_required_field_indicator() ),
                array( $this, 'certs_key_render' ),
                self::OPTIONS_PAGE_ID,
                self::CERTS_SECTION,
            );
        }
    }

    function general_section_callback() {}

    function require_zero_trust_auth_render() {
        ?>
        <input type="checkbox" id="cf0tl-require-zero-trust-auth" <?php checked( CF0TLOptions::load()->require_zero_trust_auth ); ?> name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[require_zero_trust_auth]' ); ?>" />
        <?php
    }

    function require_login_input_render() {
        ?>
        <input type="checkbox" id="cf0tl-require-login-input" <?php checked( CF0TLOptions::load()->require_login_input ); ?> name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[require_login_input]' ); ?>" />
        <?php
    }

    function disable_wp_auth_render() {
        ?>
        <input type="checkbox" id="cf0tl-disable-wp-auth" <?php checked( CF0TLOptions::load()->disable_wp_auth ); ?> name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[disable_wp_auth]' ); ?>" />
        <?php
    }

    function disable_logout_render() {
        ?>
        <input type="checkbox" id="cf0tl-disable-logout" <?php checked( CF0TLOptions::load()->disable_logout ); ?> name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[disable_logout]' ); ?>" />
        <?php
    }

    function disable_self_email_edit_render() {
        ?>
        <input type="checkbox" id="cf0tl-disable-self-email-edit" <?php checked( CF0TLOptions::load()->disable_self_email_edit ); ?> name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[disable_self_email_edit]' ); ?>" />
        <?php
    }

    function use_custom_email_field_render() {
        ?>
        <input type="checkbox" id="cf0tl-use-custom-email-field" <?php checked( CF0TLOptions::load()->use_custom_email_field ); ?> name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[use_custom_email_field]' ); ?>" />
        <?php
    }

    function auto_user_creation_render() {
        ?>
        <input type="checkbox" id="cf0tl-auto-user-creation" <?php checked( CF0TLOptions::load()->auto_user_creation ); ?> name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[auto_user_creation]' ); ?>" />
        <?php
    }

    function algo_render() {
        $algo = CF0TLOptions::load()->algo;
        ?>
        <input type="text" id="cf0tl-algo" list="algorithms" name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[algo]' ); ?>" value="<?php echo esc_attr( $algo ); ?>" />
        <datalist id="algorithms">
            <option value="<?php echo esc_attr( CF0TLOptions::ALGO_RS256 ); ?>" />
        </datalist>
        <label for="cf0tl-algo-auto">
            <input <?php checked( $algo, false ); ?> type="checkbox" id="cf0tl-algo-auto" name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[algo_auto]' ); ?>" value="auto" />
            <?php esc_html_e( 'Automatically detect algorithm.', 'cf0tl' ); ?>
        </label>
        <?php
    }

    function uad_render() {
        ?>
        <input type="text" id="cf0tl-uad" class="large-text code" name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[uad]' ); ?>" value="<?php echo esc_attr( CF0TLOptions::load()->uad ); ?>" />
        <?php
    }

    function certs_section_callback() {
        ?>
            <p>
                <?php esc_html_e( 'These settings are fetched from your Cloudflare team.', 'cf0tl' ); ?>
                <a href="https://<?php echo esc_attr( CF0TLOptions::load()->team ); ?>.cloudflareaccess.com/cdn-cgi/access/certs" target="_blank"><?php esc_html_e( 'View certificates', 'cf0tl' ); ?></a>
            </p>
        <?php
    }

    function team_render() {
        ?>
        <input required type="text" id="cf0tl-team" class="large-text" name="<?php echo esc_attr( CF0TLOptions::OPTION_NAME . '[team]' ); ?>" value="<?php echo esc_attr( CF0TLOptions::load()->team ); ?>" minlength="1">
        <?php
    }

    function certs_key_render() {
        ?>
        <textarea readonly class="large-text code" class="cf0tl-certs-key"><?php echo esc_html( json_encode( CF0TLOptions::load()->certs ) ); ?></textarea>
        <?php
    }
}
