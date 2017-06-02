<?php

/**
 * Plugin Name: Alepay payment gateway for WooCommerce
 * Plugin URI: https://www.nganluong.vn/
 * Description: Plugin tích hợp Alepay được build trên WooCommerce 3.x
 * Version: 3.1
 * Author: Đức LM(0948389111) - Thanh NA (0968381829)
 * Author URI: http://www.webckk.com/
 */


ini_set('display_errors', true);
add_action('plugins_loaded', 'woocommerce_payment_nganluong_init', 0);
add_action('parse_request', array('WC_Gateway_NganLuong', 'nganluong_return_handler'));
class importAlepay {
   public function __construct() {
       include( 'Lib/Alepay.php' );
   }
}
new importAlepay();
// Hook in
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
// Our hooked in function - $fields is passed via the filter!
function custom_override_checkout_fields( $fields ) {
//    $fields['billing']['billing_first_name']['placeholder'] = 'Full Name';
//    $fields['billing']['billing_first_name'] = 'Full Name';
//    $fields['billing']['billing_first_name']['name'] = 'billing_full_name';
    return $fields;
}

//define('URL_API', 'http://sandbox.nganluong.vn:8088/nl30/checkout.api.nganluong.post.php'); // Đường dẫn gọi api
//define('RECEIVER', 'demo@nganluong.vn'); // Email tài khoản ngân lượng
//define('MERCHANT_ID', '36680'); // Mã merchant kết nối
//define('MERCHANT_PASS', 'matkhauketnoi'); // Mật khẩu kết nôi
//define('MERCHANT_ID', '30439');
//define('MERCHANT_PASS', '212325');
//define('RECEIVER', 'nguyencamhue@gmail.com');

function woocommerce_payment_nganluong_init()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Gateway_NganLuong extends WC_Payment_Gateway
    {

        // URL checkout của nganluong.vn - Checkout URL for Ngan Luong
        private $nganluong_url;
        // Mã merchant site code
        private $merchant_site_code;
        // Mật khẩu bảo mật - Secure password
        private $secure_pass;
        // Debug parameters
        private $debug_params;
        private $debug_md5;
        private $merchant_id;
        private $nlcheckout_copy;
        private $status_order;
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
            $this->nganluong_url = $this->settings['nganluong_url'];
            $this->merchant_site_code = $this->settings['merchant_site_code'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->secure_pass = $this->settings['secure_pass'];
            $this->redirect_page_id = $this->settings['redirect_page_id'];
            $this->status_order = $this->settings['status_order'];
            $this->debug = @$this->settings['debug'];
            $this->order_button_text = __('Proceed to Ngân Lượng', 'woocommerce');
            $this->msg['message'] = "";
            $this->msg['class'] = "";
            // Add the page after checkout to redirect to Ngan Luong
            add_action('woocommerce_receipt_NganLuong', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            // add_action('woocommerce_thankyou_NganLuongVN', array($this, 'thankyou_page'));
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
                    'default' => __('NganLuongVN', 'woocommerce')),
                'icon' => array(
                    'title' => __('Icon', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Icon phương thức thanh toán', 'woocommerce'),
                    'default' => __('https://www.nganluong.vn/css/checkout/version20/images/logoNL.png', 'woocommerce')),
                'description' => array(
                    'title' => __('Mô tả', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Mô tả phương thức thanh toán.', 'woocommerce'),
                    'default' => __('Click place order and you will be directed to the Ngan Luong website in order to make payment', 'woocommerce')),
                'merchant_id' => array(
                    'title' => __('NganLuong.vn email address', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Đây là tài khoản NganLuong.vn (Email) để nhận tiền')),
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
                'nganluong_url' => array(
                    'title' => __('Ngan Luong URL', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('"https://www.nganluong.vn/checkout.php"', 'woocommerce')
                ),
                'merchant_site_code' => array(
                    'title' => __('Merchant Site Code', 'woocommerce'),
                    'type' => 'text'
                ),
                'secure_pass' => array(
                    'title' => __('Secure Password', 'woocommerce'),
                    'type' => 'password'
                ),
//                'version' => array(
//                    'title' => __('Ngân Lượng Version' ,'woocommerce'),
//                    'type' => 'select',
//                    'options' => array( 'version2' => 'Version2.0', 'version3.1' => 'version3.1'),
////                    'label' => __('Ngân Lượng Version', 'woocommerce'),
//                )
            );
        }

        /**
         *  There are no payment fields for NganLuongVN, but we want to show the description if set.
         * */
        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize(__($this->description, 'woocommerce')));
            echo '<br>';
            require_once 'template.php';
        }

        /**
         * Process the payment and return the result.
         * @param  int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
//            echo "<pre>";var_dump($order);echo "</pre>";exit();
            $checkouturl = $this->generate_NganLuongVN_url($order_id);
            echo "<pre>";var_dump(json_decode($checkouturl));echo "</pre>";exit();
            $this->log($checkouturl);
            //  echo $checkouturl;
            // die();
            return array(
                'result' => 'success',
                'redirect' => $checkouturl
            );
        }

        function generate_NganLuongVN_url($order_id)
        {
            // This is from the class provided by Ngan Luong. Not advisable to mess.
//            echo "<pre>";var_dump(plugin_dir_path(__DIR__ . 'Lib/Alepay.php'));echo "</pre>";exit();
            $callbackUrl = 'http://' . $_SERVER['SERVER_NAME'];
            if ($_SERVER['SERVER_PORT'] != '80') {
                $callbackUrl = $callbackUrl . ':' . $_SERVER['SERVER_PORT'];
            }
            $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
            $encryptKey = "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCIh+tv4h3y4piNwwX2WaDa7lo0uL7bo7vzp6xxNFc92HIOAo6WPZ8fT+EXURJzORhbUDhedp8B9wDsjgJDs9yrwoOYNsr+c3x8kH4re+AcBx/30RUwWve8h/VenXORxVUHEkhC61Onv2Y9a2WbzdT9pAp8c/WACDPkaEhiLWCbbwIDAQAB";
            $callbackUrl = $callbackUrl . $uri_parts[0];
            $alepay = new Alepay(array(
                // db dev
                // "apiKey" => "yTsl7Ycg9uhIl04EduMQoOuJWhQdZ6",
                // "encryptKey" => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC9vIbL4rPQSu3TW/MxakvwWplDarB9lBa3jlp2V1IVkPdzk3PbWWAeWM/RuHEGlvRpX8xCQEG5AzC60XXpNUT5JpqldSlyyJVdvsuDLd/BVEZ/rnC4PkOFV07XdgCn1MWwptZJkFnAY2yXTJNBxZeo+f705gQ0Mxc6cTfWjlV3bwIDAQAB",
                // "checksumKey" => "rYcX5D6Sb7JwUtglw4AWttt2g2MHsE",

                // db test
                "apiKey" => "0COVspcyOZRNrsMsbHTdt8zesP9m0y",
                "encryptKey" => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCIh+tv4h3y4piNwwX2WaDa7lo0uL7bo7vzp6xxNFc92HIOAo6WPZ8fT+EXURJzORhbUDhedp8B9wDsjgJDs9yrwoOYNsr+c3x8kH4re+AcBx/30RUwWve8h/VenXORxVUHEkhC61Onv2Y9a2WbzdT9pAp8c/WACDPkaEhiLWCbbwIDAQAB",
                "checksumKey" => "hjuEmsbcohOwgJLCmJlf7N2pPFU1Le",

                // joombooking
                // "apiKey" => "r7CcL19wBEix1ScJXv7ZXy9NoL0Ub8",
                // "encryptKey" => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCTM7z4P0XkP7pzMennhtShndJ/FcQbYZ2hxypzpzcRU2zi74B4hlrN2uBFjY7obuF68F7SGt72J0bcM9w74aBjCb5YAQX8JOD6IlZsdSCPzMwEpCALKaIUEsZ/npNQEf/RmMOV8RNfJDY2/6ElvUgCu7+eGkabl6Ete8fiI9TYKwIDAQAB",
                // "checksumKey" => "mCPjtDOYyGcg3b6IGIl3lSwCbMKq6m",

                // weshop
                // "apiKey" => "FsIFYpHt42GGDgji5SmLkLDqKRV9tt",
                // "encryptKey" => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDO8WZFC2Hwj4JmBUZN8naZetVISLyg6CCW+EhmUCPRswblGhjjxbk4+aYzkq0itmQFJ8paUJMeql2NAN4E9cBmQ0OaOqNzHeq/aGJV0sdxEga1UpqGcq2BHXYhDQHe9RQ/rSIJXxR4WhxpcZcxZdj0qrswxoPPubeKFBc+fHBdxQIDAQAB",
                // "checksumKey" => "RukyMrAGCyLeBCbfeEw2erzzao2htH",

                // live db
                // "apiKey" => "imt4pZsjbCDE2ioVxnQs71wzNv4TZW",
                // "encryptKey" => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCVdQKI15hS23XGT9DBQzIardNBBCPa86XeEhMzP2TKKi737SBUXg+z/o3BNhcFZRdTsL5uQpAmBEP3IJYEvclOGgOyWBbpjUf0MXENexaXB9gX9fI/bEiso7k0shBdi8dZt1FdabX/NSTzM+WcQElgLYgXnlwoyCiyzOFL60V4BwIDAQAB",
                // "checksumKey" => "5iaPavRj8FQXb6eXCj7gFcXC43jsg5",
                "callbackUrl" => $callbackUrl

            ));
            global $woocommerce;
            $order = new WC_Order($order_id);
            $order_items = $order->get_items();
            // Dùng tạm return_url
            $return_url = get_site_url() . '/nganluong_return?order_id=' . $order_id;
            //$return_url = 'nganluong.vn';
            $receiver = $this->merchant_id;
            $currency = $this->settings['nlcurrency'];
            $transaction_info = ''; // urlencode("Order#".$order_id." | ".$_SERVER['SERVER_NAME']);
            $order_description = $order_id;
            $order_quantity = $order->get_item_count();
            //$discount = $order->get_cart_discount();
            $discount = 0;
            //$tax = $order->get_cart_tax();
            $tax = 0;
            $fee_shipping = $order->get_total_shipping_refunded();
            $product_names = [];
            foreach ($order_items as $order_item) {
                $product_names[] = $order_item['name'];
            }
            $order_description = implode(', ', $product_names); // this goes into transaction info, which shows up on Ngan Luong as the description of goods
            $price = $order->get_total() - ($tax + $fee_shipping);
            $total_amount = $price;
            $array_items = [
                'item_name' => $order->get_order_item_totals(),
                'item_quantity' => $order->get_order_item_totals(),
                'item_amount' => $total_amount,
            ];
//            $payment_method = $_POST['option_payment'];
//            (isset($_POST['payment_method'])) ? ($payment_method = $_POST['payment_method']) : ($payment_method = get_post_meta($order->get_id(), '_payment_method', true)); // Lưu ý $order->id
            $payment_method = $_POST['option_payment'];
//            $payment_method = $order->get_payment_method();
            $bank_code = @$_POST['bankcode'];
            $order_code = $order_id;
            $payment_type = '';
            $discount_amount = 0;
            $tax_amount = 0;
            // Dùng tạm return_url
//            $return_url = get_site_url() . '/nganluong_return?order_id=' . $order_id;
            $cancel_url = urlencode('http://localhost/nganluong.vn/checkoutv3?orderid=' . $order_code);
//            $buyer_fullname = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $buyerFirstName = $order->get_billing_first_name();
            $buyerLastName = $order->get_billing_last_name();
            $buyerFullName = $buyerFirstName.$buyerLastName;
            $paramId = preg_replace('/\s+/', '', $buyerFullName) . '-' . time();
            $buyerName = $buyerFullName;
            $buyerEmail = $order->get_billing_email();
            $buyerPhone = $order->get_billing_phone();
            $buyerAddress = $order->get_formatted_billing_address();
            $buyerCity = $order->get_billing_city();
            $postalCode = $order->get_billing_postcode();
            $merchantSideUserId = 'TUT07979';
            $buyerPostalCode = '10000';
            $buyerState = 'Hanoi';
            $isCardLink = true;
            $installment = true;
            $paymentHours = 48;
            $checkoutType = 2;
            $buyerCountry = 'Viet Nam';
            $returnUrl = 'http://' . $_SERVER['SERVER_NAME'] . '/wordpressalepay';
            $cancelUrl = 'http://' . $_SERVER['SERVER_NAME'] . '/wordpressalepay';
            // Dummy data because not important
            $state = @$order->get_billing_state();
            $bankCode = 'SACOMBANK';
            $month = 12;
            $paymentMethod = 'VISA';
            $orderCode = $order_id;
            $amount = '3500000';
//            $amount = $order->get_total();
//            $totalItem = $order->get_item_count();
            $totalItem = 1;
            $currency = $order->get_currency();
            $orderDescription = $order->get_customer_order_notes();
//            echo "<pre>";var_dump($returnUrl);echo "</pre>";exit();
            $response = $alepay->sendOrderToAlepayInstallment($paramId, $orderCode, $amount, $currency,
                $orderDescription, $totalItem, $checkoutType,
                $installment, $month, $bankCode, $paymentMethod,
                $returnUrl, $cancelUrl, $buyerName, $buyerEmail,
                $buyerPhone, $buyerAddress, $buyerCity, $buyerCountry,
                $paymentHours, $returnUrl, $merchantSideUserId, $buyerPostalCode, $buyerState, $isCardLink);
            echo  $response;
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

        public static function nganluong_return_handler($order_id)
        {
            global $woocommerce;
            // This probably could be written better
            if (isset($_REQUEST['order_id']) && !empty($_REQUEST['order_id']) && $_REQUEST['error_code'] == '00') {
                self::log($_SERVER['REMOTE_ADDR'] . json_encode(@$_REQUEST));
                $settings = get_option('woocommerce_nganluong_settings', null);
                $order_id = $_REQUEST['order_id'];
                $nlcheckout = new NL_CheckOutV3($settings['merchant_site_code'], $settings['secure_pass'], $settings['merchant_id'], $settings['nganluong_url']);
//                echo "<pre>";var_dump($settings);echo "</pre>";exit();
                $nl_result = $nlcheckout->GetTransactionDetail($_GET['token']);
                if ((string)$nl_result->transaction_status == '00') {
                    $order = new WC_Order($order_id);
                    // phương thức
                    // số dư ví
                    // Xác thực mã của chủ web với mã trả về từ nganluong.vn
                    // status tạm giữ 2 ngày nên để chế độ pending
//                    $new_order_status = $settings['status_order'];
                    // tuy nhiên ta sẽ fix cứng status này là completed
                    $new_order_status = 'wc-processing';
                    $old_status = 'wc-' . $order->get_status();
                    if ($new_order_status !== $old_status) {
                        $note = 'Thanh toán trực tuyến qua Ngân Lượng.';
                        if ((string)$nl_result->payment_type == 2) {
                            $note .= ' Với hình thức thanh toán tạm giữ';
                        } else if ((string)$nl_result->payment_type == 1) {
                            $note .= ' Với hình thức thanh toán ngay';
                        }
                        $note .= ' .Mã thanh toán: ' . (string)$nl_result->transaction_id;
                        $order->update_status($new_order_status);
                        $order->add_order_note(sprintf(__('Cập nhật trạng thái từ %1$s thành %2$s.' . $note, 'woocommerce'), wc_get_order_status_name($old_status), wc_get_order_status_name($new_order_status)), 0, false);
                        $new_status = $nlcheckout->GetErrorMessage((string)$nl_result->transaction_status);
                        self::log('Cập nhật đơn hàng ID: ' . $order_id . ' trạng thái ' . $new_status);
                    }
                    // Remove cart
                    $woocommerce->cart->empty_cart();
                    // Empty awaiting payment session
                    unset($_SESSION['order_awaiting_payment']);
                    wp_redirect(get_permalink($settings['redirect_page_id']));
                    exit;
                }
            }
        }

    }

    function woocommerce_add_NganLuong_gateway($methods)
    {
        $methods[] = 'WC_Gateway_NganLuong';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_NganLuong_gateway');

}


