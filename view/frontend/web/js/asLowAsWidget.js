define(["jquery",
    "Magento_Checkout/js/model/quote"
], function ($, quote) {
    "use strict"

    var self;
    $.widget('mage.neloAsLowAs',{
        /**
         * Set price from quote to be used by nelo.js
         */
        setPrice: function(newValue) {
            self = this;
            let price = quote.getTotals()();
            if (newValue) {
                price = newValue;
            }
            let result = 0;
            if (price && price.base_grand_total) {
                if (newValue) {
                    result = price.base_grand_total;
                } else {
                    result = price.base_grand_total;
                }
                self.changePriceInDom(Math.round(result * 100));
            }
        },

        changePriceInDom: function(price) {
            const elements = document.querySelectorAll('.nelo-as-low-as');
            for (let i = 0; i < elements.length; i++) {
                elements[i].setAttribute('data-amount', price);
            }
        },

        loadScripts: function() {
            const head = document.getElementsByTagName('head')[0];

            const configScript = document.createElement('script');
            const publishableApiKey = window.checkoutConfig.payment.bnpl.publishableApiKey;
            const isSandboxMode = window.checkoutConfig.payment.bnpl.isSandboxMode;
            configScript.type = 'text/javascript';
            configScript.text = '_neloConfig={' +
                'publishableKey: \' ' + publishableApiKey + '\', ' +
                'environment: \'' + isSandboxMode ? 'sandbox' : 'production' + '\'' +
            '};';
            head.appendChild(configScript);

            const neloJsScript = document.createElement('script');
            neloJsScript.type = 'text/javascript';
            neloJsScript.src = 'https://js.nelo.co/v1/nelo.js';
            head.appendChild(neloJsScript);
        },

        /**
         * Create as low as widget
         *
         * @private
         */
        _create: function() {
            self = this;
            if (typeof nelo == "undefined") {
                $.when(self.loadScripts()).done(function() {
                    self.setPrice();
                });
            } else {
                self.setPrice();
            }
            quote.totals.subscribe(function(newValue) {
                self.setPrice(newValue);
            });
        }
    });
    return $.mage.neloAsLowAs
});
