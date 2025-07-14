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

use Tebex\Webhook\Webhook;
use TebexCheckout\Model\PaymentSubject;
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
use Tebex\Webhooks;
use const Tebex\Webhook\PAYMENT_COMPLETED;
use const Tebex\Webhook\RECURRING_PAYMENT_ENDED;
use const Tebex\Webhook\RECURRING_PAYMENT_STARTED;

$incomingSignature = $_SERVER['HTTP_X_SIGNATURE'];
$json = file_get_contents('php://input');
Webhooks::setSecretKey($gatewayParams['webhookSecretKey']);
if (!Webhooks::validateWebhookSignature($incomingSignature, $json)) {
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
    echo "Hash Verification Failure - Check Webhook Secret Key ";
    
    $event = new PluginEvent($gatewayParams['accountId'], "WARNING", "Hash verification failure");
    $event = $event->withMetadata([
        "incomingSignature" => $incomingSignature,
        "incomingJson" => $json,
    ]);
    
    $event = $event->withFrameworkVersion("Not Available")->withPluginVersion("2.1.0")->withRuntimeVersion(phpversion());
    $event->send();

    return $success;
}

// Validation webhook wants us to return the ID in a json format to confirm the endpoint works
$webhook = Webhook::fromJson($json);
if ($webhook->isType(\Tebex\Webhook\VALIDATION_WEBHOOK)) {
    echo json_encode(["id" => $webhook->getId()]);

    $event = new PluginEvent($gatewayParams['accountId'], "INFO", "Validation success");
    $event = $event->withFrameworkVersion("Not Available")->withPluginVersion("2.0.0")->withRuntimeVersion(phpversion());
    $event->send();
    return;
}

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
logTransaction($gatewayParams['name'], $json, $webhook->getType());

if ($webhook->isTypeOfRecurringPayment()) {
    // Add subscription ID to a recurring payment product if applicable

    switch ($webhook->getType()) {
        case RECURRING_PAYMENT_STARTED:
            $subject = $webhook->getSubject();
            $lastPayment = new PaymentSubject($subject->getLastPayment());

            // identify the internal WHMCS product associated with the subscription and apply our subscription id
            $products = $lastPayment->getProducts();
            foreach ($products as $paidProduct) {
                $recurringProductType = $paidProduct["custom"]["type"]; // hosting, addon, etc. included when we built the initial basket for the invoice
                $recurringProductRelid = $paidProduct["custom"]["relid"];

                if ($recurringProductType == "hosting") {
                    Capsule::table('tblhosting')->where('id', '=', intval($recurringProductRelid))->update([
                        'subscriptionid' => $subject->getReference(),
                    ]);
                } else if ($recurringProductType == "addon") {
                    Capsule::table('tblhostingaddons')->where('id', '=', intval($recurringProductRelid))->update([
                        'subscriptionid' => $subject->getReference(),
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
            break;
        case RECURRING_PAYMENT_ENDED:
            //TODO
            break;
        default:
            echo json_encode([
                "success" => false,
                "message" => "Unsupported recurring webhook type: " . $webhook->getType()
            ]);
            http_response_code(400);
    }
}

else if ($webhook->isType(PAYMENT_COMPLETED)) {
    // Handle payment notification webhooks, we need to update the paid invoice to have a status of Paid.
    $paymentSubject = $webhook->getSubject();

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

    http_response_code(200);
}

else { // Handle unrecognized webhooks
    echo json_encode([
        "success" => false,
        "message" => "Unsupported webhook type: " . $webhook->getType()
    ]);
    http_response_code(400);
}