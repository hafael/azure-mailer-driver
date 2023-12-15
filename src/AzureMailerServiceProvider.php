<?php

namespace Hafael\Mailer\Azure;

use Illuminate\Mail\MailServiceProvider;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AzureMailerServiceProvider extends MailServiceProvider
{
    /**
     * Register the Illuminate mailer instance.
     *
     * @return void
     */
    protected function registerIlluminateMailer()
    {
        $this->app->singleton('mail.manager', function($app) {
            return new AzureMailerManager($app);
        });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $this->app->extend('azure', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $this->app->extend('azure', function () {
            return (new AzureTransportFactory)->create(
                new Dsn(
                    'azure+api',
                    'default',
                    config('mail.mailers.azure.resource_name'),
                    config('mail.mailers.azure.access_key'),
                    null,
                    [
                        'api_version' => config('mail.mailers.azure.api_version'),
                        'disable_tracking' => config('mail.mailers.azure.disable_user_tracking')
                    ]
                )
            );
        });
    }
}
