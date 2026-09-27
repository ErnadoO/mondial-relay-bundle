<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle;

use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\MondialRelayClient;
use Ernadoo\MondialRelayBundle\Controller\RelayPointSearchController;
use Ernadoo\MondialRelayBundle\DataCollector\MondialRelayDataCollector;
use Ernadoo\MondialRelayBundle\DataCollector\ProfilingMondialRelayClient;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Twig\Extension\RuntimeExtensionInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class ErnadooMondialRelayBundle extends AbstractBundle
{
    /**
     * Exposes assets/dist (the relay-point-picker Stimulus controller) to AssetMapper,
     * under the "@ernadoo/mondial-relay-bundle" namespace used by controllers.json.
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->isAssetMapperAvailable($builder)) {
            return;
        }

        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    $this->getPath().'/assets/dist' => '@ernadoo/mondial-relay-bundle',
                ],
            ],
        ]);
    }

    private function isAssetMapperAvailable(ContainerBuilder $builder): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        /** @var array<string, array{path: string}> $bundlesMetadata */
        $bundlesMetadata = $builder->getParameter('kernel.bundles_metadata');

        if (!isset($bundlesMetadata['FrameworkBundle'])) {
            return false;
        }

        // Symfony 8.2 moved AssetMapper to its own bundle; before, FrameworkBundle provided its configuration.
        return isset($bundlesMetadata['AssetMapperBundle'])
            || is_file($bundlesMetadata['FrameworkBundle']['path'].'/Resources/config/asset_mapper.php');
    }

    /** Former credential keys (until 3.2) and their MR Connect names. */
    private const FORMER_CREDENTIAL_KEYS = [
        'customer_id' => 'brand_code',
        'login' => 'api_login',
        'password' => 'api_password',
        'secret_key' => 'private_key',
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('credentials')
                    ->isRequired()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function (array $credentials): array {
                            foreach (self::FORMER_CREDENTIAL_KEYS as $former => $current) {
                                if (\array_key_exists($former, $credentials)) {
                                    trigger_deprecation('ernadoo/mondial-relay-bundle', '3.3', 'The "ernadoo_mondial_relay.credentials.%s" option is deprecated, use "%s" instead.', $former, $current);
                                    $credentials[$current] ??= $credentials[$former];
                                    unset($credentials[$former]);
                                }
                            }

                            return $credentials;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('brand_code')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('Brand code ("code enseigne" in MR Connect), 8 characters. Used by the widget, label creation and relay point search')
                        ->end()
                        ->scalarNode('api_login')
                            ->defaultValue('')
                            ->info('Login of an MR Connect API user (Administration → User management → API configuration). Required to create labels')
                        ->end()
                        ->scalarNode('api_password')
                            ->defaultValue('')
                            ->info('Password of that API user. Required to create labels')
                        ->end()
                        ->scalarNode('private_key')
                            ->defaultValue('')
                            ->info('Private key of the brand ("clé privée" in MR Connect). Required to search relay points through the API (and the "api" picker)')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('sandbox')
                    ->defaultFalse()
                    ->info('Create labels in the Mondial Relay sandbox (https://connect-api-sandbox.mondialrelay.com): nothing is recorded')
                ->end()
                ->arrayNode('relay_point_picker')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('mode')
                            ->values(['widget', 'api'])
                            ->defaultValue('widget')
                            ->info('"widget": official Mondial Relay widget (brand code only). "api": list and Leaflet map fed by the relay point search API (needs the private key and the bundle routes)')
                        ->end()
                        ->integerNode('cache_ttl')
                            ->min(0)
                            ->defaultValue(3600)
                            ->info('"api" mode: seconds a search result is cached (0 disables the cache)')
                        ->end()
                        ->integerNode('rate_limit')
                            ->min(0)
                            ->defaultValue(30)
                            ->info('"api" mode: searches per minute and per IP address, when symfony/rate-limiter is installed (0 disables it)')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // ── PSR-18 client (wraps Symfony HttpClient) ──────────────────────────
        // Psr18Client implements PSR-18 (ClientInterface) + PSR-17 (RequestFactoryInterface
        // + StreamFactoryInterface), so a single instance covers all three.
        // REST calls appear automatically in the Symfony Profiler HTTP panel.

        $services
            ->set('ernadoo_mondial_relay.http_client', Psr18Client::class)
            ->args([service('http_client')]);

        // ── Core library clients ──────────────────────────────────────────────

        $credentials = $config['credentials'];

        $services
            ->set('ernadoo_mondial_relay.shipment_client', RestShipmentClient::class)
            ->args([
                '$client'         => service('ernadoo_mondial_relay.http_client'),
                '$requestFactory' => service('ernadoo_mondial_relay.http_client'),
                '$streamFactory'  => service('ernadoo_mondial_relay.http_client'),
                '$apiLogin'       => $credentials['api_login'],
                '$apiPassword'    => $credentials['api_password'],
                '$brandCode'      => $credentials['brand_code'],
                '$sandbox'        => $config['sandbox'],
            ]);

        $services
            ->set('ernadoo_mondial_relay.parcel_shop_client', SoapParcelShopClient::class)
            ->args([
                '$brandCode'  => $credentials['brand_code'],
                '$privateKey' => $credentials['private_key'],
            ]);

        // API errors, warnings and created shipments are logged in every environment, on the "mondial_relay" channel
        $services
            ->set('ernadoo_mondial_relay.client', MondialRelayClient::class)
            ->args([
                '$shipmentClient'   => service('ernadoo_mondial_relay.shipment_client'),
                '$parcelShopClient' => service('ernadoo_mondial_relay.parcel_shop_client'),
            ])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'mondial_relay']);

        // ── Autowiring entry point ─────────────────────────────────────────────

        $services->alias(MondialRelayClientInterface::class, 'ernadoo_mondial_relay.client');

        // ── Symfony Profiler (debug only) ─────────────────────────────────────
        // The decorator keeps a log of the calls: never in production.

        if ($builder->getParameter('kernel.debug')) {
            $services
                ->set('ernadoo_mondial_relay.profiling_client', ProfilingMondialRelayClient::class)
                ->decorate('ernadoo_mondial_relay.client')
                ->args([
                    '$inner'     => service('.inner'),
                    '$stopwatch' => service('debug.stopwatch')->nullOnInvalid(),
                    '$sandbox'   => $config['sandbox'],
                ])
                ->tag('kernel.reset', ['method' => 'reset']);

            $services
                ->set('ernadoo_mondial_relay.data_collector', MondialRelayDataCollector::class)
                ->args(['$client' => service('ernadoo_mondial_relay.profiling_client')])
                ->tag('data_collector', [
                    'template' => '@ErnadooMondialRelay/Collector/mondialrelay.html.twig',
                    'id'       => 'ernadoo.mondialrelay',
                ]);
        }

        // ── "api" relay point picker: search endpoint (routes: config/routes.php) ──

        $picker = $config['relay_point_picker'];
        $limiter = null;
        if ($picker['rate_limit'] > 0 && class_exists(RateLimiterFactory::class)) {
            $services
                ->set('ernadoo_mondial_relay.relay_point_search.limiter', RateLimiterFactory::class)
                ->args([
                    ['id' => 'ernadoo_mondial_relay_relay_points', 'policy' => 'sliding_window', 'limit' => $picker['rate_limit'], 'interval' => '1 minute'],
                    inline_service(CacheStorage::class)->args([service('cache.app')]),
                ]);
            $limiter = service('ernadoo_mondial_relay.relay_point_search.limiter');
        }

        $services
            ->set('ernadoo_mondial_relay.relay_point_search_controller', RelayPointSearchController::class)
            ->args([service(MondialRelayClientInterface::class), service('cache.app'), $picker['cache_ttl'], $limiter])
            ->tag('controller.service_arguments')
            ->public();

        // ── Twig (relay point picker markup), when Twig is installed ──────────

        if (interface_exists(RuntimeExtensionInterface::class)) {
            $services
                ->set('ernadoo_mondial_relay.twig.runtime', Twig\MondialRelayRuntime::class)
                ->args([
                    '$brandCode'    => $credentials['brand_code'],
                    '$mode'         => $picker['mode'],
                    '$urlGenerator' => service('router')->nullOnInvalid(),
                    '$translator'   => service('translator')->nullOnInvalid(),
                ])
                ->tag('twig.runtime');

            $services
                ->set('ernadoo_mondial_relay.twig.extension', Twig\MondialRelayTwigExtension::class)
                ->tag('twig.extension');
        }

        // ── Container parameters ──────────────────────────────────────────────

        $builder->setParameter('ernadoo_mondial_relay.brand_code', $credentials['brand_code']);
        // Former name, kept for applications that still read it
        $builder->setParameter('ernadoo_mondial_relay.customer_id', $credentials['brand_code']);
        $builder->setParameter('ernadoo_mondial_relay.sandbox', $config['sandbox']);
    }
}
