<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Catalog\Api;

/**
 * Get product type id by product sku.
 *
 * @api
 */
interface GetProductTypeBySkuInterface
{
    /**
     * Returns product type id by product sku.
     *
     * @param string $sku
     * @return string|null
     */
    public function execute(string $sku);
}