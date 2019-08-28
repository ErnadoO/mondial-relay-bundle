<?php

namespace Ernadoo\MondialRelayBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class ErnadooMondialRelayExtension extends Extension
{
	public function load(array $configs, ContainerBuilder $container)
	{
		$loader = new XmlFileLoader(
			$container,
			new FileLocator(__DIR__.'/../Resources/config')
		);
		$loader->load('services.xml');

		$configuration = new Configuration();

		$config = $this->processConfiguration($configuration, $configs);

		$definition = $container->getDefinition('ernadoo.mondial_relay.client');
		$definition->replaceArgument(0, $config['api']['credentials']['customer_code']);
		$definition->replaceArgument(1, $config['api']['credentials']['secret_key']);
	}
}
