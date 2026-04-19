# ernadoo/mondial-relay-bundle

Symfony bundle for the [ernadoo/mondial-relay](https://github.com/ernadoo/mondial-relay) PHP SDK.

- Autowiring of `MondialRelayClientInterface`
- Symfony Profiler integration (call log, duration)
- Twig helper for the relay point selection widget

## Requirements

- PHP 8.2+
- Symfony 6.4 or 7.x

## Installation

```bash
composer require ernadoo/mondial-relay-bundle
```

If Symfony Flex is enabled the bundle registers automatically. Otherwise add it to `config/bundles.php`:

```php
return [
    // ...
    Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/ernadoo_mondial_relay.yaml`:

```yaml
ernadoo_mondial_relay:
    credentials:
        login:       '%env(MR_LOGIN)%'
        password:    '%env(MR_PASSWORD)%'
        customer_id: '%env(MR_CUSTOMER_ID)%'
        secret_key:  '%env(MR_SECRET_KEY)%'
    sandbox: false   # set to true (or '%kernel.debug%') for the MR sandbox
```

Add the environment variables to your `.env`:

```dotenv
MR_LOGIN=your-login
MR_PASSWORD=your-password
MR_CUSTOMER_ID=BDTEST
MR_SECRET_KEY=your-secret-key
```

## Usage

Inject `MondialRelayClientInterface` anywhere in your Symfony application:

```php
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;

class LabelController extends AbstractController
{
    public function __construct(
        private readonly MondialRelayClientInterface $mondialRelay,
    ) {}

    public function print(): Response
    {
        $response = $this->mondialRelay->createShipment(new ShipmentRequest(
            sender:    new Address('FR', '59510', 'Hem', '4 Av. Pinay', 'Erwan', 'Nader'),
            recipient: new Address('FR', '75001', 'Paris', '1 Rue de la Paix', 'Jane', 'Doe'),
            parcels:   [new Parcel(500)],
        ));

        return $this->redirect($response->labelOutput); // download PDF
    }
}
```

## Twig widget

```twig
{# Renders the Mondial Relay relay-point selection widget #}
{{ mondial_relay_widget('FR', '75001') }}

{# Just the customer ID, for your own JS integration #}
{{ mondial_relay_customer_id() }}
```

## Documentation

- [Installation & configuration](docs/01-installation.md)
- [Usage in controllers & services](docs/02-usage.md)
- [Twig widget](docs/03-twig.md)

## Tests

```bash
composer install
vendor/bin/phpunit
```
