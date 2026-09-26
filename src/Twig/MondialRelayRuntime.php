<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for the Mondial Relay relay point picker.
 *
 * The picker itself is the `relay-point-picker` Stimulus controller shipped in assets/:
 * this runtime only renders the markup it connects to.
 */
final class MondialRelayRuntime implements RuntimeExtensionInterface
{
    /** Stimulus identifier of assets/dist/relay_point_picker_controller.js. */
    public const CONTROLLER = 'ernadoo--mondial-relay-bundle--relay-point-picker';

    public function __construct(
        private readonly string $customerId,
    ) {
    }

    /**
     * Returns the customer ID (brand code), e.g. to configure the Stimulus controller yourself.
     */
    public function customerId(): string
    {
        return $this->customerId;
    }

    /**
     * Renders a relay point picker: a map and a hidden input receiving the selected
     * relay point ID, ready for ShipmentRequest::$deliveryLocation (e.g. "FR-066974").
     *
     * @param string $postCode  postal code to center the search on
     * @param string $inputName name of the hidden input
     * @param string $country   two-letter ISO country code
     * @param string $city      city to center the search on
     * @param string $mode      delivery mode ("24R" relay point, "24L" locker…)
     */
    public function widget(
        string $postCode = '',
        string $inputName = 'relay_point_id',
        string $country = 'FR',
        string $city = '',
        string $mode = '24R',
    ): string {
        $values = [
            'brand' => $this->customerId,
            'country' => $country,
            'post-code' => $postCode,
            'city' => $city,
            'mode' => $mode,
        ];

        $attributes = sprintf('data-controller="%s"', self::CONTROLLER);
        foreach ($values as $name => $value) {
            $attributes .= sprintf(' data-%s-%s-value="%s"', self::CONTROLLER, $name, self::escape($value));
        }

        return sprintf(
            '<div %1$s><div data-%2$s-target="map"></div><input type="hidden" name="%3$s" data-%2$s-target="id"></div>',
            $attributes,
            self::CONTROLLER,
            self::escape($inputName),
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
