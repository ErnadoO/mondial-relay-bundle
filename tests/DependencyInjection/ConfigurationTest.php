<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\DependencyInjection;

use Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

final class ConfigurationTest extends TestCase
{
    public function testCredentialsAreNamedAfterMrConnect(): void
    {
        $config = $this->process(['credentials' => [
            'brand_code' => 'CC12345',
            'api_login' => 'api-login@example.com',
            'api_password' => 'api-password',
            'private_key' => 'PRIVATE',
        ]]);

        self::assertSame([
            'brand_code' => 'CC12345',
            'api_login' => 'api-login@example.com',
            'api_password' => 'api-password',
            'private_key' => 'PRIVATE',
        ], $config['credentials']);
    }

    public function testOnlyTheBrandCodeIsRequired(): void
    {
        // The widget only needs the brand code; labels need the API user, the API search the private key.
        $config = $this->process(['credentials' => ['brand_code' => 'CC12345']]);

        self::assertSame('', $config['credentials']['api_login']);
        self::assertSame('', $config['credentials']['api_password']);
        self::assertSame('', $config['credentials']['private_key']);
        self::assertSame('widget', $config['relay_point_picker']['mode']);

        $this->expectException(InvalidConfigurationException::class);
        $this->process(['credentials' => ['api_login' => 'x']]);
    }

    #[IgnoreDeprecations]
    public function testFormerKeysAreStillAcceptedWithADeprecation(): void
    {
        $this->expectUserDeprecationMessageMatches('/credentials.customer_id" option is deprecated, use "brand_code"/');

        $config = $this->process(['credentials' => [
            'login' => 'api-login@example.com',
            'password' => 'api-password',
            'customer_id' => 'CC12345',
            'secret_key' => 'PRIVATE',
        ]]);

        self::assertSame('CC12345', $config['credentials']['brand_code']);
        self::assertSame('api-login@example.com', $config['credentials']['api_login']);
        self::assertSame('api-password', $config['credentials']['api_password']);
        self::assertSame('PRIVATE', $config['credentials']['private_key']);
    }

    public function testApiPickerMode(): void
    {
        $config = $this->process([
            'credentials' => ['brand_code' => 'CC12345', 'private_key' => 'PRIVATE'],
            'relay_point_picker' => ['mode' => 'api', 'cache_ttl' => 600, 'rate_limit' => 10],
        ]);

        self::assertSame(['mode' => 'api', 'cache_ttl' => 600, 'rate_limit' => 10], $config['relay_point_picker']);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $extension = (new ErnadooMondialRelayBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return (new Processor())->processConfiguration($configuration, [$config]);
    }
}
