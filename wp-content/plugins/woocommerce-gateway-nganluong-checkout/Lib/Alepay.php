<?php
define('DS', str_replace('\\', '/', DIRECTORY_SEPARATOR));
define('ROOT_PATH', dirname(__FILE__));
include(ROOT_PATH . DS . 'Utils/AlepayUtils.php');
//echo "<pre>";var_dump(ROOT_PATH . DS . 'Utils/AlepayUtils.php');echo "</pre>";exit();
//new importAlepayUtils();
/*
 * Alepay class
 * Implement with Alepay service
 */

class Alepay
{
    protected $alepayUtils;
    protected $publicKey = "";
    protected $checksumKey = "";
    protected $apiKey = "";
    protected $callbackUrl = "";
    protected $env = "test";

    protected $baseURL = array(
        'dev' => 'localhost:8080',
        'test' => 'http://test.alepay.vn',
        'live' => 'https://alepay.vn'
    );
    protected $URI = array(
        'requestPayment' => '/checkout/v1/request-order',
        'calculateFee' => '/checkout/v1/calculate-fee',
        'getTransactionInfo' => '/checkout/v1/get-transaction-info',
        'requestCardLink' => '/checkout/v1/request-profile',
        'tokenizationPayment' => '/checkout/v1/request-tokenization-payment',
        'cancelCardLink' => '/checkout/v1/cancel-profile'
    );

    public function __construct($opts)
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Credentials: true");
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description');

        /*
         * Require curl and json extension
         */
        if (!function_exists('curl_init')) {
            throw new Exception('Alepay needs the CURL PHP extension.');
        }
        if (!function_exists('json_decode')) {
            throw new Exception('Alepay needs the JSON PHP extension.');
        }

        // set KEY
        if (isset($opts) && !empty($opts["apiKey"])) {
            $this->apiKey = $opts["apiKey"];
        } else {
            throw new Exception("API key is required !");
        }
        if (isset($opts) && !empty($opts["encryptKey"])) {
            $this->publicKey = $opts["encryptKey"];
        } else {
            throw new Exception("Encrypt key is required !");
        }
        if (isset($opts) && !empty($opts["checksumKey"])) {
            $this->checksumKey = $opts["checksumKey"];
        } else {
            throw new Exception("Checksum key is required !");
        }
        if (isset($opts) && !empty($opts["callbackUrl"])) {
            $this->callbackUrl = $opts["callbackUrl"];
        }
        $this->alepayUtils = new AlepayUtils();
    }


    /*
     * Generate data checkout demo
     */
    private function createCheckoutData()
    {
        $params = array(
            'amount' => '5000000',
            'buyerAddress' => '12 đường 18, quận 1',
            'buyerCity' => 'TP. Hồ Chí Minh',
            'buyerCountry' => 'Việt Nam',
            'buyerEmail' => 'testalepay@yopmail.com',
            'buyerName' => 'Nguyễn Văn Bê',
            'buyerPhone' => '0987654321',
            'cancelUrl' => 'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/demo-beta',
            'currency' => 'VND',
            'orderCode' => 'Order-123',
            'orderDescription' => 'Mua ai phôn 8',
            'paymentHours' => '5',
            'returnUrl' => $this->callbackUrl,
            'totalItem' => '1',
            'checkoutType' => 0,
            // 'installment' => 'true',
            // 'month' => '3',
            // 'bankCode' => 'Sacombank',
            // 'paymentMethod' => 'VISA'
        );

        return $params;
    }

    private function createRequestCardLinkData()
    {
        $params = array(
            'id' => 'acb-123',
            'firstName' => 'Nguyễn',
            'lastName' => 'Văn Bê',
            'street' => 'Nguyễn Trãi',
            'city' => 'TP. Hồ Chí Minh',
            'state' => 'Quận 1',
            'postalCode' => '100000',
            'country' => 'Việt nam',
            'email' => 'testalepay@yopmail.com',
            'phoneNumber' => '0987654321',
            'callback' => $this->callbackUrl
        );
        return $params;
    }

    private function createTokenizationPaymentData($tokenization)
    {
        $params = array(
            'customerToken' => $tokenization,    // put customer's token
            'orderCode' => 'order-123',
            'amount' => '1000000',
            'currency' => 'VND',
            'orderDescription' => 'Mua ai phôn 8',
            'returnUrl' => $this->callbackUrl,
            'cancelUrl' => 'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/demo-beta',
            'paymentHours' => 5
        );
        return $params;
    }


    /*
     * sendOrder - Send order information to Alepay service
     * @param array|null $data
     */
//    public function sendOrderToAlepay($data) {
//        // get demo data
//        $data = $this->createCheckoutData();
//        $data['returnUrl'] = $this->callbackUrl;
//        $data['cancelUrl'] =  'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/demo-beta';
//        $url = $this->baseURL[$this->env] . $this->URI['requestPayment'];
//        $result = $this->sendRequestToAlepay($data, $url);
//        if($result->errorCode == '000'){
//            $dataDecrypted = $this->alepayUtils->decryptData($result->data,$this->publicKey);
//            echo $dataDecrypted;
//        } else {
//            echo json_encode($result);
//        }
//    }
    public function sendOrderToAlepay($paramId, $orderCode, $amount, $currency,
                                      $orderDescription, $totalItem, $checkoutType,
                                      $installment, $month, $bankCode, $paymentMethod,
                                      $returnUrl, $cancelUrl, $buyerName, $buyerEmail,
                                      $buyerPhone, $buyerAddress, $buyerCity, $buyerCountry,
                                      $paymentHours, $callbackUrl, $merchantSideUserId, $buyerPostalCode, $buyerState, $isCardLink)
    {
        // get demo data
        // $data = $this->createCheckoutData();
        //$data['returnUrl'] = $this->callbackUrl;
        // $data['cancelUrl'] =  'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . '/demo-beta';
        //print_r($data);
        $data = array(
            'paramId' => $paramId,
            'orderCode' => $orderCode,
            'amount' => $amount,
            'currency' => $currency,
            'orderDescription' => $orderDescription,
            'totalItem' => $totalItem,
            'checkoutType' => $checkoutType,
            'installment' => $installment,
            'month' => $month,
            'bankCode' => $bankCode,
            'paymentMethod' => $paymentMethod,
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
            'buyerName' => $buyerName,
            'buyerEmail' => $buyerEmail,
            'buyerPhone' => $buyerPhone,
            'buyerAddress' => $buyerAddress,
            'buyerCity' => $buyerCity,
            'buyerCountry' => $buyerCountry,
            'paymentHours' => $paymentHours,
            'callbackUrl' => $callbackUrl,
            'merchantSideUserId' => $merchantSideUserId,
            'buyerPostalCode' => $buyerPostalCode,
            'buyerState' => $buyerState,
            'isCardLink' => $isCardLink
        );
//        / 'merchantSideUserId' => 'TUTQ7979',         // 'buyerPostalCode' => '10000',         //    'buyerState' => 'Hanoi',         //    'isCardLink' => true
        $url = $this->baseURL[$this->env] . $this->URI['requestPayment'];
//        echo "<pre>"; var_dump(1); echo "</pre>"; die();
        $result = $this->sendRequestToAlepay($data, $url);
        //print_r($result);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            $_SESSION['token'] = json_decode($dataDecrypted)->token;
//            echo "<pre>"; var_dump(json_decode($dataDecrypted)->token); echo "</pre>"; die();
            return json_decode($dataDecrypted);
        } else {
            return json_encode($result);
        }
    }

    public function sendOrderToAlepayInstallment(
        $paramId, $orderCode, $amount, $currency,
        $orderDescription, $totalItem,
        $returnUrl, $cancelUrl, $buyerName, $buyerEmail,
        $buyerPhone, $buyerAddress, $buyerCity, $buyerCountry,
        $callbackUrl, $buyerPostalCode, $buyerState)
    {
        // get demo data
        $data = array(
            'paramId' => $paramId,
            'orderCode' => $orderCode,
            'amount' => $amount,
            'currency' => $currency,
            'orderDescription' => $orderDescription,
            'totalItem' => $totalItem,
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
            'buyerName' => $buyerName,
            'buyerEmail' => $buyerEmail,
            'buyerPhone' => $buyerPhone,
            'buyerAddress' => $buyerAddress,
            'buyerCity' => $buyerCity,
            'buyerCountry' => $buyerCountry,
            'callbackUrl' => $callbackUrl,
            'buyerPostalCode' => $buyerPostalCode,
            'buyerState' => $buyerState,
            'paymentHours' => 48,
            'merchantSideUserId' => 'TUT07979',
            'isCardLink' => true,
            'checkoutType' => 2,
            'installment' => true,
            'month' => 12,
            'bankCode' => 'SACOMBANK',
            'paymentMethod' => 'VISA',
        );
        $url = $this->baseURL[$this->env] . $this->URI['requestPayment'];
        $result = $this->sendRequestToAlepay($data, $url);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            return json_decode($dataDecrypted);
        } else {
            return json_encode($result);
        }
    }

    /*
     * get transaction info from Alepay
     * @param array|null $data
     */
    public function getTransactionInfo($transactionCode)
    {

        // demo data
        $data = array('transactionCode' => $transactionCode);
        $url = $this->baseURL[$this->env] . $this->URI['getTransactionInfo'];
        $result = $this->sendRequestToAlepay($data, $url);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            echo $dataDecrypted;
        } else {
            echo json_encode($result);
        }
    }

    /*
     * sendCardLinkRequest - Send user's profile info to Alepay service
     * return: cardlink url
     * @param array|null $data
     */


    public function sendCardLinkRequest($paramId, $buyerFirstName, $buyerLastName,
                                        $buyerAddress, $buyerCity, $state, $postalCode,
                                        $buyerCountry, $buyerEmail, $phoneNumber, $callbackUrl)
    {
        // get demo data
//        echo "<pre>"; var_dump($data); echo "</pre>"; die();
//        $data = $this->createRequestCardLinkData();
//        echo "<pre>"; var_dump($data); echo "</pre>"; die();

        $data = [
            'id' => $paramId,
            'firstName' => $buyerFirstName,
            'lastName' => $buyerLastName,
            'street' => $buyerAddress,
            'city' => $buyerCity,
            'state' => $state,
            'postalCode' => $postalCode,
            'country' => $buyerCountry,
            'email' => $buyerEmail,
            'phoneNumber' => $phoneNumber,
            'callback' => $callbackUrl
        ];
        $url = $this->baseURL[$this->env] . $this->URI['requestCardLink'];
        $result = $this->sendRequestToAlepay($data, $url);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            return $dataDecrypted;
        } else {
            return $result;
        }
    }

    public function sendTokenizationPayment($tokenization, $orderCode, $amount, $currency, $orderDescription, $callbackUrl, $cancelUrl)
    {

        $data = array(
            'customerToken' => $tokenization,    // put customer's token
            'orderCode' => $orderCode,
            'amount' => $amount,
            'currency' => $currency,
            'orderDescription' => $orderDescription,
            'returnUrl' => $callbackUrl,
            'cancelUrl' => $cancelUrl,
            'paymentHours' => 5
        );


        $url = $this->baseURL[$this->env] . $this->URI['tokenizationPayment'];
        $result = $this->sendRequestToAlepay($data, $url);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            return json_encode($dataDecrypted);
        } else {
            return $result;
        }
    }

    public function cancelCardLink($alepayToken)
    {
        $params = array('alepayToken' => $alepayToken);
        $url = $this->baseURL[$this->env] . $this->URI['cancelCardLink'];
        $result = $this->sendRequestToAlepay($params, $url);
        echo json_encode($result);
        if ($result->errorCode == '000') {
            $dataDecrypted = $this->alepayUtils->decryptData($result->data, $this->publicKey);
            echo $dataDecrypted;
        }
    }

    private function sendRequestToAlepay($data, $url)
    {
        $dataEncrypt = $this->alepayUtils->encryptData(json_encode($data), $this->publicKey);
        $checksum = md5($dataEncrypt . $this->checksumKey);
        $items = array(
            'token' => $this->apiKey,
            'data' => $dataEncrypt,
            'checksum' => $checksum
        );
        $data_string = json_encode($items);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );
        $result = curl_exec($ch);
        return json_decode($result);
    }

    public function return_json($error, $message = "", $data = array())
    {
        header('Content-Type: application/json');
        echo json_encode(array(
            "error" => $error,
            "message" => $message,
            "data" => $data
        ));
    }

    public function decryptCallbackData($data)
    {
        return $this->alepayUtils->decryptCallbackData($data, $this->publicKey);
    }

    public function getErrorMessage($error_code)
    {
        $arrCode = array(
            '000' => 'Thành công',
            '101' => 'Checksum không hợp lệ',
            '102' => 'Mã hóa không hợp lệ',
            '103' => 'Địa chỉ IP truy cập bị từ chối',
            '104' => 'Dữ liệu không hợp lệ',
            '105' => 'Token key không hợp lệ',
            '106' => 'Token thanh toán Alepay không tồn tại hoặc đã bị hủy',
            '107' => 'Giao dịch đang được xử lý',
            '108' => 'Dữ liệu không tìm thấy',
            '109' => 'Mã đơn hàng không tìm thấy',
            '111' => 'Giao dịch thất bại',
            '120' => 'Giá trị đơn hàng phải lớn hơn 0',
            '121' => 'Loại tiền tệ không hợp lệ',
            '122' => 'Mô tả đơn hàng không tìm thấy',
            '123' => 'Tổng số sản phẩm phải lớn hơn không',
            '124' => 'Định dạng URL không chính xác (http://, https://)',
            '125' => 'Tên người mua không đúng định dạng',
            '126' => 'Email người mua không đúng định dạng',
            '127' => 'SĐT người mua không đúng định dạng',
            '128' => 'Địa chỉ người mua không hợp lệ',
            '129' => 'City người mua không hợp lệ',
            '130' => 'Quốc gia người mua không hợp lệ',
            '131' => 'hạn thanh toán phải lớn hơn 0',
            '132' => 'Email không hợp lệ',
            '133' => 'Thông tin thẻ không hợp lệ',
            '134' => 'cancel_url không hợp lệ',
            '135' => 'Giao dịch bị từ chối bởi ngân hàng phát hành thẻ',
            '136' => 'Mã giao dịch không tồn tại',
            '137' => 'Giao dịch không hợp lệ',
            '138' => 'Tài khoản Merchant không tồn tại',
            '139' => 'Tài khoản Merchant không hoạt động',
            '140' => 'Tài khoản Merchant không hợp lệ',
            '142' => 'Ngân hàng không hỗ trợ trả góp',
            '143' => 'Thẻ không được phát hành bởi ngân hàng đã chọn',
            '144' => 'Kỳ thanh toán không hợp lệ',
            '145' => 'Số tiền giao dịch trả góp không hợp lệ',
            '146' => 'Thẻ của bạn không thuộc ngân hang hỗ trợ trả góp',
            '147' => 'Số điện thoại không hợp lệ',
            '148' => 'Thông tin trả góp không hợp lệ',
            '149' => 'Loại thẻ không hợp lệ',
            '150' => 'Thẻ bị review',
            '151' => 'Ngân hàng không hỗ trợ thanh toán',
            '152' => 'Số thẻ không phù hợp với loại thẻ đã chọn',
            '153' => 'Giao dịch không tồn tại',
            '154' => 'Số tiền vượt quá hạn mức cho phép',
            '155' => 'Đợi người mua xác nhận trả góp tại ngân hàng',
            '156' => 'Số tiền thanh toán không hợp lệ',
            '157' => 'email không khớp với profile đã tồn tại',
            '158' => 'số điện thoại không khớp với profile đã tồn tại',
            '159' => 'Id không được để trống',
            '160' => 'First name không được để trống',
            '161' => 'Last name không được để trống',
            '162' => 'Email không được để trống',
            '163' => 'city không được để trống',
            '164' => 'country không được để trống',
            '165' => 'SĐT Không được để trống',
            '166' => 'state không được để trống',
            '167' => 'street không được để trống',
            '168' => 'postalcode không được để trống',
            '169' => 'url callback không đươc để trống',
            '170' => 'otp nhập sai quá 3 lần',
            '171' => 'Thẻ của khách hàng đã được liên kết trên Merchant',
            '172' => 'thẻ tạm thời bị cấm liên kết do vượt quá số lần xác thực số tiền',
            '173' => 'trạng thái liên kết thẻ không đúng',
            '174' => 'không tìm thấy phiên liên kết thẻ',
            '175' => 'số tiền thanh toán của thẻ 2D chưa xác thực vượt quá hạn mức',
            '176' => 'thẻ 2D đang chờ xác thực',
        );

        return $arrCode[(string)$error_code];
    }

}

?>