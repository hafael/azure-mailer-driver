<?php

namespace Hafael\Azure\Transport;

use Illuminate\Mail\MailServiceProvider;
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
            return (new AzureMailerTransportFactory)->create(
                new Dsn(
                    'azure+api',
                    config('mail.mailers.azure.endpoint'),
                    config('mail.mailers.azure.access_key'),
                    config('mail.mailers.azure.api_version'),
                    config('mail.mailers.azure.disable_user_tracking')
                )
            );
        });
    }
}
