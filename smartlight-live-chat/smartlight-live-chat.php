<?php
/**
 * Plugin Name:  Smartlight Live Chat
 * Plugin URI:   https://www.smartlight.sk/
 * Description:  Replaces the old-school contact form with a real-time chat widget.
 *               Visitors chat directly with your team; operators reply from the WP admin.
 * Version:      1.0.0
 * Author:       Smartlight
 * Author URI:   https://www.smartlight.sk/
 * License:      GPL-2.0+
 * Text Domain:  smartlight-live-chat
 */

defined( 'ABSPATH' ) || exit;

define( 'SLC_VERSION',    '1.0.0' );
define( 'SLC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SLC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SLC_PLUGIN_FILE', __FILE__ );

require_once SLC_PLUGIN_DIR . 'includes/class-slc-db.php';
require_once SLC_PLUGIN_DIR . 'includes/class-slc-api.php';
require_once SLC_PLUGIN_DIR . 'includes/class-slc-widget.php';
require_once SLC_PLUGIN_DIR . 'includes/class-slc-admin.php';

register_activation_hook( __FILE__, array( 'SLC_DB', 'install' ) );
register_uninstall_hook( __FILE__, array( 'SLC_DB', 'uninstall' ) );

add_action( 'plugins_loaded', 'slc_init' );

function slc_init() {
    SLC_API::init();
    SLC_Widget::init();
    SLC_Admin::init();
}
