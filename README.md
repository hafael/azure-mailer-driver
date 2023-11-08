# Microsoft Azure (ACS) Bridge

Provides Azure Communication Service integration for Symfony Mailer.

✅ Including Laravel Service Provider boot script! (L9+)

[![Latest Stable Version](http://poser.pugx.org/hafael/azure-mailer-driver/v)](https://packagist.org/packages/hafael/azure-mailer-driver)
[![Latest Unstable Version](http://poser.pugx.org/hafael/azure-mailer-driver/v/unstable)](https://packagist.org/packages/hafael/azure-mailer-driver)
[![Total Downloads](http://poser.pugx.org/hafael/azure-mailer-driver/downloads)](https://packagist.org/packages/hafael/azure-mailer-driver)
[![License](http://poser.pugx.org/hafael/azure-mailer-driver/license)](https://packagist.org/packages/hafael/azure-mailer-driver)

This library provides developers with a simple set of features for sending Laravel Framework emails through the Microsoft Azure ECS service.


## 💡 Requirements

- PHP 8.1 or higher
- [symfony/http-client](https://github.com/symfony/http-client)


## 🧩 Available resources

| Resource             | Status   |
| -------------------- | :------: |
| Plain Text           | ✅  |
| HTML                 | ✅  |
| Attachments          | ✅  |
| Multiple recipients  | ✅  |
| Auth HMAC-SHA256     | ✅  |
| Notifications        | ✅  |
| Mkt Campaigns        | ✅  |
| Markdown             | ✅  |

## 📦 Installation 

First time using Azure ECS? Create your [Azure account](https://azure.com), if you don’t have one already.

1. Download [Composer](https://getcomposer.org/doc/00-intro.md) if not already installed

2. On your project directory run on the command line
`composer require hafael/azure-mailer-driver symfony/http-client`

3. Get your Azure CS Access Key and Service Endpoint.


## 🌟 Set mail config
  
Add entry to [root-of-laravel]/config/mail.php:
  
```php
  <?php
    
    ...

    'mailers' => [
        //...other drivers

        'azure' => [
            'transport'             => 'azure',
            'endpoint'              => env('AZURE_MAIL_ENDPOINT', 'https://{communicatonServiceName}.communication.azure.com'),
            'access_key'            => env('AZURE_MAIL_KEY'),
            'api_version'           => env('AZURE_MAIL_API_VERSION', '2023-03-31'),
            'disable_user_tracking' => env('AZURE_MAIL_DISABLE_TRACKING', false),
            'http'                  => []
        ],
    ]

  ?>
```

Add entry to [root-of-laravel]/.env:
  
```text 
  
  #...other entries

  # Mail service entries... 
  MAIL_MAILER=azure
  
  # Azure Service entries
  AZURE_MAIL_ENDPOINT="https://{communicatonServiceName}.communication.azure.com"
  AZURE_MAIL_KEY="{base64accessToken}"
  # AZURE_MAIL_API_VERSION=2023-03-31 #optional
  # AZURE_MAIL_DISABLE_TRACKING=false #optional
  
```


and just sent your notification mail messages!


## 📚 Documentation 

Build powerful, cloud-based communication and customer engagement experiences by adding voice, video, chat, sms, email, teams interoperability, call automation, and telephony to your apps.

Visit our Dev Site for further information regarding:
 - Azure Communication Service Docs: [English](https://learn.microsoft.com/en-us/azure/communication-services/)


## 📜 License 

MIT license. Copyright (c) 2023 - [Rafael](https://github.com/hafael)
For more information, see the [LICENSE](https://github.com/hafael/azure-mailer-driver/blob/main/LICENSE) file.