# Nelo Payment Module for Magento
Use the Nelo module for Magento and offer users the option to buy now and pay later.

## How it works
By installing our module and enabling it for your store with all the required configuration, 
the user will see Nelo as a payment option on the checkout screen.

After a user clicks on the "Continue to Nelo" button, they will be redirected to our web checkout 
flow, which will decide whether or not to approve them for credit. Whether or not the purchase is
approved, once the flow is finished, the user will be redirected back to the appropriate page in your store.

### Order states
With our module when an order is placed the order is created with `pending_payment` state.
If either an error occurs during the checkout flow, the credit isn't approved, or the user
cancels the flow, then the order will be moved to the `canceled` state.
If the entire process was successful, the order will be put in the `processing` state.

## Requirements
- PHP versions: 7.3 || 7.4
- Magento versions: 2.3 || 2.4

## Installation
Therefore you need to add our repository (code below) to your composer.json in the repositories section.
```json
  {
    "type": "vcs",
    "url": "git@github.com:nelo/magento-payment-module.git"
  }
```
after that you might need to save your GitHub auth credentials for composer.  
Next step is add the module to your project in the following way:
 - `composer require nelo/magento-payment-module`
 - `php bin/magento setup:upgrade`
 - `php bin/magento setup:di:compile`
 - Finally, you might need `chmod -R 777 var/ generated/`

