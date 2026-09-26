<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\DependencyInjection;

use Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

final class AssetMapperTest extends TestCase
{
    public function testStimulusControllerIsExposedToAssetMapper(): void
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $bundlesMetadata = [
            'FrameworkBundle' => ['path' => \dirname((string) (new \ReflectionClass(FrameworkBundle::class))->getFileName())],
        ];
        // Since Symfony 8.2, AssetMapper is its own bundle, registered by FrameworkBundle;
        // before, FrameworkBundle provides its configuration. The CI matrix covers both.
        if (class_exists(AssetMapperBundle::class)) {
            $bundlesMetadata['AssetMapperBundle'] = ['path' => \dirname((string) (new \ReflectionClass(AssetMapperBundle::class))->getFileName())];
        }
        $builder->setParameter('kernel.bundles_metadata', $bundlesMetadata);

        $extension = (new ErnadooMondialRelayBundle())->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        $paths = $builder->getExtensionConfig('framework')[0]['asset_mapper']['paths'] ?? [];
        self::assertSame(['@ernadoo/mondial-relay-bundle'], array_values($paths));
        self::assertFileExists(array_key_first($paths).'/relay_point_picker_controller.js');
    }
}
