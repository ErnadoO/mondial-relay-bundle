<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\Twig;

use Ernadoo\MondialRelayBundle\Twig\MondialRelayRuntime;
use PHPUnit\Framework\TestCase;

final class MondialRelayRuntimeTest extends TestCase
{
    public function testWidgetRendersTheStimulusControllerWithItsValues(): void
    {
        $html = (new MondialRelayRuntime('BDTEST  '))->widget(postCode: '29950', city: 'Bénodet');

        self::assertStringContainsString('data-controller="ernadoo--mondial-relay-bundle--relay-point-picker"', $html);
        self::assertStringContainsString('data-ernadoo--mondial-relay-bundle--relay-point-picker-brand-value="BDTEST  "', $html);
        self::assertStringContainsString('data-ernadoo--mondial-relay-bundle--relay-point-picker-post-code-value="29950"', $html);
        self::assertStringContainsString('data-ernadoo--mondial-relay-bundle--relay-point-picker-city-value="Bénodet"', $html);
        self::assertStringContainsString('data-ernadoo--mondial-relay-bundle--relay-point-picker-mode-value="24R"', $html);
        self::assertStringContainsString('<input type="hidden" name="relay_point_id" data-ernadoo--mondial-relay-bundle--relay-point-picker-target="id">', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testWidgetEscapesAttributes(): void
    {
        $html = (new MondialRelayRuntime('BDTEST  '))->widget(postCode: '"><script>', inputName: 'order[relay]');

        self::assertStringNotContainsString('"><script>', $html);
        self::assertStringContainsString('name="order[relay]"', $html);
    }

    public function testCustomerIdIsExposedForCustomSetups(): void
    {
        self::assertSame('BDTEST  ', (new MondialRelayRuntime('BDTEST  '))->customerId());
    }
}
