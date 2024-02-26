<?php
/**
 * Tebex Payment Gateway for WHMCS Callback
 *
 * This sample file demonstrates how a payment gateway callback should be
 * handled within WHMCS.
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging and Adding Payment to an Invoice.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://docs.tebex.io/developers/webhooks/overview/
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 * 
 * @copyright Tebex.io
 * @license MIT License
 */

// Require libraries needed for gateway module functions.
if (substr(__DIR__, -strlen('tebexcheckout/callback')) === 'tebexcheckout/callback') {
    require_once __DIR__ . '/../../../../init.php';
    require_once __DIR__ . '/../../../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../../../includes/invoicefunctions.php';
    require_once __DIR__ . '/../../tebexcheckout/lib/WebhookSubjects.php';
} else { // Production require dirs
    require_once __DIR__ . '/../../../init.php';
    require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../../includes/invoicefunctions.php';
    require_once __DIR__ . '/../tebexcheckout/lib/WebhookSubjects.php';
}


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
    echo "Hash Verification Failure - Check Webhook Secret Key";
    return $success;
}

$data = json_decode($json, true);

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * @param string $gatewayName        Display label
 * @param string $debugData          Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $json, $data["type"]);

$paymentWebhook = new TebexCheckoutWebhook($data);

// Validation webhook wants us to return the ID in a json format
if ($paymentWebhook->type == "validation.webhook") {
    echo(json_encode(["id" => $paymentWebhook->id]));
    return;
}

$paymentWebhookSubject = $paymentWebhook->subject;

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$success = strpos($data["type"], "declined") === false;

$invoiceId = $data["subject"]["custom"]["invoiceId"] ?? $data["subject"]["last_payment"]["custom"]["invoiceId"];
$transactionId = $paymentWebhookSubject->transaction_id;
$paymentAmount = $paymentWebhookSubject->price_paid->amount;
$paymentFee = $paymentWebhookSubject->fees->gateway->amount;
$transactionStatus = $success ? 'Success' : 'Failure';


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


if ($success) {

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

    // Add subscription ID to a recurring payment product if applicable
    if ($data["type"] == "recurring-payment.started") {
        $subscriptionRef = json_decode($data["subject"]["initial_payment"]["products"][0]["custom"], true); //json encoded meta custom value containing relid
        $hosting = Capsule::table('tblhosting')->where('id', '=', $subscriptionRef["relid"])->update([
            'subscriptionid' => $paymentWebhook->subject->reference,
        ]);
        if ($hosting != null) {
            echo "Subscription ID set successfully";
        } else {
            echo "Could not find hosting object";
        }
    }
}