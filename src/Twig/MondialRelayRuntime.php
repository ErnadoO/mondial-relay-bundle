<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Twig;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for the Mondial Relay relay point picker.
 *
 * Two pickers, shipped as Stimulus controllers in assets/: the official Mondial Relay widget
 * ("widget") and a list + Leaflet map fed by the relay point search API ("api").
 * This runtime only renders the markup they connect to.
 */
final class MondialRelayRuntime implements RuntimeExtensionInterface
{
    /** Stimulus identifier of assets/dist/relay_point_picker_controller.js (official widget). */
    public const CONTROLLER = 'ernadoo--mondial-relay-bundle--relay-point-picker';

    /** Stimulus identifier of assets/dist/relay_point_api_picker_controller.js (list + map). */
    public const API_CONTROLLER = 'ernadoo--mondial-relay-bundle--relay-point-api-picker';

    public const SEARCH_ROUTE = 'ernadoo_mondial_relay_relay_points';

    private const TRANSLATION_DOMAIN = 'ErnadooMondialRelayBundle';

    /** Labels of the "api" picker, used when symfony/translation is not installed. */
    private const DEFAULT_LABELS = [
        'postCode' => 'Postal code',
        'search' => 'Search',
        'searching' => 'Searching…',
        'results' => '%count% relay point(s) found',
        'noResult' => 'No relay point found around this postal code.',
        'invalidPostCode' => 'Enter a valid postal code.',
        'unavailable' => 'Relay points are unavailable, please try again later.',
        'tooManyRequests' => 'Too many searches, please try again in a minute.',
        'choose' => 'Choose',
        'selected' => 'Selected: %name%',
        'openingHours' => 'Opening hours',
        'closed' => 'Closed',
    ];

    public function __construct(
        private readonly string $brandCode,
        private readonly string $mode = 'widget',
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Returns the brand code ("code enseigne"), e.g. to configure a Stimulus controller yourself.
     */
    public function brandCode(): string
    {
        return $this->brandCode;
    }

    /**
     * @deprecated since 3.3, use brandCode() (Twig: mondial_relay_brand_code())
     */
    public function customerId(): string
    {
        trigger_deprecation('ernadoo/mondial-relay-bundle', '3.3', 'The "mondial_relay_customer_id()" Twig function is deprecated, use "mondial_relay_brand_code()" instead.');

        return $this->brandCode;
    }

    /**
     * Renders a relay point picker and a hidden input receiving the selected relay point ID,
     * ready for ShipmentRequest::$deliveryLocation (e.g. "FR-066974").
     *
     * @param string      $postCode  postal code to center the search on
     * @param string      $inputName name of the hidden input
     * @param string      $country   two-letter ISO country code
     * @param string      $city      city to center the search on ("widget" picker only)
     * @param string      $mode      delivery mode ("24R" relay point, "24L" locker…)
     * @param string      $selected  ID of a relay point to highlight, e.g. the saved one ("FR-066974")
     * @param string|null $picker    "widget" or "api"; defaults to relay_point_picker.mode
     */
    public function widget(
        string $postCode = '',
        string $inputName = 'relay_point_id',
        string $country = 'FR',
        string $city = '',
        string $mode = '24R',
        string $selected = '',
        ?string $picker = null,
    ): string {
        $picker ??= $this->mode;

        if ('api' === $picker) {
            return $this->render(self::API_CONTROLLER, 'panel', [
                'url' => $this->searchUrl(),
                'country' => $country,
                'post-code' => $postCode,
                'mode' => $mode,
                'selected' => $selected,
                'labels' => (string) json_encode($this->labels(), \JSON_UNESCAPED_UNICODE),
            ], $inputName, $selected);
        }

        if ('widget' !== $picker) {
            throw new \InvalidArgumentException(sprintf('Unknown relay point picker "%s": use "widget" or "api".', $picker));
        }

        return $this->render(self::CONTROLLER, 'map', [
            'brand' => $this->brandCode,
            'country' => $country,
            'post-code' => $postCode,
            'city' => $city,
            'mode' => $mode,
            'selected' => $selected,
        ], $inputName, $selected);
    }

    /**
     * Translated labels of the "api" picker, e.g. to configure its Stimulus controller yourself.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        if (null === $this->translator) {
            return self::DEFAULT_LABELS;
        }

        $labels = [];
        foreach (array_keys(self::DEFAULT_LABELS) as $key) {
            $labels[$key] = $this->translator->trans('relay_point_picker.'.$key, [], self::TRANSLATION_DOMAIN);
        }

        return $labels;
    }

    private function searchUrl(): string
    {
        try {
            return $this->urlGenerator?->generate(self::SEARCH_ROUTE)
                ?? throw new RouteNotFoundException(self::SEARCH_ROUTE);
        } catch (RouteNotFoundException $e) {
            throw new \LogicException('The "api" relay point picker needs the routes of the bundle: import "@ErnadooMondialRelayBundle/config/routes.php" in your routing configuration.', 0, $e);
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function render(string $controller, string $target, array $values, string $inputName, string $selected): string
    {
        $attributes = sprintf('data-controller="%s"', $controller);
        foreach ($values as $name => $value) {
            $attributes .= sprintf(' data-%s-%s-value="%s"', $controller, $name, self::escape($value));
        }

        return sprintf(
            '<div %1$s><div data-%2$s-target="%5$s"></div><input type="hidden" name="%3$s" value="%4$s" data-%2$s-target="id"></div>',
            $attributes,
            $controller,
            self::escape($inputName),
            self::escape($selected),
            $target,
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
