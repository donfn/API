<?php

/*
Plugin Name: Viva Payments | Simple Checkout
Description: Simple Checkout from Viva Payments, allows merchants to accept payments natively on their ecommerce store. The card details are harvested inside of an elegant iframe pop-up, which means that the solution remains always up-to-date with standards such as PCI-DSS and 3D-Secure.
Version: 1.0
Author: DONFN
Author URI: https://www.donfn.space
License:           GPL-3.0+
License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'encryption.php';
add_action('plugins_loaded', 'woocommerce_VivaPayments_init', 0);

function woocommerce_VivaPayments_init()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Gateway class
     */
    class WC_simplecheckout_vivapayments_gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            global $woocommerce;

            $this->id = 'simplecheckout_vivapayments_gateway';
            $this->icon = apply_filters('woocommerce_vivaway_icon', plugins_url('img/pay-via-VivaPayments.png', __FILE__));
            $this->has_fields = false;
            $this->liveurl = 'https://www.vivapayments.com/';
            $this->testurl = 'https://demo.vivapayments.com/';
            $this->notify_url = WC()->api_request_url('WC_simplecheckout_vivapayments_gateway');
            $this->method_title = 'Viva Payments | Simple Checkout';
            $this->method_description = __('Simple Checkout from Viva Payments, allows merchants to accept payments natively on their ecommerce store. The card details are harvested inside of an elegant iframe pop-up, which means that the solution remains always up-to-date with standards such as PCI-DSS and 3D-Secure.', 'viva-woocommerce-payment-gateway');

            $this->redirect_page_id = $this->get_option('redirect_page_id');
            // Load the form fields.
            $this->init_form_fields();


            // Load the settings.
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->VivaPaymentsMerchantId = $this->get_option('VivaPaymentsMerchantId');
            $this->VivaPaymentsPublicKey = $this->get_option('VivaPaymentsPublicKey');
            $this->VivaPaymentsDescription = $this->get_option('VivaPaymentsDescription');
            $this->VivaPaymentsLanguage = $this->get_option('language');
            $this->VivaPaymentsAPIKey = $this->get_option('VivaPaymentsAPIKey');
            $this->VivaPaymentsCodeId = $this->get_option('VivaPaymentsCodeId');
            $this->mode = $this->get_option('mode');
            $this->VivaPaymentsDisableWallet = $this->get_option('VivaPaymentsDisableWallet');
			$this->VivaPaymentsTransactionType= $this->get_option('VivaPaymentsTransactionType');
			
			if ($this->mode == "yes") {
				$this->requesturl = 'https://demo.vivapayments.com'; // demo environment URL
			} else {
				$this->requesturl = 'https://www.vivapayments.com';
			}
			if($this->VivaPaymentsDisableWallet == 'yes'){
				$this->VivaPaymentsDisableWallet = 'true';
			}else{
				$this->VivaPaymentsDisableWallet = 'false';
			}
				
            //Actions
            add_action('woocommerce_receipt_simplecheckout_vivapayments_gateway', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_simplecheckout_vivapayments_gateway', array($this, 'check_VivaPayments_response'));
        }

        /**
         * Admin Panel Options
         * */
        public function admin_options()
        {
            echo '<h3>' . __('<img src="https://camo.githubusercontent.com/632c49480ddf711ac664022ea99cc1daba6a0636/68747470733a2f2f6c616e64696e672e7669766177616c6c65742e636f6d2f68732d66732f68756266732f5669766125323057616c6c65742d6a756c792d323031382d312e706e67" width="50px" height="auto"> | Simple Checkout', 'viva-woocommerce-payment-gateway') . '</h3>';
            echo '<p>' . __('Your Public Key, Merchant ID and API Key, can be found in your Viva Wallet Dashboard, under Settings > API Access. <br>Also, you can change the logo that appears in the top of the pop-up, under Sales > Online Payments > Website / Apps > Default.', 'viva-woocommerce-payment-gateway') . '</p><br>';
			echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
			echo '<p>________________________________________<br><br>This a preview of the payment button and pop-up: <br>
                        <br> <button type="button" id="SimpleCheckoutButton"
                             data-vp-publickey="'.$this->VivaPaymentsPublicKey.'"
                             data-vp-baseurl="'.$this->requesturl.'"
                             data-vp-lang="'.$this->VivaPaymentsLanguage.'"
                             data-vp-expandcard="true"
                             data-vp-amount="100"
							 data-vp-sourcecode="Default"
							 data-vp-preauth="'.$this->VivaPaymentsTransactionType.'"
                             data-vp-merchantref="Admin Panel Test Charge"
							 data-vp-disablewallet="'.$this->VivaPaymentsDisableWallet.'"
                             data-vp-description="'.$this->VivaPaymentsDescription.'">
                         </button>
						 <br>________________________________________
					<script>
					$ = jQuery;
					</script>
                    ';
			wp_enqueue_script('simplecheckout.js', $this->requesturl.'/web/checkout/js#asyncload', array('jquery'));

        }

        /**
         * Initialise Gateway Settings Form Fields
         * */
        function init_form_fields()
        {
            $this->form_fields = array(
                'title' => array(
                    'title' => __('Title', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('', 'viva-woocommerce-payment-gateway'),
                    'desc_tip' => false,
                    'default' => __('Pay via Card', 'viva-woocommerce-payment-gateway'),
                ),
                'description' => array(
                    'title' => __('Description', 'viva-woocommerce-payment-gateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'viva-woocommerce-payment-gateway'),
                    'default' => __('Pay with your bank card. We accept Visa, Mastercard, Maestro, AMEX, Dinners.', 'viva-woocommerce-payment-gateway'),
                ),
				    'language' => array(
                    'title' => __('Language', 'viva-woocommerce-payment-gateway'),
                    'type' => 'select',
					'options' => array(
						'el' => 'Greek',
						'en' => 'English',
						'ro' => 'Romanian'
					),
                    'description' => __('', 'viva-woocommerce-payment-gateway'),
                    'desc_tip' => false,
                    'default' => __('en', 'viva-woocommerce-payment-gateway'),
                ),
                'VivaPaymentsMerchantId' => array(
                    'title' => __('Merchant ID', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter your Merchant ID. This can be sourced from your account page, when you login on Viva Payments.', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => false,
                ),
                'VivaPaymentsAPIKey' => array(
                    'title' => __('API key', 'viva-woocommerce-payment-gateway'),
                    'type' => 'password',
                    'description' => __('Enter your API key. This can be sourced from your account page, when you login on Viva Payments.', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => false,
                ),
                'VivaPaymentsPublicKey' => array(
                    'title' => __('Public Key', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('Enter your Public Key. This can be sourced from your account page, when you login on Viva Payments. ', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => false,
                    
                ),  
                'mode' => array(
                    'title' => __('Demo Mode', 'viva-woocommerce-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Demo mode', 'viva-woocommerce-payment-gateway'),
                    'default' => 'yes',
                    'description' => __('You can use Demo mode to execute fake transactions and test your setup. Visit https://demo.vivapayments.com .<br> You can use 4111 1111 1111 1111 with 111 as CVV and any expiration date, to trigger a success.', 'viva-woocommerce-payment-gateway'),
                ),
                'VivaPaymentsDescription' => array(
                    'title' => __('Pop-up Description', 'viva-woocommerce-payment-gateway'),
                    'type' => 'text',
                    'description' => __('This is the description of the payment that appears in the popup. For example, you can put something like "JOHN DOE FLORIST, GR"', 'viva-woocommerce-payment-gateway'),
                    'default' => '',
                    'desc_tip' => false,
                ),
				'VivaPaymentsTransactionType' => array(
                    'title' => __('Transaction Type', 'viva-woocommerce-payment-gateway'),
                    'type' => 'select',
					'options' => array(
						'false' => 'Charge',
						'true' => 'Authorization'
					),
                    'description' => __('Authorization reserves the amount from the client\'s card and lets the merchant a (usually) 20 day window to decide whether to cancel or settle the transaction.<br>If you are not sure, select \'Charge\'.', 'viva-woocommerce-payment-gateway'),
                    'desc_tip' => false,
                    'default' => __('false', 'viva-woocommerce-payment-gateway'),
                ),
				    'VivaPaymentsDisableWallet' => array(
                    'title' => __('Disable "Pay with VivaWallet"', 'viva-woocommerce-payment-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Disable "Pay with VivaWallet"', 'viva-woocommerce-payment-gateway'),
                    'default' => 'false',
                    'description' => __('If your target audience is not in Greece -where VivaWallet is somewhat popular-,<br>"Pay with VivaWallet" may confuse your customers and thus should be disabled.', 'viva-woocommerce-payment-gateway'),
                ),
            );
        }

        public function get_option($key, $empty_value = null)
        {
            $option_value = parent::get_option($key, $empty_value);
            if ($key == 'VivaPaymentsAPIKey') {
                $decrypted = WC_Payment_Gateway_KeyEncryption_Viva::decrypt(base64_decode($option_value), substr(NONCE_KEY, 0, 32));
                $option_value = $decrypted;
            }
            return $option_value;
        }

        public function validate_VivaPaymentsAPIKey_field($key, $value)
        {
            $encrypted = WC_Payment_Gateway_KeyEncryption_Viva::encrypt($value, substr(NONCE_KEY, 0, 32));
            return base64_encode($encrypted);
        }

        function simplecheckout_get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) {
                $page_list[] = $title;
            }

            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            $page_list[-1] = __('Thank you page', 'viva-woocommerce-payment-gateway');
            return $page_list;
        }


        /**
         * Generate the VivaPayments Payment button link
         * */
        function generate_VivaPayments_form($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);
			$result = '<br>';

            $PublicKey = $this->VivaPaymentsPublicKey;
            if($PublicKey === null) return '<b> ERROR: THE PUBLIC KEY IS NOT SET. CHECK THE PLUGIN SETTINGS!</b>' ;
            
            $Amount = $order->get_total() * 100; // Amount in cents
			

			$result .= '<p><form id="" action="" method="post">
                         <button type="button" id="SimpleCheckoutButton"
                             data-vp-publickey="'.$PublicKey.'"
                             data-vp-baseurl="'.$this->requesturl.'"
                             data-vp-lang="'.$this->VivaPaymentsLanguage.'"
                             data-vp-expandcard="true"
                             data-vp-amount="'.$Amount.'"
							 data-vp-sourcecode="Default"
							 data-vp-preauth="'.$this->VivaPaymentsTransactionType.'"
                             data-vp-merchantref="WooCommerce Order ID: '.$order_id.'"
							 data-vp-disablewallet="'.$this->VivaPaymentsDisableWallet.'"
                             data-vp-description="'.$this->VivaPaymentsDescription.'">
                         </button>
                    </form>
					<script>
					$ = jQuery;
					window.onload = function() {
						setTimeout(function(){
							$(\'#SimpleCheckoutButton\').click();
							},1000);
					};
					</script>
                    ';
					
				return $result;

        }

        /**
         * Process the payment and return the result
         * */
        /**/
        function process_payment($order_id)
        {

            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true),
            );
        }

        /**
         * Output for the order received page.
         * */
        function receipt_page($order_id){

            if(isset($_POST['vivaWalletToken']))
            {

                    $order = new WC_Order($order_id);
					
					//API Authentication
                    $MerchantId = $this->VivaPaymentsMerchantId;
                    $APIKey = $this->VivaPaymentsAPIKey;
					
                    // Call Viva Payment's API
                    $session = curl_init($this->requesturl.'/api/transactions');
                    curl_setopt( $session, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                    curl_setopt($session, CURLOPT_POSTFIELDS, json_encode(array("PaymentToken" => $_POST['vivaWalletToken'])));
                    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($session, CURLOPT_USERPWD, htmlspecialchars_decode($MerchantId) . ':' . htmlspecialchars_decode($APIKey));
                    $response = curl_exec($session);
                    curl_close($session);
                    try {
                        if (is_object(json_decode($response))) {
                            $resultObj = json_decode($response);
                        } else {
                            return __("Wrong Merchant credentials or API unavailable", 'viva-woocommerce-payment-gateway');
                        }
                    } catch (Exception $e) {
                        // echo $e->getMessage();
                    }
                    
                if ($resultObj->ErrorCode == 0) {

                    $status = $resultObj->StatusId;
                    if (isset($status)) {

                        if ($status == "F") {
                            $trans_id = $resultObj->TransactionId; //Get the transaction id from response
                            if ($order->status == 'processing') {

                                //Add customer order note
                                $order->add_order_note(__('Viva Payments Transaction Reference: ', 'viva-woocommerce-payment-gateway') . $trans_id, 1);

                                // Reduce stock levels
                                $order->reduce_order_stock();

                                // Empty cart
                                WC()->cart->empty_cart();


                            } else {

                                if ($order->has_downloadable_item()) {

                                    //Update order status
                                    $order->update_status('completed');

                                    //Add customer order note
                                    $order->add_order_note(__('Viva Payments Transaction Reference: ', 'viva-woocommerce-payment-gateway') . $trans_id, 1);


                                } else {

                                    //Update order status
                                    $order->update_status('processing');

                                    //Add customer order note
                                    $order->add_order_note(__('Viva Payments Transaction Reference: ', 'viva-woocommerce-payment-gateway') . $trans_id, 1);

                                }


                                // Reduce stock levels
                                $order->reduce_order_stock();

                                // Empty cart
                                WC()->cart->empty_cart();
							  if ( $order ) {
								$return_url = $order->get_checkout_order_received_url();
							} else {
								$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
							}
              
								wp_redirect($return_url);
                            }
                        } else {
							 echo ("<script LANGUAGE='JavaScript'>
									window.alert('Payment Failed');
									history.back();
									</script>");
							}
                    }
                }else{
							 echo ("<script LANGUAGE='JavaScript'>
									window.alert('Payment Failed');
									history.back();
									</script>");
				}

                exit;
            }
            
            else
            {

				
                echo $this->generate_VivaPayments_form($order_id);
				wp_enqueue_script('simplecheckout.js', $this->requesturl.'/web/checkout/js#asyncload', array('jquery'));
				
            }
        }


        function generic_add_meta($orderid, $key, $value)
        {
            $order = new WC_Order($orderid);
            if (method_exists($order, 'add_meta_data') && method_exists($order, 'save_meta_data')) {
                $order->add_meta_data($key, $value, true);
                $order->save_meta_data();
            } else {
                update_post_meta($orderid, $key, $value);
            }
        }

    }

    function simplecheckout_VivaPayments_message()
    {
        $order_id = absint(get_query_var('order-received'));
        $order = new WC_Order($order_id);
        if (method_exists($order, 'get_payment_method')) {
            $payment_method = $order->get_payment_method();
        } else {
            $payment_method = $order->payment_method;
        }

        if (is_order_received_page() && ('simplecheckout_vivapayments_gateway' == $payment_method)) {

            $VivaPayments_message = ''; //get_post_meta($order_id, '_simplecheckout_VivaPayments_message', true);
            if (method_exists($order, 'get_meta')) {
                $VivaPayments_message = $order->get_meta('_simplecheckout_VivaPayments_message', true);
            } else {
                $VivaPayments_message = get_post_meta($order_id, '_simplecheckout_VivaPayments_message', true);
            }
            if (!empty($VivaPayments_message)) {
                $message = $VivaPayments_message['message'];
                $message_type = $VivaPayments_message['message_type'];

                //delete_post_meta($order_id, '_simplecheckout_VivaPayments_message');
                if (method_exists($order, 'delete_meta_data')) {
                    $order->delete_meta_data('_simplecheckout_VivaPayments_message');
                    $order->save_meta_data();
                } else {
                    delete_post_meta($order_id, '_simplecheckout_VivaPayments_message');
                }

                wc_add_notice($message, $message_type);
            }
        }
    }

    add_action('wp', 'simplecheckout_VivaPayments_message');

    /**
     * Add VivaPayments Gateway to WC
     * */
    function woocommerce_add_VivaPayments_gateway($methods)
    {
        $methods[] = 'WC_simplecheckout_vivapayments_gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_VivaPayments_gateway');

    /**
     * Add Settings link to the plugin entry in the plugins menu for WC below 2.1
     * */
    if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {

        add_filter('plugin_action_links', 'simplecheckout_VivaPayments_plugin_action_links', 10, 2);

        function simplecheckout_VivaPayments_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_simplecheckout_vivapayments_gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }
    /**
     * Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
     * */else {
        add_filter('plugin_action_links', 'simplecheckout_VivaPayments_plugin_action_links', 10, 2);

        function simplecheckout_VivaPayments_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_simplecheckout_vivapayments_gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }

    }
}
