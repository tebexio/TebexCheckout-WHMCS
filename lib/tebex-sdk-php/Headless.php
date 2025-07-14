<?php

namespace Tebex;

use GuzzleHttp\Client;
use TebexHeadless\ApiException;
use TebexHeadless\Configuration;
use TebexHeadless\Model\AddBasketPackageRequest;
use TebexHeadless\Model\ApplyCreatorCodeRequest;
use TebexHeadless\Model\Basket;
use TebexHeadless\Model\BasketLinks;
use TebexHeadless\Model\Coupon;
use TebexHeadless\Model\CreateBasketRequest;
use TebexHeadless\Model\GiftCard;
use TebexHeadless\Model\Package;
use TebexHeadless\TebexHeadless\HeadlessApi;

class Headless extends TebexAPI {
    protected static HeadlessApi $headlessApi;

    public static function getHeadlessApi() : HeadlessApi {
        return self::$headlessApi;
    }

    public static function setToken(string $publicToken) {
        self::$_publicToken = $publicToken;
        self::$_areApiKeysSet = true;
        self::$headlessApi = new HeadlessApi(new Client(), Configuration::getDefaultConfiguration());
    }

    /**
     * @throws ApiException
     */
    public static function createBasket(string $username, string $completeUrl, string $cancelUrl, bool $autoRedirect=true, array $custom=null) : Basket
    {
        $createBasketRequest = new CreateBasketRequest([
            "username" => $username, //TODO additional params
            "complete_url" => $completeUrl,
            "cancel_url" => $cancelUrl,
            "complete_auto_redirect" => $autoRedirect,
            "custom" => $custom ?? null,
        ]);

        return self::$headlessApi->createBasket(self::$_publicToken, $createBasketRequest)->getData();
    }

    /**
     * @throws ApiException
     */
    public static function getUserAuthUrl(Basket $basket, string $returnUrl) : string {
        return self::$headlessApi->getBasketAuthUrl(self::$_publicToken, $basket->getIdent(), $returnUrl);
    }

    /**
     * @return Package[]
     * @throws ApiException
     */
    public static function listPackages() : array
    {
        return self::$headlessApi->getAllPackages(self::$_publicToken)->getData();
    }

    /**
     * @throws ApiException
     */
    public static function listCategories() : array
    {
        return self::$headlessApi->getAllCategories(self::$_publicToken)->getData();
    }

    /**
     * @throws ApiException
     */
    public static function addPackage(Basket $basket, Package $package, $variableData=null, int $qty=1) : Basket
    {
        $addBasketPackageRequest = new AddBasketPackageRequest([
            "package_id" => $package->getId(),
            "quantity" => $qty,
            "variable_data" => $variableData,
        ]);
        return self::$headlessApi->addBasketPackage($basket->getIdent(), $addBasketPackageRequest);
    }

    public static function addPackageWithGiftCardDeliverable(Basket $basket, Package $package, string $giftcardToEmail, $qty=1) : Basket {
        $addBasketPackageRequest = new AddBasketPackageRequest([
            "package_id" => $package->getId(),
            "quantity" => $qty,
            "variable_data" => [
                "giftcard_to" => $giftcardToEmail,
            ],
        ]);
        return self::$headlessApi->addBasketPackage($basket->getIdent(), $addBasketPackageRequest);
    }

    public static function addPackageWithDiscordDeliverable(Basket $basket, Package $package, string $discordId, $qty=1) : Basket {
        $addBasketPackageRequest = new AddBasketPackageRequest([
            "package_id" => $package->getId(),
            "quantity" => $qty,
            "variable_data" => [
                "discord_id" => $discordId,
            ],
        ]);
        return self::$headlessApi->addBasketPackage($basket->getIdent(), $addBasketPackageRequest);
    }

    public static function addPackageWithGameServerCommand(Basket $basket, Package $package, string $usernameId, $qty=1) : Basket {
        $addBasketPackageRequest = new AddBasketPackageRequest([
            "package_id" => $package->getId(),
            "quantity" => $qty,
            "variable_data" => [
                "username_id" => $usernameId,
            ],
        ]);
        return self::$headlessApi->addBasketPackage($basket->getIdent(), $addBasketPackageRequest);
    }

    public static function addPackageWithCustomData(Basket $basket, Package $package, object $variableData, object $customData, $qty=1) : Basket {
        $addBasketPackageRequest = new AddBasketPackageRequest([
            "package_id" => $package->getId(),
            "quantity" => $qty,
            "variable_data" => $variableData,
            "custom" => $customData
        ]);
        return self::$headlessApi->addBasketPackage($basket->getIdent(), $addBasketPackageRequest);
    }

    public static function addPackageAsGiftToOther(Basket $basket, Package $package, string $targetUsernameId, $variableData=null, int $qty=1) : Basket
    {
        $addGiftedPackageRequest = new AddBasketPackageRequest([
            "package_id" => $package->getId(),
            "quantity" => $qty,
            "variable_data" => $variableData,
            "target_username_id" => $targetUsernameId
        ]);
        return self::$headlessApi->addBasketPackage($basket->getIdent(), $addGiftedPackageRequest);
    }

    public static function addPackageForTargetGameServer(Basket $basket, Package $package, string $usernameId, int $serverId, $qty=1) : Basket {
        $addGiftedPackageRequest = new AddBasketPackageRequest([
            "package_id" => $package->getId(),
            "quantity" => $qty,
            "variable_data" => [
                "username_id" => $usernameId,
                "server_id" => $serverId,
            ]
        ]);
        return self::$headlessApi->addBasketPackage($basket->getIdent(), $addGiftedPackageRequest);
    }

    /**
     * @throws ApiException
     */
    public static function addCreatorCodeToBasket(Basket $basket, string $code) : Basket {
        return self::$headlessApi->applyCreatorCode(self::$_publicToken, $basket->getIdent(), new ApplyCreatorCodeRequest([
            'creator_code' => $code,
        ]))->getData();
    }

    /**
     * @throws ApiException
     */
    public static function removeCreatorCodeFromBasket(Basket $basket, string $code) {
        //FIXME no body documented?
        self::$headlessApi->removeCreatorCode(self::$_publicToken, $basket->getIdent(), $code);
    }

    /**
     * @throws ApiException
     */
    public static function addCouponToBasket(Basket $basket, string $couponCode) : Basket {
        $coupon = new Coupon([
            "coupon_code" => $couponCode,
        ]);
        return self::$headlessApi->applyCoupon(self::$_publicToken, $basket->getIdent(), $coupon)->getData();
    }

    /**
     * @throws ApiException
     */
    public static function removeCouponFromBasket(Basket $basket, string $couponCode) {
        //FIXME no body documented?
        self::$headlessApi->removeCoupon(self::$_publicToken, $basket->getIdent());
    }

    /**
     * @throws ApiException
     */
    public static function addGiftCardToBasket(Basket $basket, string $cardNumber) : Basket {
        $giftCard = new GiftCard([
            "card_number" => $cardNumber,
        ]);
        return self::$headlessApi->applyGiftCard(self::$_publicToken, $basket->getIdent(), $giftCard)->getData();
    }

    /**
     * @throws ApiException
     */
    public static function removeGiftCardFromBasket(Basket $basket, string $couponCode) {
        //FIXME no body documented?
        self::$headlessApi->removeGiftCard(self::$_publicToken, $basket->getIdent());
    }

    public static function getCheckoutLinks(Basket $basket) : BasketLinks
    {
        return $basket->getLinks();
    }

    public function getApiBaseUrl(): string
    {
        return "https://headless.tebex.io/api";
    }
}