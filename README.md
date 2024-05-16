<img src="https://github.com/khxisa-3gm/pocket-pay-wc-plugin/assets/141197028/3affd880-c849-4bc8-bf9d-8f00893ef1d5"  height="70px" />
<img src="https://github.com/khxisa-3gm/pocketpay-woocommerce-plugin/assets/141197028/94829506-f43f-419f-869c-dd55a773633b"  height="70px" />

# Pocket Pay WooCommerce Plugin

Pocket Pay is a payment gateway provided by the Pocket team from ThreeG Media Sdn Bhd. This WooCommerce plugin allows the customers of associated Pocket merchants to use Pocket Pay as a payment option at store checkout. 

## Table of Contents
- [Installation](#installation)
- [Configuration](#configuration)
- [Testing](#testing)
- [Production](#production)
- [Usage](#usage)
- [Support](#support)

## Installation

1. **Download the Plugin:**
   - Clone the repository or download the latest release as a .zip file.

2. **Upload to WordPress:**
   - Log in to your WordPress admin dashboard.
   - Navigate to `Plugins` > `Add New` > `Upload Plugin`.
   - Choose the downloaded `PocketPay.zip` file and click `Install`.

3. **Activate the Plugin:**
   - After installation, click `Activate Plugin` to enable the plugin.

## Configuration

1. **Setting up Merchant's Pocket Account:**
   - Go to `WooCommerce` > `Settings` > `Payments`.

2. **Enable Pocket Pay:**
   - Locate `Pocket Pay` in the list of available payment gateways and click `Manage`.
   - Enable the payment method by checking the `Enable Pocket Pay` option.

3. **Configure PocketPay:**
   - Enter the necessary credentials and settings provided by Pocket Pay.
   - Save changes.
  
## Testing 
To test the Pocket Pay WooCommerce plugin, you can use the following test credentials. These credentials are for the Pocket Pay sandbox environment and should not be used in production. Enable sandbox environment by checking `Enable Test Mode` during Pocket Pay configuration.

### Test API Credentials

- **Test API Key:** `XnUgH1PyIZ8p1iF2IbKUiOBzdrLPNnWq`
- **Test Salt:** `FOLzaoJSdbgaNiVVA73vGiIR7yovZury4OdOalPFoWTdKmDVxfoJCJYTs4nhUFS2`
  
- **Test Card Details:**
   - **Number**: 4444 5555 6666 7777
   - **Expiry Date**: (Any future date)
   - **CVV**: (Any 3-digit number)

  
### How to Use Test Credentials

1. **Configure Pocket Pay:**
   - Enter the test API key and salt provided above in the corresponding fields inside the Pocket Pay setting page.
   - Save changes.

3. **Perform a Test Transaction:**
   - Add a product to your cart and proceed to checkout.
   - Select Pocket Pay as the payment method and use the provided test credentials to complete the transaction.

### Note

- These credentials are for testing purposes only and will not process real transactions.
- For production use, uncheck `Enable Test Mode` in the settings page and replace the test credentials with your live Pocket Pay credentials.

## Production 

To enable the gateway for production use, you must enter your live API credentials on the settings page.

### Live API Credentials

- **Live API Key & Salt:** You may generate these credentials through the Pocket Merchant portal (https://portal.pocket.com.bn/), provided that your portal account has the necessary permission. Note that access to this portal requires you to be a registered Pocket merchant or have a Pocket merchant demo account. If you're interested in acquiring a demo account and live credentials, please contact our support team. 
     - Go to `Manage Keys` to view any previously made credentials.
     - Select `Generate New Keys` to create new keys. 

## Usage

Once configured, Pocket Pay will be available as a payment option during the checkout process on your WooCommerce store. Customers can select Pocket Pay, enter their payment details, and complete the transaction securely.


## Support

For any questions, issues, or feature requests, please contact our support team at `support@threegmedia.com` or through our hotline at `+673 8888222`. We kindly request that you include your contact details (name, email address, and/or phone number) when sending your email.

---

Thank you for using Pocket Pay! We hope it helps streamline your payment processes on WooCommerce.

Copyright 2008-2024. All Rights Reserved. Proprietary of ThreeG Media Sdn Bhd
