<?php

namespace Hafael\Azure\Transport;

use Illuminate\Mail\MailManager;

class AzureMailerManager extends MailManager
{
    protected function createAzureTransport()
    {
        $config = $this->app['config']->get('mail.mailers.azure', []);
        $configHttp = isset($config['http']) ? $config['http'] : [];
        
        return new AzureMailerApiTransport(
            $config['endpoint'], 
            $config['access_key'], 
            $config['api_version'], 
            boolval($config['disable_user_tracking']),
            $this->getHttpClient($configHttp),
        );
    }
}