What is Tebex?
==============
**Tebex** provides tools designed to monetize games and gaming communities. Tebex offers over 120 payment methods, chargeback protection, fraud protection, and multi-currency support.

Introducing Tebex Checkout
=========================
Tebex Checkout is a payment gateway built directly into the Tebex platform. It allows access to **over 120 different payment gateway**s without having to apply and manage compliance with each one.

No matter which payment option your customer ultimately chooses, the payment is automatically routed to your Tebex wallet, which can be paid out to you via bank or wire transfer.

Features
========
- **Accept Over 120+ Payment Types:** With Tebex acting as your merchant of record, process payments using PayPal, paysafecard, Google Pay, and more.


- **Support for Over 30 Languages:** Tebex Checkout features extensive multi-language support so that your customers always know what kind of information is being asked.


- **Create and Manage Subscription Payments:** Build recurring revenue by managing your subscription payments with Tebex Checkout.

- **Chargeback and Fraud Protection:** Tebex handles fraud reports and disputes/chargebacks on your behalf while providing 100% insurance.


- **Powerful Dashboard & Integrations:** Use the Tebex panel or an official Tebex integration to manage payments.

- **No Hidden Fees.** Enjoy flat-rate pricing with no surprises.

Installation and Setup
========================

1. Create your account at https://tebex.io/
2. Download the latest version of the Tebex Checkout gateway for WHMCS
3. Unzip Tebex Checkout in `modules/gateways`
4. Create a webhook endpoint for your callbacks after payment: https://creator.tebex.io/webhooks/endpoints
5. Enabled Tebex Checkout as a payment gateway in WHMCS
6. Add your store’s API keys and webhook secret keys to Tebex Checkout’s configuration

Once your API keys are set, you are ready to accept payments!

Webhook Validation
==================
It is **extremely important** that a webhook is configured and passed validation for your store. If a webhook is not configured and validated, your store will not receive any callback events from Tebex. View our guide for setting up webhook endpoints here: https://docs.tebex.io/developers/webhooks/overview

Frequently Asked Questions
==========================

### Can I Use Other Payment Gateways Alongside Checkout?

- When using Tebex Checkout, all payment gateways are provided by us - from PayPal to local payment methods. This is to ensure a seamless checkout flow, correct handling of sales tax, and to make the most of our advanced fraud protection. We designed Checkout to remove the hassle of dealing with payment gateways & handling chargebacks.

### Do I Have To Provide ID Or Any Other Documentation?

- We only request further information to comply with KYC / ALM regulations if our automated system flags your account during the withdrawal process.

### What is the most a customer can purchase from my store?
- To protect both our sellers and buyers we set a global maximum of $499 a customer can purchase from you in a one-time transaction.

### Will More Payment Methods Be Added To Tebex Checkout?
- Yes, we are looking to add more methods all the time! If you do not see the payment method that you wish to use in your store, please get in touch with us via support@tebex.io and request your payment method.

Additional Resources
====================
Here are some resources to help you integrate with Tebex.

[Knowledgebase](https://docs.tebex.io/creators/tebex-checkout) - Our comprehensive knowledgebase covers everything you’ll need to know about our payment gateway.


[Fee Structure](https://docs.tebex.io/creators/tebex-checkout/fees) - Find out about the most common fees of Tebex Checkout

[Technical Support](mailto:support@tebex.io) - Get assistance as a buyer or seller, email us at support@tebex.io

[Submit Feedback](https://wkf.ms/45PQwfE) - Help us build a better product by sharing your feedback

[Source Code](https://github.com/tebexio) - All of our plugins’ source code are available on GitHub

[Developer Documentation](https://docs.tebex.io/developers/) - Develop custom integrations to suit your needs

Our Mission
============
Founded in 2011, our mission has always been the same: helping creators in the gaming industry create new revenue streams without having to invest the time and effort involved in processing and managing global payments.

Since then, we helped generate over half a billion dollars for our clients, providing them with a full suite of monetization features, handling all taxes, billing, and providing full insurance. Making sure they can focus on what they do best - creating great gaming experiences.