<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\DependencyInjection;

use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\MondialRelayClient;
use Ernadoo\MondialRelayBundle\DataCollector\ProfilingMondialRelayClient;
use Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Boots a real kernel with the bundle: catches service definitions that no longer
 * match the constructors of ernadoo/mondial-relay.
 */
final class BundleBootTest extends TestCase
{
    private ?BundleTestKernel $kernel = null;

    protected function tearDown(): void
    {
        if (null !== $this->kernel) {
            (new Filesystem())->remove($this->kernel->getCacheDir());
            $this->kernel->shutdown();
        }
    }

    public function testClientCanBeBuiltFromTheContainer(): void
    {
        $this->kernel = new BundleTestKernel('test', false);
        $this->kernel->boot();

        $client = $this->kernel->getContainer()->get(MondialRelayClientInterface::class);

        self::assertInstanceOf(MondialRelayClientInterface::class, $client);
        self::assertSame('BDTEST  ', $this->kernel->getContainer()->getParameter('ernadoo_mondial_relay.customer_id'));
    }

    public function testProfilerIntegrationIsRegisteredInDebugModeOnly(): void
    {
        $this->kernel = new BundleTestKernel('test', false);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');

        self::assertFalse($container->has(ProfilingMondialRelayClient::class), 'No call log in production');
        self::assertInstanceOf(MondialRelayClient::class, $container->get(MondialRelayClientInterface::class));

        $this->kernel->shutdown();
        (new Filesystem())->remove($this->kernel->getCacheDir());

        $this->kernel = new BundleTestKernel('debug', true);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');

        self::assertInstanceOf(ProfilingMondialRelayClient::class, $container->get(MondialRelayClientInterface::class));
    }
}

final class BundleTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ErnadooMondialRelayBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/mondial-relay-bundle-tests/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir().'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
        ]);

        $container->extension('ernadoo_mondial_relay', [
            'credentials' => [
                'login' => 'login',
                'password' => 'password',
                'customer_id' => 'BDTEST  ',
                'secret_key' => 'secret',
            ],
            'sandbox' => true,
        ]);
    }
}
