# Multi Crypto Currency Payments

## Description

Accept the most popular cryptocurrencies (BTC, LTC, BCH, Doge etc.) on your store all around the world. Use any crypto supported by provider to accept coins using the Forwarding payment process.

[Watch on YouTUBE](https://www.youtube.com/watch?v=SKvp_K_FdDU)

**Key features:**

* Payment automatically forwards from temporarily generated crypto-address directly into your wallet (temp address identify payment to exact order)

* The payment gateway has a fixed fee which does not depend on the amount of the order. Small payments are totally free. [https://apirone.com/pricing](https://apirone.com/pricing)
  
* You do not need to complete a KYC/Documentation to start using our plugin. Just fill in settings and start your business.

* White label processing (your online store accepts payments on the store side without redirects, iframes, advertisements, logo, etc.)

* This plugin works well all over the world.

* Tor network support.

## How does it work?

The Buyer adds items into the cart and prepares the order.
Using API requests, the store generates temporary crypto (BTC, LTC, BCH, Doge) address and show a QR code.
Then, the buyer scans the QR code and pays for the order. This transaction goes to the blockchain.
The payment gateway immediately notifies the store about the payment.
The store completes the transaction.

## Installation via WordPress Plugin Manager

* Go to WordPress Admin panel > Plugins > Add New in the admin panel.

* Enter "Multi CryptoCurrency Payments" in the search box.

* Click Install Now.

* Fill settings of your crypto addresses into Plugin Settings: *WooCommerce > Settings > Payments > Multi Crypto Currency Payments*.

* Turn the "On" checkbox in the Plugin on the same setting page.

___

## Third Party API & License Information

**API website:** [https://apirone.com](https://apirone.com)

**API docs:** [https://apirone.com/docs/](https://apirone.com/docs/)

**Privacy policy:** [https://apirone.com/privacy-policy](https://apirone.com/privacy-policy)

**Support:** <support@apirone.com>
___

## Frequently Asked Questions ##

**I will get money in USD, EUR, CAD, JPY, RUR...?**

>No. You will get crypto only. You can enter the crypto address of your trading platform account and convert crypto (BTC, LTC, BCH, Doge) to fiat money at any time.

**How can The Store cancel orders and return bitcoins?**
> This process is fully manual because you will get all payments to your specified wallet. Only you control your money. Contact the Customer, ask address and finish the deal.
Bitcoin protocol has no refunds, chargebacks, or transaction cancellations.
Only the store manager takes a decision of underpaid or overpaid orders.
Cancel and return the rest amount directly to the customers.

**Do the Plugin support native Bitcoin Segwit ("bc1") addresses?**
>Yes. Sure.

**I would like to accept Litecoin only. What should I do?**
>Just enter your LTC address on settings and keep other fields empty.

**Fee**
>The plugin uses the free Rest API of the Apirone crypto payment gateway. The pricing page [https://apirone.com/pricing](https://apirone.com/pricing)

## Changelog

### Version 2.0.1 | 20/11/2024 =

* Fixed render ajax response when offset exists and is zero
* SDK updated to version 1.1.6:
    * Isolated styles from sdk root element
    * Clear unused styles & code cleanup
    * Minimized styles assets
    * Added mobile view for address strings & mobile styles improved
    * JS updated
  
### Version 2.0.0 | 01/11/2024 =

* Started using the official Apirone SDK PHP library
* New official Apirone invoice design
* Split networks & tokens on the plugin settings page
* Fixed the "lost merchant address" bug for TRON network & tokens

## Changelog

### Version 1.2.10 | 11/07/2024 =

* Show tbts to unauthenticated users if test_customer is set to ```*``` (asterisk symbol)

### Version 1.2.9 | 04/04/2024

* Add settings saver when account recreated
* Plugin updater refactoring
* Code cleanup

### Version 1.2.8 | 02/04/2024

* Automatic destination addresses update
* Processing fee plan param added
* Code cleanup

### Version 1.2.7 | 09/03/2024

* Fix lost destinations
* Fix trx icon
* Add Plugin Info block
* Code cleanup

### Version 1.2.6 | 05/03/2024

* Internal QR generator
* Improved payment process
* Tron support

### Version 1.2.5 | 02/08/2023

* Improved plugin activation
* Minor fixes

### Version 1.2.4 | 25/07/2023

* Add debug mode
* Add woocommerce logs for errors & debug

### Version 1.2.2 | 10/05/2023

* Fix mobile layout.
* Clear cart after success or expired payment.

### Version 1.2.1 | 30/03/2023

* Add a message when the invoice isn't created/found.

### Version 1.2.0 | 24/03/2023

* The plugin is switched to a new fee plan.
  Now the fee is not fixed but charged in amount of 1% of the transfer.

### Version 1.1.1 | 09/03/2023

* Fix installation errors on php-8.x version.
* Fix update from 1.0.0 on php-8.x
* Improve new installation (without plugin update)
* Improve update logic

### Version 1.1.0 | 25/12/2022

* Add apirone invoices support.

### Version 1.0.0 | 11/01/2022

* First version of plugin is published.
