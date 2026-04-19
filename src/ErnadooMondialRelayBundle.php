<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle;

use Ernadoo\MondialRelay\Client\RestShipmentClient;
use Ernadoo\MondialRelay\Client\SoapParcelShopClient;
use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\MondialRelayClient;
use Ernadoo\MondialRelayBundle\DataCollector\MondialRelayDataCollector;
use Ernadoo\MondialRelayBundle\DataCollector\ProfilingMondialRelayClient;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class ErnadooMondialRelayBundle extends AbstractBundle
{
    /**
     * Required so Symfony resolves templates from <bundle_root>/templates/
     * instead of <bundle_root>/src/templates/ when the bundle class lives in src/.
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('credentials')
                    ->isRequired()
                    ->children()
                        ->scalarNode('login')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('V2 API login — MR Connect → Administration → Configuration des API')
                        ->end()
                        ->scalarNode('password')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('V2 API password')
                        ->end()
                        ->scalarNode('customer_id')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('8-character brand/customer ID (e.g. "BDTEST  ")')
                        ->end()
                        ->scalarNode('secret_key')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->info('SOAP secret key — used for relay point search (MD5 security)')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('sandbox')
                    ->defaultFalse()
                    ->info('Use the Mondial Relay sandbox environment (https://connect-api-sandbox.mondialrelay.com)')
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // ── Core library clients ──────────────────────────────────────────────

        $services
            ->set(RestShipmentClient::class)
            ->args([
                '$login'      => $config['credentials']['login'],
                '$password'   => $config['credentials']['password'],
                '$customerId' => $config['credentials']['customer_id'],
                '$sandbox'    => $config['sandbox'],
            ]);

        $services
            ->set(SoapParcelShopClient::class)
            ->args([
                '$customerId' => $config['credentials']['customer_id'],
                '$secretKey'  => $config['credentials']['secret_key'],
            ]);

        $services
            ->set(MondialRelayClient::class)
            ->args([
                '$shipmentClient'   => service(RestShipmentClient::class),
                '$parcelShopClient' => service(SoapParcelShopClient::class),
            ]);

        // ── Profiling decorator (wraps MondialRelayClient) ────────────────────

        $services
            ->set(ProfilingMondialRelayClient::class)
            ->decorate(MondialRelayClient::class)
            ->args([
                '$inner' => service('.inner'),
            ]);

        // ── Public interface alias (autowiring entry point) ────────────────────

        $services->alias(MondialRelayClientInterface::class, MondialRelayClient::class)->public();

        // ── Symfony Profiler DataCollector ────────────────────────────────────

        $services
            ->set(MondialRelayDataCollector::class)
            ->args(['$client' => service(ProfilingMondialRelayClient::class)])
            ->tag('data_collector', [
                'template' => '@ErnadooMondialRelay/Collector/mondialrelay.html.twig',
                'id'       => 'ernadoo.mondialrelay',
            ]);

        // ── Twig (relay point widget helper) ─────────────────────────────────

        $services
            ->set(Twig\MondialRelayRuntime::class)
            ->args(['$customerId' => $config['credentials']['customer_id']])
            ->tag('twig.runtime');

        $services
            ->set(Twig\MondialRelayTwigExtension::class)
            ->tag('twig.extension');

        // ── Container parameters ──────────────────────────────────────────────

        $builder->setParameter('ernadoo_mondial_relay.customer_id', $config['credentials']['customer_id']);
        $builder->setParameter('ernadoo_mondial_relay.sandbox', $config['sandbox']);
    }
}
