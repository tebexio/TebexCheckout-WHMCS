<?php

class TebexCheckoutWebhook {
    public $id;
    public $type;
    public $date;
    public $subject;

    public function __construct($data) {
        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->date = $data['date'];

        if ($data["type"] == "validation.webhook") {
            $this->subject = [];
        } else if (str_contains($this->type, "recurring")) {
            $this->subject = new RecurringPaymentWebhookSubject($data['subject']);
        } else {
            $this->subject = new PaymentWebhookSubject($data['subject']);
        }
    }
}

class PaymentWebhookSubject {
    public $transaction_id;
    public $status;
    public $payment_sequence;
    public $created_at;
    public $price;
    public $price_paid;
    public $payment_method;
    public $fees;
    public $customer;
    public $products;
    public $coupons;
    public $gift_cards;
    public $recurring_payment_reference;
    public $decline_reason;

    public function __construct($data) {
        $this->transaction_id = $data['transaction_id'];
        $this->status = new Status($data['status']);
        $this->payment_sequence = $data['payment_sequence'];
        $this->created_at = $data['created_at'];
        $this->price = new Price($data['price']);
        $this->price_paid = new Price($data['price_paid']);
        $this->payment_method = new PaymentMethod($data['payment_method']);
        $this->fees = new Fees($data['fees']);
        $this->customer = new Customer($data['customer']);
        $this->products = array_map(function ($product) {
            return new Product($product);
        }, $data['products']);
        $this->coupons = $data['coupons']; 
        $this->gift_cards = $data['gift_cards']; 
        $this->recurring_payment_reference = $data['recurring_payment_reference'];
        $this->decline_reason = new DeclineReason($data['decline_reason']);
    }
}

class RecurringPaymentWebhookSubject
{
    public $reference;
    public $created_at;
    public $next_payment_at;
    public $status;
    public $initial_payment;
    public $last_payment;
    public $fail_count;
    public $price;
    public $cancelled_at;
    public $cancel_reason;

    public function __construct($data)
    {
        $this->reference = $data['reference'];
        $this->created_at = $data['created_at'];
        $this->next_payment_at = $data['next_payment_at'];
        $this->status = new Status($data['status']);
        $this->initial_payment = new PaymentWebhookSubject($data['initial_payment']);
        $this->last_payment = new PaymentWebhookSubject($data['last_payment']);
        $this->fail_count = $data['fail_count'];
        $this->price = new Price($data['price']);
        $this->cancelled_at = $data['cancelled_at'];
        $this->cancel_reason = $data['cancel_reason'];
    }
}