<?php

namespace Ernadoo\MondialRelayBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
	/**
	 * Proxy to get root node for Symfony < 4.2.
	 *
	 * @return ArrayNodeDefinition
	 */
	protected function getRootNode(TreeBuilder $treeBuilder, string $name)
	{
		if (\method_exists($treeBuilder, 'getRootNode')) {
			return $treeBuilder->getRootNode();
		} else {
			return $treeBuilder->root($name);
		}
	}

	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder('ernadoo_mondial_relay');

		$this->getRootNode($treeBuilder, 'ernadoo_mondial_relay')
			->children()
				->arrayNode('api')->isRequired()
					->children()
						->scalarNode('wsdl')->isRequired()->cannotBeEmpty()->end()
						->arrayNode('credentials')->isRequired()
							->children()
								->scalarNode('customer_code')->isRequired()->cannotBeEmpty()->end()
								->scalarNode('secret_key')->isRequired()->cannotBeEmpty()->end()
							->end()
						->end()
					->end()
				->end()
			->end()
		;

		return $treeBuilder;
	}
}
