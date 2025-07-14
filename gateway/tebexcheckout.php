<?php
/**
 * Tebex Payment Gateway for WHMCS
 *
 * Integrates with Tebex Checkout to allow using Tebex as a payment gateway in WHMCS.
 *
 * We allow use of WHMCS's internal basket, and create a new checkout request by calling 
 *  POST /checkout and providing all details (basket, items and sale details) 
 *  in a single request.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * Features
 * - Enables Tebex Checkout as a payment gateway with over 120 payment options
 * - Supports mixed baskets (single and recurring payments in a single basket)
 * - Togglable support for subscription payments on recurring items
 * - Subscription upgrade/downgrade with prorata billing/credit
 * 
 * @copyright Tebex.io
 * @license MIT License
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Tebex\Checkout;
use TebexCheckout\Model\CheckoutItem;
use TebexCheckout\Model\Package;
use WHMCS\Database\Capsule;

/**
 * WHMCS helper functions
 */
function moduleLog(string $action, mixed $request, mixed $response) {
    /**
     * Log module call.
     *
     * @param string $module The name of the module
     * @param string $action The name of the action being performed
     * @param string|array $requestString The input parameters for the API call
     * @param string|array $responseData The response data from the API call
     * @param string|array $processedData The resulting data after any post processing (eg. json decode, xml decode, etc...)
     * @param array $replaceVars An array of strings for replacement
     */
    logModuleCall("Tebex Checkout", $action, $request, $response, $response, []);
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function tebexcheckout_MetaData()
{
    return array(
        'DisplayName' => 'Tebex Checkout',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function tebexcheckout_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Tebex Checkout',
        ),
        'accountID' => array(
            'FriendlyName' => 'Account ID',
            'Type' => 'text',         // a text field type allows for single line text input
            'Size' => '50',
            'Default' => '',
            'Description' => 'Project / Account ID (you can get this from https://creator.tebex.io/developers/api-keys)',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'password',         // a password field type allows for masked text input
            'Size' => '25',
            'Default' => '',
            'Description' => 'Your Private Key (you can get this from https://creator.tebex.io/developers/api-keys)',
        ),
        'webhookSecretKey' => array(
            'FriendlyName' => 'Webhook Secret',
            'Type' => 'password',
            'Size' => '30',
            'Default' => '',
            'Description' => 'Your webhook secret key used to sign webhook requests sent by us. (get this from https://creator.tebex.io/webhooks/endpoints)',
        ),
        'allowSubscriptions' => array(
            'FriendlyName' => 'Allow Subscriptions',
            'Type' => 'yesno',
            'Description' => 'Tick to enable subscription payments. If disabled, automatic payments will not be set up on recurring items.',
        ),
        'automaticErrorReporting' => array(
            'FriendlyName' => 'Automatic Error Reporting',
            'Type' => 'yesno',
            'Default' => true,
            'Description' => 'Tick to automatically report exceptions and errors while using the payment gateway.',
        ),
    );
}

/**
 * Generates the payment link for an invoice.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function tebexcheckout_link($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $apiKey = $params['apiKey'];
    $sandboxMode = $params['sandboxMode'];
    $allowSubscriptions = $params['allowSubscriptions'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $url = 'https://checkout.tebex.io/api/checkout';

    //base post fields
    $postfields = array();
    $postfields['username'] = "";
    $postfields['invoice_id'] = $invoiceId;
    $postfields['description'] = $description;
    $postfields['amount'] = $amount;
    $postfields['currency'] = $currencyCode;
    $postfields['first_name'] = $firstname;
    $postfields['last_name'] = $lastname;
    $postfields['email'] = $email;
    $postfields['address1'] = $address1;
    $postfields['address2'] = $address2;
    $postfields['city'] = $city;
    $postfields['state'] = $state;
    $postfields['postcode'] = $postcode;
    $postfields['country'] = $country;
    $postfields['phone'] = $phone;
    $postfields['callback_url'] = $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php';
    $postfields['return_url'] = $returnUrl;

    Checkout::setApiKeys($accountId, $apiKey);
    $basket = Checkout\BasketBuilder::new()
        ->email($email)
        ->firstname($firstname)->lastname($lastname)
        ->country($country)
        ->returnUrl($returnUrl)->completeUrl($returnUrl)
        ->custom([ // provided via webhook to mark invoice as paid
            "invoiceId" => $invoiceId
        ])->build();

    $checkoutItems = [];
    $sale = null;

    // Build a list of basket items for checkout based on the items on the invoice.
    $invoiceItems = localAPI("GetInvoice", ["invoiceid" => $invoiceId])["items"]["item"];
    foreach ($invoiceItems as $item) {
        // Look up and add items by type to the basket
        if ($item["type"] == "Hosting") {
            $hosting = Capsule::table("tblhosting")->where("id", $item["relid"])->first();
            $checkoutItems = _addHostingCheckoutItem($item, $hosting, $checkoutItems, $allowSubscriptions);
        } else if ($item["type"] == "Addon") {
            $addon = Capsule::table("tblhostingaddons")->where("id", $item["relid"])->first();
            $checkoutItems = _addAddonCheckoutItem($item, $addon, $checkoutItems, $allowSubscriptions);
        } else { // all other line items are considered ad hoc and billed directly as a single payment
            $checkoutPackage = Checkout\PackageBuilder::new()
                ->name($item["description"])
                ->price(floatval($item["amount"]))
                ->custom([
                    "relid" => $item["relid"],
                ])
                ->oneTime()
                ->qty(1)->build();

            $checkoutItem = new CheckoutItem([
                'package' => $checkoutPackage
            ]);
            array_push($checkoutItems, $checkoutItem);
        }
    }

    // Create the basket using a checkout request - the response will contain the payment link to display to the customer
    $checkoutBasket = null;
    try {
        $checkoutBasket = Checkout::checkoutRequest($basket, $checkoutItems);
        moduleLog("created Tebex basket", [$basket, $checkoutItems], $checkoutBasket);
    } catch (TebexCheckout\ApiException $e) {
        moduleLog("failed creating Tebex basket", [$basket, $checkoutItems], $e->getResponseBody());

        $event = new PluginEvent($accountId, "ERROR", "failed creating Tebex basket");
        $event = $event->withMetadata([
            "request_basket" => $checkoutBasket,
            "request_items" => $checkoutItems,
            "response" => $e->getResponseBody()
        ]);
        $event = $event->withFrameworkVersion($whmcsVersion)->withPluginVersion("2.1.0")->withRuntimeVersion(phpversion());
        $event->send();

        return '<span style="color: red">Error! Failed to create Tebex basket. See module log.</span>';
    }

    // successful response contains a checkout link
    $links = $checkoutBasket->getLinks();

    // add payment link as action target for our payment form
    $htmlOutput = '<form method="get" action="' . $links->getCheckout() . '">';

    // Tebex Logo
    $htmlOutput .= '<svg height=70 width=120>
    <path d="M12.6145 13.9757C14.7114 10.5311 18.5086 9.53311 18.5086 9.53311C18.5086 9.53311 11.2615 7.62507 11.2615 0C11.2615 7.62507 4.01015 9.53311 4.01015 9.53311C4.01015 9.53311 7.8088 10.5311 9.90702 13.9757H0V27.1818L2.25229 23.0921H6.75548V45.8837L15.7633 55V27.0939C13.4661 26.0222 10.1661 23.2976 8.98397 21.2195C11.0023 21.8361 13.7099 22.647 15.8179 23.0935H22.5201V13.9757H12.6145Z" fill="currentcolor"/>
    <path fill-rule="evenodd" clip-rule="evenodd" d="M62.7137 26.7386C63.88 25.1377 65.6939 24.2603 67.8705 24.2603H67.8732C72.0679 24.2603 74.997 27.4432 74.997 31.8678C74.997 36.2925 71.9793 39.5024 67.8168 39.5024C65.5917 39.5024 63.8209 38.6682 62.6707 37.0835L62.5955 39.1487H59.4165V18.7234H62.7137V26.7386ZM67.6394 36.7056C70.1923 36.7056 71.9739 34.7564 71.9739 31.9677C71.9739 29.1142 70.3884 27.2002 67.8033 27.2002C65.2182 27.2002 63.4124 29.0899 63.4124 31.9677C63.4124 34.8455 65.0865 36.7056 67.6394 36.7056ZM39.7703 36.2331C38.9534 36.8594 38.3031 37.1024 37.4458 37.1024C36.1829 37.1024 35.4573 36.3303 35.4573 34.9805V27.3622H40.7565V24.4304H35.4573V20.6239H32.1601V24.4277H29.8679L29.1208 27.3595H32.1601V35.0075C32.1601 38.3415 33.9256 40.1773 37.1341 40.1773C38.5987 40.1773 39.9046 39.7237 41.122 38.7897L41.2106 38.7222L39.8993 36.1332L39.7703 36.2304V36.2331ZM49.3986 24.1442C45.1823 24.1442 42.1243 27.3568 42.1243 31.7815C42.1243 36.4383 45.0345 39.4457 49.5356 39.4457C52.1073 39.4457 54.0287 38.6574 55.5765 36.9647L55.6571 36.8784L53.9077 34.7592L53.8029 34.8617C52.5964 36.0469 51.3495 36.576 49.7533 36.576C47.2488 36.576 45.709 35.1965 45.4054 32.6831H56.2376V32.5482C56.2376 32.4132 56.2429 32.2863 56.251 32.1594C56.259 32.0271 56.2644 31.8949 56.2644 31.7545C56.2644 27.1327 53.5691 24.1442 49.3986 24.1442ZM45.4726 30.5126C45.8918 28.4096 47.4074 27.0598 49.3717 27.0598C51.3361 27.0598 52.6636 28.3152 52.9538 30.5126H45.4726ZM83.9455 24.1442C79.7292 24.1442 76.6712 27.3568 76.6712 31.7815C76.6712 36.4383 79.5814 39.4457 84.0825 39.4457C86.6542 39.4457 88.5755 38.6574 90.1234 36.9647L90.204 36.8784L88.4546 34.7592L88.3498 34.8617C87.1432 36.0469 85.8964 36.576 84.3002 36.576C81.7957 36.576 80.2559 35.1965 79.9523 32.6831H90.7844V32.5482C90.7844 32.4132 90.7898 32.2863 90.7979 32.1594C90.8059 32.0271 90.8113 31.8949 90.8113 31.7545C90.8113 27.1327 88.116 24.1442 83.9455 24.1442ZM83.9186 27.0571C85.9125 27.0571 87.2104 28.3125 87.5006 30.51H80.0194C80.4386 28.4069 81.9542 27.0571 83.9186 27.0571ZM105.169 24.4277L100.055 31.4278L105.747 39.1487H101.829L98.1528 33.8791L94.5573 39.1487H90.8328L96.3308 31.6222L91.08 24.4277H94.998L98.2334 29.2222L101.442 24.4277H105.169Z" fill="currentcolor"/>
    </svg>';

    // Submit button
    $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
    $htmlOutput .= '</form>';

    return $htmlOutput;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see 
 *
 * @return array Transaction response status
 */
function tebexcheckout_refund($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $apiKey = $params['apiKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Transaction Parameters
    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    Checkout::setApiKeys($accountId, $apiKey);
    try{
        // look up the payment
        $payment = Checkout::getPayment($transactionIdToRefund);
        if (Checkout::isPaymentRefunded($payment)) {
            moduleLog("api refund - already refunded", $transactionIdToRefund, $payment);
            return array(
                'status' => 'success',
                'rawdata' => $payment,
                'transid' => $transactionIdToRefund,
                'fees' => $payment->getFees()["tax"]["amount"] + $payment->getFees()["gateway"]["amount"]
            );
        }

        // otherwise process a new refund TODO partial refund?
        $refundedPayment = Checkout::refundPayment($transactionIdToRefund);
    } catch (TebexCheckout\ApiException $e) {
        moduleLog("failed refunding payment", $payment, $e->getResponseBody());
        $event = new PluginEvent($accountId, "ERROR", "failed refunding payment");
        $event = $event->withMetadata([
            "request_transaction_id" => $transactionIdToRefund,
            "response" => $e->getResponseBody()
        ]);
        $event = $event->withFrameworkVersion($whmcsVersion)->withPluginVersion("2.1.0")->withRuntimeVersion(phpversion());
        $event->send();
        return array(
            'status' => 'error'
        );
    }

    moduleLog("api refund - new refund", $transactionIdToRefund, $refundedPayment);
    if (Checkout::isPaymentRefunded($refundedPayment)) {
        return array(
            'status' => 'success',
            'rawdata' => $refundedPayment,
            'transid' => $transactionIdToRefund,
            'fees' => $refundedPayment->getFees()["tax"]["amount"] + $refundedPayment->getFees()["gateway"]["amount"]
        );
    } else {
        return array(
            'status' => 'error',
            'rawdata' => $refundedPayment,
            'transid' => $transactionIdToRefund,
            'fees' => $refundedPayment->getFees()["tax"]["amount"] + $refundedPayment->getFees()["gateway"]["amount"]
        );
    }
}

/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/subscription-management/
 *
 * @return array Transaction response status
 */
function tebexcheckout_cancelSubscription($params)
{
    // Gateway Configuration Parameters
    $accountId = $params['accountID'];
    $apiKey = $params['apiKey'];
    $testMode = $params['testMode'];
    $dropdownField = $params['dropdownField'];
    $radioField = $params['radioField'];
    $textareaField = $params['textareaField'];

    // Subscription Parameters
    $subscriptionIdToCancel = $params['subscriptionID'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    Checkout::setApiKeys($accountId, $apiKey);
    $cancelledPayment = Checkout::cancelRecurringPayment($subscriptionIdToCancel);
    moduleLog("api subscription cancel", $subscriptionIdToCancel, $cancelledPayment);

    return array(
        'status' => 'success',
        'rawdata' => $cancelledPayment
    );
}

/**
 * @param $invoiceItem      \WHMCS\InvoiceItem
 * @param $hostingObject    \WHMCS\Hosting
 * @param $checkoutItems    \TebexCheckout\CheckoutItem[]
 * 
 * @return \TebexCheckout\CheckoutItem[]
 */
function _addHostingCheckoutItem($invoiceItem, $hostingObject, $checkoutItems, $allowSubscriptions) {
    if ($hostingObject->setupfee > 0.00) {
        // when we have a setup fee, split into one package for the single fee and the other for the recurring portion
        $oneTimeSetupFeePackage = new Package([
            "name" => "One Time Setup Fee - " . $invoiceItem["description"],
            "price" => floatval($hostingObject->setupfee),
            "custom" => [
                "relid" => $invoiceItem["relid"],
                "type" => "hosting"
            ],
            "type" => "single",
            "quantity" => 1
        ]);
        array_push($checkoutItems, new CheckoutItem(['package' => $oneTimeSetupFeePackage]));
    }

    // basic assumptions for hosting
    $expiry_period = "month";
    $expiry_length = 1;
    $payment_type = "subscription";

    // translate WHMCS billing cycles to Tebex billing cycles based on the addon we looked up
    if ($hostingObject->billingcycle == "Monthly") {
        $expiry_period = "month";
        $expiry_length = 1;
    } else if ($hostingObject->billingcycle == "Quarterly") {
        $expiry_period = "month";
        $expiry_length = 3;
    } else if ($hostingObject->billingcycle == "Semi-Annually") {
        $expiry_period = "month";
        $expiry_length = 6;
    } else if ($hostingObject->billingcycle == "Annually") {
        $expiry_period = "year";
        $expiry_length = 1;
    } else if ($hostingObject->billingcycle == "Bienially") {
        $expiry_period = "year";
        $expiry_length = 2;
    } else if ($hostingObject->billingcylce == "Trienially") {
        $expiry_period = "year";
        $expiry_length = 3;
    }

    // disable subscription if configured
    if (!$allowSubscriptions && $payment_type == "subscription") {
        $payment_type = "single";
    }

    // add the recurring portion to the basket
    $recurringHostingPackage = Checkout\PackageBuilder::new()
        ->name(mb_strimwidth($invoiceItem["description"], 0, 61, "...", "UTF-8"))
        ->price(floatval($hostingObject->amount))
        ->expiryLength($expiry_length)->expiryPeriod($expiry_period)
        ->custom([
            "relid" => $invoiceItem["relid"],
            "type" => "hosting"
        ])
        ->type($payment_type)
        ->qty(1)->build();

    $hostingCheckoutItem = new CheckoutItem([
        'package' => $recurringHostingPackage
    ]);
 
    array_push($checkoutItems, $hostingCheckoutItem);
    return $checkoutItems;
}

function _addAddonCheckoutItem($invoiceItem, $addonObject, $checkoutItems, $allowSubscriptions) {
    if ($addonObject->setupfee != 0.00) {
        // when we have a setup fee, split into one package for the single fee and the other for the recurring portion
        $oneTimeSetupFeePackage = Checkout\PackageBuilder::new()
            ->name("One Time Setup Fee - " . mb_strimwidth($invoiceItem["description"], 0, 61, "...", "UTF-8"))
            ->price(floatval($addonObject->setupfee))
            ->custom([
                "relid" => $invoiceItem["relid"],
                "type" => "addon"
            ])
            ->oneTime()->qty(1)->build();
        array_push($checkoutItems, new CheckoutItem(['package' => $oneTimeSetupFeePackage]));
    }

    // basic assumptions for an addon
    $expiry_period = "month";
    $expiry_length = 1;
    $payment_type = "subscription";

    // translate WHMCS billing cycles to Tebex billing cycles based on the addon we looked up
    if ($addonObject->billingcycle == "Monthly") {
        $expiry_period = "month";
        $expiry_length = 1;
    } else if ($addonObject->billingcycle == "Quarterly") {
        $expiry_period = "month";
        $expiry_length = 3;
    } else if ($addonObject->billingcycle == "Semi-Annually") {
        $expiry_period = "month";
        $expiry_length = 6;
    } else if ($addonObject->billingcycle == "Annually") {
        $expiry_period = "year";
        $expiry_length = 1;
    } else if ($addonObject->billingcycle == "Bienially") {
        $expiry_period = "year";
        $expiry_length = 2;
    } else if ($addonObject->billingcylce == "Trienially") {
        $expiry_period = "year";
        $expiry_length = 3;
    }

    // disable subscription if configured
    if (!$allowSubscriptions && $payment_type == "subscription") {
        $payment_type = "single";
    }

    $addonPackage = Checkout\PackageBuilder::new()
        ->name(mb_strimwidth($invoiceItem["description"], 0, 61, "...", "UTF-8"))
        ->price(floatval($addonObject->recurring))
        ->expiryPeriod($expiry_period)->expiryLength($expiry_length)
        ->type($payment_type)->qty(1)
        ->custom([
            "relid" => $invoiceItem["relid"],
            "type" => "addon"
        ])->build();

    $addonCheckoutItem = new CheckoutItem([
        'package' => $addonPackage
    ]);
 
    array_push($checkoutItems, $addonCheckoutItem);
    return $checkoutItems;
}