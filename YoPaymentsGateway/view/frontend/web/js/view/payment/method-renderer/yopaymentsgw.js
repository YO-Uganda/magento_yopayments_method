/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default'
    ],
    function (Component) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'YoUgandaLimited_YoPaymentsGateway/payment/form',
                transactionResult: '',
                mmNumber: '',
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult',
                        'mmNumber'
                    ]);
                return this;
            },

            getCode: function() {
                return 'yopaymentsgw';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        //'transaction_result': this.transactionResult(),
                        'mm_number': this.mmNumber(),
                    }
                };
            },

            getTransactionResults: function() {
                return _.map(window.checkoutConfig.payment.yopaymentsgw.transactionResults, function(value, key) {
                    return {
                        'value': key,
                        'transaction_result': value
                    }
                });
            },
            getMmNumber: function() {
                return window.checkoutConfig.payment.yopaymentsgw.mmNumber;
            }
        });
    }
);