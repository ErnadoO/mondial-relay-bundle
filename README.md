# ernadoo/mondial-relay-bundle

Symfony bundle for the [ernadoo/mondial-relay](https://github.com/ernadoo/mondial-relay) PHP SDK.

- Autowiring of `MondialRelayClientInterface`: label creation and relay point search
- Symfony Profiler integration (call log, duration, errors)
- Relay point picker (Symfony UX): the official Mondial Relay widget, or a list + Leaflet map fed by the API
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
        brand_code:   '%env(MONDIAL_RELAY_BRAND_CODE)%'
        api_login:    '%env(MONDIAL_RELAY_API_LOGIN)%'
        api_password: '%env(MONDIAL_RELAY_API_PASSWORD)%'
        private_key:  '%env(MONDIAL_RELAY_PRIVATE_KEY)%'
    sandbox: false
```

The credentials are named after MR Connect, Mondial Relay's customer area. Each one is only needed
by the features that use it:

| Environment variable | In MR Connect | Needed for |
|---|---|---|
| `MONDIAL_RELAY_BRAND_CODE` | Code enseigne (brand code, 8 characters) | Everything (always required) |
| `MONDIAL_RELAY_API_LOGIN` | Login of an API user: Administration → User management → API configuration | Creating labels |
| `MONDIAL_RELAY_API_PASSWORD` | Password of that API user | Creating labels |
| `MONDIAL_RELAY_PRIVATE_KEY` | Clé privée (private key) of the brand | Searching relay points through the API, and the `api` picker |

A missing credential only fails the feature that needs it, with a clear message and a log entry.
Add them to your `.env.local` (never commit them):

```dotenv
MONDIAL_RELAY_BRAND_CODE=CC12345
MONDIAL_RELAY_API_LOGIN=CC12345@business-api.mondialrelay.com
MONDIAL_RELAY_API_PASSWORD=your-api-password
MONDIAL_RELAY_PRIVATE_KEY=your-private-key
```

> **Upgrading from 3.2 or older:** the former options `customer_id`, `login`, `password` and
> `secret_key` still work but are deprecated: rename them `brand_code`, `api_login`, `api_password`
> and `private_key`. The Twig function `mondial_relay_customer_id()` becomes
> `mondial_relay_brand_code()`.

### Sandbox

`sandbox: true` sends label creation to `https://connect-api-sandbox.mondialrelay.com/api/shipment`:
labels are generated ("SANDBOX MODE") but nothing is recorded. It needs a valid API user. Relay point
search always uses the production endpoint. Enable it outside production, for example:

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

        $response = $this->mondialRelay->createShipment($request);

        // $response->trackingUrl is the public tracking link
        return $response->labelOutput; // PDF label URL
    }
}
```

### Handling errors

Every failure throws a `MondialRelayException`: `ApiException` when Mondial Relay rejects the
request (with its codes, `getErrors()`), `TransportException` when it cannot be reached or answers
with an unusable response, `ConfigurationException` when a credential is missing. Their messages
are in English, for developers and logs.

To tell your users what went wrong, `MondialRelayErrorMessage` turns the exception into a
translatable message (`TranslatableInterface`), in the `ErnadooMondialRelayBundle` domain:

```php
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelayBundle\Translation\MondialRelayErrorMessage;

try {
    $label = $shipping->createLabel();
} catch (MondialRelayException $e) {
    $this->logger->error('Label not created: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
    $this->addFlash('danger', MondialRelayErrorMessage::fromException($e)); // {{ message|trans }} in Twig
    // or, as a string: MondialRelayErrorMessage::fromException($e)->trans($translator)
}
```

| Message key | When |
|---|---|
| `error.phone_number` | Invalid phone number (international format expected) |
| `error.relay_point` | The relay point cannot receive the parcel (unknown, or unavailable for the delivery mode) |
| `error.parcel_weight` | Parcel weight out of range for the delivery mode |
| `error.post_code`, `error.country` | Postal code, city or country not recognised (relay point search) |
| `error.configuration` | Missing or invalid credentials |
| `error.unavailable` | Mondial Relay unreachable or unusable response: try again later |
| `error.rejected` | Any other rejection |

English and French are provided. The list of Mondial Relay codes is partial (Mondial Relay does not
publish it): unknown codes fall back to `error.rejected`. Override or add languages as usual, with a
`translations/ErnadooMondialRelayBundle.<locale>.xlf` file in your application.

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

Nothing is recorded by the Profiler outside debug mode.

### Logs

In every environment, the client logs on the `mondial_relay` channel (Monolog, or any PSR-3 logger
registered as `logger`):

| Level | Logged |
|---|---|
| `info` | Shipment created (number, delivery mode, relay point), relay point search (result count) |
| `warning` | Non-blocking warnings returned by Mondial Relay (code and message) |
| `error` | Rejections with the Mondial Relay codes and messages, HTTP failures |

Credentials, request and response bodies, and addresses are never logged. To send these logs to a
dedicated file with Monolog:

```yaml
monolog:
    handlers:
        mondial_relay:
            type: stream
            path: '%kernel.logs_dir%/mondial_relay.log'
            channels: ['mondial_relay']
```

## Relay point picker

The picker is optional: label creation and relay point search work without it. Two modes:

| | `widget` (default) | `api` |
|---|---|---|
| What it is | The official Mondial Relay widget | A list and a Leaflet map, rendered by the bundle |
| Credentials | Brand code only | Brand code and private key (the key stays on the server) |
| Loaded from Mondial Relay | jQuery (if missing), Leaflet and their widget script | Nothing: Leaflet and OpenStreetMap tiles only |
| Look and feel | Mondial Relay's | Yours (CSS custom properties) |
| Needs | — | The bundle routes; optionally `symfony/rate-limiter` and `symfony/translation` |

Both modes need:

- **`symfony/stimulus-bundle`**: it loads the Stimulus controllers shipped with this bundle.
  Without it, `mondial_relay_widget()` renders an empty block.
- **AssetMapper or Webpack Encore** to serve the JavaScript. Projects created with
  `symfony new --webapp` already have AssetMapper and StimulusBundle.

```bash
composer require symfony/stimulus-bundle
```

With AssetMapper, the controllers are registered automatically. With Webpack Encore, run
`npm install --force` then rebuild your assets.

```twig
<form method="post">
    {# Picker + hidden input "relay_point_id" receiving e.g. "FR-066974" #}
    {{ mondial_relay_widget(postCode: '75001') }}
    <button>Ship here</button>
</form>
```

To use the `api` mode, import the routes of its search endpoint and choose it:

```yaml
# config/routes/ernadoo_mondial_relay.yaml
ernadoo_mondial_relay:
    resource: '@ErnadooMondialRelayBundle/config/routes.php'
    prefix: /mondial-relay

# config/packages/ernadoo_mondial_relay.yaml
ernadoo_mondial_relay:
    # ...
    relay_point_picker:
        mode: api          # or pass picker: 'api' to mondial_relay_widget()
        cache_ttl: 3600    # seconds a search result is cached
        rate_limit: 30     # searches per minute and per IP (with symfony/rate-limiter)
```

Options, highlighting a saved relay point, filling your own form fields, theming and the `select`
event are described in the [relay point picker documentation](docs/relay-point-picker.md).


## Tests

```bash
composer install
vendor/bin/phpunit
```
