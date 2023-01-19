<?php

namespace Hafael\Azure\SwiftMailer;

use DateTime;
use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;
use Swift_Mime_SimpleMessage;
use Swift_Attachment;
use Illuminate\Support\Str;

class AzureMailerTransport extends Transport
{
    /**
     * Guzzle client instance.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * User Access Key from Azure Communication Service (Primary or Secondary key).
     *
     * @var string
     */
    protected $key;

    /**
     * The endpoint API URL to which to POST emails to Azure 
     * https://{communicationServiceName}.communication.azure.com/.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Indicates whether user engagement tracking should be disabled.
     *
     * @var bool
     */
    protected $disableUserEngagementTracking = false;

    /**
     * The version of API to invoke.
     *
     * @var string
     */
    protected $apiVersion = '2021-10-01-preview';

    /**
     * Create a new Azure Mailer Transport instance.
     *
     * @param  \GuzzleHttp\ClientInterface  $client
     * @param  string  $endpoint
     * @param  string  $key
     * @param  string|null  $apiVersion
     * @param  bool  $engagementTracking
     * @return void
     */
    public function __construct(ClientInterface $client, $endpoint, $key, $apiVersion, $engagementTracking = true)
    {
        $this->key = $key;
        $this->client = $client;
        $this->endpoint = $endpoint;
        $this->disableUserEngagementTracking = !$engagementTracking;
        $this->apiVersion = empty($apiVersion) ? self::$apiVersion : $apiVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);
        $payload = $this->getPayload($message);
        $response = $this->client->request('POST', $this->getRequestURL(), $payload);
        $response->getBody();
        $this->sendPerformed($message);
        return $this->numberOfRecipients($message);
    }

    /**
     * Get the HTTP payload for sending the message.
     *
     * @param  \Swift_Mime_SimpleMessage  $message
     * @return array
     */
    protected function getPayload(Swift_Mime_SimpleMessage $message)
    {
        $html = html_entity_decode($message->getBody(), ENT_HTML5, $message->getCharset());
        
        $json = [
            'content' => [
                'html'      => $message->getContentType() === 'multipart/alternative' ? $html : null,
                'plainText' => $message->getContentType() === 'text/plain' ? strip_tags($html) : null,
                'subject'   => $message->getSubject(),
            ],
            'recipients' => [
                'CC'  => $this->mapContactsToNameEmail($message->getCc()),
                'bCC' => $this->mapContactsToNameEmail($message->getBcc()),
                'to'  => $this->mapContactsToNameEmail($message->getTo()),
            ],
            'sender'                        => array_key_first($message->getFrom()),
            'attachments'                   => $this->mapAttachments($message),
            'disableUserEngagementTracking' => $this->getDisableUserEngagementTracking(),
            'headers'                       => $this->mapMailHeaders($message),
            'importance'                    => $this->mapPriority($message->getPriority()),
            'replyTo'                       => $this->mapContactsToNameEmail($message->getReplyTo()),
        ];

        return [
            'headers' => $this->getHeaders($json, $message),
            'json' => $json,
            // 'stream' => true,
            // 'debug' => true,
            // 'http_errors' => false,
            // 'verify' => false,
        ];
    }

    /**
     * 
     * @return string
     */
    protected function setDisableUserEngagementTracking(bool $flag)
    {
        $this->disableUserEngagementTracking = $flag;
        return $this;
    }

    /**
     * 
     * @return string
     */
    protected function getApiVersion()
    {
        return $this->apiVersion;
    }
    
    /**
     * 
     * @return string
     */
    protected function getPath()
    {
        return '/emails:send?api-version=' . $this->apiVersion;
    }
    
    /**
     * 
     * @return string
     */
    protected function getRequestURL()
    {
        return $this->endpoint . $this->getPath();
    }

    /**
     * 
     * @return bool
     */
    protected function getDisableUserEngagementTracking()
    {
        return $this->disableUserEngagementTracking;
    }

    /**
     * 
     * @return array
     */
    protected function getHeaders($request, Swift_Mime_SimpleMessage $message) 
    {
        //HMAC-SHA256 Authorization Protocol

        // Set the Date header to our Date as a UTC String.
        $dateStr = (new DateTime('UTC'))->format('D, d M Y H:i:s \G\M\T');
        $headers['Date'] = $dateStr;

        // Hash the request body using SHA256 and encode it as Base64
        $hashedBodyStr = base64_encode(hash('sha256', json_encode($request), true));

        // And add that to the header x-ms-content-sha256
        $headers['x-ms-content-sha256'] = $hashedBodyStr;

        // Remove the https, prefix to create a suitable "Host" value
        $hostStr = str_replace('https://', '', $this->endpoint);

        // This gets the part of our URL that is after the endpoint, for example in https://contoso.communication.azure.com/sms, it will get '/sms'
        $url = $this->getPath();

        // Construct our string which we'll sign, using various previously created values.
        $stringToSign = "POST\n" . $url . "\n" . $dateStr . ";" . $hostStr . ';' . $hashedBodyStr;

        // Decode our access key from previously created variables, into bytes from base64.
        $key = base64_decode($this->key);

        // Sign our previously calculated string with HMAC 256 and our key. Convert it to Base64.
        $digest = hash_hmac('sha256', $stringToSign, $key, true);
        $signature = base64_encode($digest);

        // Add our final signature in Base64 to the authorization header of the request.
        $headers['Authorization'] = "HMAC-SHA256 SignedHeaders=date;host;x-ms-content-sha256&Signature=" . $signature;

        $messageId = str_replace('@swift.generated', '', $message->getId());
        
        return array_merge($headers, [
            'Content-Type'             => 'application/json',
            'x-ms-date'                => $headers['Date'],
            'repeatability-request-id' => !empty($messageId) && Str::isUuid($messageId) ? $messageId : (string) Str::uuid(),
            'repeatability-first-sent' => $headers['Date'],
            'Host'                     => $hostStr,
        ]);
    }

    protected function mapPriority($priority)
    {
        return strtolower([
            Swift_Mime_SimpleMessage::PRIORITY_HIGHEST => 'Highest',
            Swift_Mime_SimpleMessage::PRIORITY_HIGH    => 'High',
            Swift_Mime_SimpleMessage::PRIORITY_NORMAL  => 'Normal',
            Swift_Mime_SimpleMessage::PRIORITY_LOW     => 'Low',
            Swift_Mime_SimpleMessage::PRIORITY_LOWEST  => 'Lowest',
        ][$priority]);
    }

    protected function mapAttachments(Swift_Mime_SimpleMessage $message)
    {

        return array_map(function(Swift_Attachment $at) {
            
            $extension = strtolower(substr($at->getFilename(), strrpos($at->getFilename(), '.') + 1));
            return [
                'name'               => $at->getFilename(),
                'attachmentType'     => $extension,
                'contentBytesBase64' => base64_encode($at->getBody()),
            ];
        }, array_filter($message->getChildren(), function($file) {
            return $file instanceof Swift_Attachment;
        }));

    }

    protected function mapMailHeaders(Swift_Mime_SimpleMessage $message)
    {
        return array_map(function($header) {
            return [
                'name'  => $header->getFieldName(),
                'value' => $header->getFieldBody(),
            ];
        }, $message->getHeaders()->getAll());
    }
    
    protected function mapContactsToNameEmail($contacts)
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

}