<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;

/**
 * @inheritdoc
 */
class GetProductTypesBySkus implements GetProductTypesBySkusInterface
{
    /**
     * @var ProductResourceModel
     */
    private $productResource;

    /**
     * @param ProductResourceModel $productResource
     */
    public function __construct(
        ProductResourceModel $productResource
    ) {
        $this->productResource = $productResource;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus): array
    {
        $select = $this->productResource->getConnection()->select()->from(
            $this->productResource->getTable('catalog_product_entity'),
            [ProductInterface::SKU, ProductInterface::TYPE_ID]
        )->where(
            ProductInterface::SKU . ' IN (?)',
            $skus
        );

        return $this->productResource->getConnection()->fetchPairs($select);
    }
}
