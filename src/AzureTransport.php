<?php

namespace Hafael\Mailer\Azure;

use SensitiveParameter;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AzureTransport extends AbstractTransport
{
    protected AzureApiTransport $acs;

    public function __construct(
        #[SensitiveParameter] protected string $key,
        protected string $endpoint,
        protected bool $disableTracking = false,
        protected string $apiVersion = '2023-03-31',
        ?HttpClientInterface $client = null,
    ) {
        parent::__construct();

        $this->acs = (new AzureApiTransport(
            $this->key,
            'default',
            $this->disableTracking,
            $this->apiVersion,
            $client,
        ))->setHost($this->parseHost($this->endpoint));
    }

    protected function doSend(SentMessage $message): void
    {
        $sent = $this->acs->send(
            $message->getOriginalMessage(),
            $message->getEnvelope(),
        );

        if ($sent !== null) {
            $message->setMessageId($sent->getMessageId());
        }
    }

    protected function parseHost(string $endpoint): string
    {
        $endpoint = rtrim($endpoint, '/');

        return parse_url($endpoint, PHP_URL_HOST) ?? $endpoint;
    }

    public function __toString(): string
    {
        return 'acs';
    }
}
