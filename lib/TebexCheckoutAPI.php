<?php

/**
 * Tebex Checkout allows a seller to create a custom Tebex Checkout flow and accept payments via Tebex.
 * 
 * Baskets are created with custom products instead of pre-defined products on the webstore platform.
 * 
 * This class implements the Checkout API as found at https://docs.tebex.io/developers/checkout-api
 */

class TebexCheckoutAPI
{
    private string $apiUrl = "https://checkout.tebex.io/api/";
    private string $accountId;
    private string $secretKey;
    private string $payload;   // for display in module log

    private function __construct(string $accountId, string $secretKey)
    {
        $this->accountId = $accountId ?: "";
        $this->secretKey = $secretKey ?: "";
    }

    /**
     * Create a new accessor for the API with an account ID and secret key
     * 
     * @param string accountId
     * @param string secretKey
     * 
     * @return TebexCheckoutAPI
     */
    public static function new(string $accountId, string $secretKey) {
        return new TebexCheckoutAPI($accountId, $secretKey);
    }

    /**
     * Create a new API accessor without authentication
     * 
     * @param string accountId
     * @param string secretKey
     * 
     * @return TebexCheckoutAPI
     */
    public static function noAuth() {
        return new TebexCheckoutAPI(null, null);
    }

    //                                           Baskets Endpoints                                    //

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#create-a-basket-that-can-be-used-to-pay-for-items
     * @return CheckoutBasketPayload | null
     */
    public function fetchBasket(string $basketId) {
        return json_decode($this->get("baskets"));
    }

    /**
     * @return mixed
     */
    public function addPackageToBasket(string $basketId, Package $package) {
        return json_decode($this->post("baskets/{$basketId}/packages", $package));
    }

    /**
     * @return mixed
     */
    public function createBasket(array $payload) {
        return json_decode($this->post("baskets", $payload));
    }

    /**
     * @return mixed
     */
    public function removeRowFromBasket(string $basketId, int $rowId) {
        return json_decode($this->delete("baskets/${basketId}/packages/${rowId}"));
    }

    /**
     * @return mixed
     */
    public function addSaleToBasket(string $basketId, Sale $sale) {
        return json_decode($this->post("baskets/${basketId}/sales", $sale));
    }

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#create-a-checkout-request
     * @param CheckoutBasketPayload $basket
     * @param Sale|null $sale
     * @return mixed
     */
    public function createCheckoutRequest(CheckoutBasketPayload $basket, $sale) {
        $payload = [
            "basket" => $basket,
            "items" => $basket->items(),
            "sale" => $sale ?? [],
        ];

        return json_decode($this->post("checkout", $payload), true);
    }

    //                                           Payments Endpoints                                    //

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#fetch-a-payment-by-its-transaction-id
     * @return mixed
     */
    public function fetchPaymentById(string $txn_id) {
        return json_decode($this->get("payments/$txn_id?type=txn_id"), true);
    }

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#refund-a-payment-by-its-transaction-id
     * @return mixed
     */
    public function refundPaymentByID(string $txn_id) {
        return json_decode($this->post("payments/$txn_id/refund?type=txn_id", []), true);
    }

    //                                    Recurring Payments Endpoints                                //

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#cancel-a-recurring-payment
     * @return mixed
     */
    public function fetchRecurringPayment(string $paymentReference) {
        return $this->get("recurring-payments/$paymentReference");
    }

    /**
     * @return mixed
     */
    public function updateSubscribedProduct(string $paymentReference, array $items) {
        return $this->put("recurring-payments/$paymentReference", $items);
    }

    /**
     * @return mixed
     */
    public function pauseRecurringPayment(string $paymentReference) { 
        return $this->updateRecurringPaymentStatus($paymentReference, RecurringPaymentStatus::Paused);
    }

    /**
     * @return mixed
     */
    public function reactivateRecurringPayment(string $paymentReference) {
        return $this->updateRecurringPaymentStatus($paymentReference, RecurringPaymentStatus::Active);
    }

    /**
     * @return mixed
     */
    public function updateRecurringPaymentStatus(string $paymentReference, RecurringPaymentStatus $status) {
        return $this->put("recurring-payments/$paymentReference/status", [
            "status" => $status
        ]);
    }

    /**
     * @return mixed
     */
    public function cancelRecurringPayment(string $paymentReference) {
        return $this->delete("recurring-payments/$paymentReference");
    }

    // Utility functions
    private function get(string $endpoint)
    {
        $url = $this->buildUrl($endpoint);
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        return $this->executeRequest($ch);
    }

    /**
     * @param object|array $data
     */
    private function post(string $endpoint, $data)
    {
        $this->payload = json_encode($data);
        
        $url = $this->buildUrl($endpoint);
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);

        logModuleCall("Tebex Checkout", "api post", $data, $data, $data, "", "");
        return $this->executeRequest($ch);
    }

    /**
     * @param object|array $data
     */
    public function post_plugin_log($data) 
    {
        $this->payload = json_encode($data);

        $url = "https://plugin-logs.tebex.io/";
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);

        logModuleCall("Tebex Checkout", "external post", $data, $data, $data, "", "");
        return $this->executeRequest($ch);
    }

    /**
     * @param object|array $data
     */
    private function put(string $endpoint, $data)
    {
        $this->payload = json_encode($data);

        $url = $this->buildUrl($endpoint);
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);

        return $this->executeRequest($ch);
    }

    private function delete(string $endpoint)
    {
        $url = $this->buildUrl($endpoint);
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $this->executeRequest($ch);
    }

    /**
     * @return CurlHandle
     */
    private function prepareCurl(string $url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->accountId:$this->secretKey");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        return $ch;
    }

    /**
     * @return mixed
     */
    private function executeRequest(CurlHandle $ch)
    {
        $response = curl_exec($ch);
        $requestInfo = curl_getinfo($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('Request Error: ' . curl_error($ch));
        }

        curl_close($ch);
        
        $responseData = json_decode($response, true);

        // Show error in the module log if a problem occurred
        if ($httpStatusCode >= 400) {
            $errorHandler = new CheckoutAPIError(
            [$this->payload, $requestInfo],
            [
                'status' => $httpStatusCode,'body' => $response
            ]);
            $errorHandler->logErrorDetails();
        } else {
            logModuleCall("Tebex Checkout", "api response", $requestInfo, $responseData, $responseData, "", "");
        }

        return $response;
    }

    /**
     * @return string
     */
    private function buildUrl(string $endpoint)
    {
        return rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
    }
}

/**
 * A TriageEvent represents an error occurring within a Tebex plugin. These errors collect data about their environment
 *  and the issue that occurred. These are then reported to Tebex.
 */
class TriageEvent {
    public $gameId;
    public $frameworkId;
    public $pluginVersion;
    public $serverIp;
    public $errorMessage;
    public $trace;
    public $metadata;
    public $storeName;
    public $storeUrl;

    public function __construct($gameId = "", $frameworkId = "", $pluginVersion = "", $serverIp = "", $errorMessage = "", $trace = "", $metadata = [], $storeName = "", $storeUrl = "") {
        $this->gameId = $gameId;
        $this->frameworkId = $frameworkId;
        $this->pluginVersion = $pluginVersion;
        $this->serverIp = $serverIp;
        $this->errorMessage = $errorMessage;
        $this->trace = $trace;
        $this->metadata = $metadata;
        $this->storeName = $storeName;
        $this->storeUrl = $storeUrl;
    }

    public function toJson() {
        return json_encode(get_object_vars($this));
    }

    public static function fromJson($json) {
        $data = json_decode($json, true);
        return new TriageEvent(
            $data['gameId'] ?? "",
            $data['frameworkId'] ?? "",
            $data['pluginVersion'] ?? "",
            $data['serverIp'] ?? "",
            $data['errorMessage'] ?? "",
            $data['trace'] ?? "",
            $data['metadata'] ?? [],
            $data['storeName'] ?? "",
            $data['storeUrl'] ?? ""
        );
    }
}

enum RecurringPaymentStatus {
    case Paused;
    case Active;
}

enum PackagePaymentType {
    case single;
    case subscription;
}