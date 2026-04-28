<?php

namespace Hafael\Mailer\Azure\Tests;

use Hafael\Mailer\Azure\AzureMailerServiceProvider;
use Hafael\Mailer\Azure\AzureTransport;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Mail\MailManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Email;

class AzureTransportTest extends TestCase
{
    public function test_extend_registers_acs_transport(): void
    {
        $container = new Container;

        $container->singleton('config', fn () => new Repository([
            'services' => [
                'acs' => [
                    'key' => 'dGVzdC1rZXk=',
                    'endpoint' => 'https://test-resource.communication.azure.com',
                ],
            ],
        ]));

        $manager = new MailManager($container);
        $container->instance('mail.manager', $manager);

        (new AzureMailerServiceProvider($container))->boot();

        $transport = $manager->createSymfonyTransport(['transport' => 'acs']);

        $this->assertInstanceOf(AzureTransport::class, $transport);
        $this->assertSame('acs', (string) $transport);
    }

    public function test_mailer_config_overrides_services_config(): void
    {
        $container = new Container;

        $container->singleton('config', fn () => new Repository([
            'services' => [
                'acs' => [
                    'key' => 'from-services',
                    'endpoint' => 'https://services-resource.communication.azure.com',
                ],
            ],
        ]));

        $manager = new MailManager($container);
        $container->instance('mail.manager', $manager);

        (new AzureMailerServiceProvider($container))->boot();

        $transport = $manager->createSymfonyTransport([
            'transport' => 'acs',
            'key' => 'from-mailer',
            'endpoint' => 'https://mailer-resource.communication.azure.com',
        ]);

        $this->assertInstanceOf(AzureTransport::class, $transport);
    }

    #[Group('legacy-config')]
    public function test_legacy_azure_transport_with_resource_name(): void
    {
        $container = new Container;
        $container->singleton('config', fn () => new Repository([]));

        $manager = new MailManager($container);
        $container->instance('mail.manager', $manager);

        (new AzureMailerServiceProvider($container))->boot();

        $transport = $manager->createSymfonyTransport([
            'transport' => 'azure',
            'access_key' => 'dGVzdC1rZXk=',
            'resource_name' => 'my-resource',
            'disable_user_tracking' => true,
            'api_version' => '2023-03-31',
        ]);

        $this->assertInstanceOf(AzureTransport::class, $transport);
        $this->assertSame('acs', (string) $transport);
    }

    #[Group('legacy-config')]
    public function test_legacy_azure_transport_with_endpoint(): void
    {
        $container = new Container;
        $container->singleton('config', fn () => new Repository([]));

        $manager = new MailManager($container);
        $container->instance('mail.manager', $manager);

        (new AzureMailerServiceProvider($container))->boot();

        $transport = $manager->createSymfonyTransport([
            'transport' => 'azure',
            'access_key' => 'dGVzdC1rZXk=',
            'endpoint' => 'https://my-resource.unitedstates.communication.azure.com',
        ]);

        $this->assertInstanceOf(AzureTransport::class, $transport);
        $this->assertSame('acs', (string) $transport);
    }

    #[Group('legacy-config')]
    public function test_legacy_azure_transport_endpoint_takes_precedence_over_resource_name(): void
    {
        $container = new Container;
        $container->singleton('config', fn () => new Repository([]));

        $manager = new MailManager($container);
        $container->instance('mail.manager', $manager);

        (new AzureMailerServiceProvider($container))->boot();

        $transport = $manager->createSymfonyTransport([
            'transport' => 'azure',
            'access_key' => 'dGVzdC1rZXk=',
            'resource_name' => 'ignored-resource',
            'endpoint' => 'https://my-resource.unitedstates.communication.azure.com',
        ]);

        $this->assertInstanceOf(AzureTransport::class, $transport);
        $this->assertSame('acs', (string) $transport);
    }

    #[Group('legacy-config')]
    public function test_legacy_azure_transport_rejects_region_in_resource_name(): void
    {
        $container = new Container;
        $container->singleton('config', fn () => new Repository([]));

        $manager = new MailManager($container);
        $container->instance('mail.manager', $manager);

        (new AzureMailerServiceProvider($container))->boot();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("only supports plain 'resource_name'");

        $manager->createSymfonyTransport([
            'transport' => 'azure',
            'access_key' => 'dGVzdC1rZXk=',
            'resource_name' => 'test-resource.unitedstates',
        ]);
    }

    public function test_send(): void
    {
        $requestUrl = null;
        $requestBody = null;

        $client = new MockHttpClient(function ($method, $url, $options) use (&$requestUrl, &$requestBody) {
            $requestUrl = $url;
            $requestBody = json_decode($options['body'], true);

            return new MockResponse(
                json_encode(['id' => 'test-operation-id']),
                ['http_code' => 202],
            );
        });

        $transport = new AzureTransport('dGVzdC1rZXk=', 'https://test-resource.unitedstates.communication.azure.com', false, '2023-03-31', $client);

        $message = new Email;
        $message->subject('Test subject');
        $message->html('<p>Hello</p>');
        $message->text('Hello');
        $message->sender('sender@example.com');
        $message->to('to@example.com');
        $message->cc('cc@example.com');
        $message->bcc('bcc@example.com');
        $message->replyTo('reply@example.com');
        $message->getHeaders()->addTextHeader('X-Custom-Header', 'CustomValue');

        $sent = $transport->send($message);

        $this->assertStringContainsString('test-resource.unitedstates.communication.azure.com', $requestUrl);
        $this->assertStringContainsString('/emails:send', $requestUrl);
        $this->assertSame('sender@example.com', $requestBody['senderAddress']);
        $this->assertSame([['address' => 'to@example.com']], $requestBody['recipients']['to']);
        $this->assertSame([['address' => 'cc@example.com']], $requestBody['recipients']['cc']);
        $this->assertSame([['address' => 'bcc@example.com']], $requestBody['recipients']['bcc']);
        $this->assertSame([['address' => 'reply@example.com']], $requestBody['replyTo']);
        $this->assertSame('Test subject', $requestBody['content']['subject']);
        $this->assertSame('<p>Hello</p>', $requestBody['content']['html']);
        $this->assertSame('Hello', $requestBody['content']['plainText']);
        $this->assertSame('CustomValue', $requestBody['headers']['X-Custom-Header']);
        $this->assertSame('test-operation-id', $sent->getMessageId());
    }

    public function test_send_with_trailing_slash_in_endpoint(): void
    {
        $requestUrl = null;

        $client = new MockHttpClient(function ($method, $url, $options) use (&$requestUrl) {
            $requestUrl = $url;

            return new MockResponse(
                json_encode(['id' => 'test-operation-id']),
                ['http_code' => 202],
            );
        });

        $transport = new AzureTransport('dGVzdC1rZXk=', 'https://test-resource.unitedstates.communication.azure.com/', false, '2023-03-31', $client);

        $message = new Email;
        $message->subject('Test');
        $message->text('Body');
        $message->sender('sender@example.com');
        $message->to('to@example.com');

        $transport->send($message);

        $this->assertStringContainsString('test-resource.unitedstates.communication.azure.com', $requestUrl);
    }

    public function test_send_throws_on_api_failure(): void
    {
        $client = new MockHttpClient(function () {
            return new MockResponse(
                json_encode([
                    'error' => [
                        'code' => 'InvalidRequest',
                        'message' => 'The request is invalid.',
                    ],
                ]),
                ['http_code' => 400],
            );
        });

        $transport = new AzureTransport('dGVzdC1rZXk=', 'https://test-resource.unitedstates.communication.azure.com', false, '2023-03-31', $client);

        $message = new Email;
        $message->subject('Fail');
        $message->text('Body');
        $message->sender('sender@example.com');
        $message->to('to@example.com');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('InvalidRequest');

        $transport->send($message);
    }
}
