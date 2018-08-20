<?php
/*
 * Plugin Name: WooCommerce Drop Prices
 * Plugin URI: http://www.wpgenie.org/woocommerce-drop-prices/
 * Description: Easily extend WooCommerce with drop (or raise) price features and functionalities.
 * Version: 1.1.17
 * Author: wpgenie
 * Author URI: http://www.wpgenie.org/
 * Requires at least: 4.0
 * Tested up to: 4.9.6
 *
 * Text Domain: drop_price
 * Domain Path: /lang/
 *
 * Copyright:
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least: 2.6.0
 * WC tested up to: 3.4.3
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Required minimum version of WordPress.
if ( ! function_exists( 'woo_drop_price_required' ) ) {
	function woo_drop_price_required() {
		global $wp_version;
		$plugin      = plugin_basename( __FILE__ );
		$plugin_data = get_plugin_data( __FILE__, false );

		if ( version_compare( $wp_version, '3.3', '<' ) ) {
			if ( is_plugin_active( $plugin ) ) {
				deactivate_plugins( $plugin );
				wp_die( "'" . $plugin_data['Name'] . "' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='" . admin_url() . "'>WordPress Admin</a>." );
			}
		}
	}
}
add_action( 'admin_init', 'woo_drop_price_required' );



// Checks if the WooCommerce plugins is installed and active.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) or is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {



	if ( ! class_exists( 'WooCommerce_drop_prices' ) ) {
		class WooCommerce_drop_prices {

			public $plugin_prefix;
			public $plugin_url;
			public $plugin_path;
			public $plugin_basefile;
			private $tab_data = false;
			public $bid;
			public $emails;
			public $version = '1.1.17';


			/**
			 * Gets things started by adding an action to initialize this plugin once
			 * WooCommerce is known to be active and initialized
			 *
			 */
			public function __construct() {
				$this->plugin_prefix   = 'drop_price';
				$this->plugin_basefile = plugin_basename( __FILE__ );
				$this->plugin_url      = plugin_dir_url( $this->plugin_basefile );
				$this->plugin_path     = trailingslashit( dirname( __FILE__ ) );
				add_action( 'woocommerce_init', array( &$this, 'init' ) );
				add_action( 'woocommerce_single_product_summary', array( $this, 'show_counter_on_single_page' ), 15 );
			}
			/**
			 * Init WooCommerce Simple Auction plugin once we know WooCommerce is active
			 *
			 */
			public function init() {

				global $woocommerce;

				add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
				add_action( 'init', array( $this, 'drop_price_auctions_cron' ) );
				add_action( 'wp_ajax_refresh_price', array( $this, 'ajax_refresh_price' ) );
				add_action( 'woocommerce_reduce_order_stock', array( $this, 'reset_price_on_order' ) );

				if ( is_admin() ) {
					add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_script' ) );
					add_filter( 'plugin_row_meta', array( $this, 'add_support_link' ), 10, 2 );
					add_action( 'admin_notices', array( $this, 'woocommerce_simple_auctions_admin_notice' ) );
					add_action( 'admin_init', array( $this, 'woocommerce_simple_auctions_ignore_notices' ) );
					add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_write_panel' ) );
					add_action( 'woocommerce_process_product_meta', array( $this, 'product_save_data' ), 80 );

				} else {
					add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_script' ) );
					add_action( 'woocommerce_single_product_summary', array( $this, 'show_counter_on_single_page' ), 15 );
					add_action( 'wp', array( $this, 'set_price_single' ), 10 );
					add_filter( 'woocommerce_add_cart_item', array( $this, 'add_product_modified_date_to_cart' ) );
					add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'woocommerce_get_cart_item_from_session' ), 10, 3 );

				}
			}

			/**
			 * Load Localisation files.
			 *
			 */
			public function load_plugin_textdomain() {
				/* Localisation */
				$locale = apply_filters( 'plugin_locale', get_locale(), 'drop_price' );
				load_textdomain( 'drop_price', WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/lang/drop_price-' . $locale . '.mo' );
				load_plugin_textdomain( 'drop_price', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

			}

			/**
			 * Add admin script
			 * @access public
			 * @return void
			 *
			 */
			public function admin_enqueue_script( $hook ) {
				global $post_type;
				if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
					if ( 'product' == get_post_type() ) {
						wp_enqueue_script(
							'drop-prices-admin',
							$this->plugin_url . '/js/drop-prices-admin.js',
							array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'timepicker-addon' ),
							'1',
							true
						);
						wp_enqueue_script(
							'timepicker-addon',
							$this->plugin_url . '/js/jquery-ui-timepicker-addon.js',
							array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker' ),
							'1',
							true
						);
						wp_enqueue_style( 'jquery-ui-datepicker' );
					}
				}
			}



			/**
			 * Add frontend scripts
			 * @access public
			 * @return void
			 *
			 */
			public function frontend_enqueue_script() {

				wp_enqueue_script( 'drop-prices-countdown', $this->plugin_url . 'js/jquery.countdown.min.js', array( 'jquery' ), $this->version, false );

				wp_register_script( 'drop-prices-countdown-language', $this->plugin_url . 'js/jquery.countdown.language.js', array( 'jquery', 'drop-prices-countdown' ), $this->version, false );

				$language_data = array(
					'labels'        => array(
						'Years'   => esc_html__( 'Years', 'drop_price' ),
						'Months'  => esc_html__( 'Months', 'drop_price' ),
						'Weeks'   => esc_html__( 'Weeks', 'drop_price' ),
						'Days'    => esc_html__( 'Days', 'drop_price' ),
						'Hours'   => esc_html__( 'Hours', 'drop_price' ),
						'Minutes' => esc_html__( 'Minutes', 'drop_price' ),
						'Seconds' => esc_html__( 'Seconds', 'drop_price' ),
					),
					'labels1'       => array(
						'Year'   => esc_html__( 'Year', 'drop_price' ),
						'Month'  => esc_html__( 'Month', 'drop_price' ),
						'Week'   => esc_html__( 'Week', 'drop_price' ),
						'Day'    => esc_html__( 'Day', 'drop_price' ),
						'Hour'   => esc_html__( 'Hour', 'drop_price' ),
						'Minute' => esc_html__( 'Minute', 'drop_price' ),
						'Second' => esc_html__( 'Second', 'drop_price' ),
					),
					'compactLabels' => array(
						'y' => esc_html__( 'y', 'drop_price' ),
						'm' => esc_html__( 'm', 'drop_price' ),
						'w' => esc_html__( 'w', 'drop_price' ),
						'd' => esc_html__( 'd', 'drop_price' ),
					),
				);

				wp_localize_script( 'drop-prices-countdown-language', 'drop_prices_language_data', $language_data );

				wp_enqueue_script( 'drop-prices-countdown-language' );

				wp_register_script( 'drop-prices-frontend', $this->plugin_url . 'js/drop-prices-frontend.js', array( 'jquery', 'drop-prices-countdown' ), $this->version, false );

				$custom_data = array(
					'finished'   => esc_html__( 'Price has been changed! Refresh page to see new price.', 'drop_price' ),
					'gtm_offset' => get_option( 'gmt_offset' ),
				);

				wp_localize_script( 'drop-prices-frontend', 'drop_prices_data', $custom_data );

				wp_localize_script( 'drop-prices-frontend', 'DP_Ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

				wp_enqueue_script( 'drop-prices-frontend' );

				wp_enqueue_style( 'drop-prices', $this->plugin_url . 'css/frontend.css', $this->version );
			}
			/**
			 * Add link to plugin page
			 *
			 * @access public
			 * @param  array, string
			 * @return array
			 *
			 */
			public function add_support_link( $links, $file ) {
				if ( ! current_user_can( 'install_plugins' ) ) {
					return $links;
				}
				if ( $file == $this->plugin_basefile ) {
					$links[] = '<a href="http://wpgenie.org/woocommerce-drop-prices/documentation/" target="_blank">' . esc_html__( 'Docs', 'drop_price' ) . '</a>';
					$links[] = '<a href="http://codecanyon.net/user/wpgenie#contact" target="_blank">' . esc_html__( 'Support', 'drop_price' ) . '</a>';
					$links[] = '<a href="http://codecanyon.net/user/wpgenie/" target="_blank">' . esc_html__( 'More WooCommerce Extensions', 'drop_price' ) . '</a>';
				}
				return $links;
			}
			/**
			 * Add admin notice
			 *
			 * @access public
			 * @param  array, string
			 * @return array
			 *
			 */
			public function woocommerce_simple_auctions_admin_notice() {
				global $current_user;
				if ( current_user_can( 'manage_options' ) ) {
					$user_id = $current_user->ID;
					if ( get_option( 'Woocommerce_drop_price_cron_check' ) != 'yes' && ! get_user_meta( $user_id, 'cron_check_ignore_notice' ) ) {
						echo '<div class="updated">
					   	<p>' . sprintf( esc_html__( 'Woocommerce Drop Price require that you set up a cron job : <b>%1$s/?drop-price-cron</b>. Set it to every minute| <a href="%2$s">Hide Notice</a>', 'drop_price' ), get_bloginfo( 'url' ), add_query_arg( 'cron_check_ignore', '0' ) ) . '</p>
						</div>';
					}
				}
			}
			/**
			 * Add user meta to ignor notice about crons.
			 * @access public
			 *
			 */
			public function woocommerce_simple_auctions_ignore_notices() {
				global $current_user;
				$user_id = $current_user->ID;

				/* If user clicks to ignore the notice, add that to their user meta */
				if ( isset( $_GET['cron_check_ignore'] ) && '0' == $_GET['cron_check_ignore'] ) {
					add_user_meta( $user_id, 'cron_check_ignore_notice', 'true', true );
				}
			}
			/**
			 * Adds the panel to the Product Data postbox in the product interface
			 *
			 * @return void
			 *
			 */
			public function product_write_panel() {
				global $post;

				echo '<div class="options_group hide_if_grouped">';

				woocommerce_wp_text_input(
					array(
						'id'                => '_drop_price_decrement',
						'class'             => 'wc_input_price short',
						'label'             => esc_html__( 'Price change', 'drop_price' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'type'              => 'number',
						'custom_attributes' => array(
							'step' => 'any',
							'min'  => '0',
						),
					)
				);
				woocommerce_wp_select(
					array(
						'id'      => '_drop_price_change_type',
						'label'   => esc_html__( 'Price change type', 'drop_price' ),
						'options' => array(
							'fixed' => esc_html__( 'Fixed', 'drop_price' ),
							'perc'  => esc_html__( 'Percentage', 'drop_price' ),
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => '_drop_price_min',
						'class'             => 'wc_input_price short',
						'label'             => esc_html__( 'End price', 'drop_price' ) . ' (' . get_woocommerce_currency_symbol() . ')',
						'type'              => 'number',
						'custom_attributes' => array(
							'step' => 'any',
							'min'  => '0',
						),
						'desc_tip'          => 'true',
						'description'       => esc_html__( 'Minimal or Maximal price for....', 'drop_price' ),
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'                => '_drop_price_time',
						'class'             => 'wc_input_price short',
						'label'             => esc_html__( 'Change price every n minutes', 'drop_price' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'step' => '1',
							'min'  => '0',
						),
						'desc_tip'          => 'true',
						'description'       => esc_html__( 'Time for changing prices in minutes. Make sure that this value is greater than 0 otherwise price will not drop', 'drop_price' ),
					)
				);

				woocommerce_wp_select(
					array(
						'id'      => '_drop_price_type',
						'label'   => esc_html__( 'Change type', 'drop_price' ),
						'options' => array(
							'drop' => esc_html__( 'Drop', 'drop_price' ),
							'rise' => esc_html__( 'Rise', 'drop_price' ),
						),
					)
				);

				woocommerce_wp_checkbox(
					array(
						'id'            => '_drop_price_counter',
						'wrapper_class' => '',
						'label'         => esc_html__( 'Counter?', 'drop_price' ),
						'description'   => esc_html__( 'Show counter for next price drop on single page', 'drop_price' ),
					)
				);

				woocommerce_wp_checkbox(
					array(
						'id'            => '_drop_reset_on_order',
						'wrapper_class' => '',
						'label'         => esc_html__( 'Reset price and counter on order?', 'drop_price' ),
						'description'   => esc_html__( 'It will reset price to regular price and timer. then start from begining', 'drop_price' ),
					)
				);

				$drop_price_dates_from = ( $date = get_post_meta( $post->ID, '_drop_price_dates_from', true ) ) ? $date : '';
				$drop_price_dates_to   = ( $date = get_post_meta( $post->ID, '_drop_price_dates_to', true ) ) ? $date : '';

				echo '	<p class="form-field drop_price_dates_fields">
							<label for="_drop_price_dates_from">' . esc_html__( 'Drop Price Timeframe', 'drop_price' ) . '</label>
							<input type="text" class="short datetimepicker" name="_drop_price_dates_from" id="_drop_price_dates_from" value="' . esc_html( $drop_price_dates_from ) . '" placeholder="' . _x( 'From&hellip;', 'placeholder', 'drop_price' ) . ' YYYY-MM-DD HH:MM" maxlength="16" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])[ ](0[0-9]|1[0-9]|2[0-4]):(0[0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])" />
							<span class="clear" style="display:block;"></span>
							<input type="text" class="short datetimepicker" name="_drop_price_dates_to" id="_drop_price_dates_to" value="' . esc_html( $drop_price_dates_to ) . '" placeholder="' . _x( 'To&hellip;', 'placeholder', 'drop_price' ) . '  YYYY-MM-DD HH:MM" maxlength="16" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])[ ](0[0-9]|1[0-9]|2[0-4]):(0[0-9]|1[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])" />
						</p>';
				echo '</div>';

			}
			/**
			 * Saves the data inputed into the product boxes, as post meta data
			 *
			 *
			 * @param int $post_id the post (product) identifier
			 * @param stdClass $post the post (product)
			 *
			 */
			public function product_save_data( $post_id ) {
				global $wpdb, $woocommerce, $woocommerce_errors;

				if ( get_post_type( $post_id ) == 'product' ) {

					$changenextdrop = false;

					$current_drop_price_dates_from = get_post_meta( $post_id, '_drop_price_next_drop', true );
					$current_drop_price_time       = get_post_meta( $post_id, '_drop_price_time', true );

					update_post_meta( $post_id, '_drop_price_decrement', stripslashes( $_POST['_drop_price_decrement'] ) );

					update_post_meta( $post_id, '_drop_price_min', stripslashes( $_POST['_drop_price_min'] ) );

					update_post_meta( $post_id, '_drop_price_time', stripslashes( absint( $_POST['_drop_price_time'] ) ) );

					update_post_meta( $post_id, '_drop_price_dates_from', stripslashes( $_POST['_drop_price_dates_from'] ) );

					update_post_meta( $post_id, '_drop_price_dates_to', stripslashes( $_POST['_drop_price_dates_to'] ) );

					if ( isset( $_POST['_drop_price_counter'] ) ) {
						update_post_meta( $post_id, '_drop_price_counter', stripslashes( $_POST['_drop_price_counter'] ) );
					} else {

						delete_post_meta( $post_id, '_drop_price_counter' );
					}

					if ( isset( $_POST['_drop_reset_on_order'] ) ) {
						update_post_meta( $post_id, '_drop_reset_on_order', 'yes' );
					} else {
						delete_post_meta( $post_id, '_drop_reset_on_order' );
					}

					update_post_meta( $post_id, '_drop_price_type', stripslashes( $_POST['_drop_price_type'] ) );
					update_post_meta( $post_id, '_drop_price_change_type', stripslashes( $_POST['_drop_price_change_type'] ) );

					$nextdrop = get_post_meta( $post_id, '_drop_price_next_drop', true );

					$product = wc_get_product( $post_id );

					if ( ! $nextdrop or ( $current_drop_price_dates_from != stripslashes( $_POST['_drop_price_dates_from'] ) ) or ( $current_drop_price_time != stripslashes( $_POST['_drop_price_time'] ) ) ) {
							$changenextdrop = true;

					}

					if ( $changenextdrop == true && isset( $_POST['_drop_price_time'] ) && ( absint( $_POST['_drop_price_time'] ) > 0 ) ) {

						if ( ( $_POST['_drop_price_type'] == 'drop' && ( ( $_POST['_drop_price_min'] < $_POST['_sale_price'] ) or ( empty( $_POST['_sale_price'] ) && ( $_POST['_drop_price_min'] < $_POST['_regular_price'] ) ) )
						or ( $_POST['_drop_price_type'] == 'rise' && ( ! isset( $_POST['_drop_price_min'] ) or empty( $_POST['_drop_price_min'] ) or ( $_POST['_drop_price_min'] > $_POST['_sale_price'] ) or ( empty( $_POST['_sale_price'] ) && ( $_POST['_drop_price_min'] > $_POST['_regular_price'] ) ) ) ) ) ) {

							if ( isset( $_POST['_drop_price_dates_from'] ) && ! empty( $_POST['_drop_price_dates_from'] ) ) {

								update_post_meta( $post_id, '_drop_price_next_drop', strtotime( $_POST['_drop_price_dates_from'], current_time( 'timestamp' ) ) + ( absint( $_POST['_drop_price_time'] ) * 60 ) );

							} else {

								update_post_meta( $post_id, '_drop_price_next_drop', ( current_time( 'timestamp' ) + (int) $_POST['_drop_price_time'] * 60 ) );

							}
						}

						if ( isset( $_POST['variable_post_id'] ) && ! empty( $_POST['variable_post_id'] ) ) {

							foreach ( $_POST['variable_post_id'] as $key => $value ) {

								if ( ( $_POST['_drop_price_type'] == 'drop' && ( ( $_POST['_drop_price_min'] < $_POST['variable_sale_price'][ $key ] ) or ( empty( $_POST['variable_sale_price'][ $key ] ) && ( $_POST['_drop_price_min'] < $_POST['variable_regular_price'][ $key ] ) ) )
								or ( $_POST['_drop_price_type'] == 'rise' && ( ! isset( $_POST['_drop_price_min'] ) or empty( $_POST['_drop_price_min'] ) or ( $_POST['_drop_price_min'] > $_POST['variable_sale_price'][ $key ] ) or ( empty( $_POST['variable_sale_price'][ $key ] ) && ( $_POST['_drop_price_min'] > $_POST['variable_regular_price'][ $key ] ) ) ) ) ) ) {

									if ( isset( $_POST['_drop_price_dates_from'] ) && ! empty( $_POST['_drop_price_dates_from'] ) ) {

										update_post_meta( $post_id, '_drop_price_next_drop', strtotime( $_POST['_drop_price_dates_from'] ) + ( absint( $_POST['_drop_price_time'] ) * 60 ) );

									} else {
										update_post_meta( $post_id, '_drop_price_next_drop', ( current_time( 'timestamp' ) + (int) $_POST['_drop_price_time'] * 60 ) );

									}
									break;
								}
							}
						}
					}
				}
			}
			/**
			 * Saves the data inputed into the product boxes, as post meta data
			 *
			 *
			 * @param int $post_id the post (product) identifier
			 * @param stdClass $post the post (product)
			 *
			 */
			public function set_new_price( $post ) {

				$nextdrop            = (int) get_post_meta( $post->ID, '_drop_price_next_drop', true );
				$drop_price_dates_to = get_post_meta( $post->ID, '_drop_price_dates_to', true );
				$drop_price_time     = (int) get_post_meta( $post->ID, '_drop_price_time', true );
				$product             = wc_get_product( $post->ID );

				if ( $nextdrop != 0 && $nextdrop < current_time( 'timestamp' ) && $product->is_purchasable() && $product->is_in_stock() ) {
					if ( isset( $drop_price_dates_to ) && ( $drop_price_dates_to != 0 ) && ( current_time( 'timestamp' ) >= strtotime( get_post_meta( $post->ID, '_drop_price_dates_to', true ) ) ) ) {
						delete_post_meta( $post->ID, '_drop_price_next_drop' );
						do_action( 'WooCommerce_drop_prices_price_end', $post->ID );
						return;
					} else {
						$regular_price          = (float) get_post_meta( $post->ID, '_regular_price', true );
						$saleprice              = (float) get_post_meta( $post->ID, '_sale_price', true );
						$drop_price_decrement   = (float) get_post_meta( $post->ID, '_drop_price_decrement', true );
						$drop_price_min         = (float) get_post_meta( $post->ID, '_drop_price_min', true );
						$drop_price_time        = (int) get_post_meta( $post->ID, '_drop_price_time', true );
						$drop_price_type        = get_post_meta( $post->ID, '_drop_price_type', true );
						$drop_price_change_type = get_post_meta( $post->ID, '_drop_price_change_type', true );

						$current_price = $saleprice && ( $saleprice > 0 ) ? $saleprice : $regular_price;

						$current_price = wc_format_decimal( $current_price );

						if ( $drop_price_type == 'drop' ) {
							if ( 'perc' === $drop_price_change_type ) {
								$new_price = $current_price - ( $current_price * ( $drop_price_decrement / 100 ) );
							} else {
								$new_price = $current_price - $drop_price_decrement;
							}
							if ( $saleprice && ( $saleprice > 0 ) ) {
								if ( $saleprice <= (float) get_post_meta( $post->ID, '_drop_price_min', true ) ) {
									delete_post_meta( $post->ID, '_drop_price_next_drop' );
									return;
								} else {

									if ( $new_price > 0 && $new_price > $drop_price_min ) {
										update_post_meta( $post->ID, '_sale_price', $new_price );
										update_post_meta( $post->ID, '_price', $new_price );
										update_post_meta( $post->ID, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );
									} else {
										update_post_meta( $post->ID, '_sale_price', $drop_price_min );
										update_post_meta( $post->ID, '_price', $drop_price_min );
										delete_post_meta( $post->ID, '_drop_price_next_drop' );
									}

									return;
								}
							} else {

								if ( $regular_price ) {
									update_post_meta( $post->ID, '_sale_price', $new_price );
									update_post_meta( $post->ID, '_price', $new_price );
									update_post_meta( $post->ID, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );
									return;
								}
							}
							do_action( 'WooCommerce_drop_prices_price_drop', $post->ID );
						} elseif ( $drop_price_type == 'rise' ) {
							if ( 'perc' === $drop_price_change_type ) {
								$new_price = $current_price + ( $current_price * ( $drop_price_decrement / 100 ) );
							} else {
								$new_price = $current_price + $drop_price_decrement;
							}
							if ( $saleprice && ( $saleprice > 0 ) ) {
								if ( ! empty( $drop_price_min ) && $saleprice >= $drop_price_min ) {

									delete_post_meta( $post->ID, '_drop_price_next_drop' );
									return;
								} else {
									if ( ( $new_price > 0 ) && ( ( $new_price < $drop_price_min ) or empty( $drop_price_min ) ) ) {
										update_post_meta( $post->ID, '_sale_price', $new_price );
										update_post_meta( $post->ID, '_price', $new_price );
										update_post_meta( $post->ID, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );
									} else {
										update_post_meta( $post->ID, '_sale_price', $drop_price_min );
										update_post_meta( $post->ID, '_price', $drop_price_min );
										delete_post_meta( $post->ID, '_drop_price_next_drop' );
									}

									return;
								}
							} else {

								if ( $regular_price ) {
									update_post_meta( $post->ID, '_sale_price', $new_price );
									update_post_meta( $post->ID, '_price', $new_price );
									update_post_meta( $post->ID, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );
									return;
								}
							}
							do_action( 'WooCommerce_drop_prices_price_rise', $post->ID );
						}
						if ( function_exists( 'wp_cache_post_change' ) ) {
							$GLOBALS['super_cache_enabled'] = 1;
							wp_cache_post_change( $post->ID );
						}
						if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
							w3tc_pgcache_flush_post( $post->ID );
						}

						do_action( 'WooCommerce_drop_prices_price_change', $post->ID );
					}
				}
			}
			/**
			 * Saves the data inputed into the product boxes, as post meta data for variable product
			 *
			 *
			 * @param int $post_id the post (product) identifier
			 * @param stdClass $post the post (product)
			 *
			 */
			public function set_new_price_variable( $post ) {

				$product             = wc_get_product( $post->ID );
				$nextdrop            = (int) get_post_meta( $post->ID, '_drop_price_next_drop', true );
				$drop_price_dates_to = get_post_meta( $post->ID, '_drop_price_dates_to', true );
				$drop_price_time     = (int) get_post_meta( $post->ID, '_drop_price_time', true );
				$children            = $product->get_children();
				$mainid              = $post->ID;

				if ( $nextdrop != 0 && $nextdrop < current_time( 'timestamp' ) && $product->is_purchasable() && $product->is_in_stock() ) {
					if ( isset( $drop_price_dates_to ) && ( $drop_price_dates_to != 0 ) && ( current_time( 'timestamp' ) >= strtotime( get_post_meta( $post->ID, '_drop_price_dates_to', true ) ) ) ) {

						delete_post_meta( $mainid, '_drop_price_next_drop' );
						do_action( 'WooCommerce_drop_prices_price_end', $mainid );

						return;

					} else {
						$transient_name = 'wc_var_prices_' . $post->ID;
						delete_transient( $transient_name );

						$drop_price_decrement = (float) get_post_meta( $mainid, '_drop_price_decrement', true );
						$drop_price_min       = (float) get_post_meta( $mainid, '_drop_price_min', true );
						$drop_price_time      = (int) get_post_meta( $mainid, '_drop_price_time', true );
						$drop_price_type      = get_post_meta( $mainid, '_drop_price_type', true );

						if ( $children ) {
							foreach ( $children as $key => $variable_id ) {

								$post = get_post( $variable_id );

								$regular_price = (float) get_post_meta( $post->ID, '_regular_price', true );
								$saleprice     = (float) get_post_meta( $post->ID, '_sale_price', true );

								$drop_price_change_type = get_post_meta( $post->ID, '_drop_price_change_type', true );

								$current_price = $saleprice && ( $saleprice > 0 ) ? $saleprice : $regular_price;

								$current_price = wc_format_decimal( $current_price );

								if ( $drop_price_type == 'drop' ) {

									if ( 'perc' === $drop_price_change_type ) {
										$new_price = $current_price - ( $current_price * ( $drop_price_decrement / 100 ) );
									} else {
										$new_price = $current_price - $drop_price_decrement;
									}

									if ( $saleprice && ( $saleprice > 0 ) ) {

										if ( $saleprice <= (float) get_post_meta( $post->ID, '_drop_price_min', true ) ) {
											delete_post_meta( $mainid, '_drop_price_next_drop' );
											return;
										} else {

											if ( $new_price > 0 && $new_price > $drop_price_min ) {
												update_post_meta( $post->ID, '_sale_price', $new_price );
												update_post_meta( $post->ID, '_price', $new_price );
												update_post_meta( $mainid, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );
											} else {
												update_post_meta( $post->ID, '_sale_price', $drop_price_min );
												update_post_meta( $post->ID, '_price', $drop_price_min );
												delete_post_meta( $mainid, '_drop_price_next_drop' );
											}
										}
									} else {

										if ( $regular_price ) {
											update_post_meta( $post->ID, '_sale_price', $new_price );
											update_post_meta( $post->ID, '_price', $new_price );
											update_post_meta( $mainid, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );

										}
									}
									do_action( 'WooCommerce_drop_prices_price_drop', $post->ID );
								} elseif ( $drop_price_type == 'rise' ) {

									if ( 'perc' === $drop_price_change_type ) {
										$new_price = $current_price + ( $current_price * ( $drop_price_decrement / 100 ) );
									} else {
										$new_price = $current_price + $drop_price_decrement;
									}

									if ( $saleprice && ( $saleprice > 0 ) ) {
										if ( $saleprice >= (float) get_post_meta( $post->ID, '_drop_price_min', true ) ) {
											delete_post_meta( $mainid, '_drop_price_next_drop' );
											return;
										} else {

											if ( ( $new_price > 0 ) && ( ( ( $new_price ) < $drop_price_min ) or empty( $drop_price_min ) ) ) {
												update_post_meta( $post->ID, '_sale_price', $new_price );
												update_post_meta( $post->ID, '_price', $new_price );
												update_post_meta( $mainid, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );
											} else {
												update_post_meta( $post->ID, '_sale_price', $drop_price_min );
												update_post_meta( $post->ID, '_price', $drop_price_min );
												delete_post_meta( $mainid, '_drop_price_next_drop' );
											}
										}
									} else {

										if ( $regular_price ) {
											update_post_meta( $post->ID, '_sale_price', $new_price );
											update_post_meta( $post->ID, '_price', $new_price );
											update_post_meta( $mainid, '_drop_price_next_drop', current_time( 'timestamp' ) + ( $drop_price_time * 60 ) );

										}
									}
									do_action( 'WooCommerce_drop_prices_price_rise', $post->ID );
								}
							}
						}

						do_action( 'WooCommerce_drop_prices_price_change', $post->ID );
						return;
					}
				}
			}

			/**
			 * Add drop price hash key
			 *
			 * @access public
			 * @return str
			 * @since  1.0.4
			 *
			 */

			function add_dropprice_woocommerce_get_variation_prices_hash( $hash ) {

				$hash = get_option( 'wcsss_amount', '' ) . '-' . WC()->session->client_currency . '-' . $type = get_option( 'wcsss_type', '0' ) . $hash[0];
				return $hash;

			}

			public function set_price_single() {
				global $post;
				if ( is_object( $post ) ) {
					$product = wc_get_product( $post->ID );
					if ( $product ) {
						if ( $product->is_purchasable() && $product->is_in_stock() ) {
							$product_type = method_exists( $product, 'get_type' ) ? $product->get_type() : $product->product_type;
							if ( $product_type == 'variable' ) {

								$this->set_new_price_variable( $post );
							} else {
								$this->set_new_price( $post );
							}
						}
					}
				}

			}
			/**
			 * Cron action
			 *
			 * Checks for a valid request, check auctions and closes auction if finished
			 *
			 * @access public
			 * @param void
			 * @return void
			 *
			 */
			public function show_counter_on_single_page() {
				global $product;

				if ( ! isset( $product ) ) {
					return;
				}
				$product_id                 = method_exists( $product, 'get_id' ) ? $product->get_id() : get_the_id();
				$drop_price_counter_enabled = get_post_meta( $product_id, '_drop_price_counter', true );
				$drop_price_next_drop       = get_post_meta( $product_id, '_drop_price_next_drop', true );
				$drop_price_type            = get_post_meta( $product_id, '_drop_price_type', true );
				if ( isset( $drop_price_counter_enabled ) && ( $drop_price_counter_enabled == 'yes' ) && isset( $drop_price_next_drop ) && $drop_price_next_drop && $product->is_purchasable() && $product->is_in_stock() && $this->dropprice_started( $product ) ) {
					echo '<div class="drop-price-counter">';
					echo '<p>' . sprintf( esc_html__( 'Next price %s in', 'drop_price' ), esc_html__( $drop_price_type, 'drop_price' ) ) . '</p>';
					echo '<div class="drop-price-time" id="countdown">
							<div class="drop-price-countdown" data-time="' . esc_attr( $drop_price_next_drop ) . '" data-productid="' . intval( $product_id ) . '" ></div>
						</div>';
					echo '</div>';
				}
			}
			/**
			 * Ajax refresh price
			 *
			 * Function for finishing auction with ajax when countdown is down to zero
			 *
			 * @access public
			 * @param  array
			 * @return string
			 *
			 */
			function ajax_refresh_price() {
				global $product;
				global $post;
				if ( $_POST['post_id'] ) {
					$post    = get_post( $_POST['post_id'] );
					$product = wc_get_product( $_POST['post_id'] );
					$this->set_new_price( $post );
					do_action( 'woocommerce_single_product_summary' );
				}
				die();
			}
			/**
			 * Cron action
			 *
			 * Checks for a valid request, check auctions and closes auction if finished
			 *
			 * @access public
			 * @param bool $url (default: false)
			 * @return void
			 *
			 */
			function drop_price_auctions_cron( $url = false ) {

				if ( ! isset( $_REQUEST['drop-price-cron'] ) ) {
					return;
				}

					update_option( 'Woocommerce_drop_price_cron_check', 'yes' );
					set_time_limit( 0 );
					ignore_user_abort( 1 );
					global $woocommerce;
					$args = array(
						'post_type'      => 'product',
						'posts_per_page' => '20',
						'meta_query'     => array(
							array(
								'key'     => '_drop_price_next_drop',
								'compare' => 'EXISTS',
							),
							'meta_key' => '_drop_price_next_drop',
							'orderby'  => 'meta_value',
							'order'    => 'ASC',
						),
					);
				for ( $i = 0; $i < 1; $i++ ) {
					$the_query = new WP_Query( $args );
					$time      = microtime( 1 );
					if ( $the_query->have_posts() ) {

						while ( $the_query->have_posts() ) :
							$the_query->the_post();
							$product      = wc_get_product( $the_query->post->ID );
							$product_type = method_exists( $product, 'get_type' ) ? $product->get_type() : $product->product_type;
							if ( $product_type == 'variable' ) {
								$this->set_new_price_variable( $the_query->post );
							} else {
								$this->set_new_price( $the_query->post );
							}

							endwhile;
					}
					$time = microtime( 1 ) - $time;
					//$i<3 and sleep(20-$time);
				}
				die();
			}
			/**
			 *
			 * Checks if drop price is started
			 *
			 * @access public
			 * @param int
			 * @return bolean
			 *
			 */
			function dropprice_started( $product ) {

				if ( is_object( $product ) ) {
					$product_id            = method_exists( $product, 'get_id' ) ? $product->get_id() : get_the_id();
					$drop_price_dates_from = get_post_meta( $product_id, '_drop_price_dates_from', true );
					return ( strtotime( $drop_price_dates_from ) < current_time( 'timestamp' ) );
				}
					return false;

			}

			/**
			 *
			 * Reset price when product is ordered
			 *
			 * @access public
			 * @param int
			 * @return void
			 *
			 */
			function reset_price_on_order( $order_id ) {
				if ( is_a( $order_id, 'WC_Order' ) ) {
					$order    = $order_id;
					$order_id = $order->get_id();
				} else {
					$order = wc_get_order( $order_id );
				}

				foreach ( $order->get_items() as $item ) {
					if ( $item->is_type( 'line_item' ) && $product = $item->get_product() ) {
						$product_id = $product->get_id();
						if ( get_post_meta( $product_id, '_drop_reset_on_order', true ) === 'yes' ) {
							$drop_price_time = (int) get_post_meta( $product_id, '_drop_price_time', true );
							update_post_meta( $product_id, '_drop_price_next_drop', ( current_time( 'timestamp' ) + ( $drop_price_time * 60 ) ) );
							update_post_meta( $product_id, '_drop_price_reset_time', current_time( 'timestamp' ) );

							$product_type = method_exists( $product, 'get_type' ) ? $product->get_type() : $product->product_type;
							if ( $product_type == 'variable' ) {
								$children       = $product->get_children();
								$mainid         = $product_id;
								$transient_name = 'wc_var_prices_' . $product_id;
								delete_transient( $transient_name );

								if ( $children ) {
									foreach ( $children as $key => $variable_id ) {

										$regular_price = (float) get_post_meta( $variable_id, '_regular_price', true );
										update_post_meta( $variable_id, '_price', $regular_price );
									}
								}
							} else {
								$regular_price = (float) get_post_meta( $product_id, '_regular_price', true );
								update_post_meta( $product_id, '_price', $regular_price );
								delete_post_meta( $product_id, '_sale_price' );
							}
						}
					}
				}

			}
			function add_product_modified_date_to_cart( $cart_item_data ) {
				$cart_item_data['drop_price_reset_time'] = get_post_meta( $cart_item_data['product_id'], '_drop_price_reset_time', true );
				return $cart_item_data;
			}

			function woocommerce_get_cart_item_from_session( $session_data, $values, $key ) {

				if ( $session_data['drop_price_reset_time'] !== get_post_meta( $session_data['product_id'], '_drop_price_reset_time', true ) ) {
					$product = wc_get_product( $session_data['product_id'] );
					wc_add_notice( sprintf( __( 'Product %1$s has been removed from your cart because its price have changed. Please add it to your cart again by <a href="%2$s">clicking here</a>.', 'woocommerce' ), $product->get_name(), $product->get_permalink() ), 'error' );
					return false;
				}
				return $session_data;
			}
		}
	}

	// Instantiate plugin class and add it to the set of globals.
	$woocommerce_auctions = new WooCommerce_drop_prices();

} else {
	add_action( 'admin_notices', 'drop_prices_error_notice' );
	function drop_prices_error_notice() {
		global $current_screen;
		if ( $current_screen->parent_base == 'plugins' ) {
			echo '<div class="error"><p>WooCommerce Drop Prices ' . esc_html__( 'requires <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="' . admin_url( 'plugin-install.php?tab=search&type=term&s=WooCommerce' ) . '" target="_blank">WooCommerce</a> first.', 'drop_price' ) . '</p></div>';
		}
	}
	$plugin = plugin_basename( __FILE__ );

	if ( is_plugin_active( $plugin ) ) {
		deactivate_plugins( $plugin );
	}
}
