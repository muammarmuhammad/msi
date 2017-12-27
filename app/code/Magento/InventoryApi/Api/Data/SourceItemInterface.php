<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryApi\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Represents amount of product on physical storage
 * Entity id getter is missed because entity identifies by compound identifier (sku and source_code)
 *
 * Used fully qualified namespaces in annotations for proper work of WebApi request parser
 *
 * @api
 */
interface SourceItemInterface extends ExtensibleDataInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const SKU = 'sku';
    const SOURCE_CODE = 'source_code';
    const QUANTITY = 'quantity';
    const STATUS = 'status';
    /**#@-*/

    /**#@+
     * Source items status values
     */
    const STATUS_OUT_OF_STOCK = 0;
    const STATUS_IN_STOCK = 1;
    /**#@-*/

    /**
     * Get source item sku
     *
     * @return string|null
     */
    public function getSku();

    /**
     * Set source item sku
     *
     * @param string|null $sku
     * @return void
     */
    public function setSku($sku);

    /**
     * Get source code
     *
     * @return string|null
     */
    public function getSourceCode();

    /**
     * Set source code
     *
     * @param string|null $sourceCode
     * @return void
     */
    public function setSourceCode($sourceCode);

    /**
     * Get source item quantity
     *
     * @return float|null
     */
    public function getQuantity();

    /**
     * Set source item quantity
     *
     * @param float|null $quantity
     * @return void
     */
    public function setQuantity($quantity);

    /**
     * Get source item status (One of self::STATUS_*)
     *
     * @return int|null
     */
    public function getStatus();

    /**
     * Set source item status (One of self::STATUS_*)
     *
     * @param int|null $status
     * @return int
     */
    public function setStatus($status);

    /**
     * Retrieve existing extension attributes object
     *
     * Null for return is specified for proper work SOAP requests parser
     *
     * @return \Magento\InventoryApi\Api\Data\SourceItemExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object
     *
     * @param \Magento\InventoryApi\Api\Data\SourceItemExtensionInterface $extensionAttributes
     * @return void
     */
    public function setExtensionAttributes(SourceItemExtensionInterface $extensionAttributes);
}
