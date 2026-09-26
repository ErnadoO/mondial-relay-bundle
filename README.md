# ernadoo/mondial-relay-bundle

Symfony bundle for the [ernadoo/mondial-relay](https://github.com/ernadoo/mondial-relay) PHP SDK.

- Autowiring of `MondialRelayClientInterface`: label creation and relay point search
- Symfony Profiler integration (call log, duration, errors)
- Relay point picker: a Stimulus controller (Symfony UX) and a Twig helper
  (optional, requires `symfony/stimulus-bundle`)

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
        login:       '%env(MR_LOGIN)%'        # V2 API login (label creation)
        password:    '%env(MR_PASSWORD)%'     # V2 API password
        customer_id: '%env(MR_CUSTOMER_ID)%'  # 8-character brand code
        secret_key:  '%env(MR_SECRET_KEY)%'   # SOAP secret key (relay point search)
    sandbox: false
```

Add the environment variables to your `.env.local`:

```dotenv
MR_LOGIN=your-v2-login
MR_PASSWORD=your-v2-password
MR_CUSTOMER_ID=BDTEST
MR_SECRET_KEY=your-soap-key
```

### Sandbox

`sandbox: true` sends label creation to `https://connect-api-sandbox.mondialrelay.com/api/shipment`,
so no real label is created. Relay point search always uses the production SOAP endpoint.
Enable it outside production, for example:

```yaml
when@dev:
    ernadoo_mondial_relay:
        sandbox: true
```

## Usage

Inject `MondialRelayClientInterface` anywhere in your application.

### Creating a label

```php
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;

final class ShippingService
{
    public function __construct(
        private readonly MondialRelayClientInterface $mondialRelay,
    ) {
    }

    public function createLabel(): string
    {
        $request = new ShipmentRequest(
            sender: new Address(
                countryCode: 'FR', postCode: '59510', city: 'Hem',
                streetName: '4 Av. Antoine Pinay', firstName: 'Erwan', lastName: 'Nader',
                mobileNo: '+33600000000',
            ),
            recipient: new Address(
                countryCode: 'FR', postCode: '75001', city: 'Paris',
                streetName: '1 Rue de la Paix', firstName: 'Jane', lastName: 'Doe',
                mobileNo: '+33600000001',
            ),
            parcels: [new Parcel(weightGrams: 500, content: 'Clothes')],
            deliveryMode: DeliveryMode::RELAY,
            deliveryLocation: 'FR-066974', // relay point ID, e.g. from the picker below
            // Empty deliveryLocation: Mondial Relay lets the recipient choose (notified by SMS/email)
        );

        try {
            $response = $this->mondialRelay->createShipment($request);
        } catch (ApiException $e) {
            // Mondial Relay rejected the request: the message lists its error codes
            throw $e;
        } catch (MondialRelayException $e) {
            // HTTP failure, malformed or incomplete response
            throw $e;
        }

        // $response->trackingUrl is the public tracking link
        return $response->labelOutput; // PDF label URL
    }
}
```

`ApiException` extends `MondialRelayException`: catch `MondialRelayException` alone to handle every failure.

### Searching relay points

```php
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;

$shops = $this->mondialRelay->searchParcelShops(
    new ParcelShopSearchRequest(countryCode: 'FR', postCode: '75001'),
);

foreach ($shops as $shop) {
    // $shop->id, $shop->name, $shop->locationCode() …
}
```

### Symfony Profiler

In debug mode, every call to `createShipment()` and `searchParcelShops()` appears in the Mondial Relay
panel of the Symfony Profiler: method, parameters, result, duration and error, if any. Label creation
also shows up in the HTTP Client panel.

With `symfony/stopwatch` installed, calls also appear in the Performance timeline (category
`mondial_relay`), next to your controllers and database queries.

Nothing is recorded outside debug mode.

## Relay point picker

The picker is optional: label creation and relay point search work without it. To use it, your
application needs:

- **`symfony/stimulus-bundle`**: it loads the Stimulus controller shipped with this bundle.
  Without it, `mondial_relay_widget()` renders an empty block.
- **AssetMapper or Webpack Encore** to serve the JavaScript. Projects created with
  `symfony new --webapp` already have AssetMapper and StimulusBundle.

```bash
composer require symfony/stimulus-bundle
```

With AssetMapper, the controller is registered automatically. With Webpack Encore, run
`npm install --force` then rebuild your assets.

```twig
<form method="post">
    {# Map + hidden input "relay_point_id" receiving e.g. "FR-066974" #}
    {{ mondial_relay_widget(postCode: '75001') }}
    <button>Ship here</button>
</form>
```

Options, highlighting a saved relay point, filling your own form fields and the `select` event are
described in the [relay point picker documentation](docs/relay-point-picker.md).

## Tests

```bash
composer install
vendor/bin/phpunit
```
