<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://tassawer.com
 * @since      1.0.0
 *
 * @package    Woocommerce_Credit_System
 * @subpackage Woocommerce_Credit_System/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Woocommerce_Credit_System
 * @subpackage Woocommerce_Credit_System/public
 * @author     Tassawer Hussain <hello@tassawer.com>
 */
class Woocommerce_Credit_System_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_shortcode( 'woocommerce_buy_credits', array( $this, 'woocommerce_buy_credits_shortcode_callback') );

		/*
		 * Ajax Hook Initialization to Get Generate Secret Key
		 */
		add_action( 'wp_ajax_th_purchase_credits', array( $this, 'th_add_product_into_cart' ) );
		add_action( 'wp_ajax_nopriv_th_purchase_credits', array( $this, 'th_add_product_into_cart' ) );

		// Display custom input field value @ Cart
		add_filter( 'woocommerce_get_item_data', array( $this, 'th_product_add_on_display_cart' ), 10, 2 );

		// Save custom input field value into order item meta
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'th_product_add_on_order_item_meta' ), 10, 2 );

		// Display custom input field value into order table
		add_filter( 'woocommerce_order_item_product',  array( $this, 'th_product_add_on_display_order' ), 10, 2 );

		// Display custom input field value into order emails
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'th_product_add_on_display_emails' ) );
		
		// Hook into the 'woocommerce_before_calculate_totals' action
		add_action('woocommerce_before_calculate_totals', array( $this, 'th_update_cart_item_price' ), 10, 1);
		
		// Hook into the WooCommerce payment complete action
		// add_action('woocommerce_payment_complete', array( $this, 'th_insert_credit_history_entry' ), 99, 1 );
		add_action('woocommerce_order_status_completed', array( $this, 'th_insert_credit_history_entry' ), 99, 1 );
		
		// display remianing credits in header.
		add_action('menu_after_login_before', array( $this, 'show_remaining_credits_in_header'), 5 );
		
		// display your credit menu.
		add_filter( 'user_endpoints', array( $this, 'show_user_credit_purchase_history' ), 999, 1 );
		
		// add rest rout to display the credit history.
		add_action( 'rest_api_init', array( $this, 'th_set_rest_rout_for_credit_history' ) );

		// set downloadbale permission.
		add_filter( 'play_block_download_url', array( $this, 'th_is_user_has_credit_to_download' ), 999, 2 );

		// deduct the user credit on download.
		add_action( 'play_block_download_after_save', array( $this, 'th_deduct_user_credit_for_download' ), 999, 3 );

		// display credit required.
		add_action( 'the_download_button', array( $this, 'th_display_credit_required_to_download' ), 20, 1 );

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/woocommerce-credit-system-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/woocommerce-credit-system-public.js', array( 'jquery' ), $this->version, false );

		// create nonce
		$nonce = wp_create_nonce( 'ajax_public' );
		// define ajax url
		$ajax_url = admin_url( 'admin-ajax.php' );
		// define script
		$script = array( 
			'nonce' => $nonce,
			'ajaxurl' => $ajax_url,
			'endpoint' => get_rest_url(null, 'play/user-credit-history/'),
		);

		wp_localize_script( $this->plugin_name, 'ajax_public', $script );

		// Enqueue DataTables library.
		wp_enqueue_script('jquery-dataTables', 'https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js', array('jquery'), '1.10.25', true);
		wp_enqueue_style('dataTables-css', 'https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css');

	}

	public function woocommerce_buy_credits_shortcode_callback( $atts ) {

		// Extract the attributes (product_ids) from the shortcode
		$atts = shortcode_atts(array(
			'product_ids' => '', // Default value is an empty string
		), $atts);
	
		// Split the comma-separated product IDs into an array
		$product_ids = explode(',', $atts['product_ids']);
	
		if( ! empty( $product_ids ) ) {

			// Get the value of the custom setting
			$pricing_heading = get_option('th_pricing_heading');
			$pricing_heading = isset( $pricing_heading ) ? $pricing_heading : 'BUY CREDITS'; 

			$pricing_details = get_option('th_pricing_list');
			$bullet_list = explode("\n", $pricing_details);
		
			if( isset( $_GET['message'] ) && ! empty( $_GET['message'] ) ) {
				$message = $_GET['message'];
				$message = '<div class="alert alert-warning" role="alert">'. $_GET['message'] .'</div>';
			} else {
				$message = '';
			}

			// You can use $product_ids to perform actions or display products here
			$output = $message . '
			<div class="credit-wrapper">
				<div class="PricingCard__wrapper">
					<div class="AssetsIconBar__wrapper">
						<span class="AssetsIconBar__heading">BUY CREDITS</span>
					</div>
					<div class="PricingCard__cardBody">
						<div class="PricingCard__upperGroup">
							<div class="HeaderGroup__group">
								<svg class="PricingCardSVG" focusable="false" viewBox="0 0 48 48" color="#212121" style="font-size:35px;transition:fill 200ms cubic-bezier(0.4, 0, 0.2, 1) 0ms;flex-shrink:0;user-select:none;width:35px;height:35px;display:inline-block;fill:currentColor" data-testid="CreditsIcon" aria-labelledby="Credits">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M41.2 31.6C41.2 37.9926 33.6893 43 24.1 43C14.5107 43 7 37.9926 7 31.6C7 29.5143 7.7995 27.5761 9.20984 25.9112C9.61043 27.1342 10.3886 28.2719 11.4642 29.275C11.0337 30.0109 10.8 30.7932 10.8 31.6C10.8 35.7192 16.8914 39.2 24.1 39.2C31.3086 39.2 37.4 35.7192 37.4 31.6C37.4 30.7932 37.1663 30.0109 36.7363 29.2744C37.8116 28.2719 38.5897 27.1342 38.9908 25.9117C40.4005 27.5761 41.2 29.5143 41.2 31.6Z"></path>
									<path fill-rule="evenodd" clip-rule="evenodd" d="M41.2 24C41.2 30.3925 33.6893 35.4 24.1 35.4C14.5107 35.4 7 30.3925 7 24C7 21.9143 7.7995 19.9761 9.20984 18.3112C9.61043 19.5342 10.3886 20.6719 11.4642 21.675C11.0337 22.4109 10.8 23.1932 10.8 24C10.8 28.1192 16.8914 31.6 24.1 31.6C31.3086 31.6 37.4 28.1192 37.4 24C37.4 23.1932 37.1663 22.4109 36.7363 21.6744C37.8116 20.6719 38.5897 19.5342 38.9908 18.3117C40.4005 19.9761 41.2 21.9143 41.2 24Z"></path>
									<path fill-rule="evenodd" clip-rule="evenodd" d="M24.1 8.8C16.8914 8.8 10.8 12.2808 10.8 16.4C10.8 20.5192 16.8914 24 24.1 24C31.3086 24 37.4 20.5192 37.4 16.4C37.4 12.2808 31.3086 8.8 24.1 8.8ZM24.1 27.8C14.5107 27.8 7 22.7925 7 16.4C7 10.0074 14.5107 5 24.1 5C33.6893 5 41.2 10.0074 41.2 16.4C41.2 22.7925 33.6893 27.8 24.1 27.8Z"></path>
								</svg>
								<h2 class="HeaderGroup__title">'. $pricing_heading .'</h2>
							</div>
							<div class="CreditPack__wrapper">
								<div class="CreditPackDetails__wrapper">';
									
			foreach( $bullet_list as $line ) {
				$output .= '
				<div class="PlanDetails__list">
					<img src="'. WCS_PUBLIC_URL .'img/tick.png" height="24px" width="25px" alt="yellow-tick">
					<span class="PlanDetails__listItem">
						<span>'. $line .'</span>
					</span>
				</div>';
			}
			
			$output .= '</div><div class="CreditPack__subWrapper">';

			$find_featured = false;
		
			foreach ($product_ids as $product_id) {
				// Perform actions with each product ID or display product information
				$product = wc_get_product($product_id); // Replace this with your actual function to fetch product data
		
				// Append the product information to the output
				if ($product) {

					$product_name = $product->get_title();
					$short_description = $product->get_short_description();
					$regular_price = $product->get_regular_price();
					$sale_price = $product->get_sale_price();

					// Product meta data.
					$_th_custom_credit = get_post_meta( $product_id, '_th_custom_credit', true );
					$_th_is_feature = get_post_meta( $product_id, '_th_is_feature', true );
					$_th_credit_count = get_post_meta( $product_id, '_th_credit_count', true );
					$_th_custom_credit_details = get_post_meta( $product_id, '_th_custom_credit_details', true );

					// add popular tag.
					if($_th_is_feature == 'yes' && ! $find_featured) {
						$featured = '<div class="MostPopularTag__wrapper">Most popular</div>';
						$selected = 'PlanOptionItem__wrapper--selected';
						$checked  = 'true';
						$radio_checked = 'checked';
						$find_featured = true;
					} else {
						$featured = '';
						$selected = '';
						$checked  = 'false';
						$radio_checked  = '';
					}

					// normal products.
					if( empty ( $_th_custom_credit ) ) {
						
						// sale price is set.
						if( ! empty ( $sale_price ) ) {
							$regular_price_html = wc_price( $regular_price );
							$sale_price_html    = wc_price( $sale_price );
							$cost_per_credit    = wc_price( $sale_price / $_th_credit_count );
						} else {
							$regular_price_html = '';
							$sale_price_html    = wc_price( $regular_price );
							$cost_per_credit    = wc_price( $regular_price / $_th_credit_count );
						}

						$output .= '
							<div role="radio" tabindex="0" data-productid="'. $product_id .'" data-value="'.$_th_credit_count.'" aria-checked="'. $checked .'" class="PlanOptionItem__wrapper '. $selected .'">
							'. $featured .'
								<div class="PlanOptionItem__radioButtonWrapper">
									<div class="Radio w-100">
										<label class="Radio__container" for="'.$product_id.'">
											<input type="radio" name="buy-woo-credit" id="'. $product_id .'" data-iscustom="no" class="Radio__input" value="'.$_th_credit_count.'" '. $radio_checked .'>
											<span class="Radio__checkmark"></span>
											<span class="Radio__label">
												<div class="PlanOptionItem__particularsWrapper">
													<div class="">
														<div class="PlanParticulars__wrapper">
															<span class="PlanParticulars__label">'. $product_name .'</span>
															<span class="PlanParticulars__details PlanParticulars__details--credits">
																<span class="PlanParticulars__details__creditsText">'. $short_description .'</span>
															</span>
														</div>
													</div>
													<div class="PlanPricing__priceWrapper">
														<span class="PlanPricing__pricePerUnit">'. $cost_per_credit .' <sub class="PlanPricing__subscriptText">/Credit</sub>
														</span>
														<div class="PlanPricing__priceGroup">
															<span class="PlanPricing__originalPrice">'. $regular_price_html .'</span>
															<span class="PlanPricing__totalPrice">'. $sale_price_html .'</span>
														</div>
													</div>
												</div>
											</span>
										</label>
									</div>
								</div>
							</div>
						';

					} else {
						// custome credit product.
						$_cc_details = explode( "\n", $_th_custom_credit_details );

						$custom_credit_price = array();
						foreach( $_cc_details as $key => $detail ) {

							$range_price = explode( "=", $detail );

							if ( $key == 0 ) {
								$_th_credit_count = floatval(trim($range_price[0]));
								$cost_per_credit = floatval(trim($range_price[1]));
								$regular_price_html = wc_price( $_th_credit_count );
								$sale_price_html = wc_price( $_th_credit_count * $cost_per_credit );
								$short_description = str_replace("{{XX}}", $_th_credit_count, $short_description);
							} else {
								$min_max = explode( "-", trim($range_price[0]) );
								$custom_credit_price[] = array(
									'min' => intval(trim($min_max[0])),
									'max' => intval(trim($min_max[1])),
									'cost' => floatval(trim($range_price[1])),
								);
							}
						}

						$data_details = " data-creditdetails='". json_encode($custom_credit_price) . "'";

						$output .= '
							<div role="radio" tabindex="0" data-productid="'. $product_id .'" data-value="'.$_th_credit_count.'" aria-checked="'. $checked .'" class="PlanOptionItem__wrapper '. $selected .'">
							'. $featured .'
								<div class="PlanOptionItem__radioButtonWrapper">
									<div class="Radio w-100">
										<label class="Radio__container" for="'.$product_id.'">
											<input type="radio" name="buy-woo-credit" id="'. $product_id .'" data-iscustom="yes" data-costpercredit="'. $cost_per_credit .'" class="th_custom_credit Radio__input" value="'.$_th_credit_count.'" '. $radio_checked .'>
											<span class="Radio__checkmark"></span>
											<span class="Radio__label">
												<div class="PlanOptionItem__particularsWrapper th-custom-credites-1">
													<div class="PlanParticulars__wrapper">
														<span class="PlanParticulars__label PlanParticulars__label--textNormal">'. $product_name .'</span>
													</div>
												</div>

												<div class="PlanOptionItem__particularsWrapper th-custom-credites-2" style="display:none;">
													<div class="">
														<div class="PlanParticulars__wrapper">
															<span class="PlanParticulars__label">
																<div class="CreditsTextInput__wrapper"><input id="custom-credits" data-testid="custom-credits" '. $data_details .' type="number" min="1" max="1000" inputmode="numeric" aria-label="Credits" class="CreditsTextInput__input CreditsTextInput__input--selected" value="'.$_th_credit_count.'"></div>
																&nbsp;Credits
															</span>
															<span class="PlanParticulars__details PlanParticulars__details--credits">
																<span class="PlanParticulars__details__creditsText">'. $short_description .'</span>
															</span>
														</div>
													</div>
													<div class="PlanPricing__priceWrapper">
														<span class="PlanPricing__pricePerUnit"><span class="th-costpercredit">'. $cost_per_credit .'</span><sub class="PlanPricing__subscriptText">/Credit</sub></span>
														<div class="PlanPricing__priceGroup">
															<span class="PlanPricing__originalPrice"><span class="th-regularcost">'. $regular_price_html .'</span></span>
															<span class="PlanPricing__totalPrice"><span class="th-salecost">'. $sale_price_html .'</span></span>
														</div>
													</div>
												</div>
												
											</span>
										</label>
									</div>
								</div>
							</div>
						';
					}
					
				}
			}
		}

		$output .= '
							</div>
						</div>
					</div>
					<div class="FooterGroup__wrapper">
						<button id="buy-now-credits" data-testid="buy-now-credits" name="buy-now-credits" type="button" class="Button Button__wrapper Button__link FooterGroup__button" tabindex="0">Buy now
								<!-- -->
						</button>
					</div>
					<span class="PricingCard__taxesMayApply">Taxes may apply.</span>
				</div>
			</div>
		</div>
		';

		// $output .= '
		// <div class="credit-wrapper">
		// 	<div class="PricingCard__wrapper">
		// 		<div class="AssetsIconBar__wrapper">
		// 			<span class="AssetsIconBar__heading">BUY CREDITS</span>
		// 		</div>
		// 		<div class="PricingCard__cardBody">
		// 			<div class="PricingCard__upperGroup">
		// 				<div class="HeaderGroup__group">
		// 					<svg class="PricingCardSVG" focusable="false" viewBox="0 0 48 48" color="#212121" style="font-size:35px;transition:fill 200ms cubic-bezier(0.4, 0, 0.2, 1) 0ms;flex-shrink:0;user-select:none;width:35px;height:35px;display:inline-block;fill:currentColor" data-testid="CreditsIcon" aria-labelledby="Credits">
		// 						<path fill-rule="evenodd" clip-rule="evenodd" d="M41.2 31.6C41.2 37.9926 33.6893 43 24.1 43C14.5107 43 7 37.9926 7 31.6C7 29.5143 7.7995 27.5761 9.20984 25.9112C9.61043 27.1342 10.3886 28.2719 11.4642 29.275C11.0337 30.0109 10.8 30.7932 10.8 31.6C10.8 35.7192 16.8914 39.2 24.1 39.2C31.3086 39.2 37.4 35.7192 37.4 31.6C37.4 30.7932 37.1663 30.0109 36.7363 29.2744C37.8116 28.2719 38.5897 27.1342 38.9908 25.9117C40.4005 27.5761 41.2 29.5143 41.2 31.6Z"></path>
		// 						<path fill-rule="evenodd" clip-rule="evenodd" d="M41.2 24C41.2 30.3925 33.6893 35.4 24.1 35.4C14.5107 35.4 7 30.3925 7 24C7 21.9143 7.7995 19.9761 9.20984 18.3112C9.61043 19.5342 10.3886 20.6719 11.4642 21.675C11.0337 22.4109 10.8 23.1932 10.8 24C10.8 28.1192 16.8914 31.6 24.1 31.6C31.3086 31.6 37.4 28.1192 37.4 24C37.4 23.1932 37.1663 22.4109 36.7363 21.6744C37.8116 20.6719 38.5897 19.5342 38.9908 18.3117C40.4005 19.9761 41.2 21.9143 41.2 24Z"></path>
		// 						<path fill-rule="evenodd" clip-rule="evenodd" d="M24.1 8.8C16.8914 8.8 10.8 12.2808 10.8 16.4C10.8 20.5192 16.8914 24 24.1 24C31.3086 24 37.4 20.5192 37.4 16.4C37.4 12.2808 31.3086 8.8 24.1 8.8ZM24.1 27.8C14.5107 27.8 7 22.7925 7 16.4C7 10.0074 14.5107 5 24.1 5C33.6893 5 41.2 10.0074 41.2 16.4C41.2 22.7925 33.6893 27.8 24.1 27.8Z"></path>
		// 					</svg>
		// 					<h2 class="HeaderGroup__title">Credit Pack</h2>
		// 				</div>
		// 				<div class="CreditPack__wrapper">
		// 					<div class="CreditPackDetails__wrapper">
		// 						<div class="PlanDetails__list">
		// 							<svg xmlns="http://www.w3.org/2000/svg" width="25" height="24" viewBox="0 0 25 24" fill="none">
		// 								<path fill-rule="evenodd" clip-rule="evenodd" d="M10.6053 16.4211C10.3224 16.4211 10.04 16.3133 9.8239 16.0972L6.50804 12.7814C6.07642 12.3498 6.07642 11.6501 6.50804 11.2185C6.93965 10.7869 7.6393 10.7869 8.07092 11.2185L10.6053 13.7529L16.4556 7.90266C16.8872 7.47105 17.5869 7.47105 18.0185 7.90266C18.4501 8.33427 18.4501 9.03391 18.0185 9.46552L11.3868 16.0972C11.1707 16.3133 10.8883 16.4211 10.6053 16.4211Z" fill="#c5480a"/>
		// 							</svg>
		// 							<span class="PlanDetails__listItem">
		// 								<span>210 million PREMIUM assets</span>
		// 							</span>
		// 						</div>
		// 						<div class="PlanDetails__list">
		// 							<svg xmlns="http://www.w3.org/2000/svg" width="25" height="24" viewBox="0 0 25 24" fill="none">
		// 								<path fill-rule="evenodd" clip-rule="evenodd" d="M10.6053 16.4211C10.3224 16.4211 10.04 16.3133 9.8239 16.0972L6.50804 12.7814C6.07642 12.3498 6.07642 11.6501 6.50804 11.2185C6.93965 10.7869 7.6393 10.7869 8.07092 11.2185L10.6053 13.7529L16.4556 7.90266C16.8872 7.47105 17.5869 7.47105 18.0185 7.90266C18.4501 8.33427 18.4501 9.03391 18.0185 9.46552L11.3868 16.0972C11.1707 16.3133 10.8883 16.4211 10.6053 16.4211Z" fill="#F0BA27"/>
		// 							</svg>
		// 							<span class="PlanDetails__listItem">
		// 								<span>Credits never expire</span>
		// 							</span>
		// 						</div>
		// 						<div class="PlanDetails__list">
		// 							<svg xmlns="http://www.w3.org/2000/svg" width="25" height="24" viewBox="0 0 25 24" fill="none">
		// 								<path fill-rule="evenodd" clip-rule="evenodd" d="M10.6053 16.4211C10.3224 16.4211 10.04 16.3133 9.8239 16.0972L6.50804 12.7814C6.07642 12.3498 6.07642 11.6501 6.50804 11.2185C6.93965 10.7869 7.6393 10.7869 8.07092 11.2185L10.6053 13.7529L16.4556 7.90266C16.8872 7.47105 17.5869 7.47105 18.0185 7.90266C18.4501 8.33427 18.4501 9.03391 18.0185 9.46552L11.3868 16.0972C11.1707 16.3133 10.8883 16.4211 10.6053 16.4211Z" fill="#c5480a"/>
		// 							</svg>
		// 							<span class="PlanDetails__listItem">
		// 								<span>Standard and Extended Licenses</span>
		// 							</span>
		// 						</div>
		// 						<div class="PlanDetails__list">
		// 							<img src="https://assets-cdn.123rf.com/payments/assets/images/tick-yellow.svg" height="24px" width="25px" alt="yellow-tick">
		// 							<span class="PlanDetails__listItem">
		// 								<span>Mix and match images, vectors, HD videos, audio and fonts – prices vary</span>
		// 							</span>
		// 						</div>
		// 					</div>
		// 					<div class="CreditPack__subWrapper">
		// 						<div role="radio" tabindex="0" data-testid="30" data-value="30" aria-checked="false" class="PlanOptionItem__wrapper">
		// 							<div class="PlanOptionItem__radioButtonWrapper">
		// 								<div class="Radio w-100">
		// 									<label class="Radio__container" for="30">
		// 										<input type="radio" name="buy-woo-credit" id="30" class="Radio__input" value="30">
		// 										<span class="Radio__checkmark"></span>
		// 										<span class="Radio__label">
		// 											<div class="PlanOptionItem__particularsWrapper">
		// 												<div class="">
		// 													<div class="PlanParticulars__wrapper">
		// 														<span class="PlanParticulars__label">30&nbsp;Credits</span>
		// 														<span class="PlanParticulars__details PlanParticulars__details--credits">
		// 															<span class="PlanParticulars__details__creditsText">3 fonts / 3 images / 1 video</span>
		// 														</span>
		// 													</div>
		// 												</div>
		// 												<div class="PlanPricing__priceWrapper">
		// 													<span class="PlanPricing__pricePerUnit">$1 <sub class="PlanPricing__subscriptText">/Credit</sub>
		// 													</span>
		// 													<div class="PlanPricing__priceGroup">
		// 														<span class="PlanPricing__originalPrice"></span>
		// 														<span class="PlanPricing__totalPrice">$30</span>
		// 													</div>
		// 												</div>
		// 											</div>
		// 										</span>
		// 									</label>
		// 								</div>
		// 							</div>
		// 						</div>
		// 						<div role="radio" tabindex="0" data-testid="90" data-value="90" aria-checked="true" class="PlanOptionItem__wrapper PlanOptionItem__wrapper--selected">
		// 							<div class="MostPopularTag__wrapper">Most popular</div>
		// 							<div class="PlanOptionItem__radioButtonWrapper">
		// 								<div class="Radio w-100">
		// 									<label class="Radio__container" for="90">
		// 										<input type="radio" name="buy-woo-credit" id="90" class="Radio__input" checked="" value="90">
		// 										<span class="Radio__checkmark"></span>
		// 										<span class="Radio__label">
		// 											<div class="PlanOptionItem__particularsWrapper">
		// 												<div class="">
		// 													<div class="PlanParticulars__wrapper">
		// 														<span class="PlanParticulars__label">90&nbsp;Credits</span>
		// 														<span class="PlanParticulars__details PlanParticulars__details--credits">
		// 															<span class="PlanParticulars__details__creditsText">9 fonts / 9 images / 3 videos</span>
		// 														</span>
		// 													</div>
		// 												</div>
		// 												<div class="PlanPricing__priceWrapper">
		// 													<span class="PlanPricing__pricePerUnit">$0.85 <sub class="PlanPricing__subscriptText">/Credit</sub>
		// 													</span>
		// 													<div class="PlanPricing__priceGroup">
		// 														<span class="PlanPricing__originalPrice">$90</span>
		// 														<span class="PlanPricing__totalPrice">$76.50</span>
		// 													</div>
		// 												</div>
		// 											</div>
		// 										</span>
		// 									</label>
		// 								</div>
		// 							</div>
		// 						</div>
		// 						<div role="radio" tabindex="0" data-testid="270" data-value="270" aria-checked="false" class="PlanOptionItem__wrapper">
		// 							<div class="PlanOptionItem__radioButtonWrapper">
		// 								<div class="Radio w-100">
		// 									<label class="Radio__container" for="270">
		// 										<input type="radio" name="buy-woo-credit" id="270" class="Radio__input" value="270">
		// 										<span class="Radio__checkmark"></span>
		// 										<span class="Radio__label">
		// 											<div class="PlanOptionItem__particularsWrapper">
		// 												<div class="">
		// 													<div class="PlanParticulars__wrapper">
		// 														<span class="PlanParticulars__label">270&nbsp;Credits</span>
		// 														<span class="PlanParticulars__details PlanParticulars__details--credits">
		// 															<span class="PlanParticulars__details__creditsText">27 fonts / 27 images / 9 videos</span>
		// 														</span>
		// 													</div>
		// 												</div>
		// 												<div class="PlanPricing__priceWrapper">
		// 													<span class="PlanPricing__pricePerUnit">$0.75 <sub class="PlanPricing__subscriptText">/Credit</sub>
		// 													</span>
		// 													<div class="PlanPricing__priceGroup">
		// 														<span class="PlanPricing__originalPrice">$270</span>
		// 														<span class="PlanPricing__totalPrice">$202.50</span>
		// 													</div>
		// 												</div>
		// 											</div>
		// 										</span>
		// 									</label>
		// 								</div>
		// 							</div>
		// 						</div>
		// 						<div role="radio" tabindex="0" data-testid="600" data-value="600" aria-checked="false" class="PlanOptionItem__wrapper">
		// 							<div class="PlanOptionItem__radioButtonWrapper">
		// 								<div class="Radio w-100">
		// 									<label class="Radio__container" for="600">
		// 										<input type="radio" name="buy-woo-credit" id="600" class="Radio__input" value="600">
		// 										<span class="Radio__checkmark"></span>
		// 										<span class="Radio__label">
		// 											<div class="PlanOptionItem__particularsWrapper th-custom-credites-1">
		// 												<div class="PlanParticulars__wrapper">
		// 													<span class="PlanParticulars__label PlanParticulars__label--textNormal">Custom credits</span>
		// 												</div>
		// 											</div>

		// 											<div class="PlanOptionItem__particularsWrapper th-custom-credites-2" style="display:none;">
		// 												<div class="">
		// 													<div class="PlanParticulars__wrapper">
		// 														<span class="PlanParticulars__label">
		// 															<div class="CreditsTextInput__wrapper"><input id="custom-credits" data-testid="custom-credits" type="number" min="10" max="1000" inputmode="numeric" aria-label="Credits" class="CreditsTextInput__input CreditsTextInput__input--selected" value="600"></div>
		// 															&nbsp;Credits
		// 														</span>
		// 														<span class="PlanParticulars__details PlanParticulars__details--credits"><span class="PlanParticulars__details__creditsText">60 fonts / 60 images / 20 videos</span></span>
		// 													</div>
		// 												</div>
		// 												<div class="PlanPricing__priceWrapper">
		// 													<span class="PlanPricing__pricePerUnit">$0.73<sub class="PlanPricing__subscriptText">/Credit</sub></span>
		// 													<div class="PlanPricing__priceGroup">
		// 														<span class="PlanPricing__originalPrice">$600</span>
		// 														<span class="PlanPricing__totalPrice">$436.40</span>
		// 													</div>
		// 												</div>
		// 											</div>
													
		// 										</span>
		// 									</label>
		// 								</div>
		// 							</div>
		// 						</div>
		// 					</div>
		// 				</div>
		// 			</div>
		// 			<div class="FooterGroup__wrapper">
		// 				<button id="buy-now-credits" data-testid="buy-now-credits" name="buy-now-credits" type="button" class="Button Button__wrapper Button__link FooterGroup__button" tabindex="0">Buy now
		// 						<!-- -->
		// 				</button>
		// 			</div>
		// 			<span class="PricingCard__taxesMayApply">Taxes may apply.</span>
		// 		</div>
		// 	</div>
		// </div>
		// ';
	
		return $output;

	}

	/**
	 * Function to add product into cart.
	 *
	 * @since   1.0.0
	 */
	public function th_add_product_into_cart() {

		// check nonce.
		check_ajax_referer( 'ajax_public', 'nonce' );

		$product_id      = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';
		$credit          = isset( $_POST['credit'] ) ? sanitize_text_field( wp_unslash( $_POST['credit'] ) ) : '';
		$cost_per_credit = isset( $_POST['cost_per_credit'] ) ? sanitize_text_field( wp_unslash( $_POST['cost_per_credit'] ) ) : '';
		$iscustom        = isset( $_POST['iscustom'] ) ? sanitize_text_field( wp_unslash( $_POST['iscustom'] ) ) : '';

		// Clear the cart
		WC()->cart->empty_cart();

		// Custom data array
		$custom_data = array(
			'credit_purchase' => $credit,
		);

		if( $iscustom == "yes" ) {
			$custom_data['new_price'] = $cost_per_credit * $credit;
		}

		WC()->cart->add_to_cart($product_id, 1, 0, array(), $custom_data );
		
		echo wp_json_encode( wc_get_checkout_url() );

		wp_die();
	}
	
	public function th_product_add_on_display_cart( $data, $cart_item ) {

		// var_dump($cart_item);
		if ( isset( $cart_item['credit_purchase'] ) ){
			$data[] = array(
				'name' => 'Credit Purchased',
				'value' => sanitize_text_field( $cart_item['credit_purchase'] )
			);
		}
		return $data;
	}

	public function th_product_add_on_order_item_meta( $item_id, $values ) {
		
		if ( ! empty( $values['credit_purchase'] ) ) {
			wc_add_order_item_meta( $item_id, 'Credit Purchased', $values['credit_purchase'], true );
		}
	}

	public function th_product_add_on_display_order( $cart_item, $order_item ){
		if( isset( $order_item['credit_purchase'] ) ){
			$cart_item['credit_purchase'] = $order_item['credit_purchase'];
		}
		return $cart_item;
	}

	public function th_product_add_on_display_emails( $fields ) { 
		$fields['credit_purchase'] = 'Credit Purchased';
		return $fields; 
	}

	public function th_update_cart_item_price($cart) {
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}

		// Check for your custom data in the cart items
		foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
			// Replace 'custom_key' with the actual key you're checking for
			if (isset($cart_item['new_price'])) {
				// Get the custom data and set the new price
				$new_price = $cart_item['new_price'];
				// Update the product price
				$cart_item['data']->set_price($new_price); // Replace with your new price
			}
		}
	}

	public function th_insert_credit_history_entry($order_id) {

		
		// Get the order object
		$order = wc_get_order($order_id);
		$user_id = $order->get_customer_id();
		
		// Get the order items
		$order_items = $order->get_items();
	
		// Loop through order items to find and process the one you want
		foreach ($order_items as $item_id => $item) {
			
			$credit_count = wc_get_order_item_meta($item_id, 'Credit Purchased', true);

			if ( ! empty ($credit_count) ) {
				// Get the credit count from order item meta (replace 'credit_count' with the actual meta key)
	
				// Insert an entry into your custom table
				global $wpdb;
				$table_name = $wpdb->prefix . 'user_credit_history';
	
				$insert_data = array(
					'user_id' => $user_id,
					'credit' => $credit_count,
					'price' => $item->get_total(),
					'date' => current_time('mysql'),
					'description' => 'Credit Purchased. Order # ' . $order_id, // Replace with your description
				);
	
				$wpdb->insert($table_name, $insert_data);

				// sum up the credits for user.
				$credits = intval(get_user_meta($user_id, "_remaining_credits", true));
				$credits = $credits ? $credits : 0;
				$credits += intval( $credit_count );
				update_user_meta( $user_id, '_remaining_credits', $credits );
			}
		}
	}

	public function show_remaining_credits_in_header() {

		$credits = intval( get_user_meta( get_current_user_id(  ), "_remaining_credits", true ) );
		$credits = $credits ? $credits : 0;
		
		echo  "<span class='th_head_credits'>Credits: ". $credits ."</span>";

	}

	public function show_user_credit_purchase_history( $endpoints ) {
		
		$endpoints['user-credit-history']  = array(
			'id' => 'user-credit-history',
			'name' => esc_html__( 'Your Credit', 'woocommerce-credit-system' ),
			'public' => true
		);

		return $endpoints;
	}

	public function th_set_rest_rout_for_credit_history() {
		register_rest_route( 'play', '/user-credit-history', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_user_credit_history' ),
            'permission_callback' => '__return_true',
        ) );
	}

	public function get_user_credit_history( $request ) {

		ob_start();
		if ( is_user_logged_in() ) {

			$user_id = get_current_user_id(  );
			$credits = intval( get_user_meta( $user_id, "_remaining_credits", true ) );
			$credits = $credits ? $credits : 0;

			global $wpdb;
			$table_name = $wpdb->prefix . 'user_credit_history';

			$query = $wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %s ORDER BY ID DESC",
				$user_id
			);

			$results = $wpdb->get_results($query);
            ?>

			<div class="remaining-credits">
				<hr>
				<p><strong>Remaining Credites: </strong><?php echo $credits; ?></p>
				<hr>
				
				<table id="userCreditHistory" class="display" style="width:100%">
					<thead>
						<tr>
							<th>Credit</th>
							<th>Price</th>
							<th>Date</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<!-- PHP loop to populate the table rows -->
						<?php foreach ($results as $row) : ?>
							<tr>
								<td><?php echo $row->credit . " Credits"; ?></td>
								<td><?php echo ( $row->price !== "N\A" ) ? wc_price( $row->price ) : $row->price ; ?></td>
								<td><?php echo date('d M Y', strtotime($row->date)); ?></td>
								<td><?php echo $row->description; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
            
        } else {
			echo '
			<div class="user-placeholder">
				<p>Please login to see your credit history.</p>
			</div>
			';
		}
		$content = ob_get_clean();
		wp_send_json(
			array( 'content' => $content )
		);
	}

	public function th_is_user_has_credit_to_download( $url, $id ) {

		$credits = intval( get_user_meta( get_current_user_id(  ), "_remaining_credits", true ) );
		$credits = $credits ? $credits : 0;

		// Retrieve the current value of the credit_required field
		$credit_required = get_post_meta($id, 'credit_required', true);
		$credit_required = $credit_required ? $credit_required : 1;

		if( $credits <= 0 || $credits < $credit_required) {
			$url = add_query_arg( array(
				'message' => 'Please purchase credits to download the music.',
			), get_permalink( get_option('_th_pricing_page') ) );

		}

		return $url;

	}

	public function th_deduct_user_credit_for_download( $user_id, $id, $download_id ) {

		// sum up the credits for user.
		$credits = intval(get_user_meta($user_id, "_remaining_credits", true));
		$credits = $credits ? $credits : 0;

		// Retrieve the current value of the credit_required field
		$credit_required = get_post_meta($id, 'credit_required', true);
		$credit_required = $credit_required ? $credit_required : 1;

		$credits = $credits - $credit_required;
		update_user_meta( $user_id, '_remaining_credits', $credits );

		// Insert an entry into your custom table
		global $wpdb;
		$table_name = $wpdb->prefix . 'user_credit_history';

		$insert_data = array(
			'user_id' => $user_id,
			'credit' => $credit_required,
			'price' => 'N\A',
			'date' => current_time('mysql'),
			'description' => 'Credit used to download ' . get_the_title( $id ), // Replace with your description
		);

		$wpdb->insert($table_name, $insert_data);

	}

	public function th_display_credit_required_to_download( $id ) {

		// Retrieve the current value of the credit_required field
		$credit_required = get_post_meta($id, 'credit_required', true);
		$credit_required = $credit_required ? $credit_required : 1;

		echo '<img class="th-dollar-bill" src="'. WCS_PUBLIC_URL .'img/dollar-bill.png" height="24px" width="25px" alt="yellow-tick"> '. $credit_required .' CREDIT';
	}
}
