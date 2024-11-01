<?php
/**
 * Plugin Name: Shopinext Online Payment
 * Plugin URI: https://wordpress.org/plugins/shopinext-for-woocommerce
 * Description: Shopinext Payment Module specially developed for WooCommerce
 * Version: 1.0.4
 * Author: Shopinext
 * Author URI: https://www.shopinext.com
 * Text Domain: shopinext-for-woocommerce
 */
 
define('SHOPINEXT_PATH',untrailingslashit( plugin_dir_path( __FILE__ )));
define('SHOPINEXT_URL',untrailingslashit( plugin_dir_url( __FILE__ )));
define('SHOPINEXT_LANG_PATH',plugin_basename(dirname(__FILE__)) . '/languages/');
define('SHOPINEXT_PLUGIN_NAME','/'.plugin_basename(dirname(__FILE__)));
if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'Shopinext_For_WooCommerce' ) ) {
    class Shopinext_For_WooCommerce {
		
		protected static $instance;
       
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        protected function __construct() {
			global $typenow;
            add_action('plugins_loaded', array($this,'init'));
			add_action( 'admin_menu', array(&$this,'shopinext_list_menu') );
        }
		
		function shopinext_list_menu()
	    {
			$transaction = add_menu_page( 
				__( 'Transaction List', 'shopinext-for-woocommerce' ),
				__( 'Transaction List', 'shopinext-for-woocommerce' ),
				'manage_options',
				'shopinext_transaction_list',
				array(&$this,'shopinext_order_list'),
				SHOPINEXT_URL.'/image/favicon.jpg',
				6
			); 
			$log = add_menu_page( 
				__( 'Transaction Logs', 'shopinext-for-woocommerce' ),
				__( 'Transaction Logs', 'shopinext-for-woocommerce' ),
				'manage_options',
				'shopinext_logs',
				array(&$this,'shopinext_log'),
				SHOPINEXT_URL.'/image/favicon.jpg',
				6
			);
		    add_action( 'admin_print_styles-' . $transaction, array($this,'shopinext_enqueue_styles') );
		    add_action( 'admin_print_styles-' . $log, array($this,'shopinext_enqueue_styles') );
			add_action( 'admin_print_scripts-' . $transaction, array($this,'shopinext_enqueue_scripts') );
			add_action( 'admin_print_scripts-' . $log, array($this,'shopinext_enqueue_scripts') );
			add_action( 'admin_print_scripts-' . $transaction, array($this,'shopinext_transaction_addline') );
			add_action( 'admin_print_scripts-' . $log, array($this,'shopinext_log_addline') );
	    }
		
		function shopinext_enqueue_scripts() {
		   wp_enqueue_script( 'shopinext-datatable', SHOPINEXT_URL.'/assets/js/jquery.dataTables.min.js', array(), '1.0' );
		   wp_enqueue_script( 'shopinext-datatable-bootstrap', SHOPINEXT_URL.'/assets/js/dataTables.bootstrap.min.js', array(), '1.0' );
		}
		
		function shopinext_enqueue_styles() {
		   wp_enqueue_style( 'shopinext-bootstrap', SHOPINEXT_URL.'/assets/css/bootstrap.min.css', array(), '1.0' );
		   wp_enqueue_style( 'shopinext-datatable-bootstrap', SHOPINEXT_URL.'/assets/css/dataTables.bootstrap.min.css', array(), '1.0' );
		}
		
		function shopinext_transaction_addline () {
		   wp_add_inline_script( 'shopinext-datatable', 'jQuery(document).ready(function () {document.title = "Shopinext Transaction List";jQuery("#transaction").DataTable({dom: \'<"dt-buttons"Bf><"clear">lirtp\',paging: true,autoWidth: true,order: [0, "desc"],buttons: ["csvHtml5","excelHtml5","print"]});});' );
		}
		
		function shopinext_log_addline () {
		   wp_add_inline_script( 'shopinext-datatable', 'jQuery(document).ready(function () {document.title = "Shopinext Transaction List";jQuery("#transaction").DataTable({dom: \'<"dt-buttons"Bf><"clear">lirtp\',paging: true,autoWidth: true,order: [0, "desc"],buttons: ["csvHtml5","excelHtml5","print"]});});' );
		}
		
	    function shopinext_order_list(){
			global $wpdb;
			echo esc_html_e( 'Shopinext Transaction List', 'shopinext-for-woocommerce' );
			$spnxtorders = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."shopinext_order" );
			$currency = get_woocommerce_currency_symbol();
			$html = '<table id="transaction" class="wp-list-table widefat fixed striped table-view-list">';
			$html .= '<thead><tr>';
			$html .= '<th style="width:30px;">ID</th>';
			$html .= '<th>Shopinext Numarası</th>';
			$html .= '<th>Sipariş No</th>';
			$html .= '<th>Kargo Kodu</th>';
			$html .= '<th>Kargo Firma</th>';
			$html .= '<th>Tutar</th>';
			$html .= '<th style="width:300px;">Yanıt</th>';
			$html .= '<th>Tarih</th>';
			$html .= '</tr></thead><tbody>';
			if(count($spnxtorders) > 0){
				foreach($spnxtorders as $spnxt){
					$html .= '<tr>';
					$html .= '<td>'.esc_html($spnxt->shopinext_order_id).'</td>';
					$html .= '<td>'.esc_html($spnxt->payment_id).'</td>';
					$html .= '<td>'.esc_html($spnxt->order_id).'</td>';
					$html .= '<td>'.esc_html($spnxt->cargo_code).'</td>';
					$html .= '<td>'.esc_html($spnxt->cargo_company).'</td>';
					$html .= '<td>'.esc_html($spnxt->total_amount).'</td>';
					$html .= '<td>'.esc_html($spnxt->status).'</td>';
					$html .= '<td>'.esc_html($spnxt->created_at).'</td>';
					$html .= '</tr>';
				}
					$html .= '</tbody></table>';
			}else{
				echo esc_html("Shopinext ile yapılan ödeme bulunamadı.");
			}
			$allowed_html = array(
				'table'     => array(
					'id' => 'transaction',
					'class' => 'wp-list-table widefat fixed striped table-view-list'
					),
				'thead'     => array(),
				'tbody'     => array(),
				'tr'     => array(),
				'th'     => array(),
				'td'     => array()
			);
			echo wp_kses( $html, $allowed_html );
		}
		
	    function shopinext_log(){
			global $wpdb;
			echo esc_html_e( 'Shopinext Transaction Logs', 'shopinext-for-woocommerce' );
			$spnxtorders = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."shopinext_logs" );
			$currency = get_woocommerce_currency_symbol();
			if(count($spnxtorders) > 0){
			$html = '<table id="transaction" class="wp-list-table widefat fixed striped table-view-list">';
			$html .= '<thead><tr>';
			$html .= '<th style="width:30px;">ID</th>';
			$html .= '<th>İşlem</th>';
			$html .= '<th style="width:600px;">Açıklama</th>';
			$html .= '<th>Kullanıcı</th>';
			$html .= '<th>Tarih</th>';
			$html .= '</tr></thead><tbody>';
				foreach($spnxtorders as $spnxt){
					$html .= '<tr>';
					$html .= '<td>'.esc_html($spnxt->id).'</td>';
					$html .= '<td>'.esc_html($spnxt->islem).'</td>';
					$html .= '<td>'.esc_html($spnxt->aciklama).'</td>';
					$html .= '<td>'.esc_html($spnxt->kullanici).'</td>';
					$html .= '<td>'.esc_html($spnxt->created_at).'</td>';
					$html .= '</tr>';
				}
					$html .= '</tbody></table>';
			}else{
				echo esc_html("Shopinext log kaydı bulunamadı.");
			}
			$allowed_html = array(
				'table'     => array(
					'id' => 'transaction',
					'class' => 'wp-list-table widefat fixed striped table-view-list'
					),
				'thead'     => array(),
				'tbody'     => array(),
				'tr'     => array(),
				'th'     => array(),
				'td'     => array()
			);
			echo wp_kses( $html, $allowed_html );
		}
			
		public static function ShopinextActive() {
			global $wpdb;
            $table_name = $wpdb->prefix . 'shopinext_order';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                shopinext_order_id int(11) NOT NULL AUTO_INCREMENT,
                payment_id  TEXT NOT NULL,
                order_id TEXT NOT NULL,
                cargo_code TEXT NOT NULL,
                cargo_company TEXT NOT NULL,
                total_amount TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at  timestamp DEFAULT current_timestamp,
              PRIMARY KEY (shopinext_order_id)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta($sql);
            $table_name = $wpdb->prefix . 'shopinext_logs';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                islem  TEXT NOT NULL,
                aciklama TEXT NOT NULL,
                kullanici TEXT NOT NULL,
                created_at  timestamp DEFAULT current_timestamp,
              PRIMARY KEY (id)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta($sql);
        }

        public static function ShopinextDeactive() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'shopinext_order';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "DROP TABLE IF EXISTS $table_name;";
            $wpdb->query($sql);
            $table_name = $wpdb->prefix . 'shopinext_logs';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "DROP TABLE IF EXISTS $table_name;";
            $wpdb->query($sql);
            flush_rewrite_rules();
        }
		
		public function init() {
            $this->ShopinextPaymentGateway();
        }

		public function ShopinextPaymentGateway() {

            if ( ! class_exists('WC_Payment_Gateway')) {
                return;
            }

            include_once SHOPINEXT_PATH . '/library/shopinext.php';
            include_once SHOPINEXT_PATH . '/library/gateway.php';
			
			add_filter('woocommerce_payment_gateways', array($this,'Shopinext_Payment_Gateway'));
		
            if (is_admin()){
                add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),
                    array($this,
                        'actionLinks' ) );
            }
        }

		function Shopinext_Payment_Gateway( $methods ) {
			$methods[] = 'Shopinext_Payment_Gateway';
			return $methods;
		}
		
		public function actionLinks( $links ) {
            $custom_links   = array();
            $custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=shopinext' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
            $custom_links[] = '<a target="_blank" href="https://www.shopinext.com/api/v2">' . __( 'Docs', 'woocommerce' ) . '</a>';
            $custom_links[] = '<a target="_blank" href="https://www.shopinext.com/iletisim">' . __( 'Support', 'shopinext-for-woocommerce' ) . '</a>';
            return array_merge( $custom_links, $links );
        }

        public static function installLanguage() {

          load_plugin_textdomain('shopinext-for-woocommerce',false,SHOPINEXT_LANG_PATH);
        
        }
		
	}
Shopinext_For_WooCommerce::get_instance();
add_action('plugins_loaded',array('Shopinext_For_WooCommerce','installLanguage'));
add_action('plugins_loaded',array('Shopinext_For_WooCommerce','ShopinextActive'));
register_deactivation_hook(__FILE__,array('Shopinext_For_WooCommerce','ShopinextDeactive'));
}