<?php
/**
 * Fired when the plugin is uninstalled.
 * Drops plugin database tables and removes all plugin options.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-slc-db.php';
SLC_DB::uninstall();
