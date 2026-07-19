<?php
/**
 * Database layer – creates / manages the two plugin tables.
 *
 * slc_sessions  – one row per visitor chat session
 * slc_messages  – individual messages belonging to a session
 */
defined( 'ABSPATH' ) || exit;

class SLC_DB {

    const TABLE_SESSIONS = 'slc_sessions';
    const TABLE_MESSAGES = 'slc_messages';

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sessions = $wpdb->prefix . self::TABLE_SESSIONS;
        $messages = $wpdb->prefix . self::TABLE_MESSAGES;

        $sql = "
        CREATE TABLE IF NOT EXISTS {$sessions} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_token CHAR(64)        NOT NULL,
            visitor_name  VARCHAR(120)    NOT NULL DEFAULT '',
            visitor_email VARCHAR(200)    NOT NULL DEFAULT '',
            status        ENUM('open','closed') NOT NULL DEFAULT 'open',
            operator_id   BIGINT UNSIGNED NULL,
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY   session_token (session_token),
            KEY          status (status)
        ) {$charset};

        CREATE TABLE IF NOT EXISTS {$messages} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            sender     ENUM('visitor','operator') NOT NULL,
            body       TEXT            NOT NULL,
            sent_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_read    TINYINT(1)      NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY         session_id (session_id),
            KEY         sent_at    (sent_at)
        ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        add_option( 'slc_db_version', SLC_VERSION );
    }

    public static function uninstall() {
        global $wpdb;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE_MESSAGES );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . self::TABLE_SESSIONS );
        delete_option( 'slc_db_version' );
        delete_option( 'slc_settings' );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public static function sessions_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SESSIONS;
    }

    public static function messages_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_MESSAGES;
    }

    public static function get_session_by_token( $token ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::sessions_table() . ' WHERE session_token = %s',
                $token
            )
        );
    }

    public static function create_session( $name, $email ) {
        global $wpdb;
        $token = bin2hex( random_bytes( 32 ) );
        $wpdb->insert( self::sessions_table(), array(
            'session_token' => $token,
            'visitor_name'  => sanitize_text_field( $name ),
            'visitor_email' => sanitize_email( $email ),
        ) );
        return array( 'id' => $wpdb->insert_id, 'token' => $token );
    }

    public static function add_message( $session_id, $sender, $body ) {
        global $wpdb;
        $wpdb->insert( self::messages_table(), array(
            'session_id' => absint( $session_id ),
            'sender'     => $sender,
            'body'       => wp_kses_post( $body ),
        ) );
        // bump session updated_at
        $wpdb->update( self::sessions_table(), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $session_id ) );
        return $wpdb->insert_id;
    }

    /**
     * Fetch messages newer than $after_id for a session.
     */
    public static function get_messages( $session_id, $after_id = 0 ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, sender, body, sent_at FROM ' . self::messages_table()
                . ' WHERE session_id = %d AND id > %d ORDER BY id ASC',
                $session_id,
                $after_id
            )
        );
    }

    public static function get_open_sessions() {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT s.*, '
            . '( SELECT COUNT(*) FROM ' . self::messages_table() . ' m WHERE m.session_id = s.id AND m.is_read = 0 AND m.sender = \'visitor\' ) AS unread '
            . 'FROM ' . self::sessions_table() . ' s '
            . 'WHERE s.status = \'open\' ORDER BY s.updated_at DESC'
        );
    }

    public static function close_session( $session_id ) {
        global $wpdb;
        $wpdb->update( self::sessions_table(), array( 'status' => 'closed' ), array( 'id' => $session_id ) );
    }

    public static function mark_read( $session_id ) {
        global $wpdb;
        $wpdb->update( self::messages_table(), array( 'is_read' => 1 ),
            array( 'session_id' => $session_id, 'sender' => 'visitor' ) );
    }
}
