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

    private function __construct(string|null $accountId, string|null $secretKey)
    {
        $this->accountId = $accountId ?? "";
        $this->secretKey = $secretKey ?? "";
    }

    public static function new(string $accountId, string $secretKey) : TebexCheckoutAPI {
        return new TebexCheckoutAPI($accountId, $secretKey);
    }

    public static function noAuth() : TebexCheckoutAPI {
        return new TebexCheckoutAPI(null, null);
    }

    //                                           Baskets Endpoints                                    //

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#create-a-basket-that-can-be-used-to-pay-for-items
     */
    public function fetchBasket(string $basketId) : CheckoutBasketPayload | null {
        return json_decode($this->get("baskets"));
    }

    public function addPackageToBasket(string $basketId, Package $package) : mixed {
        return json_decode($this->post("baskets/{$basketId}/packages", $package));
    }

    public function createBasket(array $payload) : mixed {
        return json_decode($this->post("baskets", $payload));
    }

    public function removeRowFromBasket(string $basketId, int $rowId) : mixed {
        return json_decode($this->delete("baskets/${basketId}/packages/${rowId}"));
    }

    public function addSaleToBasket(string $basketId, Sale $sale) : mixed {
        return json_decode($this->post("baskets/${basketId}/sales", $sale));
    }

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#create-a-checkout-request
     */
    public function createCheckoutRequest(CheckoutBasketPayload $basket, Sale|null $sale) : mixed {
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
     */
    public function fetchPaymentById(string $txn_id) : mixed {
        return json_decode($this->get("payments/$txn_id?type=txn_id"), true);
    }

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#refund-a-payment-by-its-transaction-id
     */
    public function refundPaymentByID(string $txn_id) : mixed {
        return json_decode($this->post("payments/$txn_id/refund?type=txn_id", []), true);
    }

    //                                    Recurring Payments Endpoints                                //

    /**
     * @see https://docs.tebex.io/developers/checkout-api/endpoints#cancel-a-recurring-payment
     */
    public function fetchRecurringPayment(string $paymentReference) : mixed {
        return $this->get("recurring-payments/$paymentReference");
    }

    public function updateSubscribedProduct(string $paymentReference, array $items) : mixed {
        return $this->put("recurring-payments/$paymentReference", $items);
    }

    public function pauseRecurringPayment(string $paymentReference) : mixed { 
        return $this->updateRecurringPaymentStatus($paymentReference, RecurringPaymentStatus::Paused);
    }

    public function reactivateRecurringPayment(string $paymentReference) : mixed {
        return $this->updateRecurringPaymentStatus($paymentReference, RecurringPaymentStatus::Active);
    }

    public function updateRecurringPaymentStatus(string $paymentReference, RecurringPaymentStatus $status) : mixed {
        return $this->put("recurring-payments/$paymentReference/status", [
            "status" => $status
        ]);
    }

    public function cancelRecurringPayment(string $paymentReference) : mixed {
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

    private function post(string $endpoint, object|array $data)
    {
        $this->payload = json_encode($data);
        
        $url = $this->buildUrl($endpoint);
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);

        logModuleCall("Tebex Checkout", "api post", $data, $data, $data, "", "");
        return $this->executeRequest($ch);
    }

    public function post_plugin_log(object|array $data) 
    {
        $this->payload = json_encode($data);
        
        $url = "https://plugin-logs.tebex.io/";
        $ch = $this->prepareCurl($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);

        logModuleCall("Tebex Checkout", "external post", $data, $data, $data, "", "");
        return $this->executeRequest($ch);
    }

    private function put(string $endpoint, object|array $data)
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

    private function prepareCurl(string $url) : CurlHandle
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

    private function executeRequest(CurlHandle $ch) : mixed
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

    private function buildUrl(string $endpoint) : string
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