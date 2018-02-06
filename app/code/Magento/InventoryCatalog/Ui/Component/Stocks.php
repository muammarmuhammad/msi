<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Ui\Component;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\InventoryApi\Api\GetSalableProductQtyInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\Ui\Component\Container;
use Magento\Inventory\Model\ResourceModel\GetAssignedStockIdsBySku;

class Stocks extends Container
{
    /**
     * @var StockRepositoryInterface
     */
    private $stockRepository;

    /**
     * @var GetSalableProductQtyInterface
     */
    private $getSalableProductQty;

    /**
     * @var GetAssignedStockIdsBySku
     */
    private $getAssignedStockIdsBySku;

    /**
     * @param ContextInterface $context
     * @param StockRepositoryInterface $stockRepository
     * @param GetSalableProductQtyInterface $getSalableProductQty
     * @param GetAssignedStockIdsBySku $getAssignedStockIdsBySku
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        StockRepositoryInterface $stockRepository,
        GetSalableProductQtyInterface $getSalableProductQty,
        GetAssignedStockIdsBySku $getAssignedStockIdsBySku,
        $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
        $this->stockRepository = $stockRepository;
        $this->getSalableProductQty = $getSalableProductQty;
        $this->getAssignedStockIdsBySku = $getAssignedStockIdsBySku;
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['product']['sku']) || empty($dataSource['data']['product']['sku'])) {
            return $dataSource;
        }
        $productSku = $dataSource['data']['product']['sku'];
        $dataSource['data']['stocks'] = $this->getActiveStocksQtyInfo($productSku);
        return $dataSource;
    }

    /**
     * @param string $productSku
     * @return array
     */
    private function getActiveStocksQtyInfo(string $productSku): array
    {
        $stockIds = $this->getAssignedStockIdsBySku->execute($productSku);
        $stockInfo = [];
        if (count($stockIds)) {
            foreach ($stockIds as $stockId) {
                $stockId = (int)$stockId;
                $stock = $this->stockRepository->get($stockId);
                $stockInfo[] = [
                    'stock_name' => $stock->getName(),
                    'qty' => $this->getSalableProductQty->execute($productSku, $stockId),
                ];
            }
        }
        return $stockInfo;
    }
}