# Whalestack Prestashop Module

This is the official Prestashop Module for [Whalestack](https://www.whalestack.com), a leading cryptocurrency payment processor. Accept Bitcoin, stablecoins and other cryptocurrencies on your Prestashop website and settle payments in your preferred currency. 

The module is available as a white-label crypto payment solution and allows you to customize the payment pages of your online shop to your brand's unique look and feel with Whalestack [Brand Connect](https://www.whalestack.com/en/blog/a-guide-to-white-label-crypto-payment-processing-with-brand-connect-ba732a9160fa).

This Prestashop module implements the Whalestack [REST API](https://www.whalestack.com/en/api-docs).

For more details and how to get started with the Prestashop module please read the [installation guide](https://www.whalestack.com/en/prestashop).

Key Features
------------
* Accepts Bitcoin (BTC), Stellar Lumens (XLM), Litecoin (LTC), Bitcoin Lightning (BTC LN), EURC and USDC payments on your Prestashop from customers.
* Instantly settles in your preferred stablecoin or cryptocurrency.
* 50 billing currencies are available, see full list [here](https://www.whalestack.com/en/currencies).
* Integrates seemlessly into your Prestashop website.
* Sets the product price in your national currency.
* Sets the checkout page language in your preferred language.
* Eliminates chargebacks and gives you control over refunds.
* Eliminates currency volatility risks due to instant conversions and settlement.
* Let's you translate the module into any required language.
* Built-in white-label options to customize checkout pages to your brand's look and feel.

Supported Currencies
------------

Argentine Peso (ARS), Australian Dollar (AUD), Bahraini Dinar (BHD), Bangladeshi Taka (BDT), Bermudian Dollar (BMD), Bitcoin (BTC), Brazilian Real (BRL), British Pound (GBP), Canadian Dollar (CAD), Chilean Peso (CLP), Chinese Yuan (CNY), Czech Koruna (CZK), Danish Krone (DKK), Emirati Dirham (AED), Ethereum (ETH), Euro (EUR), Hong Kong Dollar (HKD), Hungarian Forint (HUF), Indian Rupee (INR), Indonesian Rupiah (IDR), Israeli Shekel (ILS), Japanese Yen (JPY), Korean Won (KRW), Kuwaiti Dinar (KWD), Litecoin (LTC), Malaysian Ringgit (MYR), Mexican Peso (MXN), Myanmar Kyat (MMK), New Zealand Dollar (NZD), Nigerian Naira (NGN), Norwegian Krone (NOK), Pakistani Rupee (PKR), Philippine Peso (PHP), Polish Zloty (PLN), Ripple (XRP), Russian Ruble (RUB), Saudi Arabian Riyal (SAR), Singapore Dollar (SGD), South African Rand (ZAR), Sri Lankan Rupee (LKR), Stellar (XLM), Swedish Krona (SEK), Swiss Franc (CHF), Taiwan Dollar (TWD), Thai Baht (THB), Turkish Lira (TRY), Ukrainian Hryvnia (UAH), US Dollar (USD), Venezuelan Bolivar (VEF), Vietnamese Dong (VND)

Requirements
------------
* Prestashop >= 1.7
* PHP >= 5.6

Installation
---------------------
**Requirements**

* A Whalestack merchant account -> Sign up [here](https://www.whalestack.com)

**Module Installation**

1. Download the module from [Github](https://github.com/whalestackhq/prestashop-module).
1. Turn off debug mode during installation (in Prestashop > Advanced Parameters > Performance).
1. Zip the `whalestack` folder and upload it in the Prestashop module manager. Or copy the entire `whalestack` folder to the `/modules/` directory.
1. Configure the module in the Prestashop module manager.

**Module Configuration**

1. Get your [API key and secret](https://www.whalestack.com/en/api-settings) from your Whalestack merchant account.
1. Enter API key and secret in the module configuration.
1. Completed payments will automatically update Prestashop orders to `Payment accepted`.
1. Manage all payments and refunds in your [merchant account](https://www.whalestack.com). You will be notified by email about every new payment.

**White-label Setup**

Optionally customize the checkout page to your brand's look and feel directly in the Brand Connect [settings](https://www.whalestack.com/en/account-settings#brandingConfigs) of your Whalestack account. Read the [Brand Connect guide](https://www.whalestack.com/en/blog/a-guide-to-white-label-crypto-payment-processing-with-brand-connect-ba732a9160fa) for more details.

Please inspect our [API documentation](https://www.whalestack.com/en/api-docs) for more info or send us an email to service@whalestack.com.

Support and Feedback
--------------------
Your feedback is appreciated! If you have specific problems or bugs with this Prestashop module, please file an issue on Github. For general feedback and support requests, send an email to service@whalestack.com.

Contributing
------------

1. Fork it ( https://github.com/whalestackhq/prestashop-module/fork )
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create a new Pull Request