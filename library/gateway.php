<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shopinext_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {

        $this->id = 'shopinext';
        $this->SNV = '1.0.4';
        $this->method_title = __('Pay with Shopinext', 'shopinext-for-woocommerce');
        $this->method_description = __('You can pay securely with a credit or debit card.', 'shopinext-for-woocommerce');
        $this->has_fields = true;
        $this->order_button_text = __('Pay by Credit/Debit Card', 'shopinext-for-woocommerce');
        $this->supports = array('products');
		$this->database = $GLOBALS['wpdb'];
        $this->init_form_fields();
        $this->init_settings();
		$this->supports = array(
			'products',
			'refunds'
		);

        $this->title        = __($this->get_option( 'title' ),'shopinext-for-woocommerce');
        $this->description  = __($this->get_option( 'description'),'shopinext-for-woocommerce');
        $this->enabled      = $this->get_option( 'enabled' );
        $this->icon         = plugins_url().SHOPINEXT_PLUGIN_NAME.'/image/cards.png?v=3';

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options',
        ) );
		
        add_action('woocommerce_receipt_shopinext', array($this, 'shopinext_loading_bar'));
		if(isset($_POST['responseCode'])){
			add_action('woocommerce_receipt_shopinext', array($this, 'shopinext_response'));
		} else {
			add_action('woocommerce_receipt_shopinext', array($this, 'shopinext_payment_form'));
		}

    }
	
	public function get_icon() {
		$icon_html = '<img src="' . $this->icon . '" alt="' . $this->method_title . '" style="height:60px;width:max-content!important;"  />';
		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}
	
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$user = wp_get_current_user();
		if(in_array( 'administrator', (array) $user->roles ) ) {
			
		}else{
			return new WP_Error( 'notpermitted', __( "Only administrators can process refunds.", "shopinext-for-woocommerce" ) );
		}

		if ( ! isset( $amount ) ) {
			return true;
		}

		if ( $amount < 1 ) {
			return new WP_Error( 'toolow', __( "The refund amount must be greater than 0.", "shopinext-for-woocommerce" ) );
		}

		$refund_result = $this->shopinext_refund_payment($order_id, $amount, '');
		if ( is_wp_error( $refund_result ) ) {
			return $refund_result;
		} else {
			$message = sprintf( __( 'Order %s has been successfully returned.', 'shopinext-for-woocommerce' ), $order_id );
		}

		return true;
	}
	
	public function shopinext_refund_payment( $order_id, $amount, $currency ) {
		$snid                      = $this->settings['shopinext_id'];
        $apikey                    = $this->settings['api_key'];
        $secret                    = $this->settings['secret_key'];
        $type                      = $this->settings['api_type'];
        $shopinext        		   = new Shopinext($snid,$apikey,$secret,$type,'');
		$order = wc_get_order( $order_id );
		if( empty( $currency ) ) {
			$currency = $order->get_currency();
		}
		$amount = str_replace( ',', '.', $amount);
		$record = $this->findPaymentId($order_id);
		if(!empty($record)) {
			$user = wp_get_current_user();
			$this->insertShopinextLog((object) array('islem'=>'İade İşlemi Başlatıldı','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.$record.' işlem için '.$amount.' '.$currency.' tutarlık iade isteği gönderildi.', 'kullanici'=>$user->user_login));
			$shopinext->refundOrder(array(
				'orderId' => $record,
				'amount' => $amount,
				'currency' => $currency
			));
			if($shopinext->output->responseCode == '00') {
				$this->insertShopinextLog((object) array('islem'=>'İade İşlemi Başarılı','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.$record.' işlem için '.$amount.' '.$currency.' tutarlık iade yapıldı.', 'kullanici'=>$user->user_login));
				do_action( 'shopinext_refund_after', $order_id );
				return true;
			} else {
				$get_transaction_error = isset( $shopinext->output->responseMsg ) ? $shopinext->output->responseMsg : __( 'Failed to connect to Shopinext.', 'shopinext-for-woocommerce' );
				$message = sprintf( __( 'The order refund process failed. Order ID: %s - Error: %s', 'shopinext-for-woocommerce' ), $order_id, $get_transaction_error );
				$this->insertShopinextLog((object) array('islem'=>'İade İşlemi Başarısız','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.$record.' işlem için '.$amount.' '.$currency.' tutarlık iade işlemi başarısız sonuçlandı. Hata: '.$get_transaction_error, 'kullanici'=>$user->user_login));
				return new WP_Error( 'shopinext_refund_error', $message);
			}
		}
	}
	
	public function admin_options() {

        if ( $this->is_currency_valid_for_use() ) {
            parent::admin_options();
			
			$pluginUrl = plugins_url().SHOPINEXT_PLUGIN_NAME;

			echo '<style scoped>@media (max-width:768px){.shopiLogo{position:fixed;bottom:0;top:auto!important;right:0!important}}</style><div class="shopiLogoContent"><div class="shopiLogo" style="clear:both;position:absolute;right: 50px;top:440px;display: flex;flex-direction: column;justify-content: center;"><img src='.esc_url($pluginUrl).'/image/shopinext-logo.svg style="width: 250px;margin-left: auto;"><p style="text-align:center;"><strong>Modül Versiyon: </strong>'.esc_html($this->SNV).'</p></div></div>';
        } else {
			echo '<div class="inline error"><p><strong>'.esc_html_e( 'Plugin Not Working', 'shopinext-for-woocommerce' ).'</strong>:'._e( 'The current exchange does not support the Shopinext module. You can only use TRY, USD, EUR, GBP.', 'shopinext-for-woocommerce' ).'<a href="'.get_admin_url().'admin.php?page=wc-settings">Buradan</a> güncelleyebilirsiniz.</p></div>';
        }
    }
	
    public function init_form_fields() {

        $this->form_fields = array(
			 'api_type' => array(
		        'title' 	=> __('API Type', 'shopinext-for-woocommerce'),
		        'type' 		=> 'select',
		        'required'  => true,
		        'default' 	=> 'responsive',
		        'options' 	=> 
		        	array(
		        	 'live'    => __('Live', 'shopinext-for-woocommerce'),
		        	 'test' => __('Test', 'shopinext-for-woocommerce')
		     )),
		     'shopinext_id' => array(
		         'title' => __('Shopinext ID', 'shopinext-for-woocommerce'),
		         'type' => 'text',
                 'description' =>  "Shopinext API Bilgileri sayfasından <b>Shopinext ID</b> bilginize erişebilirsiniz.",
		         'desc_tip' => true,
		     ),
		     'api_key' => array(
		         'title' => __('API Key', 'shopinext-for-woocommerce'),
		         'type' => 'text',
                 'description' =>  "Shopinext API Bilgileri sayfasından <b>API Anahtarı</b> bilginize erişebilirsiniz.",
		         'desc_tip' => true,
		     ),
		     'secret_key' => array(
		         'title' => __('Secret Key', 'shopinext-for-woocommerce'),
		         'type' => 'text',
                 'description' =>  "Shopinext API Bilgileri sayfasından <b>Gizli Anahtar</b> bilginize erişebilirsiniz.",
		         'desc_tip' => true,

		     ),
		    'title' => array(
		        'title' => __('Payment Option Text', 'shopinext-for-woocommerce'),
		        'type' => 'text',
		        'description' => __('This message will show to the user at the time of payment.', 'shopinext-for-woocommerce'),
                'default' => __('Secure Payment with Shopinext', 'shopinext-for-woocommerce'),
		        'desc_tip' => true,
		    ),
		    'description' => array(
		        'title' => __('Payment Option Description', 'shopinext-for-woocommerce'),
		        'type' => 'text',
		        'description' => __('This message will show to the user at the time of payment.', 'shopinext-for-woocommerce'),
                'default' => __('You can pay securely with a credit or debit card.','shopinext-for-woocommerce'),
		        'desc_tip' => true,
		    ),
		     'cargo_code' => array(
		         'title' => __('Cargo Code', 'shopinext-for-woocommerce'),
		         'type' => 'select',
		         'description' => __('The tracking number that is valid in contractual Shopinext courier companies is added to order notes and Transaction List table after the order.', 'shopinext-for-woocommerce'),
		         'default' => 'no',
		         'options' => array('yes' => __('Active', 'shopinext-for-woocommerce'), 
		         					'no' => __('Passive', 'shopinext-for-woocommerce'))
		    ),
		     'order_status' => array(
		         'title' => __('Order Status', 'shopinext-for-woocommerce'),
		         'type' => 'select',
		         'description' => __('Recommended, Default', 'shopinext-for-woocommerce'),
		         'default' => 'default',
		         'options' => array('default' => __('Default', 'shopinext-for-woocommerce'), 
		         					'pending' => __('Payment Awaiting', 'shopinext-for-woocommerce'),
		         					'processing' => __('Processing', 'shopinext-for-woocommerce'),
		         					'on-hold' => __('Waiting', 'shopinext-for-woocommerce'),
		         					'completed' => __('Completed', 'shopinext-for-woocommerce'),
		         					'cancelled' => __('Cancelled', 'shopinext-for-woocommerce'),
		         					'refunded' => __('Returned', 'shopinext-for-woocommerce'),
		         					'failed' => __('Failed', 'shopinext-for-woocommerce'))
		    ),
		    'enabled' => array(
		        'title' => __('Plugin Status', 'shopinext-for-woocommerce'),
		        'label' => __('Activate Shopinext checkout form', 'shopinext-for-woocommerce'),
		        'type' => 'checkbox',
		        'default' => 'yes'
		    ),
		);
    }

    public function process_payment($order_id) {

        $order = wc_get_order($order_id);

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );

    }

    public function shopinext_loading_bar() {

        echo '<style>.sk-cube8,.sk-cube7, .sk-cube4 {background-color:#00aeef!important;}.sk-cube-grid {   width: 40px;   height: 40px;   margin: 100px auto; }.sk-cube-grid .sk-cube {   width: 33%;   height: 33%;   background-color: #263a95;   float: left;   -webkit-animation: sk-cubeGridScaleDelay 1.3s infinite ease-in-out;           animation: sk-cubeGridScaleDelay 1.3s infinite ease-in-out; }.sk-cube-grid .sk-cube1 {   -webkit-animation-delay: 0.2s;           animation-delay: 0.2s; } .sk-cube-grid .sk-cube2 {   -webkit-animation-delay: 0.3s;           animation-delay: 0.3s; } .sk-cube-grid .sk-cube3 {   -webkit-animation-delay: 0.4s;           animation-delay: 0.4s; } .sk-cube-grid .sk-cube4 {   -webkit-animation-delay: 0.1s;           animation-delay: 0.1s; } .sk-cube-grid .sk-cube5 {   -webkit-animation-delay: 0.2s;           animation-delay: 0.2s; } .sk-cube-grid .sk-cube6 {   -webkit-animation-delay: 0.3s;           animation-delay: 0.3s; } .sk-cube-grid .sk-cube7 {   -webkit-animation-delay: 0s;           animation-delay: 0s; } .sk-cube-grid .sk-cube8 {   -webkit-animation-delay: 0.1s;           animation-delay: 0.1s; } .sk-cube-grid .sk-cube9 {   -webkit-animation-delay: 0.2s;           animation-delay: 0.2s; }  @-webkit-keyframes sk-cubeGridScaleDelay {   0%, 70%, 100% {     -webkit-transform: scale3D(1, 1, 1);             transform: scale3D(1, 1, 1);   } 35% {     -webkit-transform: scale3D(0, 0, 1);             transform: scale3D(0, 0, 1);   } }  @keyframes sk-cubeGridScaleDelay {   0%, 70%, 100% {     -webkit-transform: scale3D(1, 1, 1);             transform: scale3D(1, 1, 1);   } 35% {     -webkit-transform: scale3D(0, 0, 1);      transform: scale3D(0, 0, 1);   } }</style>';

        echo '<div id="loading"><div class="sk-cube-grid"><div class="sk-cube sk-cube1"></div><div class="sk-cube sk-cube2"></div><div class="sk-cube sk-cube3"></div><div class="sk-cube sk-cube4"></div><div class="sk-cube sk-cube5"></div> <div class="sk-cube sk-cube6"></div><div class="sk-cube sk-cube7"></div><div class="sk-cube sk-cube8"></div><div class="sk-cube sk-cube9"></div></div></div>';

    }

    private function setcookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly) {

        if (PHP_VERSION_ID < 70300) {

            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
        }
        else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => $secure,
                'httponly' => $httponly,
            ]);


        }
    }
	
	public function valueCheck($data) {

        if(!$data || $data == ' ') {

            $data = "NOT PROVIDED";
        }

        return $data;

	}
	
	public function is_currency_valid_for_use() {
        return in_array(get_woocommerce_currency(),array('TRY','USD','EUR','GBP'));
    }
	
	public function turnMoney($price) {

	    return floatval(number_format(str_replace(',','',$price),2,'.',''));
	}
	
	public function insertShopinextLog($localOrder) {

		$insertOrder = $this->database->insert( 
			$this->database->prefix.'shopinext_logs', 
			array( 
				'islem' 	=> $localOrder->islem, 
				'aciklama' 		=> $localOrder->aciklama, 
				'kullanici' 	=> $localOrder->kullanici
			), 
			array( 
				'%s', 
				'%s', 
				'%s'
			) 
		);

		return $insertOrder;

	}
	
	public function insertShopinextOrder($localOrder) {

		$insertOrder = $this->database->insert( 
			$this->database->prefix.'shopinext_order', 
			array( 
				'payment_id' 	=> $localOrder->paymentId, 
				'order_id' 		=> $localOrder->orderId, 
				'cargo_code' 	=> $localOrder->cargoCode, 
				'cargo_company' => $localOrder->cargoCompany, 
				'total_amount' 	=> $localOrder->totalAmount,
				'status' 		=> $localOrder->status
			), 
			array( 
				'%s', 
				'%s', 
				'%s', 
				'%s', 
				'%s', 
				'%s' 
			) 
		);

		return $insertOrder;

	}

	public function findPaymentId($orderId) {

		$table_name = $this->database->prefix .'shopinext_order';
		$fieldName  = 'payment_id';
		
		$query = $this->database->prepare("
				SELECT {$fieldName} FROM {$table_name} 
				 	WHERE  order_id = %s ORDER BY shopinext_order_id DESC LIMIT 1;
					",$orderId
				);

		$result = $this->database->get_col($query);


		if(isset($result[0])) {

			return $result[0];

		} else {

			return '';
		}
	
	}
	
	public function cropLocale($locale) {
		$locale = explode('_',$locale);
		$locale = $locale[0];
		return $locale;
	}

    private function versionCheck() {

        $phpVersion = phpversion();
        $requiredPhpVersion = 5.4;
        $locale = $this->cropLocale(get_locale());
        $warningMessage = 'Required PHP 5.4 and greater for Shopinext WooCommerce Payment Gateway';
        if($locale == 'tr') {
            $warningMessage = 'Shopinext WooCommerce eklentisini çalıştırabilmek için, PHP 5.4 veya üzeri versiyonları kullanmanız gerekmektedir. ';
        }

        if($phpVersion < $requiredPhpVersion) {
            echo esc_html($warningMessage);
            exit;
        }

        $wooCommerceVersion = WOOCOMMERCE_VERSION;
        $requiredWoocommerceVersion = 3.0;

        $warningMessage = 'Required WooCommerce 3.0 and greater for shopinext WooCommerce Payment Gateway';

        if($locale == 'tr') {
            $warningMessage = 'Shopinext WooCommerce eklentisini çalıştırabilmek için, WooCommerce 3.0 veya üzeri versiyonları kullanmanız gerekmektedir. ';
        }

        if($wooCommerceVersion < $requiredWoocommerceVersion) {
            echo esc_html($warningMessage);
            exit;
        }

        /* Required TLS */
        $tlsUrl = 'https://www.shopinext.com';
        $tlsVersion = get_option('SPNXTTLS');

        if(!$tlsVersion) {

            $result = $this->verifyTLS($tlsUrl);
            if($result) {
                add_option('SPNXTTLS',1.2,'','no');
                $tlsVersion = get_option('SPNXTTLS');
            }

        } elseif($tlsVersion != 1.2) {

            $result = $this->verifyTLS($tlsUrl);
            if($result) {
                update_option('SPNXTTLS',1.2);
                $tlsVersion = get_option('SPNXTTLS');
            }
        }


        $requiredTlsVersion = 1.2;

        $warningMessage = 'WARNING! Minimum TLS v1.2 will be supported after March 2018. Please upgrade your openssl version to minimum 1.0.2.';

        if($locale == 'tr') {
            $warningMessage = "UYARI! Ödeme formunuzu görüntüleyebilmeniz için, TLS versiyonunuzun minimum TLS v1.2 olması gerekmektedir. Lütfen servis sağlayıcınız ile görüşerek openssl versiyonunuzu minimum 1.0.2'e yükseltin.";
        }

        if($tlsVersion < $requiredTlsVersion) {
            echo esc_html($warningMessage);
            exit;
        }
    }

    private function verifyTLS($url) {
		
		$response = wp_remote_get($url);

        return $response;
    }
	
	public function shopinext_payment_form($order_id) {

        $wooCommerceCookieKey = 'wp_woocommerce_session_';
        foreach ($_COOKIE as $name => $value) {
            if (stripos($name,$wooCommerceCookieKey) === 0) {
                $wooCommerceCookieKey = sanitize_text_field($name);
            }
        }

        $setCookie = $this->setcookieSameSite($wooCommerceCookieKey,sanitize_text_field($_COOKIE[$wooCommerceCookieKey]), time() + 86400, "/", $_SERVER['SERVER_NAME'],true, true);

        $this->versionCheck();

        global $woocommerce;

        $order                     = new WC_Order($order_id);
        $cart                      = $woocommerce->cart->get_cart();
        $snid                      = $this->settings['shopinext_id'];
        $apikey                    = $this->settings['api_key'];
        $secret                    = $this->settings['secret_key'];
        $type                      = $this->settings['api_type'];
        $rand                      = rand(1,99999);
        $user                      = wp_get_current_user();
        $shopinextConversationId   = WC()->session->set('shopinextConversationId',$order_id);
        $shopinextCustomerId       = WC()->session->set('shopinextCustomerId',$user->ID);
        $totalAmount               = WC()->session->set('shopinextOrderTotalAmount',$order->get_total());
        $shopinext        		   = new Shopinext($snid,$apikey,$secret,$type,$order->get_checkout_payment_url(true));
		
		$price 		= 0;
		foreach ($cart as $key => $item) {
            if ($item['variation_id']){
                $productId = $item['variation_id'];
            }else{
                $productId = $item['product_id'];
            }
			$product 	= wc_get_product($productId);
			$realPrice = $product->get_sale_price();
			if(empty($product->get_sale_price())) {
				$realPrice = $product->get_price();
			}
			if($realPrice && $realPrice != '0' && $realPrice != '0.0' && $realPrice != '0.00' && $realPrice != false) {
				$shopinext->addProduct(array(
					'name'=>$product->get_name(),
					'price'=>$this->turnMoney(round($realPrice,2)),
					'quantity'=>$item['quantity']
				));
			}
			$price+= round($realPrice, 2)*$item['quantity'];
		}
		if($order->get_total_shipping() > 0) {
			$shopinext->addProduct(array(
				'name'=>__('Delivery Fee', 'shopinext-for-woocommerce'),
				'price'=>$this->turnMoney($order->get_total_shipping()),
				'quantity'=>1
			));
			$price += round($order->get_total_shipping(), 2);
		}
		if($order->get_total_tax() > 0 && $order->get_prices_include_tax() == false) {
			$shopinext->addProduct(array(
				'name'=>__('Tax Fee', 'shopinext-for-woocommerce'),
				'price'=>$this->turnMoney($order->get_total_tax()),
				'quantity'=>1
			));
			$price += round($order->get_total_tax(), 2);
		}
		if($order->get_discount_total() > 0) {
			$shopinext->addProduct(array(
				'name'=>__('Coupon Discount', 'shopinext-for-woocommerce'),
				'price'=>$this->turnMoney(-1*$order->get_discount_total()),
				'quantity'=>1
			));
			$price = $price - $order->get_discount_total();
		}
		foreach($order->get_fees() as $item_fee) {
			if($item_fee->get_amount() > 0) {
				$shopinext->addProduct(array(
					'name'=>$item_fee->get_name(),
					'price'=>$this->turnMoney($item_fee->get_amount()),
					'quantity'=>1
				));
				$price += round($item_fee->get_amount(), 2);
			}
		}
		$price = $this->turnMoney($price);
		if($price !== $this->turnMoney($order->get_total())) {
			$this->insertShopinextLog((object) array('islem'=>'Sepet Tutarı Uyuşmadı','aciklama'=>'Sepetin toplam tutarı ile hesaplanan sepet tutarı uyuşmadı. Sepet tutarını sadece görsel olarak etkileyecek eklentileri devredışı bıraktığınıza emin olun.', 'kullanici'=>$user->user_login));
			echo esc_html('Gönderilen tutar ile sepetteki ürünlerin tutarı eşleşmemektedir, lütfen kontrol edin.');
			return false;
		}
		$shopinext->createToken(array(
			'customerName' => $this->valueCheck($order->get_billing_first_name().' '.$order->get_billing_last_name()),
			'customerMail' => $this->valueCheck($order->get_billing_email()),
			'customerPhone' => $this->valueCheck($order->get_billing_phone()),
			'price' => $price,
			'currency' => $this->valueCheck($order->get_currency()),
			'shipCode' => ($this->settings['cargo_code'] == 'yes')?true:false,
			'customerCountry' => ($this->valueCheck(WC()->countries->countries[$order->get_shipping_country()]) !== 'NOT PROVIDED')?$this->valueCheck(WC()->countries->countries[$order->get_shipping_country()]):$this->valueCheck(WC()->countries->countries[$order->get_billing_country()]),
			'customerCity' => ($this->valueCheck(WC()->countries->states[$order->get_shipping_country()][$order->get_shipping_state()]) !== 'NOT PROVIDED')?$this->valueCheck(WC()->countries->states[$order->get_shipping_country()][$order->get_shipping_state()]):$this->valueCheck(WC()->countries->states[$order->get_billing_country()][$order->get_billing_state()]),
			'customerTown' => ($this->valueCheck($order->get_shipping_city()) !== 'NOT PROVIDED')?$this->valueCheck($order->get_shipping_city()):$this->valueCheck($order->get_billing_city()),
			'customerAddress' => ($this->valueCheck($order->get_shipping_address_1().$order->get_shipping_address_2()) !== 'NOT PROVIDED')?$this->valueCheck($order->get_shipping_address_1().$order->get_shipping_address_2()):$this->valueCheck($order->get_billing_address_1().$order->get_billing_address_2()),
			'woocommerceAppVersion' => $this->SNV
		));
		echo '<script>jQuery(window).on("load", function(){document.querySelector(".order_details").style.display="none";document.getElementById("loading").style.display="none";});</script>';
		$user = wp_get_current_user();
        if($shopinext->output->responseCode !== '00') {
			echo esc_html($shopinext->output->responseMsg);
			$this->insertShopinextLog((object) array('islem'=>'Token Oluşturulamadı','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.$shopinext->output->orderId.' işlem için token oluşturulamadı. Hata: '.$shopinext->output->responseMsg, 'kullanici'=>$user->user_login));
			return false;
		}
		$this->insertShopinextLog((object) array('islem'=>'Token Oluşturuldu','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.$shopinext->output->orderId.' işlem için token oluşturuldu. Token: '.$shopinext->output->sessionToken, 'kullanici'=>$user->user_login));
		$token = $shopinext->output->sessionToken;
		$spnxt = $shopinext->output->orderId;
        $shopinext->getPaymentForm(array(
			'sessionToken' => $shopinext->output->sessionToken
		));
        if($shopinext->output->responseCode == '00') {
			$this->insertShopinextLog((object) array('islem'=>'Ödeme Formu Alındı','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.$spnxt.' Token: '.$token.' işlem için ödeme formu alındı.', 'kullanici'=>$user->user_login));
			echo $shopinext->output->iframeData;
		} else {
			$this->insertShopinextLog((object) array('islem'=>'Ödeme Formu Alınamadı','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.$spnxt.' Token: '.$token.' işlem için ödeme formu alınamadı.', 'kullanici'=>$user->user_login));
			echo esc_html($shopinext->output->responseMsg);
			return false;
		}
    }
	
	public function shopinext_response($order_id) {

        global $woocommerce;
        try {
			$snid            = $this->settings['shopinext_id'];
			$apikey          = $this->settings['api_key'];
			$secret          = $this->settings['secret_key'];
			$type            = $this->settings['api_type'];
			$shopinext       = new Shopinext($snid,$apikey,$secret,$type,'');
            $order           = new WC_Order($order_id);
            if(!$order->get_payment_method_title()) {
                $order->set_payment_method_title("Shopinext Online Ödeme");
            }
			$shopinext->checkOrder(array('orderId'=>sanitize_text_field($_POST['orderId'])));
			if(sanitize_text_field($_POST['responseCode']) == '00' && $shopinext->output->responseCode == '00' && $shopinext->output->orderDetail->orderStatus == 'Sale') {
				$shopinextLocalOrder = new stdClass;
				$shopinextLocalOrder->paymentId     = !empty(sanitize_text_field($_POST['orderId'])) ? sanitize_text_field($_POST['orderId']) : '';
				$shopinextLocalOrder->orderId       = $order_id;
				$shopinextLocalOrder->cargoCode     = !empty(sanitize_text_field($_POST['cargoCode'])) ? sanitize_text_field($_POST['cargoCode']) : '';
				$shopinextLocalOrder->cargoCompany  = !empty(sanitize_text_field($_POST['cargoCompany'])) ? sanitize_text_field($_POST['cargoCompany']) : '';
				$shopinextLocalOrder->totalAmount   = !empty($order->get_total()) ? $order->get_total().' '.$order->get_currency() : '';
				$shopinextLocalOrder->status        = !empty(sanitize_text_field($_POST['responseMsg'])) ? sanitize_text_field($_POST['responseMsg']) : '';
				$orderMessage = 'Ödeme ID: '.$order_id.'<br />Shopinext Sipariş Numarası: '.sanitize_text_field($_POST['orderId']);
				if(!empty(sanitize_text_field($_POST['cargoCode']))) {
					$orderMessage .= '<br />Kargo Kodu: '.sanitize_text_field($_POST['cargoCode']).'<br />Kargo Firması: '.sanitize_text_field($_POST['cargoCompany']);
				}
				$order->add_order_note($orderMessage,0,true);
				if($type == 'test') {
					$orderMessage = '<strong><p style="color:red">TEST ÖDEMESİ</a></strong>';
					$order->add_order_note($orderMessage,0,true);
				}
				$order->payment_complete();
				$orderStatus = $this->settings['order_status'];
				if($orderStatus != 'default' && !empty($orderStatus)) {
					$order->update_status($orderStatus);
				}
				$record = $this->findPaymentId($order_id);
				if(empty($record)) {
					$this->insertShopinextOrder($shopinextLocalOrder);
					$user = wp_get_current_user();
					$this->insertShopinextLog((object) array('islem'=>'Ödeme Tamamlandı','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.sanitize_text_field($_POST['orderId']).' işlem başarılı tamamlandı.', 'kullanici'=>$user->user_login));
				}
				$woocommerce->cart->empty_cart();
				$checkoutOrderUrl = $order->get_checkout_order_received_url();
				$redirectUrl = add_query_arg(array('msg' => 'Thank You', 'type' => 'woocommerce-message'), $checkoutOrderUrl);
				return wp_redirect($redirectUrl);
			} else {
				$errorMessage = !empty(sanitize_text_field($_POST['responseMsg'])) ? sanitize_text_field($_POST['responseMsg']) : 'Başarısız';
				throw new \Exception($errorMessage);
			}

        } catch (Exception $e) {
            $respMsg = $e->getMessage();
            $order = new WC_Order($order_id);
            $order->update_status('failed');
            $order->add_order_note($respMsg,0,true);
			$user = wp_get_current_user();
			$this->insertShopinextLog((object) array('islem'=>'Ödeme Başarısız','aciklama'=>'Ödeme ID: '.$order_id.' Shopinext Sipariş Numarası: '.sanitize_text_field($_POST['orderId']).' işlem başarısız sonuçlandı. Hata: '.$respMsg, 'kullanici'=>$user->user_login));
            wc_add_notice(__($respMsg, 'woocommerce-message'), 'error');
            $redirectUrl = $woocommerce->cart->get_cart_url();
            return wp_redirect($redirectUrl);
        }

    }
}

