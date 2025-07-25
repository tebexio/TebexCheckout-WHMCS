<?php

namespace Tebex\Checkout;

use Tebex\Checkout;
use TebexCheckout\ApiException;
use TebexCheckout\Model\Basket;
use TebexCheckout\Model\CreateBasketRequest;

class BasketBuilder
{
    private string $_email;
    private string $_firstname;
    private string $_lastname;
    private string $_returnUrl;
    private string $_completeUrl;
    private string $_country;
    private array $_custom = [];

    private function __construct()
    {

    }

    public static function new() : BasketBuilder
    {
        return new BasketBuilder();
    }

    /**
     * @throws ApiException
     */
    public function build(): Basket
    {
        $missingParams = [];
        if (empty($this->_email)) {
            $missingParams[] = 'email';
        }
        if (empty($this->_returnUrl)) {
            $missingParams[] = 'return_url';
        }
        if (empty($this->_completeUrl)) {
            $missingParams[] = 'complete_url';
        }
        if (empty($this->_country)) {
            $missingParams[] = 'country';
        }
        if (empty($this->_firstname)) {
            $missingParams[] = 'firstname';
        }
        if (empty($this->_lastname)) {
            $missingParams[] = 'lastname';
        }
        if (!empty($missingParams)) {
            throw new ApiException("The following required basket parameters are missing: " . implode(', ', $missingParams));
        }

        $basketCreateData = [];
        $basketCreateData['email'] = $this->_email;
        $basketCreateData['return_url'] = $this->_returnUrl;
        $basketCreateData['complete_url'] = $this->_completeUrl;
        $basketCreateData['country'] = $this->_country;
        $basketCreateData['first_name'] = $this->_country;
        $basketCreateData['last_name'] = $this->_country;
        $basketCreateData['custom'] = $this->_custom;

        // Pass to OpenAPI SDK
        $createBasketRequest = new CreateBasketRequest($basketCreateData);
        return Checkout::createBasket($createBasketRequest);
    }

    public function email(string $email) : BasketBuilder {
        $this->_email = $email;
        return $this;
    }

    public function firstname(string $firstname) : BasketBuilder {
        $this->_firstname = $firstname;
        return $this;
    }

    public function lastname(string $lastname) : BasketBuilder {
        $this->_lastname = $lastname;
        return $this;
    }

    public function country(string $country) : BasketBuilder {
        $this->_country = $country;
        return $this;
    }

    public function returnUrl(string $returnUrl) : BasketBuilder {
        $this->_returnUrl = $returnUrl;
        return $this;
    }

    public function completeUrl(string $completeUrl) : BasketBuilder
    {
        $this->_completeUrl = $completeUrl;
        return $this;
    }

    public function custom(array $data) : BasketBuilder
    {
        $this->_custom = $data;
        return $this;
    }
}