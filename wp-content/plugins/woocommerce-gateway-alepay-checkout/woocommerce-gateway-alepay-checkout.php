<?php

/**
 * Plugin Name: Alepay payment gateway for WooCommerce
 * Plugin URI: https://www.nganluong.vn/
 * Description: Plugin tích hợp Alepay được build trên WooCommerce 3.x
 * Version: 3.1
 * Author: Đức LM(0948389111) - Thanh NA (0968381829)
 * Author URI: http://www.webckk.com/
 */
include_once(plugin_dir_path(__FILE__) . 'Lib/Alepay.php');
ini_set('display_errors', true);
add_action('plugins_loaded', 'woocommerce_payment_nganluong_init', 0);
add_action('parse_request', array('WC_Gateway_Alepay', 'alepay_return_handler'));
// Hook in
add_filter('woocommerce_checkout_fields', 'custom_override_checkout_fields');
// Our hooked in function - $fields is passed via the filter!
function custom_override_checkout_fields($fields)
{
//    $fields['billing']['billing_first_name']['name'] = 'billing_full_name';
    return $fields;
}

function woocommerce_payment_nganluong_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Gateway_Alepay extends WC_Payment_Gateway
    {

        // URL checkout của nganluong.vn - Checkout URL for Ngan Luong
        // Mã merchant site code
        private $merchant_site_code;
        // Mật khẩu bảo mật - Secure password
        private $secure_pass;
        // Debug parameters
        private $debug_params;
        private $debug_md5;
        private $merchant_id;
        private $status_order;
        private $api_key;
        private $encrypt_key;
        private $checksum_key;
        private $orderCode;

        function __construct()
        {
            $this->icon = @$this->settings['icon']; // Icon URL
            $this->id = 'alepay';
            $this->method_title = 'Alepay';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->api_key = $this->settings['api_key'];
            $this->checksum_key = $this->settings['checksum_key'];
            $this->encrypt_key = $this->settings['encrypt_key'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->status_order = $this->settings['status_order'];

            $this->debug = @$this->settings['debug'];
            $this->order_button_text = __('Proceed to Alepay', 'woocommerce');
            $this->msg['message'] = "";
            $this->msg['class'] = "";
            // Add the page after checkout to redirect to Ngan Luong
            add_action('woocommerce_receipt_NganLuong', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Logging method.
         * @param string $message
         */
        public static function log($message)
        {
            $log = new WC_Logger();
            $log->add('nganluong', $message);
        }

        public function init_form_fields()
        {
            // Admin fields
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activate', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Activate the payment gateway for Alepay', 'woocommerce'),
                    'default' => 'yes'),
                'title' => array(
                    'title' => __('Name', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Tên phương thức thanh toán ( khi khách hàng chọn phương thức thanh toán )', 'woocommerce'),
                    'default' => __('AlepayVN', 'woocommerce')),
                'icon' => array(
                    'title' => __('Icon', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Icon phương thức thanh toán', 'woocommerce'),
                    'default' => __('https://alepay.vn/images/alego-Logo.png', 'woocommerce')),
                'description' => array(
                    'title' => __('Mô tả', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Mô tả phương thức thanh toán.', 'woocommerce'),
                    'default' => __('Click place order and you will be directed to the Alepay website in order to make payment', 'woocommerce')),
                'merchant_id' => array(
                    'title' => __('Alepay.vn email address', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Đây là tài khoản Alepay.vn (Email) để nhận tiền')),
                'redirect_page_id' => array(
                    'title' => __('Return URL'),
                    'type' => 'select',
                    'options' => $this->get_pages('Hãy chọn...'),
                    'description' => __('Hãy chọn trang/url để chuyển đến sau khi khách hàng đã thanh toán tại NganLuong.vn thành công', 'woocommerce')
                ),
                'status_order' => array(
                    'title' => __('Trạng thái Order'),
                    'type' => 'select',
                    'options' => wc_get_order_statuses(),
                    'description' => __('Chọn trạng thái orders cập nhật', 'woocommerce')
                ),
                'nlcurrency' => array(
                    'title' => __('Currency', 'woocommerce'),
                    'type' => 'text',
                    'default' => 'vnd',
                    'description' => __('"vnd" or "usd"', 'woocommerce')
                ),
                'api_key' => array(
                    'title' => __('TOKEN KEY', 'woocommerce'),
                    'type' => 'text',
                ),
                'checksum_key' => array(
                    'title' => __('CHECKSUM KEY', 'woocommerce'),
                    'type' => 'text',
                ),
                'encrypt_key' => array(
                    'title' => __('ENCRYPT KEY', 'woocommerce'),
                    'type' => 'text',
                )
            );
        }

        /**
         *  There are no payment fields for NganLuongVN, but we want to show the description if set.
         * */
        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize(__($this->description, 'woocommerce')));
        }

        /**
         * Process the payment and return the result.
         * @param  int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $checkouturl = $this->generate_Alepay_url($order_id);

            if (isset($checkouturl->token)) {
                global $wpdb;
                $current_user = wp_get_current_user();
                $payment_token_data = [
                    'gateway_id' => parse_url($checkouturl->checkoutUrl)['host'],
                    'token' => $checkouturl->token,
                    'user_id' => $current_user->ID,
                    'type' => gettype($checkouturl->token)
                ];
                $wpdb->insert('woocommerce_payment_tokens', $payment_token_data);
            }
            // Save token for database
            foreach ($checkouturl as $k => $v) {
                $this->log($k . '=>' . $v);
            }
            return array(
                'result' => 'success',
                'redirect' => (string)$checkouturl->checkoutUrl
            );
        }

        function generate_Alepay_url($order_id)
        {
            // This is from the class provided by Alepay. Not advisable to mess.
            $settings = get_option('woocommerce_alepay_settings', null);
            $alepay = new Alepay(array(
                "apiKey" => $settings['api_key'],
                "encryptKey" => $settings['encrypt_key'],
                "checksumKey" => $settings['checksum_key'],
                "callbackUrl" => $settings['redirect_page_id']
            ));
            global $woocommerce;
            $order = new WC_Order($order_id);
            $buyerFirstName = $order->get_billing_first_name();
            $buyerLastName = $order->get_billing_last_name();
            $buyerFullName = $buyerFirstName . $buyerLastName;
            $paramId = preg_replace('/\s+/', '', $buyerFullName) . '-' . time();
            $buyerName = $buyerFullName;
            $buyerEmail = $order->get_billing_email();
            $buyerPhone = $order->get_billing_phone();
            $buyerAddress = $order->get_billing_address_1();
            $buyerCity = $order->get_billing_city();
            $amount = (int)$order->get_total();
            $buyerPostalCode = $order->get_billing_postcode();
            $buyerState = $order->get_billing_state();
            $buyerCountry = $order->get_billing_country();
            $returnUrl = 'http://' . $_SERVER['SERVER_NAME'] . '/wordpressalepay/';
            $cancelUrl = 'http://' . $_SERVER['SERVER_NAME'] . '/wordpressalepay/';
            // Dummy data because not important
            $orderCode = $order_id;
            $totalItem = $order->get_item_count();
            $currency = $order->get_currency();
            $orderDescription = $order->get_customer_note() || ($buyerName . '-' . $buyerEmail . '-' . $buyerPhone . '-' . $buyerAddress);
            $response = $alepay->sendOrderToAlepayInstallment($paramId, $orderCode, $amount,
                $currency, $orderDescription, $totalItem,
                $returnUrl, $cancelUrl, $buyerName, $buyerEmail,
                $buyerPhone, $buyerAddress, $buyerCity, $buyerCountry,
                $returnUrl, $buyerPostalCode, $buyerState);

            if (!empty($response)) {
                //Cập nhât order với token  $nl_result->token để sử dụng check hoàn thành sau này
                echo "<pre>"; var_dump($response); echo "</pre>"; die();
                $errorException = json_decode($response);
                if ($errorException->errorCode !== '155') {
                    wp_send_json_error(array('error' => (string)$errorException->errorDescription));
                    exit;
                }else{
                    $old_status = 'wc-' . $order->get_status();
                    $new_status = 'wc-processing';
                    $order->update_status($new_status);
                    $this->orderCode = $orderCode;
                    $note = ': Thanh toán trực tuyến qua Alepay';
                    $order->add_order_note(sprintf(__('Cập nhật trạng thái từ %2$s thành %1$s. ' . $note, 'woocommerce'),
                        wc_get_order_status_name($old_status), wc_get_order_status_name($new_status)), 0, false);
                    $new_status = $alepay->GetErrorMessage((string)$response->transaction_status);
                    self::log('Cập nhật đơn hàng ID: ' . $order_id . ' trạng thái ' . $new_status);
                    return $response;
                }
            } else {
                echo $response;
            }
        }

        function showMessage($content)
        {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }

        // get all pages
        function get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
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
            return $page_list;
        }

        /* Hàm thực hiện xác minh tính đúng đắn của các tham số trả về từ nganluong.vn */

        public static function alepay_return_handler($order_id)
        {
            global $woocommerce;
            // This probably could be written better
            if (isset($_REQUEST['data']) && !empty($_REQUEST['checksum'])) {
                self::log($_SERVER['REMOTE_ADDR'] . json_encode(@$_REQUEST));
                $settings = get_option('woocommerce_alepay_settings', null);
                $data = $_REQUEST['data'];
                $checksum = $_REQUEST['checksum'];
                $decryptData = new AlepayUtils();
                $decryptData = $decryptData->decryptCallbackData($data, $settings['encrypt_key']);
                $jsonDecodeData = json_decode($decryptData);
                if ($jsonDecodeData->errorCode === "155" && !empty($jsonDecodeData->data)) {
                    // What Trick
                    $alepay = new Alepay(array(
                        "apiKey" => $settings['api_key'],
                        "encryptKey" => $settings['encrypt_key'],
                        "checksumKey" => $settings['checksum_key'],
                        "callbackUrl" => $settings['redirect_page_id']
                    ));
                    $infoTransactions = json_decode($alepay->getTransactionInfo($jsonDecodeData->data));
                    $order = new WC_Order($infoTransactions->orderCode);
                    // status tạm giữ 2 ngày nên để chế độ pending
                    $new_order_status = $settings['status_order'];
                    $order->update_status('wc-completed');
                    // Chưa thay đổi được trạng thái giao dịch của hóa đơn về wc-completed
                    $old_status = 'wc-' . $order->get_status();
                    $note = 'Thanh toán trực tuyến qua Alepay';
                    $note .= ' .Mã Giao Dịch: ' . $jsonDecodeData->data;
                    $order->add_order_note(sprintf(__('Cập nhật trạng thái từ %2$s thành %1$s.' . $note, 'woocommerce'),
                        wc_get_order_status_name($old_status), wc_get_order_status_name($new_order_status)), 0, false);
                    $new_status = $alepay->getErrorMessage($jsonDecodeData->errorCode);
                    self::log('Cập nhật đơn hàng ID: ' . $infoTransactions->orderCode . ' trạng thái ' . $new_status );
                    // Remove cart
                    $woocommerce->cart->empty_cart();
                    // Empty awaiting payment session
                    wp_redirect(get_permalink($settings['redirect_page_id']));
                    exit;
                }
            }
        }
    }

    function woocommerce_add_Alepay_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Alepay';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_Alepay_gateway');
}


