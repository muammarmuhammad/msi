<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\CatalogInventory\StockManagement;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\StockManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalog\Model\GetProductTypesBySkusInterface;
use Magento\InventoryReservations\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Api\AppendReservationsInterface;
use Magento\InventoryCatalog\Model\GetSkusByProductIdsInterface;
use Magento\InventorySales\Model\StockByWebsiteIdResolver;
use Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface;

/**
 * Class provides around Plugin on \Magento\CatalogInventory\Model\StockManagement::registerProductsSale
 */
class ProcessRegisterProductsSalePlugin
{
    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    /**
     * @var StockByWebsiteIdResolver
     */
    private $stockByWebsiteIdResolver;

    /**
     * @var ReservationBuilderInterface
     */
    private $reservationBuilder;

    /**
     * @var AppendReservationsInterface
     */
    private $appendReservations;

    /**
     * @var IsProductSalableForRequestedQtyInterface
     */
    private $isProductSalableForRequestedQty;

    /**
     * @var GetProductTypesBySkusInterface
     */
    private $getProductTypesBySkus;
    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param StockByWebsiteIdResolver $stockByWebsiteIdResolver
     * @param ReservationBuilderInterface $reservationBuilder
     * @param AppendReservationsInterface $appendReservations
     * @param IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQty
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param StockConfigurationInterface $stockConfiguration
     */
    public function __construct(
        GetSkusByProductIdsInterface $getSkusByProductIds,
        StockByWebsiteIdResolver $stockByWebsiteIdResolver,
        ReservationBuilderInterface $reservationBuilder,
        AppendReservationsInterface $appendReservations,
        IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQty,
        GetProductTypesBySkusInterface $getProductTypesBySkus,
        StockConfigurationInterface $stockConfiguration
    ) {
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
        $this->reservationBuilder = $reservationBuilder;
        $this->appendReservations = $appendReservations;
        $this->isProductSalableForRequestedQty = $isProductSalableForRequestedQty;
        $this->getProductTypesBySkus = $getProductTypesBySkus;
        $this->stockConfiguration = $stockConfiguration;
    }

    /**
     * @param StockManagement $subject
     * @param callable $proceed
     * @param float[] $items
     * @param int|null $websiteId
     * @return StockItemInterface[]
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundRegisterProductsSale(StockManagement $subject, callable $proceed, $items, $websiteId = null)
    {
        if (empty($items)) {
            return [];
        }
        if (null === $websiteId) {
            //TODO: Do we need to throw exception?
            throw new LocalizedException(__('$websiteId parameter is required'));
        }
        $stockId = (int)$this->stockByWebsiteIdResolver->get((int)$websiteId)->getStockId();
        $productSkus = $this->getSkusByProductIds->execute(array_keys($items));

        // skip product with types where Stock Management is not applicable
        $productTypesBySku = $this->getProductTypesBySkus->execute($productSkus);
        foreach ($productSkus as $productId => $sku) {
            $type = $productTypesBySku[$sku];
            if (!$this->stockConfiguration->isQty($type)) {
                unset($productSkus[$productId]);
            }
        }

        $this->checkItemsQuantity($items, $productSkus, $stockId);
        $reservations = [];
        foreach ($productSkus as $productId => $sku) {
            $reservations[] = $this->reservationBuilder
                ->setSku($sku)
                ->setQuantity(-(float)$items[$productId])
                ->setStockId($stockId)
                ->build();
        }
        $this->appendReservations->execute($reservations);

        return [];
    }

    /**
     * Check is all items salable
     *
     * @param array $items
     * @param array $productSkus
     * @param int $stockId
     * @return void
     * @throws LocalizedException
     */
    private function checkItemsQuantity(array $items, array $productSkus, int $stockId)
    {
        foreach ($productSkus as $productId => $sku) {
            $qty = (float)$items[$productId];
            $isSalable = $this->isProductSalableForRequestedQty->execute($sku, $stockId, $qty)->isSalable();
            if (!$isSalable) {
                throw new LocalizedException(
                    __('Not all of your products are available in the requested quantity.')
                );
            }
        }
    }
}
