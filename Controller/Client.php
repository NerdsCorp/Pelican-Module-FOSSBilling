<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicepterodactyl\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected $di;

    public function setDi(\Pimple\Container|null $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }
    
    public function register(\FOSSBilling\Events $hooks): void
    {
        $hooks->on(
            'system.client.controller',
            'get:/servicepterodactyl/:id',
            [$this, 'get_manage'],
            ['id' => '[0-9]+'],
            'servicepterodactyl'
        );
    }

    public function get_manage(\FOSSBilling\View $view, int $id): string
    {
        $api = $this->di['api']('client');
        $data = $api->order_get(['id' => $id]);
        if ($data['service_type'] !== 'servicepterodactyl') {
            throw new \FOSSBilling\Exception('Invalid order type');
        }

        $service = $api->servicepterodactyl_get(['order_id' => $id]);
        
        return $view->render('mod_servicepterodactyl_manage', [
            'order' => $data,
            'service' => $service
        ]);
    }
}
