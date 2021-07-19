define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';

        rendererList.push(
            {
                type: 'nelo',
                component: 'Nelo_Bnpl/js/view/payment/method-renderer/bnpl'
            }
        );

        /**
         * Add view logic here if needed
         */

        return Component.extend({});
    }
);
