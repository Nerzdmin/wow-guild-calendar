<?php
/**
 * Admin – settings page + live inbox.
 */
defined( 'ABSPATH' ) || exit;

class SLC_Admin {

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    // ── menu ──────────────────────────────────────────────────────

    public static function add_menu() {
        add_menu_page(
            __( 'Live Chat', 'smartlight-live-chat' ),
            __( 'Live Chat', 'smartlight-live-chat' ),
            'manage_options',
            'slc-inbox',
            array( __CLASS__, 'page_inbox' ),
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            'slc-inbox',
            __( 'Inbox', 'smartlight-live-chat' ),
            __( 'Inbox', 'smartlight-live-chat' ),
            'manage_options',
            'slc-inbox',
            array( __CLASS__, 'page_inbox' )
        );

        add_submenu_page(
            'slc-inbox',
            __( 'Settings', 'smartlight-live-chat' ),
            __( 'Settings', 'smartlight-live-chat' ),
            'manage_options',
            'slc-settings',
            array( __CLASS__, 'page_settings' )
        );
    }

    // ── enqueue (admin only) ──────────────────────────────────────

    public static function enqueue( $hook ) {
        $pages = array( 'toplevel_page_slc-inbox', 'live-chat_page_slc-settings' );
        if ( ! in_array( $hook, $pages, true ) ) {
            return;
        }
        wp_enqueue_style(
            'slc-admin',
            SLC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SLC_VERSION
        );
        if ( strpos( $hook, 'slc-inbox' ) !== false ) {
            wp_enqueue_script(
                'slc-admin',
                SLC_PLUGIN_URL . 'assets/js/admin.js',
                array( 'wp-api-fetch' ),
                SLC_VERSION,
                true
            );
            wp_localize_script( 'slc-admin', 'SLC_ADMIN', array(
                'apiRoot'      => esc_url_raw( rest_url( 'slc/v1' ) ),
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'pollInterval' => 4000,
            ) );
        }
    }

    // ── settings registration ─────────────────────────────────────

    public static function register_settings() {
        register_setting( 'slc_settings_group', 'slc_settings', array(
            'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
        ) );

        add_settings_section( 'slc_general', __( 'General', 'smartlight-live-chat' ), null, 'slc-settings' );
        add_settings_section( 'slc_appearance', __( 'Appearance', 'smartlight-live-chat' ), null, 'slc-settings' );
        add_settings_section( 'slc_notifications', __( 'Notifications', 'smartlight-live-chat' ), null, 'slc-settings' );

        $fields = array(
            // general
            array( 'slc_general', 'operator_name',   __( 'Operator name', 'smartlight-live-chat' ),    'text' ),
            array( 'slc_general', 'welcome_message',  __( 'Welcome message', 'smartlight-live-chat' ),  'textarea' ),
            array( 'slc_general', 'offline_message',  __( 'Offline message', 'smartlight-live-chat' ),  'textarea' ),
            array( 'slc_general', 'offline_mode',     __( 'Offline mode (disable live chat)', 'smartlight-live-chat' ), 'checkbox' ),
            // appearance
            array( 'slc_appearance', 'chat_title',    __( 'Chat window title', 'smartlight-live-chat' ), 'text' ),
            array( 'slc_appearance', 'accent_color',  __( 'Accent color', 'smartlight-live-chat' ),     'color' ),
            array( 'slc_appearance', 'position',      __( 'Button position', 'smartlight-live-chat' ),   'select' ),
            array( 'slc_appearance', 'operator_avatar', __( 'Operator avatar URL', 'smartlight-live-chat' ), 'text' ),
            // notifications
            array( 'slc_notifications', 'notify_email', __( 'Notification e-mail', 'smartlight-live-chat' ), 'email' ),
        );

        foreach ( $fields as $f ) {
            add_settings_field(
                'slc_' . $f[1],
                $f[2],
                array( __CLASS__, 'render_field' ),
                'slc-settings',
                $f[0],
                array( 'key' => $f[1], 'type' => $f[3] )
            );
        }
    }

    public static function sanitize_settings( $input ) {
        $clean = array();
        $text_keys     = array( 'operator_name', 'chat_title', 'operator_avatar', 'position' );
        $textarea_keys = array( 'welcome_message', 'offline_message' );
        $email_keys    = array( 'notify_email' );
        $color_keys    = array( 'accent_color' );
        $checkbox_keys = array( 'offline_mode' );

        foreach ( $text_keys     as $k ) { $clean[ $k ] = sanitize_text_field( $input[ $k ] ?? '' ); }
        foreach ( $textarea_keys as $k ) { $clean[ $k ] = sanitize_textarea_field( $input[ $k ] ?? '' ); }
        foreach ( $email_keys    as $k ) { $clean[ $k ] = sanitize_email( $input[ $k ] ?? '' ); }
        foreach ( $color_keys    as $k ) { $clean[ $k ] = sanitize_hex_color( $input[ $k ] ?? '' ); }
        foreach ( $checkbox_keys as $k ) { $clean[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0; }

        return $clean;
    }

    public static function render_field( $args ) {
        $settings = get_option( 'slc_settings', array() );
        $key      = $args['key'];
        $type     = $args['type'];
        $value    = $settings[ $key ] ?? '';
        $name     = "slc_settings[{$key}]";
        $id       = 'slc_' . $key;

        switch ( $type ) {
            case 'textarea':
                printf( '<textarea id="%s" name="%s" rows="3" class="large-text">%s</textarea>',
                    esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
                break;
            case 'checkbox':
                printf( '<input type="checkbox" id="%s" name="%s" value="1" %s>',
                    esc_attr( $id ), esc_attr( $name ), checked( $value, 1, false ) );
                break;
            case 'select':
                $options = array( 'right' => __( 'Bottom-right', 'smartlight-live-chat' ),
                                  'left'  => __( 'Bottom-left',  'smartlight-live-chat' ) );
                echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
                foreach ( $options as $v => $label ) {
                    printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $value, $v, false ), esc_html( $label ) );
                }
                echo '</select>';
                break;
            default:
                printf( '<input type="%s" id="%s" name="%s" value="%s" class="regular-text">',
                    esc_attr( $type ), esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
        }
    }

    // ── pages ─────────────────────────────────────────────────────

    public static function page_settings() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Live Chat Settings', 'smartlight-live-chat' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'slc_settings_group' );
                do_settings_sections( 'slc-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function page_inbox() {
        include SLC_PLUGIN_DIR . 'templates/admin-inbox.php';
    }
}
