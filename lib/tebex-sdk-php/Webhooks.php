<?php

namespace Tebex {

    use Tebex\Webhook\ValidationWebhook;

    /**
     * Provides access to all Tebex webhook-related functionality, such as setting secret key and validating signatures.
     */
    class Webhooks {
        /**
         * @var string The secret key used to sign webhook requests sent by Tebex.
         * Retrieve from https://creator.tebex.io/webhooks/endpoints
         */
        private static string $_webhookSecret;

        /**
         * Sets the webhook secret key to the provided value.
         *
         * @param string $value
         * @return void
         */
        public static function setSecretKey(string $value) {
            self::$_webhookSecret = $value;
        }

        /**
         * @return bool True if the secret key is set to some non-empty string value.
         */
        public static function isSecretKeySet() : bool {
            return !is_null(self::$_webhookSecret) && self::$_webhookSecret != "";
        }

        /**
         * Validates the webhook signature and IP, ensuring that the webhook was received from Tebex. Requires that your secret
         * key is set and that the REMOTE_ADDR header is present.
         *
         * This is a helper function. You may also validate webhooks using the ValidationWebhook's
         *  @see ValidationWebhook::validateSignature()
         *  @see ValidationWebhook::validateIp()
         *
         * @param string $expectedSignature The expected signature that will be compared with the actual calculated webhook signature. Should be $_SERVER["X-SIGNATURE"].
         * @param string $json The webhook request body in JSON format.
         *
         * @return bool Returns true if the webhook signature and IP are valid. False otherwise.
         */
        public static function validateWebhookSignature(string $expectedSignature, string $json) : bool {
            $hook = ValidationWebhook::fromJson($json);

            $validSignature = $hook->validateSignature($expectedSignature, self::$_webhookSecret);
            $validIp = $hook->validateIp();

            if (!$validSignature) {
                return false;
            }

            if (!$validIp) {
                return false;
            }

            return true;
        }
    }
}

namespace Tebex\Webhook {
    // constant values for webhook types
    const PAYMENT_COMPLETED = "payment.completed";
    const PAYMENT_DECLINED = "payment.declined";
    const PAYMENT_REFUNDED = "payment.refunded";
    const PAYMENT_DISPUTE_OPENED = "payment.dispute.opened";
    const PAYMENT_DISPUTE_WON = "payment.dispute.won";
    const PAYMENT_DISPUTE_LOST = "payment.dispute.lost";
    const PAYMENT_DISPUTE_CLOSED = "payment.dispute.closed";
    const RECURRING_PAYMENT_STARTED = "recurring-payment.started";
    const RECURRING_PAYMENT_RENEWED = "recurring-payment.renewed";
    const RECURRING_PAYMENT_STATUS_CHANGED = "recurring-payment.status-changed";
    const RECURRING_PAYMENT_ENDED = "recurring-payment.ended";
    const RECURRING_PAYMENT_CANCELLATION_REQUESTED = "recurring-payment.cancellation.requested";
    const RECURRING_PAYMENT_CANCELLATION_ABORTED = "recurring-payment.cancellation.aborted";
    const BASKET_ABANDONED = "basket.abandoned";
    const VALIDATION_WEBHOOK = "validation.webhook";

    // map of webhook types to their appropriate class
    const WEBHOOK_TYPES = [
        PAYMENT_COMPLETED => PaymentCompletedWebhook::class,
        PAYMENT_DECLINED => PaymentDeclinedWebhook::class,
        PAYMENT_REFUNDED => PaymentRefundedWebhook::class,
        PAYMENT_DISPUTE_OPENED => PaymentDisputeOpenedWebhook::class,
        PAYMENT_DISPUTE_WON => PaymentDisputeWonWebhook::class,
        PAYMENT_DISPUTE_LOST => PaymentDisputeLostWebhook::class,
        PAYMENT_DISPUTE_CLOSED => PaymentDisputeClosedWebhook::class,
        RECURRING_PAYMENT_STARTED => RecurringPaymentStartedWebhook::class,
        RECURRING_PAYMENT_RENEWED => RecurringPaymentRenewed::class,
        RECURRING_PAYMENT_STATUS_CHANGED => RecurringPaymentStatusChangedWebhook::class,
        RECURRING_PAYMENT_ENDED => RecurringPaymentEndedWebhook::class,
        RECURRING_PAYMENT_CANCELLATION_REQUESTED => RecurringPaymentCancellationRequestedWebhook::class,
        RECURRING_PAYMENT_CANCELLATION_ABORTED => RecurringPaymentCancellationAbortedWebhook::class,
        BASKET_ABANDONED => BasketAbandonedWebhook::class,
        VALIDATION_WEBHOOK => ValidationWebhook::class
    ];

    // possible webhook statuses
    const WEBHOOK_STATUSES = [
        1 => "Complete",
        2 => "Refund",
        3 => "Chargeback",
        18 => "Declined",
        19 => "Pending Checkout",
        21 => "Refund Pending"
    ];
}