<?php

namespace Tebex\Webhook;

use stdClass;
use Tebex\Util\StringUtil;
use TebexCheckout\Model\PaymentSubject;
use TebexCheckout\Model\RecurringPaymentSubject;
use ValueError;

/**
 * Base class inherited by all webhooks. Create a webhook instance using Webhook::fromJson() with the received JSON data
 * from Tebex.
 *
 * @see Webhook::fromJson()
 */
class Webhook {
    /**
     * @var string The JSON data provided at initialization (not expected to be encoded)
     */
    protected string $_rawJson;

    /**
     * @var string The $_rawJson data which has been decoded and then re-encoded. This is expected to remove any
     * "pretty-printed" JSON which would interferes with webhook signature validation.
     */
    protected string $_encodedJson;

    /**
     * @var string The webhook's unique ID.
     */
    private string $_id;

    /**
     * @var string The webhook's type.
     */
    private string $_type;

    /**
     * @var string The date the webhook was sent as a string.
     */
    private string $_date;

    /**
     * @var object|mixed|stdClass|PaymentSubject|RecurringPaymentSubject Data about the webhook's action.
     */
    private object $_subject;

    private function __construct($rawJson) {
        $this->_rawJson = $rawJson;
        $decodedJson = json_decode($rawJson, true);
        if (!$decodedJson) {
            throw new ValueError("Invalid or malformed webhook JSON: " . $rawJson);
        }

        $this->_encodedJson = json_encode($decodedJson);
        $this->_id = $decodedJson["id"];
        $this->_type = $decodedJson["type"];
        $this->_date = $decodedJson["date"];

        $encodedSubject = json_encode($decodedJson["subject"]);
        if (!$encodedSubject) {
            throw new ValueError("Invalid or malformed webhook subject: " . $decodedJson["subject"]);
        }

        $decodedSubjectObject = json_decode($encodedSubject);

        // empty subject is parsed as an array but must always be an object
        if (is_array($decodedSubjectObject) && sizeof($decodedSubjectObject) == 0) {
            $this->_subject = new stdClass();
        } else {
            $this->_subject = $decodedSubjectObject;
        }

        // create a new subject object based on the type of webhook using the appropriate subject object from the OpenAPI project.
        if ($this->isTypeOfPayment() || $this->isTypeOfDispute()) {
            $this->_subject = new PaymentSubject($decodedJson["subject"]);
        }
        else if ($this->isTypeOfRecurringPayment()) {
            $this->_subject = new RecurringPaymentSubject($decodedJson["subject"]);
        }
        else if ($this->isType(VALIDATION_WEBHOOK)) {
            $this->_subject = new stdClass();
        }
//        else if ($this->isTypeOfBasket()) { FIXME
//            //TODO subject
//        }
    }

    /**
     * Converts a JSON string into the appropriate Webhook.
     *
     * Returns an instance of the calling webhook class instead of the base class (such as ValidationWebhook).
     *
     * @param string $webhookJsonStr The JSON string containing received webhook data.
     *
     * @return static Returns an instance of the calling class with the decoded webhook data.
     *
     * @throws ValueError If the provided JSON is invalid or contains an unrecognized webhook type.
     */
    public static function fromJson(string $webhookJsonStr) : self {
        $decodedJson = json_decode($webhookJsonStr, true);
        if (!$decodedJson) {
            throw new ValueError("Invalid or malformed webhook JSON: " . $webhookJsonStr);
        }

        $webhookType = $decodedJson['type'] ?? null;
        if (!$webhookType) {
            throw new ValueError("Webhook type is missing from the payload: " . $webhookJsonStr);
        }

        if (!array_key_exists("subject", $decodedJson)) {
            throw new ValueError("Webhook is missing subject from the payload: " . $webhookJsonStr);
        }

        $webhookSubject = $decodedJson['subject'];
        if (is_null($webhookSubject)) {
            throw new ValueError("Webhook subject is null in payload: " . $webhookJsonStr);
        }

        if (!array_key_exists($webhookType, WEBHOOK_TYPES)) {
            throw new ValueError("Unrecognized webhook type: " . $webhookType);
        }

        // lookup appropriate class based on our received type
        $webhookClass = WEBHOOK_TYPES[$webhookType];
        if (!class_exists($webhookClass)) {
            throw new ValueError("Webhook class for type '{$webhookType}' does not exist: " . $webhookClass);
        }

        // instantiate the appropriate webhook class
        return new $webhookClass($webhookJsonStr);
    }

    public function __toString() {
        $output = "id = " . $this->_id . ", ";
        $output .= "type = " . $this->_type . ", ";
        $output .= "date = " . $this->_date . ", ";
        $printedSubject = print_r($this->_subject, true);
        $output .= "subject = " . $printedSubject. ", \n";
        $output .= "rawJson = " . $this->_rawJson . ", ";
        $output .= "compactJson = " . $this->_encodedJson . ", ";
        return $output;
    }

    public function getId(): string
    {
        return $this->_id;
    }

    public function getDate(): string
    {
        return $this->_date;
    }

    public function getSubject(): object
    {
        return $this->_subject;
    }

    public function getType(): string {
        return $this->_type;
    }

    public function isTypeOfDispute(): bool {
        return StringUtil::containsString($this->_type, "dispute");
    }

    public function isTypeOfPayment(): bool {
        return StringUtil::containsString($this->_type, "payment")
            && !StringUtil::containsString($this->_type, "recurring");
    }

    public function isTypeOfRecurringPayment(): bool {
        return StringUtil::containsString($this->_type, "recurring-payment");
    }

    public function isStatusCompleted(): bool {
        $status = $this->_subject->getStatus();
        return WEBHOOK_STATUSES[$status["id"]] === "Complete";
    }

    public function isType(string $type): bool {
        if (!key_exists($type, WEBHOOK_TYPES)) {
            throw new ValueError("Invalid webhook type: " . $type);
        }
        return $this->_type == $type;
    }

    /**
     * Validates if the webhook originated from Tebex by checking IP header.
     *
     * It is always recommended to use REMOTE_ADDR as the client can spoof any HTTP headers ("HTTP_CLIENT_IP", "HTTP_X_FORWARDED_FOR")
     * to anything they want. This can lead to security vulnerabilities. If you use a proxy, it should be configured to replace the
     * REMOTE_ADDR header with the real remote address.
     *
     * In addition to validating IP, you should also use validateSignature() to authenticate the webhook.
     *
     * @param $ipHeaderName string The name of the header containing the requestor's IP
     * @return bool True if the request IP matches a Tebex IP
     */
    public function validateIp(string $ipHeaderName = "REMOTE_ADDR"): bool
    {
        if (!array_key_exists($ipHeaderName, $_SERVER)) {
            throw new ValueError("IP header not present: " . $ipHeaderName);
        }
        $requestIp = $_SERVER[$ipHeaderName];
        return $requestIp == "18.209.80.3" || $requestIp == "54.87.231.232";
    }

    /**
     * Compares the provided webhook signature to a calculated signature based on the signature's compact JSON.
     *
     * The signature is generated by SHA256 hashing the JSON body and then building a SHA256 HMAC with the body hash as
     * the data/content and your webhook secret as the key.
     *
     * @param $expectedSignature string   The signature received in the X-Signature header.
     * @param $webhookSecret string       The secret key for webhooks.
     * @return bool
     */
    public function validateSignature(string $expectedSignature, string $webhookSecret): bool
    {
        $calculatedSignature = hash_hmac('sha256', hash('sha256', $this->_encodedJson), $webhookSecret);
        return $expectedSignature === $calculatedSignature;
    }
}