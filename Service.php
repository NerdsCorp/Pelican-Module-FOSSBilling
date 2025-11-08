<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicepelican;

use FOSSBilling\InjectionAwareInterface;
use RedBeanPHP\OODBBean;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?array $panelConfig = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function attachOrderConfig(\Model_Product $product, array $data): array
    {
        !empty($product->config) ? $config = json_decode($product->config, true) : $config = [];

        return array_merge($config, $data);
    }

    public function create(OODBBean $order)
    {
        $model = $this->di['db']->dispense('service_pelican');
        $model->client_id = $order->client_id;
        $model->config = $order->config;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function activate(OODBBean $order, OODBBean $model): bool
    {
        return $this->provision($order, $model);
    }

    public function provision(OODBBean $order, OODBBean $model): bool
    {
        $config = json_decode($order->config, 1);
        if (!is_object($model)) {
            throw new \FOSSBilling\Exception('Order does not exist.');
        }

        try {
            // Store panel config for later use
            $this->panelConfig = $this->getPanelConfig($config);
            
            $client = $this->di['db']->load('client', $model->client_id);
            if (!$client) {
                throw new \FOSSBilling\Exception('Client not found');
            }
            $serverData = $this->createPterodactylServer($config, $client);
            
            $model->server_id = $serverData['id'];
            $model->server_identifier = $serverData['identifier'];
            $model->status = 'active';
            $model->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($model);

            return true;
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to provision server: ' . $e->getMessage());
        }
    }

    public function suspend(OODBBean $order, OODBBean $model): bool
    {
        try {
            $config = json_decode($order->config, 1);
            $this->panelConfig = $this->getPanelConfig($config);
            
            if ($model->server_id) {
                $this->suspendPelicanServer($model->server_id);
            }
            
            $model->status = 'suspended';
            $model->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($model);

            return true;
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to suspend server: ' . $e->getMessage());
        }
    }

    public function unsuspend(OODBBean $order, OODBBean $model): bool
    {
        try {
            $config = json_decode($order->config, 1);
            $this->panelConfig = $this->getPanelConfig($config);
            
            if ($model->server_id) {
                $this->unsuspendPelicanServer($model->server_id);
            }
            
            $model->status = 'active';
            $model->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($model);

            return true;
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to unsuspend server: ' . $e->getMessage());
        }
    }

    public function cancel(OODBBean $order, OODBBean $model): bool
    {
        return $this->suspend($order, $model);
    }

    public function uncancel(OODBBean $order, OODBBean $model): bool
    {
        return $this->unsuspend($order, $model);
    }

    public function delete(?OODBBean $order, ?OODBBean $model): void
    {
        $this->unprovision($order, $model);
    }

    public function unprovision(?OODBBean $order, ?OODBBean $model): void
    {
        if (is_object($model)) {
            try {
                // Get panel config
                $config = [];
                if ($order) {
                    $config = json_decode($order->config, 1) ?? [];
                }
                $this->panelConfig = $this->getPanelConfig($config);
                
                // Delete server from Pterodactyl if exists
                if ($model->server_id) {
                    $this->deletePelicanServer($model->server_id);
                }
                
                // Update model status instead of deleting
                $model->status = 'deleted';
                $model->server_id = null;
                $model->server_identifier = null;
                $model->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($model);
            } catch (\Exception $e) {
                throw new \FOSSBilling\Exception('Failed to unprovision server: ' . $e->getMessage());
            }
        }
    }

    public function toApiArray(OODBBean $model): array
    {
        $result = [
            'id' => $model->id,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'status' => $model->status ?? 'active',
            'server_id' => $model->server_id,
            'server_identifier' => $model->server_identifier,
            'config' => json_decode($model->config, true),
        ];
        
        // Add panel URL if server exists
        if ($model->server_identifier) {
            try {
                $panelConfig = $this->getGlobalPanelConfig();
                $result['panel_url'] = rtrim($panelConfig['panel_url'], '/') . '/server/' . $model->server_identifier;
            } catch (\Exception $e) {
                $result['panel_url'] = null;
            }
        }
        
        return $result;
    }


    /**
     * Creates the database structure to store the Pterodactyl server information.
     */
    public function install(): bool
    {
        $sql = '
        CREATE TABLE IF NOT EXISTS `service_pelican` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT UNIQUE,
            `client_id` bigint(20) NOT NULL,
            `server_id` bigint(20),
            `server_identifier` varchar(8),
            `status` varchar(50) DEFAULT "pending",
            `config` text NOT NULL,
            `created_at` datetime,
            `updated_at` datetime,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $this->di['db']->exec($sql);

        return true;
    }

    /**
     * Removes the Pterodactyl service table from the database.
     */
    public function uninstall(): bool
    {
        $this->di['db']->exec('DROP TABLE IF EXISTS `service_pelican`');

        return true;
    }

    /**
     * Creates a new server on Pterodactyl panel
     */
    private function createPelicanServer(array $config, OODBBean $client): array
    {
        $panelConfig = $this->panelConfig ?? $this->getPanelConfig($config);
        
        // First, we need to create or get the user
        $userEmail = $client->email ?? 'noemail@example.com';
        $userId = $this->getOrCreateUser($userEmail, $client);
        
        // Get or create allocation on the specified node
        $nodeId = (int)($config['node_id'] ?? $panelConfig['default_node']);
        $allocationId = $this->getOrCreateAllocation($nodeId);
        
        // Get egg information and prepare environment variables
        $eggId = (int)($config['egg_id'] ?? $panelConfig['default_egg']);
        $eggInfo = $this->getEggInfo($eggId);
        $environment = $this->prepareEnvironmentVariables($eggInfo, $config);
        
        $serverData = [
            'name' => $config['server_name'] ?? 'Server-' . time(),
            'user' => $userId,
            'egg' => $eggId,
            'docker_image' => $config['docker_image'] ?? $eggInfo['docker_image'] ?? $panelConfig['default_docker_image'],
            'startup' => $config['startup_command'] ?? $eggInfo['startup'] ?? $panelConfig['default_startup'],
            'environment' => $environment,
            'limits' => [
                'memory' => (int)($config['memory'] ?? $panelConfig['default_memory']),
                'swap' => (int)($config['swap'] ?? $panelConfig['default_swap']),
                'disk' => (int)($config['disk'] ?? $panelConfig['default_disk']),
                'io' => (int)($config['io'] ?? $panelConfig['default_io']),
                'cpu' => (int)($config['cpu'] ?? $panelConfig['default_cpu']),
            ],
            'feature_limits' => [
                'databases' => (int)($config['databases'] ?? $panelConfig['default_databases']),
                'allocations' => (int)($config['allocations'] ?? $panelConfig['default_allocations']),
                'backups' => (int)($config['backups'] ?? $panelConfig['default_backups']),
            ],
            'allocation' => [
                'default' => $allocationId,
            ],
        ];

        $response = $this->pelicanApiRequest('POST', '/api/application/servers', $serverData);
        
        if (!isset($response['attributes']['id'])) {
            throw new \FOSSBilling\Exception('Failed to create server on Pelican');
        }

        // Store both the admin ID (for API calls) and identifier (for display)
        $serverId = $response['attributes']['id'];
        $serverIdentifier = $response['attributes']['identifier'];
        
        return ['id' => $serverId, 'identifier' => $serverIdentifier];
    }

    /**
     * Suspend a server on Pterodactyl panel
     */
    private function suspendPelicanServer(int $serverId): void
    {
        $this->pelicanApiRequest('POST', "/api/application/servers/{$serverId}/suspend");
    }

    /**
     * Unsuspend a server on Pterodactyl panel
     */
    private function unsuspendPelicanServer(int $serverId): void
    {
        $this->pelicanApiRequest('POST', "/api/application/servers/{$serverId}/unsuspend");
    }

    /**
     * Delete a server on Pterodactyl panel
     */
    private function deletePelicanServer(int $serverId): void
    {
        $this->pelicanApiRequest('DELETE', "/api/application/servers/{$serverId}");
    }

    /**
     * Get or create a user on Pterodactyl panel
     */
    private function getOrCreateUser(string $email, OODBBean $client): int
    {
        try {
            // First try to find existing user
            $response = $this->pelicanApiRequest('GET', "/api/application/users?filter[email]={$email}");
            
            if (!empty($response['data'])) {
                return $response['data'][0]['attributes']['id'];
            }
            
            // Create new user if not found
            $userData = [
                'email' => $email,
                'username' => $this->generateUsername($email),
                'first_name' => $client->first_name ?? 'Client',
                'last_name' => $client->last_name ?? 'User',
                'password' => $this->generateRandomPassword(),
            ];
            
            $response = $this->pelicanApiRequest('POST', '/api/application/users', $userData);
            
            if (!isset($response['attributes']['id'])) {
                throw new \FOSSBilling\Exception('Failed to create user on Pelican');
            }
            
            return $response['attributes']['id'];
            
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to get or create user: ' . $e->getMessage());
        }
    }

    /**
     * Generate username from email
     */
    private function generateUsername(string $email): string
    {
        $username = explode('@', $email)[0];
        $username = preg_replace('/[^a-zA-Z0-9]/', '', $username);
        $username = substr($username, 0, 20);
        
        if (empty($username)) {
            $username = 'user' . time();
        }
        
        return strtolower($username);
    }

    /**
     * Generate random password
     */
    private function generateRandomPassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get or create an allocation on the specified node
     */
    private function getOrCreateAllocation(int $nodeId): int
    {
        try {
            // First try to find an available allocation on the node (unassigned ones)
            $response = $this->pelicanApiRequest('GET', "/api/application/nodes/{$nodeId}/allocations");
            
            // Filter unassigned allocations client-side
            if (!empty($response['data'])) {
                foreach ($response['data'] as $allocation) {
                    if (empty($allocation['attributes']['assigned'])) {
                        return $allocation['attributes']['id'];
                    }
                }
            }
            
            // If no available allocation, create a new one
            // We'll try to find an available port
            $port = $this->findAvailablePort($nodeId);
            
            $allocationData = [
                'ip' => '0.0.0.0', // Default IP
                'ports' => [$port],
            ];
            
            $response = $this->pelicanApiRequest('POST', "/api/application/nodes/{$nodeId}/allocations", $allocationData);
            
            if (!empty($response['data'])) {
                return $response['data'][0]['attributes']['id'];
            }
            
            throw new \FOSSBilling\Exception('Failed to create allocation on node');
            
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to get or create allocation: ' . $e->getMessage());
        }
    }

    /**
     * Find an available port on the node
     */
    private function findAvailablePort(int $nodeId): int
    {
        // Get existing allocations to find used ports
        $response = $this->pelicanApiRequest('GET', "/api/application/nodes/{$nodeId}/allocations");
        
        $usedPorts = [];
        if (!empty($response['data'])) {
            foreach ($response['data'] as $allocation) {
                $usedPorts[] = $allocation['attributes']['port'];
            }
        }
        
        // Find an available port starting from 25565 (common Minecraft port)
        $startPort = 25565;
        $maxTries = 1000;
        
        for ($i = 0; $i < $maxTries; $i++) {
            $port = $startPort + $i;
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }
        
        // Fallback to a random high port
        return rand(30000, 65535);
    }

    /**
     * Get egg information from Pterodactyl
     */
    private function getEggInfo(int $eggId): array
    {
        try {
            // First get all nests
            $nestsResponse = $this->pelicanApiRequest('GET', "/api/application/nests?include=eggs");
            
            // Find which nest contains our egg
            $nestId = null;
            foreach ($nestsResponse['data'] as $nest) {
                foreach ($nest['attributes']['relationships']['eggs']['data'] as $egg) {
                    if ($egg['attributes']['id'] === $eggId) {
                        $nestId = $nest['attributes']['id'];
                        break 2;
                    }
                }
            }
            
            if (!$nestId) {
                throw new \FOSSBilling\Exception('Could not find nest containing egg ID ' . $eggId);
            }
            
            // Get detailed egg info from the nest
            $response = $this->pelicanApiRequest('GET', "/api/application/nests/{$nestId}/eggs/{$eggId}?include=variables");
            return $response['attributes'] ?? [];
            
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to get egg info: ' . $e->getMessage());
        }
    }

    /**
     * Prepare environment variables based on egg requirements
     */
    private function prepareEnvironmentVariables(array $eggInfo, array $config): array
    {
        $environment = [];
        
        // Start with user-provided environment variables
        if (!empty($config['environment']) && is_array($config['environment'])) {
            $environment = $config['environment'];
        }
        
        // Add required egg variables with default values
        if (!empty($eggInfo['relationships']['variables']['data'])) {
            foreach ($eggInfo['relationships']['variables']['data'] as $variable) {
                $varData = $variable['attributes'];
                $envKey = $varData['env_variable'];
                
                // If variable is not set by user, use default value
                if (!isset($environment[$envKey])) {
                    $defaultValue = $varData['default_value'] ?? '';
                    
                    // Set common default values for typical Minecraft variables
                    switch ($envKey) {
                        case 'SERVER_JARFILE':
                            $environment[$envKey] = $defaultValue ?: 'server.jar';
                            break;
                        case 'VANILLA_VERSION':
                        case 'MC_VERSION':
                        case 'VERSION':
                            $environment[$envKey] = $defaultValue ?: 'latest';
                            break;
                        case 'FORGE_VERSION':
                            $environment[$envKey] = $defaultValue ?: 'recommended';
                            break;
                        case 'BUILD_NUMBER':
                            $environment[$envKey] = $defaultValue ?: 'latest';
                            break;
                        default:
                            $environment[$envKey] = $defaultValue;
                            break;
                    }
                }
            }
        }
        
        return $environment;
    }

    /**
     * Restart a server on Pterodactyl panel
     */
    public function restartServer(int $orderId): bool
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        
        if (!$model->server_id) {
            throw new \FOSSBilling\Exception('Server not provisioned');
        }

        try {
            $this->pelicanApiRequest('POST', "/api/client/servers/{$model->server_id}/power", [
                'signal' => 'restart'
            ]);
            
            return true;
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to restart server: ' . $e->getMessage());
        }
    }

    /**
     * Change account password on Pelican panel
     * This follows FOSSBilling naming convention for compatibility with admin interface
     * Can be called from both admin and client contexts
     * 
     * @param OODBBean|int $orderOrId - Order bean or order ID
     * @param OODBBean|null $model - Service model (optional)
     * @param array|string $data - Password data or string
     * @return bool
     */
    public function changeAccountPassword($orderOrId, $model = null, $data = []): bool
    {
        // Handle different parameter formats from FOSSBilling
        if (is_object($orderOrId)) {
            // Called with order bean
            $order = $orderOrId;
            $orderId = $order->id;
        } else {
            // Called with order ID
            $orderId = $orderOrId;
            $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');
        }
        
        // Extract password from data
        $newPassword = null;
        if (is_string($data)) {
            $newPassword = $data;
        } elseif (is_array($data)) {
            $newPassword = $data['password'] ?? $data['new_password'] ?? null;
        } elseif (is_string($model)) {
            // Sometimes password is passed as second parameter
            $newPassword = $model;
            $model = null;
        }
        
        if (empty($newPassword)) {
            throw new \FOSSBilling\Exception('Password is required');
        }
        
        // Get service model if not provided
        if (!$model) {
            $orderService = $this->di['mod_service']('order');
            $model = $orderService->getOrderService($order);
        }
        
        if (!$model->server_id) {
            throw new \FOSSBilling\Exception('Server not provisioned');
        }

        try {
            // Get config to access panel
            $config = json_decode($order->config, 1);
            $this->panelConfig = $this->getPanelConfig($config);
            
            // Get client information
            $client = $this->di['db']->load('client', $order->client_id);
            if (!$client) {
                throw new \FOSSBilling\Exception('Client not found');
            }
            
            // Get user ID from Pterodactyl
            $userEmail = $client->email ?? 'noemail@example.com';
            $userId = $this->getOrCreateUser($userEmail, $client);
            
            // Update password via Pelican API
            $userData = [
                'email' => $userEmail,
                'username' => $this->generateUsername($userEmail),
                'first_name' => $client->first_name ?? 'Client',
                'last_name' => $client->last_name ?? 'User',
                'password' => $newPassword,
            ];
            
            $this->pelicanApiRequest('PATCH', "/api/application/users/{$userId}", $userData);
            
            // Log the password change
            if (isset($this->di['logger'])) {
                $this->di['logger']->info('Pelican password changed for order #%s', $orderId);
            }
            
            return true;
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to change password: ' . $e->getMessage());
        }
    }

    /**
     * Get server information from Pelican
     */
    public function getServerInfo(int $serverId): array
    {
        try {
            $response = $this->pelicanApiRequest('GET', "/api/application/servers/{$serverId}");
            return $response['attributes'] ?? [];
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to get server info: ' . $e->getMessage());
        }
    }

    /**
     * Get server status from Pelican
     */
    public function getServerStatus(int $serverId): array
    {
        try {
            $response = $this->pelicanApiRequest('GET', "/api/client/servers/{$serverId}/resources");
            return $response['attributes'] ?? [];
        } catch (\Exception $e) {
            throw new \FOSSBilling\Exception('Failed to get server status: ' . $e->getMessage());
        }
    }

    /**
     * Make API request to Pelican panel
     */
    private function pelicanApiRequest(string $method, string $endpoint, array $data = []): array
    {
        $panelConfig = $this->panelConfig;
        
        $url = rtrim($panelConfig['panel_url'], '/') . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $panelConfig['api_key'],
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $errorDetails = '';
            if ($response) {
                $errorData = json_decode($response, true);
                if (isset($errorData['errors'])) {
                    $errorDetails = ' - ' . json_encode($errorData['errors']);
                }
            }
            throw new \FOSSBilling\Exception('Pelican API request failed with HTTP code: ' . $httpCode . $errorDetails);
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Get panel configuration from order/product
     */
    private function getPanelConfig(array $orderConfig = []): array
    {
        // First try to get config from order
        if (!empty($orderConfig['panel_url']) && !empty($orderConfig['api_key'])) {
            return $orderConfig;
        }
        
        // Try to get from global service configuration (stored in a settings table or config file)
        $globalConfig = $this->getGlobalPanelConfig();
        if (!empty($globalConfig['panel_url']) && !empty($globalConfig['api_key'])) {
            // Merge order config with global config
            return array_merge($globalConfig, $orderConfig);
        }
        
        // Fallback: try to get from product configuration
        $defaultConfig = [
            'panel_url' => $orderConfig['panel_url'] ?? $globalConfig['panel_url'] ?? '',
            'api_key' => $orderConfig['api_key'] ?? $globalConfig['api_key'] ?? '',
            'default_node' => $orderConfig['default_node'] ?? $globalConfig['default_node'] ?? 1,
            'default_egg' => $orderConfig['default_egg'] ?? $globalConfig['default_egg'] ?? 1,
            'default_docker_image' => $orderConfig['default_docker_image'] ?? $globalConfig['default_docker_image'] ?? 'quay.io/pelican/core:java',
            'default_startup' => $orderConfig['default_startup'] ?? $globalConfig['default_startup'] ?? 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
            'default_memory' => $orderConfig['default_memory'] ?? $globalConfig['default_memory'] ?? 512,
            'default_swap' => $orderConfig['default_swap'] ?? $globalConfig['default_swap'] ?? 0,
            'default_disk' => $orderConfig['default_disk'] ?? $globalConfig['default_disk'] ?? 1024,
            'default_io' => $orderConfig['default_io'] ?? $globalConfig['default_io'] ?? 500,
            'default_cpu' => $orderConfig['default_cpu'] ?? $globalConfig['default_cpu'] ?? 100,
            'default_databases' => $orderConfig['default_databases'] ?? $globalConfig['default_databases'] ?? 1,
            'default_allocations' => $orderConfig['default_allocations'] ?? $globalConfig['default_allocations'] ?? 1,
            'default_backups' => $orderConfig['default_backups'] ?? $globalConfig['default_backups'] ?? 1,
        ];
        
        if (empty($defaultConfig['panel_url']) || empty($defaultConfig['api_key'])) {
            throw new \FOSSBilling\Exception('Pelican panel URL and API key must be configured. Please configure them in the product settings.');
        }

        return $defaultConfig;
    }

    /**
     * Get global panel configuration from system settings
     */
    private function getGlobalPanelConfig(): array
    {
        try {
            // Try to get from system settings or config table
            $settingService = $this->di['mod_service']('system');
            
            return [
                'panel_url' => $settingService->getParamValue('servicepelican_panel_url', ''),
                'api_key' => $settingService->getParamValue('servicepelican_api_key', ''),
                'default_node' => (int)$settingService->getParamValue('servicepelican_default_node', 1),
                'default_egg' => (int)$settingService->getParamValue('servicepelican_default_egg', 1),
                'default_docker_image' => $settingService->getParamValue('servicepelican_default_docker_image', 'quay.io/pelican/core:java'),
                'default_startup' => $settingService->getParamValue('servicepelican_default_startup', 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}'),
                'default_memory' => (int)$settingService->getParamValue('servicepelican_default_memory', 512),
                'default_swap' => (int)$settingService->getParamValue('servicepelican_default_swap', 0),
                'default_disk' => (int)$settingService->getParamValue('servicepelican_default_disk', 1024),
                'default_io' => (int)$settingService->getParamValue('servicepelican_default_io', 500),
                'default_cpu' => (int)$settingService->getParamValue('servicepelicanl_default_cpu', 100),
                'default_databases' => (int)$settingService->getParamValue('servicepelican_default_databases', 1),
                'default_allocations' => (int)$settingService->getParamValue('servicepelican_default_allocations', 1),
                'default_backups' => (int)$settingService->getParamValue('servicepelican_default_backups', 1),
            ];
        } catch (\Exception $e) {
            // If system service not available, return empty array
            return [];
        }
    }

}
