<?php

namespace Tebex\Checkout;

use Tebex\Checkout;
use TebexCheckout\ApiException;
use TebexCheckout\Model\Basket;
use TebexCheckout\Model\CreateBasketRequest;
use TebexCheckout\Model\Package;
use TebexCheckout\TebexCheckout\BasketsApi;
use ValueError;

class PackageBuilder
{
    private string $_name;
    private float $_price;
    private string $_type;
    private int $_qty;
    private string $_expiryPeriod;
    private int $_expiryLength;
    private object $_custom;

    private function __construct()
    {
    }

    public static function new(): PackageBuilder
    {
        return new PackageBuilder();
    }

    /**
     * @throws ValueError
     */
    public function build(): Package
    {
        $missingParams = [];
        if (empty($this->_name)) {
            $missingParams[] = 'name';
        }
        if (empty($this->_price)) {
            $missingParams[] = 'price';
        }
        if (empty($this->_type)) {
            $missingParams[] = 'type';
        }
        if (empty($this->_qty) || $this->_qty < 1) {
            $missingParams[] = 'qty';
        }
        if ($this->_type === 'subscription') {
            if (empty($this->_expiryPeriod)) {
                $missingParams[] = 'expiry_period';
            }
            if (empty($this->_expiryLength)) {
                $missingParams[] = 'expiry_length';
            }
        }

        if (!empty($missingParams)) {
            throw new ValueError("The following required package parameters are missing or invalid: " . implode(', ', $missingParams));
        }

        $packageCreateData = [
            'name' => $this->_name,
            'price' => $this->_price,
            'type' => $this->_type,
            'qty' => $this->_qty,
            'expiry_period' => $this->_expiryPeriod ?? null,
            'expiry_length' => $this->_expiryLength ?? null,
            'custom' => $this->_custom ?? null,
        ];

        return new Package($packageCreateData);
    }

    public function name(string $name): PackageBuilder
    {
        $this->_name = $name;
        return $this;
    }

    public function price(float $price): PackageBuilder
    {
        $this->_price = $price;
        return $this;
    }

    public function type(string $type): PackageBuilder
    {
        $this->_type = $type;
        return $this;
    }

    public function oneTime(): PackageBuilder
    {
        $this->_type = 'single';
        return $this;
    }

    public function subscription(): PackageBuilder
    {
        $this->_type = 'subscription';
        return $this;
    }

    public function qty(int $qty): PackageBuilder
    {
        $this->_qty = $qty;
        return $this;
    }

    public function expiryPeriod(string $expiryPeriod): PackageBuilder
    {
        $this->_expiryPeriod = $expiryPeriod;
        return $this;
    }

    public function expiryLength(int $expiryLength): PackageBuilder
    {
        $this->_expiryLength = $expiryLength;
        return $this;
    }

    public function custom(array $custom): PackageBuilder
    {
        $this->_custom = json_decode(json_encode($custom), false);
        return $this;
    }
}