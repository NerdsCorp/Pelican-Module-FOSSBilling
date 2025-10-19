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
            'system.admin.controller',
            'get:/servicepterodactyl/settings',
            [$this, 'get_settings'],
            [],
            'servicepterodactyl'
        );
    }

    public function get_settings(\FOSSBilling\View $view): string
    {
        $api = $this->di['api']('admin');
        
        // Get current settings
        $systemService = $this->di['mod_service']('system');
        $settings = [
            'panel_url' => $systemService->getParamValue('servicepterodactyl_panel_url', ''),
            'api_key' => $systemService->getParamValue('servicepterodactyl_api_key', ''),
            'default_node' => $systemService->getParamValue('servicepterodactyl_default_node', 1),
            'default_egg' => $systemService->getParamValue('servicepterodactyl_default_egg', 1),
            'default_docker_image' => $systemService->getParamValue('servicepterodactyl_default_docker_image', 'quay.io/pterodactyl/core:java'),
            'default_memory' => $systemService->getParamValue('servicepterodactyl_default_memory', 512),
            'default_disk' => $systemService->getParamValue('servicepterodactyl_default_disk', 1024),
            'default_cpu' => $systemService->getParamValue('servicepterodactyl_default_cpu', 100),
        ];
        
        return $view->render('mod_servicepterodactyl_settings', ['settings' => $settings]);
    }
}
