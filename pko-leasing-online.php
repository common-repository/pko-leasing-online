<?php
require_once("vendor/autoload.php");
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://pkoleasing.pl
 * @since             1.0.6
 * @package           Pkol
 *
 * @wordpress-plugin
 * Plugin Name:       PKO Leasing Online
 * Plugin URI:        https://pkoleasing.pl
 * Description:       PKO Leasing Online - finansowanie leasingiem.
 * Version:           1.0.6
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       PKO Leasing Online
 * Domain Path:       /languages
 */
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Currently plugin version.
 * Start at version 1.0.6 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PKOL_VERSION', '1.0.6' );
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-pkol-activator.php
 */
function activate_pkol() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pkol-activator.php';
	Pkol_Activator::activate();
}
/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-pkol-deactivator.php
 */
function deactivate_pkol() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pkol-deactivator.php';
	Pkol_Deactivator::deactivate();
}
register_activation_hook( __FILE__, 'activate_pkol' );
register_deactivation_hook( __FILE__, 'deactivate_pkol' );
/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-pkol.php';
/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.3
 */
function run_pkol() {
	$plugin = new Pkol();
	$plugin->run();
}
run_pkol();
