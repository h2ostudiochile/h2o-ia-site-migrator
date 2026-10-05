<?php
/**
 * Plugin Name: h2o IA Site Migrator
 * Description: Motor desacoplado y versionado para migraciones editoriales de sitios H2O sobre WordPress core.
 * Version: 1.0.3
 * Author: h2o Studio
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: h2o-ia-site-migrator
 * Update URI: https://github.com/h2ostudiochile/h2o-ia-site-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'H2OSM_VERSION', '1.0.3' );
define( 'H2OSM_FILE', __FILE__ );
define( 'H2OSM_DIR', plugin_dir_path( __FILE__ ) );
define( 'H2OSM_URL', plugin_dir_url( __FILE__ ) );

require_once H2OSM_DIR . 'includes/class-registry.php';
require_once H2OSM_DIR . 'includes/class-sync.php';
require_once H2OSM_DIR . 'includes/class-admin.php';
require_once H2OSM_DIR . 'includes/class-updater.php';
require_once H2OSM_DIR . 'includes/class-plugin.php';

register_activation_hook( H2OSM_FILE, array( '\\H2O\\Site_Migrator\\Sync', 'activate' ) );

add_action(
    'plugins_loaded',
    static function () {
        \H2O\Site_Migrator\Plugin::boot();
    }
);
