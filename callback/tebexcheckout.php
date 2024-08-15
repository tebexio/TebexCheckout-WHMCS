<?php
/**
 * Tebex Checkout Callback Script for WHMCS
 *
 * This file is based on the example WHMCS payment gateawy callback.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

require_once __DIR__ . '/../tebexcheckout/vendor/guzzlehttp/psr7/src/Query.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/HeaderSelector.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/ObjectSerializer.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Configuration.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/BasketsApi.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/ApiException.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/CheckoutApi.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/PaymentsApi.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/RecurringPaymentsApi.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/ModelInterface.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/CheckoutRequest.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/CheckoutRequestBasket.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/CreateBasketRequest.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/CheckoutItem.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/TebexWebhook.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/Package.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/Basket.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/PriceDetails.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/Address.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/BasketRow.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/BasketRowMeta.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/BasketRowMetaLimits.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/BasketRowMetaLimitsUser.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/BasketLinks.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/PaymentSubject.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/PaymentSubjectProductsInner.php';
require_once __DIR__ . '/../tebexcheckout/lib/checkout-sdk/Model/RecurringPaymentSubject.php';
require_once __DIR__ . '/../tebexcheckout/lib/plugin-sdk/PluginEvent.php';

use TebexCheckout\Model\Address;
use TebexCheckout\Model\PriceDetails;
use TebexCheckout\Model\Basket;
use TebexCheckout\Model\BasketRow;
use TebexCheckout\Model\CheckoutItem;
use TebexCheckout\Model\CheckoutRequest;
use TebexCheckout\Model\CheckoutRequestBasket;
use TebexCheckout\Model\CreateBasketRequest;
use TebexCheckout\Model\Package;
use TebexCheckout\Model\TebexWebhook;
use TebexCheckout\TebexCheckout\BasketsApi;
use TebexCheckout\TebexCheckout\CheckoutApi;
use TebexCheckout\TebexCheckout\PaymentsApi;
use TebexCheckout\TebexCheckout\RecurringPaymentsApi;
use TebexCheckout\Model\PaymentSubjectProductsInner;
use TebexCheckout\Model\PaymentSubject;
use TebexCheckout\Model\RecurringPaymentSubject;
use GuzzleHttp\Psr7\Utils;
use WHMCS\Database\Capsule;

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

/**
 * Validate callback authenticity.
 *
 * Most payment gateways provide a method of verifying that a callback
 * originated from them. In the case of our example here, this is achieved by
 * way of a shared secret which is used to build and compare a hash.
 */
$webhookSecretKey = $gatewayParams['webhookSecretKey'];
$json = file_get_contents('php://input');
$incomingSignature = $_SERVER['HTTP_X_SIGNATURE'];
if ($incomingSignature != hash_hmac('sha256', hash('sha256', $json), $webhookSecretKey)) {
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
    echo "Hash Verification Failure - Check Webhook Secret Key ";
    
    $event = new PluginEvent($gatewayParams['accountId'], "WARNING", "Hash verification failure");
    $event = $event->withMetadata([
        "incomingSignature" => $incomingSignature,
        "incomingJson" => $json,
    ]);
    
    $event = $event->withFrameworkVersion("Not Available")->withPluginVersion("2.0.0")->withRuntimeVersion(phpversion());
    $event->send();

    return $success;
}

$webhookDecodedJson = json_decode($json, true);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $json, $webhookDecodedJson["type"]);

$webhook = new TebexWebhook([
    "id" => $webhookDecodedJson["id"],
    "type" => $webhookDecodedJson["type"],
    "date" => $webhookDecodedJson["date"],
    "subject" =>$webhookDecodedJson["subject"]
]);

if ($webhook->getType() == "validation.webhook") {
    // Validation webhook wants us to return the ID in a json format to confirm the endpoint works
    echo json_encode(["id" => $webhook->getId()]);
    $event = new PluginEvent($gatewayParams['accountId'], "INFO", "Validation success");
    $event = $event->withFrameworkVersion("Not Available")->withPluginVersion("2.0.0")->withRuntimeVersion(phpversion());
    $event->send();
    return;
}

if (str_contains($webhook->getType(), "recurring-payment.")) {
    // Add subscription ID to a recurring payment product if applicable
    $recurringSubject = new RecurringPaymentSubject($webhook->getSubject());
    
    if ($webhook->getType() == "recurring-payment.started") {
        $subject = $webhook->getSubject();
        $recurringWebhookSubject = new RecurringPaymentSubject([
            'reference' => $subject["reference"],
            'created_at' => $subject["created_at"],
            'paused_at' => $subject["paused_at"],
            'paused_until' => $subject["paused_until"],
            'next_payment_at' => $subject["next_payment_at"],
            'status' => $subject["status"],
            'initial_payment' => new PaymentSubject($subject["initial_payment"]),
            'last_payment' => new PaymentSubject($subject["last_payment"]),
            'fail_count' => $subject["fail_count"],
            'price' => $subject["price"],
            'cancelled_at' => $subject["cancelled_at"],
            'cancel_reason' => $subject["cancel_reason"]
        ]);

        $lastPayment = new PaymentSubject($recurringSubject->getLastPayment());
        
        // identify the internal WHMCS product associated with the subscription and apply our subscription id
        $products = $lastPayment->getProducts();
        foreach ($products as $paidProduct) {
            $recurringProductType = $paidProduct["custom"]["type"]; // hosting, addon, etc. included when we built the initial basket for the invoice
            $recurringProductRelid = $paidProduct["custom"]["relid"];

            if ($recurringProductType == "hosting") {
                Capsule::table('tblhosting')->where('id', '=', intval($recurringProductRelid))->update([
                    'subscriptionid' => $recurringWebhookSubject->getReference(),
                ]);
            } else if ($recurringProductType == "addon") {
                Capsule::table('tblhostingaddons')->where('id', '=', intval($recurringProductRelid))->update([
                    'subscriptionid' => $recurringWebhookSubject->getReference(),
                ]);
            } else if ($recurringProductType == "lineitem") {
                //NOOP support for lineitems included with subscription items
            } else { // unrecognized product type is being sent with a subscription payment, should be a hosting or a addon.
                logModuleCall("Tebex Checkout", "unrecognized product type for subscription. supported products are 'hosting', 'addon', and 'lineitem'.", $webhook, $subject, $subject, "", "");
                echo json_encode([
                    "success" => false,
                    "message" => "unrecognized product type for subscription '" . $recurringProductType . "' supported products are 'hosting', 'addon', and 'lineitem'"
                ]);
                http_response_code(400);

                $event = new PluginEvent($gatewayParams['accountId'], "ERROR", "unrecognized product type for subscription: " . $recurringProductType);                
                $event = $event->withFrameworkVersion("Not Available")->withPluginVersion("2.0.0")->withRuntimeVersion(phpversion());
                $event->send();
                return;
            }
        }

        $paymentType = $lastPayment->getProducts();
        $paymentRelid = $lastPayment->getProducts()[0]["custom"]["relid"];
        
    } else { // unrecognized recurring payment webhook
        echo json_encode([
            "success" => false,
            "message" => "Unsupported webhook type: " . $webhook->getType()
        ]);
        http_response_code(400);
    }
} else if (str_contains($webhook->getType(), "payment.")) {
    // Handle payment notification webhooks, we need to update the paid invoice to have a status of Paid.
    $paymentSubject = new PaymentSubject($webhook->getSubject());

    $invoiceId = $paymentSubject->getCustom()["invoiceId"];
    $transactionId = $paymentSubject->getTransactionId();

    // tax is included as part of the transaction fees. the payment amount will be the full amount paid so we must separate the two for reporting
    // otherwise it will appear that the customer overpays and has a remaining credit balance
    $paymentFee = $paymentSubject->getFees()["gateway"]["amount"] + $paymentSubject->getFees()["tax"]["amount"];
    $paymentAmount = $paymentSubject->getPricePaid()["amount"] - $paymentFee;
    $transactionStatus = $paymentSubject->getStatus()["description"] == "Complete" ? 'Success' : 'Failure';

    /**
     * Validate Callback Invoice ID.
     *
     * Checks invoice ID is a valid invoice number. Note it will count an
     * invoice in any status as valid.
     *
     * Performs a die upon encountering an invalid Invoice ID.
     *
     * Returns a normalised invoice ID.
     *
     * @param int $invoiceId Invoice ID
     * @param string $gatewayName Gateway Name
     */
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

    /**
     * Check Callback Transaction ID.
     *
     * Performs a check for any existing transactions with the same given
     * transaction number.
     *
     * Performs a die upon encountering a duplicate.
     *
     * @param string $transactionId Unique Transaction ID
     */
    checkCbTransID($transactionId);

    if ($transactionStatus == "Success") {
        /**
         * Add Invoice Payment.
         *
         * Applies a payment transaction entry to the given invoice ID.
         *
         * @param int $invoiceId         Invoice ID
         * @param string $transactionId  Transaction ID
         * @param float $paymentAmount   Amount paid (defaults to full balance)
         * @param float $paymentFee      Payment fee (optional)
         * @param string $gatewayModule  Gateway module name
         */
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            $paymentFee,
            $gatewayModuleName
        );
    }
} else { // Handle unrecognized webhooks
    echo json_encode([
        "success" => false,
        "message" => "Unsupported webhook type: " . $webhook->getType()
    ]);
    http_response_code(400);
}