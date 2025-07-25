<?php

namespace Tebex;

use GuzzleHttp\Client;
use TebexCheckout\ApiException;
use TebexCheckout\Configuration;
use TebexCheckout\Model\AddPackageRequest;
use TebexCheckout\Model\Basket;
use TebexCheckout\Model\CheckoutRequest;
use TebexCheckout\Model\CreateBasketRequest;
use TebexCheckout\Model\Package;
use TebexCheckout\Model\Payment;
use TebexCheckout\Model\RecurringPayment;
use TebexCheckout\Model\Sale;
use TebexCheckout\Model\UpdateSubscriptionRequest;
use TebexCheckout\TebexCheckout\BasketsApi;
use TebexCheckout\TebexCheckout\CheckoutApi;
use TebexCheckout\TebexCheckout\PaymentsApi;
use TebexCheckout\TebexCheckout\RecurringPaymentsApi;

class Checkout extends TebexAPI {
    protected static CheckoutApi $checkoutApi;
    protected static BasketsApi $basketsApi;
    protected static PaymentsApi $paymentsApi;
    protected static RecurringPaymentsApi $recurringPaymentsApi;

    public static function createBasket(CreateBasketRequest $request) : Basket {
        return self::$basketsApi->createBasket($request);
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
        self::$checkoutApi = new CheckoutApi(new Client(),
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

    public static function checkoutRequest(Basket $basket, array $items, Sale $sale = null) : Basket {
        $request = new CheckoutRequest([
            "basket" => $basket,
            "items"=> $items,
            "sale"=>$sale
        ]);
        return self::$checkoutApi->checkout($request);
    }

    /**
     * @throws ApiException if the row or basket is invalid.
     */
    public static function removeBasketRow(Basket $basket, int $rowId) {
        return self::$basketsApi->removeRowFromBasket($basket->getIdent(), $rowId);
    }

    public static function addSaleToBasket(Basket $basket, Sale $sale) : Basket {
        return self::$basketsApi->addSaleToBasket($basket->getIdent(), $sale);
    }

    /**
     * @throws ApiException
     */
    public static function getBasket(String $basketIdent) : Basket {
        return self::$basketsApi->getBasketById($basketIdent);
    }

    public static function getPayment(string $transactionId) : Payment {
        return self::$paymentsApi->getPaymentById($transactionId);
    }

    public static function refundPayment(string $transactionId) : Payment {
        return self::$paymentsApi->refundPaymentById($transactionId);
    }

    public static function isPaymentRefunded(Payment $payment) : bool {
        return $payment->getStatus()["description"] == "Refund";
    }

    public static function getRecurringPayment(string $ref) : RecurringPayment {
        return self::$recurringPaymentsApi->getRecurringPayment($ref);
    }

    /**
     * @throws ApiException
     */
    public static function cancelRecurringPayment(string $recurringPaymentId) : RecurringPayment {
        return self::$recurringPaymentsApi->cancelRecurringPayment($recurringPaymentId);
    }

    public function getApiBaseUrl(): string
    {
        return "https://checkout.tebex.io/api";
    }
}