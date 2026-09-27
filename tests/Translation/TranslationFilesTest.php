<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\Translation;

use Ernadoo\MondialRelayBundle\Translation\MondialRelayErrorMessage;
use Ernadoo\MondialRelayBundle\Twig\MondialRelayRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\XliffFileLoader;

/**
 * Every message used by the bundle exists in every provided language.
 */
final class TranslationFilesTest extends TestCase
{
    private const LOCALES = ['en', 'fr'];

    public function testEveryLanguageHasTheSameMessages(): void
    {
        $english = array_keys($this->messages('en'));
        sort($english);

        foreach (self::LOCALES as $locale) {
            $keys = array_keys($this->messages($locale));
            sort($keys);
            self::assertSame($english, $keys, $locale);
        }
    }

    public function testEveryMessageUsedByTheBundleIsTranslated(): void
    {
        $used = array_map(static fn (string $label) => 'relay_point_picker.'.$label, array_keys((new MondialRelayRuntime('BDTEST'))->labels()));
        foreach ((new \ReflectionClassConstant(MondialRelayErrorMessage::class, 'API_CODES'))->getValue() as $key) {
            $used[] = $key;
        }
        array_push($used, 'error.configuration', 'error.unavailable', 'error.rejected');

        foreach (self::LOCALES as $locale) {
            $messages = $this->messages($locale);
            foreach (array_unique($used) as $key) {
                self::assertArrayHasKey($key, $messages, "$key in $locale");
                self::assertNotSame($key, $messages[$key], "$key is translated in $locale");
            }
        }
    }

    /** @return array<string, string> */
    private function messages(string $locale): array
    {
        $path = \dirname(__DIR__, 2).'/translations/'.MondialRelayErrorMessage::DOMAIN.'.'.$locale.'.xlf';

        return (new XliffFileLoader())->load($path, $locale, MondialRelayErrorMessage::DOMAIN)->all(MondialRelayErrorMessage::DOMAIN);
    }
}
