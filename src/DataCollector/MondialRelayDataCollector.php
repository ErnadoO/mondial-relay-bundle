<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class MondialRelayDataCollector extends DataCollector
{
    public function __construct(
        private readonly ProfilingMondialRelayClient $client,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $profiles = $this->client->getProfiles();

        $this->data = [
            'calls'         => [],
            'total_duration' => $this->client->getTotalDurationMs(),
            'call_count'    => count($profiles),
        ];

        foreach ($profiles as $profile) {
            $this->data['calls'][] = [
                'method'   => $this->cloneVar($profile['method']),
                'params'   => $this->cloneVar($profile['params']),
                'result'   => $this->cloneVar($profile['result']),
                'duration' => $profile['duration'],
                'error'    => $profile['error'],
            ];
        }
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'ernadoo.mondialrelay';
    }

    /** @return array<int, mixed> */
    public function getCalls(): array
    {
        return $this->data['calls'] ?? [];
    }

    public function getCallCount(): int
    {
        return $this->data['call_count'] ?? 0;
    }

    public function getTotalDuration(): float
    {
        return $this->data['total_duration'] ?? 0.0;
    }
}
