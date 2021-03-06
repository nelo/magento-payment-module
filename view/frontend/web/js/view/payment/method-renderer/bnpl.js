define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/set-payment-information',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Nelo_Bnpl/js/action/redirect-on-success',
    "Magento_Checkout/js/model/quote"
], function ($, Component, setPaymentAction, additionalValidators, redirectOnSuccessAction, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Nelo_Bnpl/payment/nelo-button'
        },
        redirectAfterPlaceOrder: true,
        placeOrderHandler: null,
        validateHandler: null,

        initialize: function() {
            self = this;
            self._super();

            self._setPrice();
            quote.totals.subscribe(function(newValue) {
                self._setPrice(newValue);
            });
        },

        _setPrice: function(newPrice) {
            let price = self.getPriceUpdated(newPrice);
            self._changePriceInDom(price);
        },

        getPriceUpdated: function(newValue) {
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
            }
            return result * 100;
        },

        _changePriceInDom: function(price) {
            const elements = document.querySelectorAll('.nelo-as-low-as');
            for (let i = 0; i < elements.length; i++) {
                elements[i].setAttribute('data-amount', price);
            }
        },

        _loadScripts: function() {
            const head = document.getElementsByTagName('head')[0];

            const configScript = document.createElement('script');
            const publishableApiKey = window.checkoutConfig.payment.bnpl.publishableApiKey;
            const isSandboxMode = window.checkoutConfig.payment.bnpl.isSandboxMode;
            console.log(window.checkoutConfig.payment.bnpl.locale);
            const environment = (isSandboxMode ? 'sandbox' : 'production')
            configScript.type = 'text/javascript';
            var script = '_neloConfig={' +
                'publishableKey: \'' + publishableApiKey + '\', ' +
                'environment: \'' + environment + '\'';
            if(window.checkoutConfig.payment.bnpl.locale != null) {
                script += ', acceptLanguage: \'' + window.checkoutConfig.payment.bnpl.locale + '\''
            }
            script += '};';
            configScript.text = script;
            head.appendChild(configScript);

            const neloJsScript = document.createElement('script');
            neloJsScript.type = 'text/javascript';
            neloJsScript.src = 'https://js.nelo.co/v1/nelo.js';
            head.appendChild(neloJsScript);
        },

        /**
         * @param {Function} handler
         */
        setPlaceOrderHandler: function (handler) {
            this.placeOrderHandler = handler;
        },

        /**
         * @param {Function} handler
         */
        setValidateHandler: function (handler) {
            this.validateHandler = handler;
        },

        /**
         * @returns {Object}
         */
        context: function () {
            return this;
        },

        /**
         * @returns {Boolean}
         */
        isShowLegend: function () {
            return true;
        },

        /**
         * @returns {String}
         */
        getCode: function () {
            return 'nelo';
        },

        /**
         * @returns {Boolean}
         */
        isActive: function () {
            return true;
        },

        /**
         * Logo Src
         * @returns {*}
         */
        getPaymentAcceptanceMarkSrc: function () {
            return window.checkoutConfig.payment.bnpl.logoSrc;
        },

        /**
         * Place order.
         */
        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (this.validate() &&
                additionalValidators.validate() &&
                this.isPlaceOrderActionAllowed() === true
            ) {
                this.isPlaceOrderActionAllowed(false);
                this.getPlaceOrderDeferredObject()
                    .done(
                        function () {
                            self.afterPlaceOrder();
                            if (self.redirectAfterPlaceOrder) {
                                redirectOnSuccessAction.execute();
                            }
                        }
                    ).always(
                    function () {
                        self.isPlaceOrderActionAllowed(true);
                    }
                );

                return true;
            }

            return false;
        },
        /** Redirect to Nelo */
        continueToNelo: function () {
            if (additionalValidators.validate()) {
                var self = this;
                //update payment method information if additional data was changed
                this.selectPaymentMethod();
                setPaymentAction(self.messageContainer, {'method': self.getCode()}).done(
                    function () {
                        self.placeOrder();
                    }
                );

                return false;
            }
        }
    });
});
