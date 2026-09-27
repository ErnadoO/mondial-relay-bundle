<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\Controller;

use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelayBundle\Controller\RelayPointSearchController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RelayPointSearchControllerTest extends TestCase
{
    public function testReturnsRelayPointsReadyForTheShipment(): void
    {
        $client = $this->createMock(MondialRelayClientInterface::class);
        $client->expects(self::once())->method('searchParcelShops')
            ->with(self::callback(static fn (ParcelShopSearchRequest $r) => 'FR' === $r->countryCode && '29950' === $r->postCode && '24R' === $r->deliveryMode->value))
            ->willReturn([$this->locker()]);

        $response = (new RelayPointSearchController($client, new ArrayAdapter(), 3600))($this->request(['postCode' => '29950']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'id' => 'FR-018332',
            'number' => '018332',
            'name' => 'LOCKER 24/7 NETTO FOUESNANT',
            'address' => "95 ZONE D'ACTIVITE DE PARK C, 29170 FOUESNANT",
            'postCode' => '29170',
            'city' => 'FOUESNANT',
            'country' => 'FR',
            'latitude' => 47.886905,
            'longitude' => -4.029426,
            'distanceKm' => 5.126,
            'openingHours' => [['day' => 1, 'slots' => ['00:01-23:59']], ['day' => 7, 'slots' => []]],
            'pictureUrl' => 'https://example.com/photo.jpg',
        ], json_decode((string) $response->getContent(), true)['relayPoints'][0]);
    }

    public function testIdenticalSearchesAreServedFromTheCache(): void
    {
        $client = $this->createMock(MondialRelayClientInterface::class);
        $client->expects(self::once())->method('searchParcelShops')->willReturn([$this->locker()]);
        $controller = new RelayPointSearchController($client, new ArrayAdapter(), 3600);

        $controller($this->request(['postCode' => '29950']));
        $response = $controller($this->request(['postCode' => '29950']));

        self::assertCount(1, json_decode((string) $response->getContent(), true)['relayPoints']);
    }

    public function testInvalidParametersAreRejected(): void
    {
        $client = $this->createMock(MondialRelayClientInterface::class);
        $client->expects(self::never())->method('searchParcelShops');
        $controller = new RelayPointSearchController($client, new ArrayAdapter(), 3600);

        foreach ([['postCode' => ''], ['postCode' => '<script>'], ['postCode' => '29950', 'country' => 'France'], ['postCode' => '29950', 'mode' => 'LCC']] as $query) {
            self::assertSame(400, $controller($this->request($query))->getStatusCode(), json_encode($query));
        }
    }

    public function testSearchesAreRateLimitedPerIpAddress(): void
    {
        $client = $this->createStub(MondialRelayClientInterface::class);
        $client->method('searchParcelShops')->willReturn([]);
        $limiter = new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage());
        $controller = new RelayPointSearchController($client, new ArrayAdapter(), 0, $limiter);

        self::assertSame(200, $controller($this->request(['postCode' => '29950']))->getStatusCode());
        self::assertSame(200, $controller($this->request(['postCode' => '29000']))->getStatusCode());
        self::assertSame(429, $controller($this->request(['postCode' => '29100']))->getStatusCode());
    }

    public function testMondialRelayErrorsAreNotExposed(): void
    {
        $client = $this->createStub(MondialRelayClientInterface::class);
        $client->method('searchParcelShops')->willThrowException(new ApiException('Mondial Relay API error: [97] Incorrect security key'));

        $response = (new RelayPointSearchController($client, new ArrayAdapter(), 3600))($this->request(['postCode' => '29950']));

        self::assertSame(502, $response->getStatusCode());
        self::assertStringNotContainsString('security key', (string) $response->getContent());
    }

    /** @param array<string, string> $query */
    private function request(array $query): Request
    {
        return Request::create('/relay-points', 'GET', $query, server: ['REMOTE_ADDR' => '192.0.2.1']);
    }

    private function locker(): ParcelShop
    {
        return new ParcelShop(
            id: '018332',
            name: 'LOCKER 24/7 NETTO FOUESNANT',
            address1: '95 ZONE D\'ACTIVITE DE PARK C',
            address2: '',
            postCode: '29170',
            city: 'FOUESNANT',
            countryCode: 'FR',
            latitude: 47.886905,
            longitude: -4.029426,
            distanceKm: 5.126,
            openingHours: ['Lundi' => '0001-2359 0000-0000', 'Dimanche' => '0000-0000 0000-0000'],
            pictureUrl: 'https://example.com/photo.jpg',
        );
    }
}
