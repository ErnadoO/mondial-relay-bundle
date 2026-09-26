<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\DataCollector;

use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decorator that records Mondial Relay API calls for the Symfony Profiler.
 *
 * Registered in debug mode only. When symfony/stopwatch is available, calls also appear
 * in the Profiler's Performance timeline (category "mondial_relay").
 * Reset between requests (kernel.reset), so long-running processes do not accumulate calls.
 */
final class ProfilingMondialRelayClient implements MondialRelayClientInterface, ResetInterface
{
    private const STOPWATCH_CATEGORY = 'mondial_relay';

    /** @var array<int, array{method: string, params: mixed, result: mixed, duration: float, error: ?string}> */
    private array $profiles = [];

    public function __construct(
        private readonly MondialRelayClientInterface $inner,
        private readonly ?Stopwatch $stopwatch = null,
        private readonly bool $sandbox = false,
    ) {
    }

    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        $event = $this->stopwatch?->start('mondial_relay.createShipment', self::STOPWATCH_CATEGORY);
        $start = microtime(true);
        $error = null;

        try {
            $result = $this->inner->createShipment($request);
        } catch (MondialRelayException $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $event?->stop();
            $this->profiles[] = [
                'method'   => 'createShipment',
                'params'   => [
                    'deliveryMode'   => $request->deliveryMode->value,
                    'collectionMode' => $request->collectionMode->value,
                    'parcels'        => count($request->parcels),
                    'totalWeightGr'  => $request->totalWeightGrams(),
                    'sandbox'        => $this->sandbox,
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
        $event = $this->stopwatch?->start('mondial_relay.searchParcelShops', self::STOPWATCH_CATEGORY);
        $start = microtime(true);
        $error = null;

        try {
            $result = $this->inner->searchParcelShops($request);
        } catch (MondialRelayException $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            $event?->stop();
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

    public function reset(): void
    {
        $this->profiles = [];
    }
}
