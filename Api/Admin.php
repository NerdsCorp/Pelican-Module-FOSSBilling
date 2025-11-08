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

class Admin extends \Api_Abstract
{
    /**
     * Update a Pelican server. Can be used to change the config.
     *
     * @param array $data - An associative array
     *                    - int 'order_id' (required) The order ID of the server to update.
     *                    - array 'config' (optional) The new configuration for the server.
     */
    public function update($data): bool
    {
        return $this->getService()->updateServer($data);
    }

    /**
     * Provision a new Pelican server.
     *
     * @param array $data - An associative array
     *                    - int 'order_id' (required) The order ID to provision.
     */
    public function provision($data): bool
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required for provisioning.');
        }

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        
        return $this->getService()->provision($order, $model);
    }

    /**
     * Unprovision a Pelican server.
     *
     * @param array $data - An associative array
     *                    - int 'order_id' (required) The order ID to unprovision.
     */
    public function unprovision($data): bool
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required for unprovisioning.');
        }

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        
        $this->getService()->unprovision($order, $model);
        return true;
    }

    /**
     * Suspend a Pelican server.
     *
     * @param array $data - An associative array
     *                    - int 'order_id' (required) The order ID to suspend.
     */
    public function suspend($data): bool
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required for suspension.');
        }

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        
        return $this->getService()->suspend($order, $model);
    }

    /**
     * Unsuspend a Pelican server.
     *
     * @param array $data - An associative array
     *                    - int 'order_id' (required) The order ID to unsuspend.
     */
    public function unsuspend($data): bool
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required for unsuspension.');
        }

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        
        return $this->getService()->unsuspend($order, $model);
    }

    /**
     * Change account password on Pelican server.
     * This method is called by FOSSBilling admin interface
     *
     * @param array $data - An associative array
     *                    - int 'order_id' (required) The order ID
     *                    - string 'password' (required) The new password
     */
    public function change_account_password($data): bool
    {
        if (empty($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required.');
        }

        if (empty($data['password'])) {
            throw new \FOSSBilling\Exception('Password is required.');
        }

        // Get order
        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        
        // Admin context - no ownership check needed
        return $this->getService()->changeAccountPassword($order, null, ['password' => $data['password']]);
    }

    /**
     * Save global Pelican settings.
     *
     * @param array $data - An associative array with settings
     */
    public function save_settings($data): bool
    {
        $systemService = $this->di['mod_service']('system');

        $settings = [
            'servicepelican_panel_url' => $data['panel_url'] ?? '',
            'servicepelican_api_key' => $data['api_key'] ?? '',
            'servicepelican_default_node' => $data['default_node'] ?? 1,
            'servicepelican_default_egg' => $data['default_egg'] ?? 1,
            'servicepelican_default_docker_image' => $data['default_docker_image'] ?? 'quay.io/pelican/core:java',
            'servicepelican_default_memory' => $data['default_memory'] ?? 512,
            'servicepelican_default_disk' => $data['default_disk'] ?? 1024,
            'servicepelican_default_cpu' => $data['default_cpu'] ?? 100,
        ];
        
        foreach ($settings as $key => $value) {
            $systemService->setParamValue($key, $value);
        }
        
        return true;
    }
}
