<?php

namespace Hafael\Azure\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class AzureMailerTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $endpoint = $dsn->getHost();
        $key = $this->getPassword($dsn);
        $apiVersion = $this->getApiVersion($dsn);
        $engagementTracking = $this->getEngagementTracking($dsn);

        if ('azure+api' === $scheme || 'azure' === $scheme) {
            return new AzureMailerApiTransport($endpoint, $key, $apiVersion, $engagementTracking);
        }

        throw new UnsupportedSchemeException($dsn, 'azure', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['azure', 'azure+api'];
    }

    protected function getApiVersion(Dsn $dsn): string
    {
        return $dsn->getOption('api-version', '2023-03-31');
    }

    protected function getEngagementTracking(Dsn $dsn): bool
    {
        return boolval($dsn->getOption('tracking', false));
    }
}