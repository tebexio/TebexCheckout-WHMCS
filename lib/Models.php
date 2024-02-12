<?php

/**
 * CheckoutBasketPayload represents the payload sent to https://checkout.tebex.io/api/baskets, creating a new basket
 *  in one request that can be used to pay for items.
 *
 * See https://docs.tebex.io/developers/checkout-api/endpoints#create-a-checkout-request
 *
 * Create new payloads with method chains:
 *      CheckoutBasketPayload::new()->firstName('foo')->lastName('bar')
 */
class CheckoutBasketPayload implements JsonSerializable {
    /** URL the customer can return to when clicking 'return to ACCOUNT'*/
    private string $return_url = "";
    /** URL the customer can return to after completing their payment */
    private string $complete_url = "";

    /** @var array Custom data passed and returned to us by the webhook */
    private array $custom;
    /** @var DateTime Basket expiration date, default 1 day */
    private DateTime $expires_at;

    /**
     * @var string Customer's first name
     */
    private string $first_name = "";
    /**
     * @var string Customer's last name
     */
    private string $last_name = "";

    /**
     * @var string Customer's email
     */
    private string $email = "";

    /**
     * @var bool If the basket contains recurring items
     */
    private bool $recurring = false;

    /** @var array Array of items in the basket */
    private array $basketItems = [];

    private function __construct() {
    }

    public static function new() {
        return new CheckoutBasketPayload();
    }

    public function recurring(bool $value) {
        $this->recurring = $value;
        return $this;
    }

    public function firstname(string $value) {
        $this->first_name = $value;
        return $this;
    }

    public function lastname(string $value) {
        $this->last_name = $value;
        return $this;
    }

    public function email(string $value) {
        $this->email = $value;
        return $this;
    }

    public function returnUrl(string $value) {
        $this->return_url = $value;
        return $this;
    }

    public function completeUrl(string $value) {
        $this->complete_url = $value;
        return $this;
    }

    public function custom(array $custom) {
        $this->custom = $custom;        
        return $this;
    }

    public function expires_at(DateTime $value) {
        $this->expires_at = $value;
        return $this;
    }

    /**
     * Sets the baskets items to the array provided, overwriting any other items in the basket
     * @return CheckoutBasketPayload
     */
    public function withItems(array $basketItems) {
        $this->basketItems = $basketItems;
        return $this;
    }

    public function addItem(BasketItem $item) {
        array_push($this->basketItems, $item);
        return $this;
    }

    public function items() {
        return $this->basketItems;
    }

    public function jsonSerialize() {
        return [
            'return_url' => $this->return_url,
            'complete_url' => $this->complete_url,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'expires_at' => $this->expires_at ?? (new DateTime('tomorrow'))->format('Y-m-d'),
            'custom' => $this->custom,
            'recurring' =>$this->recurring,
        ];
    }
}

class BasketItem implements JsonSerializable {
    private Package $package;
    private int $qty = 0;
    private string $type = "single"; // "single" or "subscription"
    private array $revenue_share = [];
    private Sale $sale;

    private function __construct() {
    }

    public static function new() {
        return new BasketItem();
    }

    public function package(Package $package) {
        $this->package = $package;

        if ($this->qty == 0) {
            $this->qty = 1;
        }

        if ($package->subscription == true) {
            $this->type = "subscription";
        }

        return $this;
    }

    public function quantity(int $quantity) {
        $this->quantity = $quantity;
        return $this;
    }

    public function revenueShare(object $revShareObj) {
        $this->revenueShare = $revShareObj;
        return $this;
    }

    public function sale(Sale $saleObj) {
        $this->sale = $saleObj;
        return $this;
    }

    public function jsonSerialize() {
        return [
            'package' => $this->package,
            'qty' => $this->qty,
            'type' => $this->type,
            'revenue_share' => $this->revenue_share,
            'sale' => $this->sale ?? [],
        ];
    }
}

class JsonData implements JsonSerializable {
    public function jsonSerialize() {
        return get_object_vars($this);
    }
}

class Sale extends JsonData {
    public int $id;
    public string $name;
    public $package_id; // null or another type
    public float $discountAmount;
    public string $type;
}

class LimitDetail extends JsonData {
    public bool $enabled;
    public int $timestamp;
    public $limit; // null or another type
}

class Limits extends JsonData {
    public LimitDetail $user;
    public LimitDetail $global;
    public int $packageExpiryTime;
}

class Meta extends JsonData {
    public string $name;
    public float $rowprice;
    public float $initialprice;
    public bool $isCumulative;
    public array $requiredPackages;
    public bool $requiresAny;
    public $category;
    public bool $producesGiftCard;
    public bool $allowsGiftCards;
    public array $servers;
    public Limits $limits;
    public bool $hasDeliverables;
    public $itemType; 
    public array $revenue_share;
    public string $custom;          // free-text string field that is passed back to you via the webhook
    public float $realprice;

    private function __construct() {

    }

    public static function new() {
        return new Meta();
    }

    public function custom(array $value) {
        $this->custom = json_encode($value);
        return $this;
    }
}

class Address extends JsonData {
    public string $name;
    public string $first_name;
    public string $last_name;
    public string $address;
    public string $email;
    public $state_id; // null or another type
    public string $country;
    public $postal_code; // null or another type
}

class Links extends JsonData {
    public string $checkout;
    public string $payment;
}

class Status {
    public $id;
    public $description;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->description = $data['description'];
    }
}

class Price {
    public $amount;
    public $currency;

    public function __construct($data) {
        $this->amount = $data['amount'];
        $this->currency = $data['currency'];
    }
}

class PaymentMethod {
    public $name;
    public $refundable;

    public function __construct($data) {
        $this->name = $data['name'];
        $this->refundable = $data['refundable'];
    }
}

class Fees {
    public $tax;
    public $gateway;

    public function __construct($data) {
        $this->tax = new Price($data['tax']);
        $this->gateway = new Price($data['gateway']);
    }
}

class Customer {
    public $first_name;
    public $last_name;
    public $email;
    public $ip;
    public $username;
    public $marketing_consent;
    public $country;
    public $postal_code;

    public function __construct($data) {
        $this->first_name = $data['first_name'];
        $this->last_name = $data['last_name'];
        $this->email = $data['email'];
        $this->ip = $data['ip'];
        $this->username = new Username($data['username']);
        $this->marketing_consent = $data['marketing_consent'];
        $this->country = $data['country'];
        $this->postal_code = $data['postal_code'];
    }
}

class Username {
    public $id;
    public $username;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->username = $data['username'];
    }
}

class Product {
    public $id;
    public $name;
    public $quantity;
    public $base_price;
    public $paid_price;
    public $variables;
    public $expires_at;
    public $custom;
    public $username;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->quantity = $data['quantity'];
        $this->base_price = new Price($data['base_price']);
        $this->paid_price = new Price($data['paid_price']);
        $this->variables = array_map(function ($variable) {
            return new Variable($variable);
        }, $data['variables']);
        $this->expires_at = $data['expires_at'];
        $this->custom = $data['custom'];
        $this->username = new Username($data['username']);
    }
}

class Variable {
    public $identifier;
    public $option;

    public function __construct($data) {
        $this->identifier = $data['identifier'];
        $this->option = $data['option'];
    }
}

class DeclineReason {
    public $code;
    public $message;

    public function __construct($data) {
        $this->code = $data['code'];
        $this->message = $data['message'];
    }
}

class RevenueShare implements JsonSerializable {
    public string $wallet_ref = "";
    public float $amount = 0;
    public float $gateway_fee_percent = 0;

    private function __construct() {
    }

    public static function new() {
        return new RevenueShare();
    }

    public function wallet_ref(string $value) {
        $this->wallet_ref = $value;
        return $this;
    }

    public function amount(float $value) {
        $this->amount = $value;
        return $this;
    }

    public function gateway_fee_percent(string $value) {
        $this->gateway_fee_percent = 0;
        return $this;
    }

    public function jsonSerialize() {
        return [
            'wallet_ref' => $this->wallet_ref,
            'amount' => $this->amount,
            'gateway_fee_percent' => $this->gateway_fee_percent,
        ];
    }
}

class Package implements JsonSerializable {
    private string $name = "";
    private float $price = 0.00;
    private ExpiryPeriod $expiry_period;
    private int $expiry_length = 1;
    private Meta $metadata;

    public bool $subscription = false;

    private function __construct() {

    }

    public static function new() {
        return new Package();
    }

    public function name(string $name) {
        $this->name = $name;
        return $this;
    }

    public function price(float $price) {
        $this->price = $price;
        return $this;
    }
    public function expiryPeriod(ExpiryPeriod $value) {
        $this->expiry_period = $value;
        return $this;
    }

    public function expiryLength(int $days) {
        $this->expiry_length = $days;
        return $this;
    }

    public function metadata(Meta $data) {
        $this->metadata = $data;
        return $this;
    }

    public function subscription(bool $value) {
        $this->subscription = $value;
        return $this;
    }

    public function jsonSerialize() {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'expiry_period' => $this->expiry_period != null ? $this->expiry_period->name : '',
            'expiry_length' => $this->expiry_length,
            'metaData' => $this->metadata ?? [],
        ];
    }
}

enum ExpiryPeriod {
    case day;
    case month;
    case year;
}