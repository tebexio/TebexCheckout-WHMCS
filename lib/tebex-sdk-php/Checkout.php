<?php

namespace Tebex;

use GuzzleHttp\Client;
use TebexCheckout\ApiException;
use TebexCheckout\Configuration;
use TebexCheckout\Model\AddPackageRequest;
use TebexCheckout\Model\Basket;
use TebexCheckout\Model\CreateBasketRequest;
use TebexCheckout\Model\Package;
use TebexCheckout\Model\Payment;
use TebexCheckout\Model\RecurringPayment;
use TebexCheckout\Model\Sale;
use TebexCheckout\TebexCheckout\BasketsApi;
use TebexCheckout\TebexCheckout\PaymentsApi;
use TebexCheckout\TebexCheckout\RecurringPaymentsApi;

class Checkout extends TebexAPI {
    protected static BasketsApi $basketsApi;
    protected static PaymentsApi $paymentsApi;
    protected static RecurringPaymentsApi $recurringPaymentsApi;

    public static function createBasket(CreateBasketRequest $request) : Basket {
        return self::$basketsApi->createBasket();
    }

    public static function setApiKeys(string $projectId, string $privateKey) {
        self::$_projectId = $projectId;
        self::$_privateKey = $privateKey;
        self::$_areApiKeysSet = true;

        self::$basketsApi = new BasketsApi(new Client(),
            Configuration::getDefaultConfiguration()->setUsername(self::$_projectId)->setPassword(self::$_privateKey));
        self::$paymentsApi = new PaymentsApi(new Client(),
            Configuration::getDefaultConfiguration()->setUsername(self::$_projectId)->setPassword(self::$_privateKey));
        self::$recurringPaymentsApi = new RecurringPaymentsApi(new Client(),
            Configuration::getDefaultConfiguration()->setUsername(self::$_projectId)->setPassword(self::$_privateKey));
    }

    public static function areApiKeysSet() : bool
    {
        return !empty(Checkout::$_privateKey)
            && !empty(Checkout::$_publicToken)
            && !empty(Checkout::$_projectId)
            && is_string(Checkout::$_privateKey)
            && is_string(Checkout::$_publicToken)
            && is_string(Checkout::$_projectId);
    }

    /**
     * @throws ApiException
     */
    public static function addPackage(Basket $basket, Package $package) : Basket
    {
        $addPackageRequest = new AddPackageRequest([
            "package" => $package,
            "qty" => $package->getQty(),
            "type" => $package->getType(),
        ]);
        return self::$basketsApi->addPackage($basket->getIdent(), $addPackageRequest);
    }

    /**
     * @param Basket $basket
     * @param array $items An array of CheckoutItems to apply to the basket
     * @param Sale|null $sale
     * @return void
     */
    public static function checkoutRequest(Basket $basket, array $items, Sale $sale = null) : Basket {
        //TODO
    }

    public static function removeBasketRow(Basket $basket, int $rowId) : Basket {
        //TODO
    }

    public static function addSaleToBasket(Basket $basket, Sale $sale) : Basket {
        //TODO
    }

    /**
     * @throws ApiException
     */
    public static function getBasket(String $basketIdent) : Basket {
        return self::$basketsApi->getBasketById($basketIdent);
    }

    public static function getPayment(string $transactionId) : Payment {
        //TODO
    }

    public static function refundPayment(string $transactionId) : Payment {
        //TODO
    }

    public static function isPaymentRefunded(Payment $payment) : bool {
        return $payment->getStatus()["description"] == "Refund";
    }

    public static function getRecurringPayment() : RecurringPayment {
        //TODO
    }

    public static function updateSubscriptionProduct() {
        //TODO
    }

    /**
     * @throws ApiException
     */
    public static function cancelRecurringPayment(string $recurringPaymentId) : RecurringPayment {
        return self::$recurringPaymentsApi->cancelRecurringPayment($recurringPaymentId);
    }

    public static function pauseRecurringPayment() {
        //TODO
    }

    public static function reactivateRecurringPayment() {
        //TODO
    }

    public function getApiBaseUrl(): string
    {
        return "https://checkout.tebex.io/api";
    }
}