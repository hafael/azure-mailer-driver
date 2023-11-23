<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace Hafael\Azure\Transport;

use Hafael\Azure\Transport\AzureMailerApiTransport;
use Hafael\Azure\Transport\AzureMailerTransportFactory;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

class AzureMailerTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new AzureMailerTransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('azure+api', 'default'),
            true,
        ];

        yield [
            new Dsn('azure', 'default'),
            true,
        ];
    }

    public static function createProvider(): iterable
    {
        $logger = new NullLogger();

        yield [
            new Dsn('azure+api', 'default', self::USER),
            new AzureMailerApiTransport(self::USER, new MockHttpClient(), null, $logger),
        ];

        yield [
            new Dsn('azure+api', 'example.com', self::USER, '', 8080),
            (new AzureMailerApiTransport(self::USER, new MockHttpClient(), null, $logger))->setHost('example.com')->setPort(8080),
        ];

        yield [
            new Dsn('azure', 'default', self::USER),
            new AzureMailerApiTransport(self::USER, null, $logger),
        ];

    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('azure+foo', 'azure', self::USER),
            'The "azure+foo" scheme is not supported; supported schemes for mailer "azure" are: "azure", "azure+api".',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('azure+api', 'default')];
    }
}