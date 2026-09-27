<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Controller;

use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\Exception\MondialRelayException;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\DeliveryMode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Relay point search used by the "api" picker: GET ?postCode=29950&country=FR&mode=24R.
 *
 * The private key stays on the server. Results are cached, searches are rate-limited per IP
 * address (when symfony/rate-limiter is installed), and Mondial Relay errors are not exposed.
 */
final class RelayPointSearchController
{
    /** Day names returned by Mondial Relay, as ISO-8601 day numbers (1 = Monday). */
    private const DAYS = ['Lundi' => 1, 'Mardi' => 2, 'Mercredi' => 3, 'Jeudi' => 4, 'Vendredi' => 5, 'Samedi' => 6, 'Dimanche' => 7];

    public function __construct(
        private readonly MondialRelayClientInterface $client,
        private readonly CacheInterface $cache,
        private readonly int $cacheTtl,
        private readonly ?RateLimiterFactory $limiter = null,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $this->limiter && !$this->limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return new JsonResponse(['error' => 'too_many_requests'], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $country = strtoupper($request->query->getString('country', 'FR'));
        $postCode = trim($request->query->getString('postCode'));
        $mode = DeliveryMode::tryFrom($request->query->getString('mode', DeliveryMode::RELAY->value));

        if (1 !== preg_match('/^[A-Z]{2}$/', $country) || 1 !== preg_match('/^[A-Za-z0-9 -]{2,10}$/', $postCode) || null === $mode || !$mode->isRelay()) {
            return new JsonResponse(['error' => 'invalid_request'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $relayPoints = 0 === $this->cacheTtl
                ? $this->search($country, $postCode, $mode)
                : $this->cache->get(
                    'ernadoo_mondial_relay.relay_points.'.md5($country.'|'.$postCode.'|'.$mode->value),
                    function (ItemInterface $item) use ($country, $postCode, $mode): array {
                        $item->expiresAfter($this->cacheTtl);

                        return $this->search($country, $postCode, $mode);
                    },
                );
        } catch (MondialRelayException) {
            // Already logged by the client, with the Mondial Relay codes.
            return new JsonResponse(['error' => 'unavailable'], JsonResponse::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['relayPoints' => $relayPoints]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(string $country, string $postCode, DeliveryMode $mode): array
    {
        $shops = $this->client->searchParcelShops(new ParcelShopSearchRequest(countryCode: $country, postCode: $postCode, deliveryMode: $mode));

        return array_values(array_map(self::toArray(...), $shops));
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(ParcelShop $shop): array
    {
        $street = implode(', ', array_filter([$shop->address1, $shop->address2]));

        return [
            'id' => $shop->locationCode(),
            'number' => $shop->id,
            'name' => $shop->name,
            'address' => trim($street.', '.$shop->postCode.' '.$shop->city, ', '),
            'postCode' => $shop->postCode,
            'city' => $shop->city,
            'country' => $shop->countryCode,
            'latitude' => $shop->latitude,
            'longitude' => $shop->longitude,
            'distanceKm' => $shop->distanceKm,
            'openingHours' => self::openingHours($shop->openingHours),
            'pictureUrl' => $shop->pictureUrl,
        ];
    }

    /**
     * "0001-2359 0000-0000" → ["00:01-23:59"]; "0000-0000" means no slot.
     *
     * @param array<string, string> $hours
     *
     * @return list<array{day: int, slots: list<string>}>
     */
    private static function openingHours(array $hours): array
    {
        $days = [];
        foreach ($hours as $day => $slots) {
            $parsed = [];
            foreach (explode(' ', $slots) as $slot) {
                if (1 === preg_match('/^(\d{2})(\d{2})-(\d{2})(\d{2})$/', $slot, $m) && '0000-0000' !== $slot) {
                    $parsed[] = sprintf('%s:%s-%s:%s', $m[1], $m[2], $m[3], $m[4]);
                }
            }
            $days[] = ['day' => self::DAYS[$day] ?? 0, 'slots' => $parsed];
        }

        return $days;
    }
}
