<?php
/**
 * Created by PhpStorm.
 * User: serhii
 * Date: 23.03.18
 * Time: 16:14
 */

namespace Magento\InventoryCatalog\Model;


/**
 * @inheritdoc
 */
interface GetProductTypesBySkusInterface
{
    /**
     * Retrieve product types by skus
     *
     * @param array $skus
     * @return array ['sku' => 'product_type']
     */
    public function execute(array $skus): array;
}