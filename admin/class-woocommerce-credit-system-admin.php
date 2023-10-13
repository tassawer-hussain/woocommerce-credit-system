<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://tassawer.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Credit_System
 * @subpackage Woocommerce_Credit_System/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Woocommerce_Credit_System
 * @subpackage Woocommerce_Credit_System/admin
 * @author     Tassawer Hussain <hello@tassawer.com>
 */
class Woocommerce_Credit_System_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Step 1: Add the custom field to the Product General tab
		add_action('woocommerce_product_options_general_product_data', array( $this, 'add_custom_product_field' ), 10 );

		// Step 2: Save the custom field data when the product is saved
		add_action('woocommerce_process_product_meta', array( $this, 'save_custom_product_field' ), 10, 1 );

		// Add textarea on the settings page.
		add_filter( 'woocommerce_general_settings', array( $this, 'th_add_pricing_bullet_points' ), 999, 1 );

		add_action('add_meta_boxes', array( $this, 'th_add_station_meta_box' ) );

		add_action('save_post', array( $this, 'th_save_station_credit_required_field' ) );

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woocommerce-credit-system-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woocommerce-credit-system-admin.js', array( 'jquery' ), $this->version, false );

	}

	public function add_custom_product_field() {
		global $post;
	
		echo '<div class="options_group">';

		woocommerce_wp_checkbox(array(
			'id'          => '_th_custom_credit',
			'label'       => 'Custom Credit',
			'desc_tip'    => 'true',
			'description' => 'Check this box if the product has custom credit option.',
		));

		woocommerce_wp_checkbox(array(
			'id'          => '_th_is_feature',
			'label'       => 'Is Featured?',
			'desc_tip'    => 'true',
			'description' => 'Check this box if the product is featured.',
		));
	
		woocommerce_wp_text_input(array(
			'id'          => '_th_credit_count',
			'label'       => 'Credit Count',
			'placeholder' => 'How much credit user will get?',
			'desc_tip'    => 'true',
			'description' => 'How many credit user will get on purchasing of this product.',
		));

		woocommerce_wp_textarea_input(array(
			'id'          => '_th_custom_credit_details',
			'label'       => 'Custom Credits Details',
			'placeholder' => '301-500 = 0.70 &#13;&#10;501-800 = 0.68 &#13;&#10;801-1000 = 0.65 ',
			'desc_tip'    => 'true',
			'style'       => 'min-width: 60%; height: 150px;',
			'rows'        => 5,
			'cols'        => 20,
			'desc_tip'    => 'true',
			'description' => 'Add One rule per line in this format.<br>301-500 = 0.70 <br> 501-800 = 0.68 <br> 801-1000 = 0.65 ',
		));
	
		echo '</div>';
	}
	
	public function save_custom_product_field($product_id) {
		
		if( isset( $_POST['_th_is_feature'] ) ) {
			$_th_is_feature = sanitize_text_field($_POST['_th_is_feature']);
			update_post_meta($product_id, '_th_is_feature', $_th_is_feature);
		}

		if( isset( $_POST['_th_custom_credit'] ) ) {
			$_th_custom_credit = sanitize_text_field($_POST['_th_custom_credit']);
			update_post_meta($product_id, '_th_custom_credit', $_th_custom_credit);
		}

		if( isset( $_POST['_th_credit_count'] ) ) {
			$_th_credit_count = sanitize_text_field($_POST['_th_credit_count']);
			update_post_meta($product_id, '_th_credit_count', $_th_credit_count);
		}

		if( isset( $_POST['_th_custom_credit_details'] ) ) {
			$_th_custom_credit_details = sanitize_textarea_field($_POST['_th_custom_credit_details']);
			update_post_meta($product_id, '_th_custom_credit_details', $_th_custom_credit_details);
		}
		
	}

	public function th_add_pricing_bullet_points($settings) {
	
		// Search array for the id you want
		$key              = array_search('woocommerce_store_postcode', array_column($settings, 'id')) + 1;
		$custom_setting[] = array(
			'title'    => __('Enter Heading.'),
			'desc'     => __("Enter heading to display on the pricing page."),
			'id'       => 'th_pricing_heading',
			'default'  => '',
			'type'     => 'text',
			'desc_tip' => true,
		);
		$custom_setting[] = array(
			'title'    => __('Enter bullet list to display on the pricing page.'),
			'desc'     => __("Enter bullet list to display on the pricing page. one per line"),
			'id'       => 'th_pricing_list',
			'default'  => '',
			'type'     => 'textarea',
			'css'      => 'min-width: 50%; height: 150px;',
			'desc_tip' => true,
		);

		$custom_setting[] = array(
			'title'    => __('Enter Pricing Page ID.'),
			'desc'     => __("User will redirect to this page if they don't have any credite and tried to download the song."),
			'id'       => '_th_pricing_page',
			'default'  => '',
			'type'     => 'text',
			'desc_tip' => true,
		);

	
		// Merge with existing settings at the specified index
		$new_settings = array_merge(array_slice($settings, 0, $key), $custom_setting, array_slice($settings, $key));
		return $new_settings;
	}

	public function th_add_station_meta_box() {
		add_meta_box(
			'station_credit_required_meta_box', // Unique ID
			'Credit Required', // Meta Box Title
			array( $this, 'th_display_station_credit_required_field' ), // Callback function to display the field
			'station', // Custom Post Type
			'side', // Context (normal, advanced, side)
			'default' // Priority (default, high, low)
		);
	}

	public function th_display_station_credit_required_field( $post ) {

		// Retrieve the current value of the credit_required field
		$credit_required = get_post_meta($post->ID, 'credit_required', true);

		// Output the input field
		echo '<label for="credit_required">Credit Required</label>';
		echo '<input type="number" id="credit_required" name="credit_required" value="' . esc_attr($credit_required) . '" />';

	}

	public function th_save_station_credit_required_field($post_id) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) 
			return $post_id;
	
		if ($post_id && isset($_POST['credit_required'])) {
			update_post_meta($post_id, 'credit_required', sanitize_text_field($_POST['credit_required']));
		}
	}

}

