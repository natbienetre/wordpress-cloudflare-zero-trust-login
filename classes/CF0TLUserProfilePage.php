<?php
class CF0TLUserProfilePage {
    public static function register_hooks() {
        $instance = new self();

        add_action( 'show_user_profile', array( $instance, 'show_user_profile' ) );
        add_action( 'admin_bar_menu', array( $instance, 'admin_bar_menu' ), 100 );
        add_action( 'admin_enqueue_scripts', array( $instance, 'admin_enqueue_scripts' ) );
    }

    function show_user_profile( WP_User $profile_user ) {
		$email = CF0TLAuthentication::get_email_from_jwt();

        ?>
        <h2>
            <img src="<?php echo esc_url( plugins_url( 'images/cloudflare-logo.png', CF0TL_PLUGIN_FILE ) ); ?>" alt="Cloudflare Logo" class="cf0tl-logo" />
            <?php _e( 'Cloudflare Zero Trust Login', 'cf0tl' ); ?>
        </h2>
        <p><?php _e( 'Authenticated via <a href="https://dash.cloudflare.com" target="_blank">Cloudflare Zero Trust</a>.', 'cf0tl' ); ?></p>
        <table class="form-table">
            <tr>
                <th><label for="cf0tl-team"><?php _e( 'Team', 'cf0tl' ); ?></label></th>
                <td>
                    <input type="text" name="cf0tl-team" id="cf0tl-team" value="<?php echo esc_attr( CF0TLOptions::load()->team ); ?>" class="regular-text" readonly>
                </td>
            </tr>
            <tr>
                <th><label for="cf0tl-email"><?php _e( 'Email', 'cf0tl' ); ?></label></th>
                <td>
                    <input type="email" name="cf0tl-email" id="cf0tl-email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" readonly>
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
        $userInfo->title = str_replace(
            '</span>',
            '<img src="' . esc_url( plugins_url( 'images/cloudflare-logo.png', CF0TL_PLUGIN_FILE ) ) . '" alt="CF" class="cf0tl-logo" /></span>',
            $userInfo->title
        );
        $wp_admin_bar->add_node( (array)$userInfo );
    }

    function admin_enqueue_scripts() {
        wp_enqueue_style( 'cf0tl-admin', plugins_url( 'styles/admin.css', CF0TL_PLUGIN_FILE ), array(), '0.1.0' );
    }
}
