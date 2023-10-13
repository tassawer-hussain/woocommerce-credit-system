<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://tassawer.com
 * @since             1.0.0
 * @package           Woocommerce_Credit_System
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Credit System
 * Plugin URI:        https://tassawer.com
 * Description:       Custom Credit System for WooCommerce
 * Version:           1.0.0
 * Author:            Tassawer Hussain
 * Author URI:        https://tassawer.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-credit-system
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WOOCOMMERCE_CREDIT_SYSTEM_VERSION', '1.0.0' );

/**
 * Define plugin wise constant.
 */
if ( ! defined( 'WCS_PUBLIC_PATH' ) ) {
	define( 'WCS_PUBLIC_PATH', plugin_dir_path( __FILE__ ) . 'public/' );
}

if ( ! defined( 'WCS_PUBLIC_URL' ) ) {
	define( 'WCS_PUBLIC_URL', plugin_dir_url( __FILE__ ) . 'public/' );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woocommerce-credit-system-activator.php
 */
function activate_woocommerce_credit_system() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-credit-system-activator.php';
	Woocommerce_Credit_System_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woocommerce-credit-system-deactivator.php
 */
function deactivate_woocommerce_credit_system() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-credit-system-deactivator.php';
	Woocommerce_Credit_System_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_woocommerce_credit_system' );
register_deactivation_hook( __FILE__, 'deactivate_woocommerce_credit_system' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woocommerce-credit-system.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woocommerce_credit_system() {

	$plugin = new Woocommerce_Credit_System();
	$plugin->run();

}
run_woocommerce_credit_system();


add_filter('use_block_editor_for_post_type', 'prefix_disable_gutenberg', 10, 2);
function prefix_disable_gutenberg($current_status, $post_type)
{
    // Use your post type key instead of 'product'
    if ($post_type === 'product') return false;
    return $current_status;
}
