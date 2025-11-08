<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicepelican\Controller;

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
            'get:/servicepelican/settings',
            [$this, 'get_settings'],
            [],
            'servicepelican'
        );
    }

    public function get_settings(\FOSSBilling\View $view): string
    {
        $api = $this->di['api']('admin');
        
        // Get current settings
        $systemService = $this->di['mod_service']('system');
        $settings = [
            'panel_url' => $systemService->getParamValue('servicepelican_panel_url', ''),
            'api_key' => $systemService->getParamValue('servicepelican_api_key', ''),
            'default_node' => $systemService->getParamValue('servicepelican_default_node', 1),
            'default_egg' => $systemService->getParamValue('servicepelican_default_egg', 1),
            'default_docker_image' => $systemService->getParamValue('servicepelican_default_docker_image', 'quay.io/pelican/core:java'),
            'default_memory' => $systemService->getParamValue('servicepelican_default_memory', 512),
            'default_disk' => $systemService->getParamValue('servicepelican_default_disk', 1024),
            'default_cpu' => $systemService->getParamValue('servicepelican_default_cpu', 100),
        ];

        return $view->render('mod_servicepelican_settings', ['settings' => $settings]);
    }
}
