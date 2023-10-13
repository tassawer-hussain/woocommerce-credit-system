<?php

/**
 * Fired during plugin activation
 *
 * @link       https://tassawer.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Credit_System
 * @subpackage Woocommerce_Credit_System/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Woocommerce_Credit_System
 * @subpackage Woocommerce_Credit_System/includes
 * @author     Tassawer Hussain <hello@tassawer.com>
 */
class Woocommerce_Credit_System_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// create users limit table.
		$table_name = $wpdb->prefix . 'user_credit_history';

		$query = "CREATE TABLE IF NOT EXISTS $table_name (
			`ID` int(11) NOT NULL AUTO_INCREMENT,
			`user_id` int(20),
			`credit` varchar(200),
			`price` varchar(200),
			`date` date NOT NULL,
			`description` varchar(200),
			PRIMARY KEY  (`ID`)
		) $charset_collate;";
		dbDelta( $query );

	}

}
