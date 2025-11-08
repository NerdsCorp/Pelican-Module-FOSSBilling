<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicepelican\Api;

class Client extends \Api_Abstract
{
    /**
     * Get service details
     */
    public function get($data): array
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        
        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        
        // Verify ownership
        $client = $this->getIdentity();
        if ($order->client_id !== $client->id) {
            throw new \FOSSBilling\Exception('Order not found');
        }
        
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        
        if (!$model) {
            throw new \FOSSBilling\Exception('Service not found');
        }
        
        $service = $this->getService();
        return $service->toApiArray($model);
    }
    /**
     * Restart a Pelican server
     *
     * @param array $data - An associative array
     *                    - int 'order_id' The order ID of the server to restart.
     */
    public function restart($data): bool
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required for server restart.');
        }
        
        return $this->getService()->restartServer($data['order_id']);
    }

    /**
     * Change password for Pelican user
     *
     * @param array $data - An associative array
     *                    - int 'order_id' The order ID of the server
     *                    - string 'new_password' The new password
     *                    - string 'confirm_password' Password confirmation
     */
    public function change_password($data): array
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required.');
        }
        
        if (empty($data['new_password'])) {
            throw new \FOSSBilling\Exception('New password is required.');
        }
        
        if (empty($data['confirm_password'])) {
            throw new \FOSSBilling\Exception('Password confirmation is required.');
        }
        
        if ($data['new_password'] !== $data['confirm_password']) {
            throw new \FOSSBilling\Exception('Passwords do not match.');
        }
        
        if (strlen($data['new_password']) < 8) {
            throw new \FOSSBilling\Exception('Password must be at least 8 characters long.');
        }
        
        // Additional password strength checks
        if (!preg_match('/[A-Z]/', $data['new_password'])) {
            throw new \FOSSBilling\Exception('Password must contain at least one uppercase letter.');
        }
        
        if (!preg_match('/[a-z]/', $data['new_password'])) {
            throw new \FOSSBilling\Exception('Password must contain at least one lowercase letter.');
        }
        
        if (!preg_match('/[0-9]/', $data['new_password'])) {
            throw new \FOSSBilling\Exception('Password must contain at least one number.');
        }
        
        // Get order and verify ownership
        $orderId = $data['order_id'];
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');
        
        $client = $this->getIdentity();
        if ($order->client_id !== $client->id) {
            throw new \FOSSBilling\Exception('Order not found');
        }
        
        // Call service method with new signature
        $result = $this->getService()->changeAccountPassword($order, null, ['new_password' => $data['new_password']]);
        
        return ['result' => $result];
    }
}
