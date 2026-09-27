<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\Translation;

use Ernadoo\MondialRelay\Exception\ApiException;
use Ernadoo\MondialRelay\Exception\ConfigurationException;
use Ernadoo\MondialRelay\Exception\TransportException;
use Ernadoo\MondialRelayBundle\Translation\MondialRelayErrorMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;

final class MondialRelayErrorMessageTest extends TestCase
{
    /** @return iterable<string, array{\Throwable, string}> */
    public static function exceptions(): iterable
    {
        // Codes and messages returned by the Mondial Relay sandbox
        yield 'invalid phone number' => [ApiException::fromApiErrors(['10051' => 'Le numéro de téléphone "+3312" est invalide, il doit être au format internationnal.']), 'error.phone_number'];
        yield 'no sorting plan for the relay point' => [ApiException::fromApiErrors(['10055' => 'Le plan de tri est introuvable pour le produit Point Relais L (24R)']), 'error.relay_point'];
        yield 'parcel too heavy' => [ApiException::fromApiErrors(['10034' => 'Le poids des colis doit être inférieur ou égal à 30000,00']), 'error.parcel_weight'];
        yield 'invalid API user' => [ApiException::fromApiErrors(['10001' => 'Login et/ou mot de passe non valide.']), 'error.configuration'];
        yield 'unknown code' => [ApiException::fromApiErrors(['99999' => 'Service_Expedition_PaysObligatoire']), 'error.rejected'];
        yield 'first known code wins' => [ApiException::fromApiErrors(['99999' => 'Generic', '10051' => 'Phone']), 'error.phone_number'];
        // Relay point search (STAT codes)
        yield 'incorrect security key' => [ApiException::fromApiErrors(['97' => 'Incorrect security key']), 'error.configuration'];
        yield 'incorrect zipcode' => [ApiException::fromApiErrors(['36' => 'Incorrect zipcode']), 'error.post_code'];
        yield 'search service error' => [ApiException::fromApiErrors(['99' => 'Generic error of service system']), 'error.unavailable'];
        // Failures that are not Mondial Relay rejections
        yield 'missing credential' => [new ConfigurationException('Label creation requires the API login and password'), 'error.configuration'];
        yield 'Mondial Relay unreachable' => [new TransportException('HTTP 503 from Mondial Relay API.'), 'error.unavailable'];
        yield 'unexpected error' => [new \RuntimeException('Boom'), 'error.unavailable'];
    }

    #[DataProvider('exceptions')]
    public function testExceptionIsTurnedIntoAMessageKey(\Throwable $exception, string $key): void
    {
        self::assertSame($key, MondialRelayErrorMessage::fromException($exception)->getKey());
    }

    public function testMessageIsTranslatedWithTheBundleTranslations(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('xlf', new XliffFileLoader());
        foreach (['en', 'fr'] as $locale) {
            $translator->addResource('xlf', \dirname(__DIR__, 2).'/translations/ErnadooMondialRelayBundle.'.$locale.'.xlf', $locale, MondialRelayErrorMessage::DOMAIN);
        }

        $message = MondialRelayErrorMessage::fromException(ApiException::fromApiErrors(['10051' => 'Téléphone invalide']));

        self::assertSame('Le numéro de téléphone est invalide : saisissez-le au format international, par exemple +33612345678.', $message->trans($translator));
        self::assertSame('The phone number is invalid: enter it in international format, e.g. +33612345678.', $message->trans($translator, 'en'));
    }
}
