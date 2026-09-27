<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Translation;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\ConfigurationException;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Message to show to users when a Mondial Relay call fails, translated in the ErnadooMondialRelayBundle
 * domain. Exception messages stay in English for developers and logs.
 *
 *     $this->addFlash('danger', MondialRelayErrorMessage::fromException($e)); // {{ message|trans }}
 */
final class MondialRelayErrorMessage implements TranslatableInterface
{
    public const DOMAIN = 'ErnadooMondialRelayBundle';

    /**
     * Mondial Relay codes that users can act on, or that call for a specific message.
     * 5 digits: label creation (codes seen on the sandbox); 1–99: relay point search (STAT).
     */
    private const API_CODES = [
        '10001' => 'error.configuration', // Invalid API login or password
        '10034' => 'error.parcel_weight',
        '10051' => 'error.phone_number',
        '10055' => 'error.relay_point',   // No sorting plan: unknown relay point, or unavailable for the delivery mode
        '1' => 'error.configuration',     // Incorrect merchant
        '2' => 'error.configuration',     // Merchant number empty
        '3' => 'error.configuration',     // Incorrect merchant account number
        '8' => 'error.configuration',     // Incorrect password or hash
        '9' => 'error.post_code',         // Unknown or not unique city
        '36' => 'error.post_code',        // Incorrect zipcode
        '37' => 'error.country',          // Incorrect country
        '69' => 'error.configuration',    // Incorrect merchant code
        '95' => 'error.configuration',    // Merchant account not activated
        '97' => 'error.configuration',    // Incorrect security key
        '99' => 'error.unavailable',      // Generic error of service system
    ];

    private function __construct(
        private readonly string $key,
    ) {
    }

    public static function fromException(\Throwable $exception): self
    {
        return new self(match (true) {
            $exception instanceof ApiException => self::keyForCodes(array_keys($exception->getErrors())),
            $exception instanceof ConfigurationException => 'error.configuration',
            // TransportException, and anything unexpected
            default => 'error.unavailable',
        });
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->key, [], self::DOMAIN, $locale);
    }

    /** @param list<int|string> $codes */
    private static function keyForCodes(array $codes): string
    {
        foreach ($codes as $code) {
            if (isset(self::API_CODES[(string) $code])) {
                return self::API_CODES[(string) $code];
            }
        }

        return 'error.rejected';
    }
}
