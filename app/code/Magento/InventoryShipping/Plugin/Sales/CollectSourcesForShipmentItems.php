<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryShipping\Plugin\Sales;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ShipmentFactory;

/**
 * This is the best entry point for both POST and API request
 */
class CollectSourcesForShipmentItems
{
    /**
     * @param ShipmentFactory $subject
     * @param callable $proceed
     * @param Order $order
     * @param array $items
     * @param null $tracks
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @return
     */
    public function aroundCreate(
        ShipmentFactory $subject,
        callable $proceed,
        Order $order,
        array $items = [],
        $tracks = null
    ) {
        $itemToProcess = [];
        foreach ($items as $orderItemId => $data) {
            if (!is_array($data)) {
                //TODO: What we should do with bundle product items?
            } else {
                $qtySum = 0;
                foreach ($data as $sourceCode => $qty) {
                    $qty = (float)$qty;
                    if ($qty > 0) {
                        $qtySum += (float)$qty;
                        $itemToProcess[$orderItemId][] = [
                            'sourceCode' => $sourceCode,
                            'qtyToDeduct' => (float)$qty
                        ];
                    }
                }
            }
        }
        /** @var \Magento\Sales\Api\Data\ShipmentInterface $shipment */
        $shipment = $proceed($order, $items, $tracks);
        if (empty($items)) {
            return $shipment;
        }

        /** @var \Magento\Sales\Api\Data\ShipmentItemInterface $item */
        foreach ($shipment->getItems() as $item) {
            if (isset($itemToProcess[$item->getOrderItemId()])) {
                $item->setSources($itemToProcess[$item->getOrderItemId()]);
            }
        }

        return $shipment;
    }
}
