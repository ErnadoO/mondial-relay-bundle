<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MondialRelayTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'mondial_relay_customer_id',
                [MondialRelayRuntime::class, 'customerId'],
            ),
            new TwigFunction(
                'mondial_relay_widget',
                [MondialRelayRuntime::class, 'widget'],
                ['is_safe' => ['html']],
            ),
        ];
    }
}
