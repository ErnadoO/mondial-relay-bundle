# Symfony Bundle to use the MondialRelay API

## Description
This package uses [QuentinBontemps/php-mondialrelay-api](https://github.com/QuentinBontemps/php-mondialrelay-api).

This client allow to use the [Mondial Relay Soap API](https://api.mondialrelay.com/Web_Services.asmx) with Symfony.

Installation
============

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
$ composer require ernadoo/mondial-relay-bundle
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require ernadoo/mondial-relay-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle::class => ['all' => true],
];
```

### Step 3: Configure the Bundle

```yaml
# config/packages/ernadoo_mondial_relay.yaml
ernadoo_mondial_relay:
    api:
        wsdl: https://api.mondialrelay.com/Web_Services.asmx?WSDL
        options:
            trace: '%kernel.debug%'
            #keep_alive : false
            #cache_wsdl : !php/const WSDL_CACHE_NONE

        credentials:
            customer_code:
            secret_key:
            brand_id:
```

## Usage

```php
# src\Controller\DefaultController.php
<?php

namespace App\Controller;

use MondialRelay\ApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DefaultController extends AbstractController
{
	public function findDeliveryPoints(ApiClient $mondialRelayClient)
	{
		$shops = $mondialRelayClient->findDeliveryPoints([
		    'Pays'            => 'FR',
		    'Ville'           => 'Paris',
		    'CP'              => '75000',
		    'DelaiEnvoi'      => "0",
		    'RayonRecherche'  => '20',
		    'NombreResultats' => '10',
		]);
	}
}
```

## Contribution
Contributions are always welcome.