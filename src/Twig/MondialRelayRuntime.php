<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Twig;

use Twig\Extension\RuntimeExtensionInterface;

/**
 * Twig runtime for the Mondial Relay relay-point selection widget.
 *
 * Usage in Twig:
 *   {{ mondial_relay_widget('FR', '75001') }}
 *   {{ mondial_relay_customer_id() }}
 */
final class MondialRelayRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly string $customerId,
    ) {
    }

    /**
     * Returns the customer ID for use in the JS widget initialization.
     */
    public function customerId(): string
    {
        return $this->customerId;
    }

    /**
     * Renders the Mondial Relay relay-point selection widget HTML + JS snippet.
     *
     * @param string $country  Two-letter ISO country code (e.g. "FR")
     * @param string $postCode Postal code to center the search on
     * @param string $mode     Delivery mode code ("24R" or "24L")
     */
    public function widget(string $country = 'FR', string $postCode = '', string $mode = '24R'): string
    {
        $escapedCode = htmlspecialchars($this->customerId, \ENT_QUOTES);
        $escapedCountry = htmlspecialchars($country, \ENT_QUOTES);
        $escapedPostCode = htmlspecialchars($postCode, \ENT_QUOTES);
        $escapedMode = htmlspecialchars($mode, \ENT_QUOTES);

        return sprintf(
            <<<'HTML'
            <div id="Zone_Widget">
                <div id="Zone_Map" style="width:100%%;height:400px;"></div>
                <input type="hidden" name="relay_point_id" id="relay_point_id" value="" />
            </div>
            <script type="text/javascript">
                MR_params = {
                    Target: "",
                    Brand: "%s",
                    Country: "%s",
                    Postcode: "%s",
                    ColLivMod: "%s",
                    NbResults: "7",
                    MapWidth: "100%%",
                    MapHeight: "400px",
                    AllowedCountries: "%s",
                    EnableGeolocalistion: "1"
                };
            </script>
            <script type="text/javascript" src="https://widget.mondialrelay.com/parcelshop-picker/jquery.mondialrelay.parcelshoppicker.min.js"></script>
            HTML,
            $escapedCode,
            $escapedCountry,
            $escapedPostCode,
            $escapedMode,
            $escapedCountry,
        );
    }
}
