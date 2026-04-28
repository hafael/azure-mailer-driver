# Microsoft Azure Mailer for Laravel

✅ Simple implementation example of [Symfony Azure Mailer Bridge](https://github.com/symfony/azure-mailer) for Laravel Framework.

✅ Bootable scripts for Laravel

[![Latest Stable Version](http://poser.pugx.org/hafael/azure-mailer-driver/v)](https://packagist.org/packages/hafael/azure-mailer-driver)
[![Latest Unstable Version](http://poser.pugx.org/hafael/azure-mailer-driver/v/unstable)](https://packagist.org/packages/hafael/azure-mailer-driver)
[![Total Downloads](http://poser.pugx.org/hafael/azure-mailer-driver/downloads)](https://packagist.org/packages/hafael/azure-mailer-driver)
[![License](http://poser.pugx.org/hafael/azure-mailer-driver/license)](https://packagist.org/packages/hafael/azure-mailer-driver)

A use case of the [symfony/azure-mailer](https://github.com/symfony/azure-mailer) component using bootable scripts in the Laravel framework to send email messages.


## 💡 Requirements

- PHP 8.2 or higher
- Laravel ([actively maintained versions](https://laravel.com/docs/releases#support-policy))


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

First time using Azure Communication Services (ACS)? Create your [Azure account](https://azure.com) if you don't have one already.

On your project directory run on the command line
```shell
composer require hafael/azure-mailer-driver symfony/http-client
```


## 🌟 Set mail config
  
Add credentials to `config/services.php`:

```php
'acs' => [
    'endpoint' => env('AZURE_COMMUNICATION_ENDPOINT'),
    'key'      => env('AZURE_COMMUNICATION_KEY'),
],
```

Add entry to `config/mail.php`:
  
```php
'mailers' => [
    //...other drivers

    'acs' => [
        'transport'        => 'acs',
        // 'api_version'      => '2023-03-31', // optional
        // 'disable_tracking' => false,         // optional
    ],
]
```

Add entry to `.env`:
  
```text
#...other entries

# Mail service entries... 
MAIL_MAILER=acs

# Azure Communication Services entries
AZURE_COMMUNICATION_ENDPOINT=https://my-resource.communication.azure.com
AZURE_COMMUNICATION_KEY=Base64AzureAccessKey
```

and just send your notification mail messages!


## 🔄 Upgrading from previous versions

Previous versions used `transport: azure` with different config keys. Both the old transport name and config keys are still supported for backwards compatibility.

| Old key | New key | Notes |
|---|---|---|
| `access_key` | `key` | renamed |
| `resource_name` | `endpoint` | expanded to full URL: `https://{resource_name}.communication.azure.com` |
| `disable_user_tracking` | `disable_tracking` | renamed |


## 📚 Documentation 

Build powerful, cloud-based communication and customer engagement experiences by adding voice, video, chat, sms, email, teams interoperability, call automation, and telephony to your apps.

Visit our Dev Site for further information regarding:
 - Azure Communication Service Docs: [English](https://learn.microsoft.com/en-us/azure/communication-services/)

 
## 💡 Last change

  * Modernized to use Laravel's native transport extension hook (`Mail::extend`)
  * New transport name `acs` with credentials via `config/services.php`
  * Full backwards compatibility with legacy `azure` transport and old config keys

## 📜 License 

MIT license. Copyright (c) 2023 - [Rafael](https://github.com/hafael)
For more information, see the [LICENSE](https://github.com/hafael/azure-mailer-driver/blob/main/LICENSE) file.
