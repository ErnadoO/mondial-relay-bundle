# Usage in Controllers & Services

## Autowiring

The bundle exposes `MondialRelayClientInterface`. Inject it anywhere:

```php
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;

class ShippingService
{
    public function __construct(
        private readonly MondialRelayClientInterface $mondialRelay,
    ) {}
}
```

## Creating a label

```php
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;

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
    parcels:      [new Parcel(weightGrams: 500, content: 'Vêtements')],
    deliveryMode: DeliveryMode::RELAY,
    // deliveryLocation: '' → "Notif Destinataire" (MR notifies recipient by SMS)
);

try {
    $response = $this->mondialRelay->createShipment($request);
    $pdfUrl   = $response->labelOutput;      // download the PDF label
    $tracking = $response->trackingUrl;      // public tracking link
} catch (ApiException $e) {
    // Handle MR API error codes
}
```

## Searching relay points

```php
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;

$shops = $this->mondialRelay->searchParcelShops(
    new ParcelShopSearchRequest(countryCode: 'FR', postCode: '75001')
);

foreach ($shops as $shop) {
    // $shop->id, $shop->name, $shop->locationCode() …
}
```

## Symfony Profiler

Every call to `createShipment()` and `searchParcelShops()` is recorded in the
Symfony Profiler toolbar (Mondial Relay panel). You can see the method called,
parameters, result, duration, and any error.
