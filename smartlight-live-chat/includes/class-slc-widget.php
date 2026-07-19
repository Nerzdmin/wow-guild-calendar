<?php
/**
 * Front-end widget – enqueues assets and injects the chat bubble into every page.
 * Also registers the [slc_chat] shortcode for placing the widget inside content.
 */
defined( 'ABSPATH' ) || exit;

class SLC_Widget {

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_footer',          array( __CLASS__, 'render' ) );
        add_shortcode( 'slc_chat',        array( __CLASS__, 'shortcode' ) );
    }

    public static function enqueue() {
        wp_enqueue_style(
            'slc-widget',
            SLC_PLUGIN_URL . 'assets/css/widget.css',
            array(),
            SLC_VERSION
        );
        wp_enqueue_script(
            'slc-widget',
            SLC_PLUGIN_URL . 'assets/js/widget.js',
            array(),
            SLC_VERSION,
            true
        );

        $settings = get_option( 'slc_settings', array() );

        wp_localize_script( 'slc-widget', 'SLC', array(
            'apiRoot'       => esc_url_raw( rest_url( 'slc/v1' ) ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'pollInterval'  => 4000,  // ms
            'accentColor'   => ! empty( $settings['accent_color'] ) ? $settings['accent_color'] : '#e63c1e',
            'position'      => ! empty( $settings['position'] )     ? $settings['position']     : 'right',
            'operatorName'  => ! empty( $settings['operator_name'] ) ? $settings['operator_name'] : get_option( 'blogname' ),
            'operatorAvatar'=> ! empty( $settings['operator_avatar'] ) ? $settings['operator_avatar'] : SLC_PLUGIN_URL . 'assets/img/default-avatar.svg',
            'i18n'          => array(
                'title'       => ! empty( $settings['chat_title'] ) ? $settings['chat_title'] : __( 'Chat with us', 'smartlight-live-chat' ),
                'namePlaceholder'  => __( 'Your name', 'smartlight-live-chat' ),
                'emailPlaceholder' => __( 'Your e-mail (optional)', 'smartlight-live-chat' ),
                'startChat'   => __( 'Start chat', 'smartlight-live-chat' ),
                'typePlaceholder' => __( 'Type a message…', 'smartlight-live-chat' ),
                'send'        => __( 'Send', 'smartlight-live-chat' ),
                'offline'     => ! empty( $settings['offline_message'] ) ? $settings['offline_message'] : __( 'We are currently offline. Leave a message and we will get back to you!', 'smartlight-live-chat' ),
                'closed'      => __( 'This chat session has ended. Thank you!', 'smartlight-live-chat' ),
                'errorSend'   => __( 'Message could not be sent. Please try again.', 'smartlight-live-chat' ),
            ),
        ) );
    }

    public static function render() {
        include SLC_PLUGIN_DIR . 'templates/widget.php';
    }

    public static function shortcode( $atts ) {
        ob_start();
        include SLC_PLUGIN_DIR . 'templates/widget.php';
        return ob_get_clean();
    }
}
