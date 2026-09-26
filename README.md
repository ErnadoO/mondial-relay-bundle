# ernadoo/mondial-relay-bundle

Symfony bundle for the [ernadoo/mondial-relay](https://github.com/ernadoo/mondial-relay) PHP SDK.

- Autowiring of `MondialRelayClientInterface`
- Symfony Profiler integration (call log, duration)
- Relay point picker: a Stimulus controller (Symfony UX) and a Twig helper

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x (Symfony 8 requires PHP 8.4+)

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

## Relay point picker

Requires `symfony/stimulus-bundle` (AssetMapper or Webpack Encore).

```twig
<form method="post">
    {# Map + hidden input "relay_point_id" receiving e.g. "FR-066974" #}
    {{ mondial_relay_widget(postCode: '75001') }}
    <button>Ship here</button>
</form>
```

## Documentation

- [Installation & configuration](docs/01-installation.md)
- [Usage in controllers & services](docs/02-usage.md)
- [Relay point picker](docs/03-twig.md)

## Tests

```bash
composer install
vendor/bin/phpunit
```
