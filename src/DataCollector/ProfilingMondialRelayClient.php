<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\DataCollector;

use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;

/**
 * Decorator that records Mondial Relay API calls for the Symfony Profiler.
 */
final class ProfilingMondialRelayClient implements MondialRelayClientInterface
{
    /** @var array<int, array{method: string, params: mixed, result: mixed, duration: float, error: ?string}> */
    private array $profiles = [];

    public function __construct(
        private readonly MondialRelayClientInterface $inner,
    ) {
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $start = microtime(true);
        $error = null;

        try {
            $result = $this->inner->createShipment($request);
        } catch (ApiException $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $this->profiles[] = [
                'method'   => 'createShipment',
                'params'   => [
                    'deliveryMode'   => $request->deliveryMode->value,
                    'collectionMode' => $request->collectionMode->value,
                    'parcels'        => count($request->parcels),
                    'totalWeightGr'  => $request->totalWeightGrams(),
                    'sandbox'        => false,
                ],
                'result'   => isset($result) ? $result->shipmentNumber : null,
                'duration' => (microtime(true) - $start) * 1000,
                'error'    => $error,
            ];
        }

        return $result;
    }

    /**
     * @return ParcelShop[]
     */
    public function searchParcelShops(ParcelShopSearchRequest $request): array
    {
        $start = microtime(true);
        $error = null;

        try {
            $result = $this->inner->searchParcelShops($request);
        } catch (ApiException $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $this->profiles[] = [
                'method'   => 'searchParcelShops',
                'params'   => [
                    'countryCode' => $request->countryCode,
                    'postCode'    => $request->postCode,
                    'mode'        => $request->deliveryMode->value,
                ],
                'result'   => isset($result) ? sprintf('%d relay points found', count($result)) : null,
                'duration' => (microtime(true) - $start) * 1000,
                'error'    => $error,
            ];
        }

        return $result;
    }

    /** @return array<int, array{method: string, params: mixed, result: mixed, duration: float, error: ?string}> */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    public function getTotalDurationMs(): float
    {
        return array_sum(array_column($this->profiles, 'duration'));
    }
}
