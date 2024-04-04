<?php
class CF0TLUser {
    const EMAIL_METADATA_KEY = 'cf0tl_email';

    const NOT_FOUND_ERROR_CODE = 'user_not_found';

    public static function register_hooks() {
        $instance = new self();

        add_filter( 'manage_users_columns', array( $instance, 'manage_users_columns' ) );
        add_filter( 'manage_users_custom_column', array( $instance, 'manage_users_custom_column' ), 10, 3 );
        add_filter( 'insert_custom_user_meta', array( $instance, 'insert_custom_user_meta' ) );
        add_action( 'edit_user_profile', array( $instance, 'edit_user_profile' ) );
        add_action( 'show_user_profile', array( $instance, 'show_user_profile' ) );
        add_action( 'admin_bar_menu', array( $instance, 'admin_bar_menu' ), 100 );
        add_action( 'admin_enqueue_scripts', array( $instance, 'admin_enqueue_scripts' ) );
        add_action( 'user_profile_update_errors', array( $instance, 'user_profile_update_errors' ), 10, 3 );
    }

    public function manage_users_columns( array $columns ): array {
        $opts = CF0TLOptions::load();

        if ( $opts->use_custom_email_field ) {
            $columns['cf0tl-email'] = __( 'CloudFlare Zero Trust email', 'cf0tl' );
        }

        return $columns;
    }

    public function manage_users_custom_column( string $output, string $column_name, int $user_id ): string {
        if ( 'cf0tl-email' !== $column_name ) {
            return $output;
        }

        return $this->get_registered_email( $user_id );
    }

    function insert_custom_user_meta( array $meta ): array {
        $email = trim( $_POST[self::EMAIL_METADATA_KEY] ?? '' );

        if ( ! is_email( $email ) ) {
            return $meta;
        }

        $meta[self::EMAIL_METADATA_KEY] = $email;

        return $meta;
    }

    public function get_registered_email( WP_User | int $user ): string {
        if ( ! CF0TLOptions::load()->use_custom_email_field ) {
            if ( $user instanceof WP_User ) {
                return $user->user_email;
            }

            return get_userdata( $user )->user_email;
        }

        if ( $user instanceof WP_User ) {
            return get_user_meta( $user->ID, self::EMAIL_METADATA_KEY, true );
        }

        return get_user_meta( $user, self::EMAIL_METADATA_KEY, true );
    }

    public function user_profile_update_errors( WP_Error $errors, bool $update_user, stdClass $user ) {
        if ( ! CF0TLOptions::load()->use_custom_email_field ) {
            return;
        }

        $email = trim( $_POST['cf0tl_email'] ?? '' );

        if ( empty( $email ) ) {
            if ( ! delete_user_meta( $user->ID, self::EMAIL_METADATA_KEY ) ) {
                $errors->add( 'cf0tl_email', '<img src="' . esc_url( plugin_dir_url( CF0TL_PLUGIN_FILE ) . 'images/cloudflare-logo.png' ) . '" alt="' . esc_attr__( 'CloudFlare Zero Trust', 'cft0l' ) . '" class="cf0tl-logo" />' . __( 'Failed to delete the email.', 'cf0tl' ) );
            }
            return;
        }

        if ( ! is_email( $email ) ) {
            $errors->add( 'cf0tl_email', '<img src="' . esc_url( plugin_dir_url( CF0TL_PLUGIN_FILE ) . 'images/cloudflare-logo.png' ) . '" alt="' . esc_attr__( 'CloudFlare Zero Trust', 'cft0l' ) . '" class="cf0tl-logo" />' . __( 'Please enter a valid email address.', 'cf0tl' ) );
            return;
        }

        $existing_user = $this->find_user( $email );

        if ( is_wp_error( $existing_user ) ) {
            if ( $update_user && self::NOT_FOUND_ERROR_CODE === $existing_user->get_error_code() ) {
                return;
            }

            $errors->merge_from( $existing_user );
            return;
        }

        if ( $existing_user->ID !== $user->ID || ! $update_user ) {
            $errors->add( 'cf0tl_email', '<img src="' . esc_url( plugin_dir_url( CF0TL_PLUGIN_FILE ) . 'images/cloudflare-logo.png' ) . '" alt="' . esc_attr__( 'CloudFlare Zero Trust', 'cft0l' ) . '" class="cf0tl-logo" />' . __( 'Email already in use.', 'cf0tl' ) );
            return;
        }

        $previous_email = $this->get_registered_email( $user->ID );

        if ( $previous_email != $email && false === update_user_meta( $user->ID, self::EMAIL_METADATA_KEY, $email ) ) {
            $errors->add(
                'cf0tl_email',
                '<img src="' . esc_url( plugin_dir_url( CF0TL_PLUGIN_FILE ) . 'images/cloudflare-logo.png' ) . '" alt="' . esc_attr__( 'CloudFlare Zero Trust', 'cft0l' ) . '" class="cf0tl-logo" />' .
                /* translators: %1$s is the class name, %2$s is the email */
                sprintf( __( 'Failed to update the email to <span class="%1$s">%2$s<span>.', 'cf0tl' ), 'email', $email )
            );
            return;
        }
    }

    public static function find_user( string $email ): WP_User | WP_Error {
        if ( ! CF0TLOptions::load()->use_custom_email_field ) {
            $user = get_user_by( 'email', $email );

            if ( false === $user ) return new WP_Error( self::NOT_FOUND_ERROR_CODE, __( 'User not found', 'cf0tl' ) );

            return $user;
        }

        $users = get_users(array(
            'meta_key' => self::EMAIL_METADATA_KEY,
            'meta_value' => $email
        ));

        if ( empty( $users ) ) {
            return new WP_Error( self::NOT_FOUND_ERROR_CODE, __( 'User not found', 'cf0tl' ) );
        }

        if ( count( $users ) > 1 ) {
            return new WP_Error( 'multiple_users', __( 'Multiple users found', 'cf0tl' ) );
        }

        return $users[0];
    }

    function show_user_profile( WP_User $user ) {
        $this->edit_user_profile(
            $user,
            CF0TLOptions::load()->disable_self_email_edit && ! current_user_can( 'edit_user', $user->ID )
        );
    }

    function edit_user_profile( WP_User $user, $readonly = false ) {
        ?>
        <h2>
            <img src="<?php echo esc_url( plugins_url( 'images/cloudflare-logo.png', CF0TL_PLUGIN_FILE ) ); ?>" class="cf0tl-logo" />
            <?php _e( 'Cloudflare Zero Trust Login', 'cf0tl' ); ?>
        </h2>
        <p><?php _e( 'Authenticated via <a href="https://dash.cloudflare.com" target="_blank">Cloudflare Zero Trust</a>.', 'cf0tl' ); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="cf0tl-email"><?php _e( 'Email', 'cf0tl' ); ?></label></th>
                <td>
                    <input type="email" name="cf0tl_email" id="cf0tl-email" class="regular-text"
                        value="<?php echo esc_attr( get_user_meta( $user->ID, self::EMAIL_METADATA_KEY, true ) ); ?>"
                        placeholder="<?php echo esc_attr( $user->user_email ); ?>"
                        <?php echo $readonly || ! CF0TLOptions::load()->use_custom_email_field ? 'readonly' : ''; ?>
                        />
                </td>
            </tr>
        </table>
        <?php
	}

    function admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
        $opts = CF0TLOptions::load();

        if ( $opts->disable_logout ) {
            $wp_admin_bar->remove_node( 'logout' );
        }
        $userInfo = $wp_admin_bar->get_node('user-info');
        $logo_markup = '<img src="' . esc_url( plugins_url( 'images/cloudflare-logo.png', CF0TL_PLUGIN_FILE ) ) . '" alt="CF" class="cf0tl-logo" />';

        // TODO: parse the html and add the logo to the display-name span
        $userInfo->title = preg_replace(
            '@<span class=(["\'])display-name\1>(.*)</span>@i',
            '<span class="display-name">$2' . $logo_markup . '</span>',
            $userInfo->title
        );

        $wp_admin_bar->add_node( (array)$userInfo );
    }

    function admin_enqueue_scripts() {
        wp_enqueue_style( 'cf0tl-admin', plugins_url( 'styles/admin.css', CF0TL_PLUGIN_FILE ), array(), '0.0.1' );
    }
}
