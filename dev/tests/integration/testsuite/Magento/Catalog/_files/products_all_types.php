<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Bundle\Model\Product\Price;
use Magento\Bundle\Model\Product\Type as Bundle;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type as Simple;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Downloadable\Model\Product\Type as Downloadable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
/** @var  ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var ProductFactory $productFactory */
$productFactory = $objectManager->get(ProductFactory::class);

$productTypes = [
    Bundle::TYPE_CODE,
    Configurable::TYPE_CODE,
    Downloadable::TYPE_DOWNLOADABLE,
    Grouped::TYPE_CODE,
    Simple::TYPE_SIMPLE,
    Simple::TYPE_VIRTUAL,
];

foreach ($productTypes as $productType) {
    /** @var $product Product */
    $product = $productFactory->create(
        [
            'data' => [
                'attribute_set_id' => 4,
                'type_id' => $productType,
                'name' => $productType . '_name',
                'sku' => $productType . '_sku',
                'price' => 10,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'status' => Status::STATUS_ENABLED,
                'custom_attributes' => [
                    'price_type' => [
                        'attribute_code' => 'price_type',
                        'value' => Price::PRICE_TYPE_FIXED
                    ],
                    'price_view' => [
                        'attribute_code' => 'price_view',
                        'value' => '1',
                    ],
                ],
            ]
        ]
    );
    $productRepository->save($product);
}
