<?php
/**
 * REST API – all public + admin endpoints for the chat widget.
 *
 * Namespace: slc/v1
 *
 * Public endpoints (no auth required):
 *   POST   /start         – begin a new chat session
 *   POST   /message       – visitor sends a message
 *   GET    /poll          – visitor polls for new operator messages
 *
 * Admin endpoints (requires manage_options):
 *   GET    /admin/sessions           – list open sessions
 *   GET    /admin/session/<id>       – get all messages for a session
 *   POST   /admin/reply              – operator sends a message
 *   POST   /admin/close              – close a session
 */
defined( 'ABSPATH' ) || exit;

class SLC_API {

    const NS = 'slc/v1';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        // ── visitor ────────────────────────────────────────────────
        register_rest_route( self::NS, '/start', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'start' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'name'  => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
                'email' => array( 'required' => false, 'sanitize_callback' => 'sanitize_email' ),
            ),
        ) );

        register_rest_route( self::NS, '/message', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'visitor_message' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'token'   => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                'message' => array( 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ),
            ),
        ) );

        register_rest_route( self::NS, '/poll', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'poll' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'token'    => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
                'after_id' => array( 'required' => false, 'default' => 0, 'sanitize_callback' => 'absint' ),
            ),
        ) );

        // ── admin ──────────────────────────────────────────────────
        register_rest_route( self::NS, '/admin/sessions', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'admin_sessions' ),
            'permission_callback' => array( __CLASS__, 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/session/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'admin_session' ),
            'permission_callback' => array( __CLASS__, 'admin_permission' ),
        ) );

        register_rest_route( self::NS, '/admin/reply', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'admin_reply' ),
            'permission_callback' => array( __CLASS__, 'admin_permission' ),
            'args'                => array(
                'session_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
                'message'    => array( 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ),
            ),
        ) );

        register_rest_route( self::NS, '/admin/close', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'admin_close' ),
            'permission_callback' => array( __CLASS__, 'admin_permission' ),
            'args'                => array(
                'session_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
            ),
        ) );
    }

    // ── permission ────────────────────────────────────────────────

    public static function admin_permission() {
        return current_user_can( 'manage_options' );
    }

    // ── visitor handlers ─────────────────────────────────────────

    public static function start( WP_REST_Request $req ) {
        $settings = get_option( 'slc_settings', array() );
        $offline  = ! empty( $settings['offline_mode'] );

        $result = SLC_DB::create_session( $req['name'], $req['email'] ?? '' );

        // send admin notification
        self::notify_admin_new_chat( $result['id'], $req['name'], $req['email'] ?? '' );

        $welcome = ! empty( $settings['welcome_message'] )
            ? $settings['welcome_message']
            : __( 'Hello! How can we help you today?', 'smartlight-live-chat' );

        SLC_DB::add_message( $result['id'], 'operator', $welcome );

        return rest_ensure_response( array(
            'session_id' => $result['id'],
            'token'      => $result['token'],
            'offline'    => $offline,
        ) );
    }

    public static function visitor_message( WP_REST_Request $req ) {
        $session = SLC_DB::get_session_by_token( $req['token'] );
        if ( ! $session ) {
            return new WP_Error( 'invalid_token', 'Invalid session token.', array( 'status' => 403 ) );
        }
        if ( $session->status === 'closed' ) {
            return new WP_Error( 'session_closed', 'This chat session is closed.', array( 'status' => 410 ) );
        }

        $msg_id = SLC_DB::add_message( $session->id, 'visitor', $req['message'] );
        return rest_ensure_response( array( 'message_id' => $msg_id ) );
    }

    public static function poll( WP_REST_Request $req ) {
        $session = SLC_DB::get_session_by_token( $req['token'] );
        if ( ! $session ) {
            return new WP_Error( 'invalid_token', 'Invalid session token.', array( 'status' => 403 ) );
        }

        $messages = SLC_DB::get_messages( $session->id, $req['after_id'] );
        // filter: visitor only receives operator messages
        $out = array();
        foreach ( $messages as $m ) {
            if ( $m->sender === 'operator' ) {
                $out[] = array(
                    'id'      => (int) $m->id,
                    'body'    => $m->body,
                    'sent_at' => $m->sent_at,
                );
            }
        }

        return rest_ensure_response( array(
            'messages' => $out,
            'status'   => $session->status,
        ) );
    }

    // ── admin handlers ───────────────────────────────────────────

    public static function admin_sessions( WP_REST_Request $req ) {
        $sessions = SLC_DB::get_open_sessions();
        return rest_ensure_response( $sessions );
    }

    public static function admin_session( WP_REST_Request $req ) {
        $id       = (int) $req['id'];
        $messages = SLC_DB::get_messages( $id, 0 );
        SLC_DB::mark_read( $id );
        return rest_ensure_response( $messages );
    }

    public static function admin_reply( WP_REST_Request $req ) {
        $msg_id = SLC_DB::add_message( $req['session_id'], 'operator', $req['message'] );
        return rest_ensure_response( array( 'message_id' => $msg_id ) );
    }

    public static function admin_close( WP_REST_Request $req ) {
        SLC_DB::close_session( $req['session_id'] );
        return rest_ensure_response( array( 'closed' => true ) );
    }

    // ── notifications ─────────────────────────────────────────────

    private static function notify_admin_new_chat( $session_id, $name, $email ) {
        $settings     = get_option( 'slc_settings', array() );
        $notify_email = ! empty( $settings['notify_email'] )
            ? $settings['notify_email']
            : get_option( 'admin_email' );

        $inbox_url = admin_url( 'admin.php?page=slc-inbox&session=' . $session_id );

        $subject = sprintf(
            /* translators: %s: visitor name */
            __( '[Smartlight Chat] New chat from %s', 'smartlight-live-chat' ),
            $name
        );
        $body = sprintf(
            __( "A new chat session has started.\n\nVisitor: %s\nEmail: %s\n\nReply here:\n%s", 'smartlight-live-chat' ),
            $name,
            $email ?: __( '(not provided)', 'smartlight-live-chat' ),
            $inbox_url
        );

        wp_mail( $notify_email, $subject, $body );
    }
}
