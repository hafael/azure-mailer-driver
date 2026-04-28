<?php

namespace Hafael\Mailer\Azure;

use Illuminate\Support\ServiceProvider;

class AzureMailerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $manager = $this->app->make('mail.manager');

        $manager->extend('acs', fn (array $config) => $this->makeTransport($config));

        // Legacy 'azure' transport — registers a lazy callback that maps old config keys to the
        // new format. The transport is only instantiated if legacy 'transport: azure' is actually used.
        $manager->extend('azure', fn (array $config) => $this->makeTransport($this->normalizeLegacyConfig($config)));
    }

    private function makeTransport(array $config): AzureTransport
    {
        return new AzureTransport(
            $config['key'] ?? $this->app['config']->get('services.acs.key'),
            $config['endpoint'] ?? $this->app['config']->get('services.acs.endpoint'),
            $config['disable_tracking'] ?? false,
            $config['api_version'] ?? '2023-03-31',
        );
    }

    /**
     * Maps old 'azure' transport config keys to the current format:
     *   access_key       → key
     *   resource_name    → endpoint (only plain short names supported, e.g. "my-resource")
     *   disable_user_tracking → disable_tracking
     */
    private function normalizeLegacyConfig(array $config): array
    {
        if (! isset($config['key']) && isset($config['access_key'])) {
            $config['key'] = $config['access_key'];
        }

        if (! isset($config['endpoint']) && isset($config['resource_name'])) {
            $name = $config['resource_name'];

            if (str_contains($name, '.') || str_contains($name, '/') || str_contains($name, ':')) {
                throw new \InvalidArgumentException(
                    "Legacy config 'mailers.azure' only supports plain 'resource_name' (e.g. \"my-resource\"). ".
                    "Switch your 'mailers.azure.transport' to 'acs' and move your 'endpoint' and 'key' to 'services.acs' instead."
                );
            }

            $config['endpoint'] = "https://{$name}.communication.azure.com";
        }

        if (! isset($config['disable_tracking']) && isset($config['disable_user_tracking'])) {
            $config['disable_tracking'] = (bool) $config['disable_user_tracking'];
        }

        return $config;
    }
}
