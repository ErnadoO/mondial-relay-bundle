<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\Twig;

use Ernadoo\MondialRelayBundle\Twig\MondialRelayRuntime;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        self::assertStringContainsString('<input type="hidden" name="relay_point_id" value="" data-ernadoo--mondial-relay-bundle--relay-point-picker-target="id">', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testWidgetHighlightsAndPrefillsTheSavedRelayPoint(): void
    {
        $html = (new MondialRelayRuntime('BDTEST  '))->widget(postCode: '29170', selected: 'FR-018332');

        self::assertStringContainsString('data-ernadoo--mondial-relay-bundle--relay-point-picker-selected-value="FR-018332"', $html);
        self::assertStringContainsString('name="relay_point_id" value="FR-018332"', $html);
    }

    public function testWidgetEscapesAttributes(): void
    {
        $html = (new MondialRelayRuntime('BDTEST  '))->widget(postCode: '"><script>', inputName: 'order[relay]');

        self::assertStringNotContainsString('"><script>', $html);
        self::assertStringContainsString('name="order[relay]"', $html);
    }

    public function testBrandCodeIsExposedForCustomSetups(): void
    {
        self::assertSame('BDTEST  ', (new MondialRelayRuntime('BDTEST  '))->brandCode());
    }

    #[IgnoreDeprecations]
    public function testCustomerIdIsDeprecated(): void
    {
        $this->expectUserDeprecationMessageMatches('/"mondial_relay_customer_id\(\)" Twig function is deprecated/');

        self::assertSame('BDTEST  ', (new MondialRelayRuntime('BDTEST  '))->customerId());
    }

    public function testApiPickerRendersTheListAndMapControllerWithTheSearchUrl(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/mondial-relay/relay-points');

        $html = (new MondialRelayRuntime('CC12345', 'widget', $urls))->widget(postCode: '29950', selected: 'FR-018332', picker: 'api');

        $controller = 'ernadoo--mondial-relay-bundle--relay-point-api-picker';
        self::assertStringContainsString(sprintf('data-controller="%s"', $controller), $html);
        self::assertStringContainsString(sprintf('data-%s-url-value="/mondial-relay/relay-points"', $controller), $html);
        self::assertStringContainsString(sprintf('data-%s-post-code-value="29950"', $controller), $html);
        self::assertStringContainsString(sprintf('<div data-%s-target="panel"></div>', $controller), $html);
        self::assertStringContainsString(sprintf('name="relay_point_id" value="FR-018332" data-%s-target="id"', $controller), $html);
        self::assertStringContainsString('&quot;search&quot;:&quot;Search&quot;', $html, 'Labels are passed to the controller');
        self::assertStringNotContainsString('CC12345', $html, 'The API picker does not need the brand code in the page');
    }

    public function testConfiguredPickerModeIsTheDefault(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/relay-points');

        self::assertStringContainsString('relay-point-api-picker', (new MondialRelayRuntime('CC12345', 'api', $urls))->widget());
        self::assertStringContainsString('"ernadoo--mondial-relay-bundle--relay-point-picker"', (new MondialRelayRuntime('CC12345', 'api', $urls))->widget(picker: 'widget'));
    }

    public function testApiPickerWithoutTheRoutesFailsWithAClearMessage(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willThrowException(new RouteNotFoundException());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('@ErnadooMondialRelayBundle/config/routes.php');

        (new MondialRelayRuntime('CC12345', 'api', $urls))->widget();
    }
}
