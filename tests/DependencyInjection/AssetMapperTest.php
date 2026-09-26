<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\DependencyInjection;

use Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

final class AssetMapperTest extends TestCase
{
    public function testStimulusControllerIsExposedToAssetMapper(): void
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $builder->setParameter('kernel.bundles_metadata', [
            'FrameworkBundle' => ['path' => \dirname((string) (new \ReflectionClass(FrameworkBundle::class))->getFileName())],
            // Registered automatically by FrameworkBundle since Symfony 8.2
            'AssetMapperBundle' => ['path' => ''],
        ]);

        $extension = (new ErnadooMondialRelayBundle())->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        $paths = $builder->getExtensionConfig('framework')[0]['asset_mapper']['paths'] ?? [];
        self::assertSame(['@ernadoo/mondial-relay-bundle'], array_values($paths));
        self::assertFileExists(array_key_first($paths).'/relay_point_picker_controller.js');
    }
}
