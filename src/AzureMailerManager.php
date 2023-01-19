<?php

namespace Hafael\Azure\SwiftMailer;

use Illuminate\Mail\MailManager;

class AzureMailerManager extends MailManager
{
    protected function createAzureTransport()
    {
        $config = $this->app['config']->get('mail.mailers.azure', []);

        return new AzureMailerTransport(
            $this->guzzle($config), 
            $config['endpoint'], 
            $config['access_key'], 
            $config['api_version'], 
            boolval($config['disable_user_tracking']), 
        );
    }
}