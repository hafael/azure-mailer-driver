<?php

namespace Hafael\Azure\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AzureMailerApiTransport extends AbstractApiTransport
{
    private const HOST = 'my-acs-resource.communication.azure.com';

    /**
     * Guzzle client instance.
     *
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * User Access Key from Azure Communication Service (Primary or Secondary key).
     *
     * @var string
     */
    private string $key;

    /**
     * The endpoint API URL to which to POST emails to Azure 
     * https://{communicationServiceName}.communication.azure.com/.
     *
     * @var string
     */
    private string $endpoint;

    /**
     * The version of API to invoke.
     *
     * @var string
     */
    private string $apiVersion = '2023-03-31';

    /**
     * Indicates whether user engagement tracking should be disabled.
     *
     * @var bool
     */
    protected $disableUserEngagementTracking = false;

    /**
     * Create a new Azure Mailer Transport instance.
     *
     * @param  string  $endpoint
     * @param  string  $key
     * @param  string|null  $apiVersion
     * @param  bool  $engagementTracking
     * @param  HttpClientInterface  $client
     * @return void
     */
    public function __construct(string $endpoint, string $key, string $apiVersion = null, bool $engagementTracking = true, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
        $this->key = $key;
        $this->apiVersion = empty($apiVersion) ? self::$apiVersion : $apiVersion;
        $this->disableUserEngagementTracking = !$engagementTracking;
        parent::__construct($client, $dispatcher, $logger);
    }

    /**
     * 
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('azure+api://%s', $this->getAzureCSEndpoint());
    }

    /**
     * Queues an email message to be sent to one or more recipients.
     * 
     * @return ResponseInterface
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
     
        $endpointURL = $this->getEndpointRequestURL();        
        $messagePayload = $this->getMessagePayload($email, $envelope);
        $headers = $this->getSignedHeaders($messagePayload, $email);
        
        $requestOptions = [
            'body' => json_encode($messagePayload),
            'headers' => $headers,
        ];

        $response = $this->client->request('POST', $endpointURL, $requestOptions);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Azure server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            try {
                $result = $response->toArray(false);
                throw new HttpTransportException('Unable to send an email (' . $result['error']['code'] . '): ' . $result['error']['message'], $response, $statusCode);
            } catch (DecodingExceptionInterface $e) {
                throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response, 0, $e);
            }
        }

        $sentMessage->setMessageId(json_decode($response->getContent(false), true)['id']);
       
        return $response;
    }

    /**
     * Get the message request body
     * 
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    private function getMessagePayload(Email $email, Envelope $envelope): array
    {
        $addressStringifier = function (Address $address) {
            $stringified = ['address' => $address->getAddress()];

            if ($address->getName()) {
                $stringified['displayName'] = $address->getName();
            }

            return $stringified;
        };

        $data = [
            'content' => [
                'html' => $email->getHtmlBody(),
                'plainText' => $email->getTextBody(),
                'subject' => $email->getSubject(),
            ],
            'recipients' => [
                'to' => array_map($addressStringifier, $this->getRecipients($email, $envelope)),
            ],
            'senderAddress' => $envelope->getSender()->getAddress(),
            'attachments' => $this->getMessageAttachments($email),
            'userEngagementTrackingDisabled' => $this->getDisableUserEngagementTracking(),
            'headers' => empty($headers = $this->getMessageCustomHeaders($email)) ? null : $headers,
            'importance' => $this->getPriorityLevel($email->getPriority()),
        ];

        if ($emails = array_map($addressStringifier, $email->getCc())) {
            $data['recipients']['cc'] = $emails;
        }

        if ($emails = array_map($addressStringifier, $email->getBcc())) {
            $data['recipients']['bcc'] = $emails;
        }

        if ($emails = array_map($addressStringifier, $email->getReplyTo())) {
            $data['replyTo'] = $emails;
        }

        return $data;
    }

    /**
     * List of attachments. Please note that we limit the total size of 
     * an email request (which includes attachments) to 10MB. 
     * 
     * @param Email $email
     * @return array
     */
    private function getMessageAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $att = [
                'name'            => $filename,
                'contentInBase64' => base64_encode(str_replace("\r\n", '', $attachment->bodyToString())),
                'contentType'     => $headers->get('Content-Type')->getBody(),
            ];

            if ('inline' === $disposition) {
                $att['content_id'] = $filename;
            }

            $attachments[] = $att;
        }

        return $attachments;
    }

    /**
     * Version of ACS API to invoke.
     * 
     * @return string
     */
    private function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    /**
     * The communication request path with query string, for example /emails:send?api-version=2023-03-31
     * 
     * @return string
     */
    private function getURIPathAndQuery(): string
    {
        return '/emails:send?api-version=' . $this->getApiVersion();
    }
    
    /**
     * The communication resource, for example https://my-acs-resource.communication.azure.com
     * 
     * @return string
     */
    private function getEndpointRequestURL(): string
    {
        return $this->getAzureCSEndpoint() . $this->getURIPathAndQuery();
    }
    
    /**
     * The communication domain host, for example my-acs-resource.communication.azure.com
     * 
     * @return string
     */
    private function getAzureCSEndpoint(): string
    {
        return ($this->endpoint ?: self::HOST);
    }

    /**
     * Get name and email from contact list
     * 
     * @param array $contacs
     * @return array
     */
    private function getContactNameAndEmail($contacts): array
    {
        $formatted = [];
        if (empty($contacts)) {
            return [];
        }
        foreach ($contacts as $address => $display) {
            $formatted[] =  array_filter([
                'displayName' => $display,
                'email'       => $address,
            ]);
        }
        return $formatted;
    }

    /**
     * Generate hash from message content
     * 
     * @param string $content
     * @return string
     */
    private function getContentHash(string $content): string
    {
        //$data = mb_convert_encoding($content, 'UTF-8');
        $hash = hash('sha256', $content, true);
        //return base64 hash
        return base64_encode($hash);
    }

    /**
     * Generate sha256 hash and encode to base64 to produces the digest string.
     * 
     * @return string
     */
    private function getAuthenticationSignature(string $content): string
    {
        //dd($content);
        $key = base64_decode($this->key);
        $hashedBytes = hash_hmac('sha256', mb_convert_encoding($content, 'UTF-8'), $key, true);
        return base64_encode($hashedBytes);
    }
   
    /**
     * Get authenticated headers for signed request,
     * 
     * @return array
     */
    private function getSignedHeaders(array $payload, Email $message): array
    {
        //HTTP Method verb (uppercase)
        $verb = "POST";

        //Request time
        $datetime = new \DateTime("now", new \DateTimeZone("UTC"));
        $utcNow = $datetime->format('D, d M Y H:i:s \G\M\T');
        
        //Content hash signature
        $contentHash = $this->getContentHash(json_encode($payload));
        
        //ACS Endpoint
        $host = str_replace('https://', '', $this->endpoint);
        
        //Sendmail endpoint from communication email delivery service
        $urlPathAndQuery = '/emails:send?api-version=' . $this->getApiVersion();
        
        //Signed request headers
        $stringToSign = "{$verb}\n{$urlPathAndQuery}\n{$utcNow};{$host};{$contentHash}";
        
        //Authenticate headers with ACS primary or secondary key
        $signature = $this->getAuthenticationSignature($stringToSign);
        
        //get GUID part of message id to identify the long running operation
        $messageId = explode('@', $message->generateMessageId(), 2)[0];

        $headers = [
            'Content-Type' => 'application/json',
            'repeatability-request-id' => $messageId,
            'operation-id' => $messageId,
            'repeatability-first-sent' => $utcNow,
            'x-ms-date' => $utcNow,
            'x-ms-content-sha256' => $contentHash,
            'x-ms-client-request-id' => $messageId,
            "Authorization" => "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature={$signature}",
        ];

        //return signed headers to http client request
        return $headers;
    }
    
    /**
     * Custom email headers to be passed.
     * 
     * @return array
     */
    private function getMessageCustomHeaders(Email $email): array
    {

        $headers = [];

        $headersToBypass = ['x-ms-client-request-id', 'operation-id', 'authorization', 'x-ms-content-sha256', 'received', 'dkim-signature', 'content-transfer-encoding', 'from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'reply-to'];
        
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }
            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }
    
    /**
     * Get the message priority level
     * 
     * @return string
     */
    private function getPriorityLevel($priority): ?string
    {
        return strtolower([
            Email::PRIORITY_HIGHEST => 'Highest',
            Email::PRIORITY_HIGH    => 'High',
            Email::PRIORITY_NORMAL  => 'Normal',
            Email::PRIORITY_LOW     => 'Low',
            Email::PRIORITY_LOWEST  => 'Lowest',
        ][$priority]);
    }

    /**
     * Indicates whether user engagement tracking should be disabled.
     * 
     * @return bool
     */
    private function getDisableUserEngagementTracking(): bool
    {
        return $this->disableUserEngagementTracking;
    }
}