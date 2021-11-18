<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace YoUgandaLimited\YoPaymentsGateway\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use YoUgandaLimited\YoPaymentsGateway\Gateway\Http\Client\ClientMock;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'yopaymentsgw';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE => [
                    'mmNumber'=> [
                        ClientMock::MM_NUMBER => ''
                    ],
                    'transactionResults' => [
                        ClientMock::SUCCESS => __('Success'),
                        ClientMock::FAILURE => __('Failed')
                    ]
                ]
            ]
        ];
    }
}