<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\DataCollector;

use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\Address;
use Ernadoo\MondialRelay\Shipment\OutputType;
use Ernadoo\MondialRelay\Shipment\Parcel;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;
use Ernadoo\MondialRelayBundle\DataCollector\ProfilingMondialRelayClient;
use PHPUnit\Framework\TestCase;

final class ProfilingMondialRelayClientTest extends TestCase
{
    private function makeAddress(): Address
    {
        return new Address('FR', '75001', 'Paris', '1 Rue de la Paix', 'John', 'Doe');
    }

    private function makeShipmentRequest(): ShipmentRequest
    {
        return new ShipmentRequest(
            sender: $this->makeAddress(),
            recipient: $this->makeAddress(),
            parcels: [new Parcel(500)],
        );
    }

    public function testCreateShipmentRecordsProfile(): void
    {
        $response = new ShipmentResponse('12345678', 'https://label.url', OutputType::PDF_URL, 'https://tracking.url');

        $inner = $this->createMock(MondialRelayClientInterface::class);
        $inner->method('createShipment')->willReturn($response);

        $client = new ProfilingMondialRelayClient($inner);
        $client->createShipment($this->makeShipmentRequest());

        $profiles = $client->getProfiles();

        self::assertCount(1, $profiles);
        self::assertSame('createShipment', $profiles[0]['method']);
        self::assertSame('12345678', $profiles[0]['result']);
        self::assertNull($profiles[0]['error']);
        self::assertGreaterThan(0.0, $profiles[0]['duration']);
    }

    public function testSearchParcelShopsRecordsProfile(): void
    {
        $shops = [new ParcelShop('066974', 'Tabac', 'Addr', '', '59510', 'Hem', 'FR', 50.6, 3.2, 0.5)];

        $inner = $this->createMock(MondialRelayClientInterface::class);
        $inner->method('searchParcelShops')->willReturn($shops);

        $client = new ProfilingMondialRelayClient($inner);
        $client->searchParcelShops(new ParcelShopSearchRequest('FR', '59510'));

        $profiles = $client->getProfiles();

        self::assertCount(1, $profiles);
        self::assertSame('searchParcelShops', $profiles[0]['method']);
        self::assertStringContainsString('1 relay point', $profiles[0]['result']);
    }

    public function testErrorIsRecordedInProfileAndRethrown(): void
    {
        $inner = $this->createMock(MondialRelayClientInterface::class);
        $inner->method('createShipment')->willThrowException(ApiException::fromApiErrors(['ERR30' => 'Adresse invalide']));

        $client = new ProfilingMondialRelayClient($inner);

        $this->expectException(ApiException::class);

        try {
            $client->createShipment($this->makeShipmentRequest());
        } finally {
            $profiles = $client->getProfiles();
            self::assertCount(1, $profiles);
            self::assertNotNull($profiles[0]['error']);
            self::assertStringContainsString('Adresse invalide', $profiles[0]['error']);
        }
    }

    public function testTotalDurationSumsAllCalls(): void
    {
        $response = new ShipmentResponse('1', 'url', OutputType::PDF_URL, 'tracking');
        $shops    = [];

        $inner = $this->createMock(MondialRelayClientInterface::class);
        $inner->method('createShipment')->willReturn($response);
        $inner->method('searchParcelShops')->willReturn($shops);

        $client = new ProfilingMondialRelayClient($inner);
        $client->createShipment($this->makeShipmentRequest());
        $client->searchParcelShops(new ParcelShopSearchRequest('FR', '75001'));

        self::assertCount(2, $client->getProfiles());
        self::assertGreaterThan(0.0, $client->getTotalDurationMs());
    }
}
