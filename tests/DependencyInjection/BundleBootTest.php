<?php

declare(strict_types=1);

namespace Ernadoo\MondialRelayBundle\Tests\DependencyInjection;

use Ernadoo\MondialRelay\Contract\MondialRelayClientInterface;
use Ernadoo\MondialRelay\MondialRelayClient;
use Ernadoo\MondialRelay\ParcelShop\ParcelShop;
use Ernadoo\MondialRelay\ParcelShop\ParcelShopSearchRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentRequest;
use Ernadoo\MondialRelay\Shipment\ShipmentResponse;
use Ernadoo\MondialRelayBundle\DataCollector\ProfilingMondialRelayClient;
use Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

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

    public function testRelayPointSearchEndpointIsRoutedCachedAndRateLimited(): void
    {
        $this->kernel = new BundleTestKernel('stub', false);
        $this->kernel->boot();

        $search = fn (string $postCode) => $this->kernel->handle(Request::create('/mondial-relay/relay-points', 'GET', ['postCode' => $postCode], server: ['REMOTE_ADDR' => '192.0.2.10']));

        $response = $search('29950');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('FR-018332', json_decode((string) $response->getContent(), true)['relayPoints'][0]['id']);

        self::assertSame(200, $search('29000')->getStatusCode());
        self::assertSame(429, $search('29100')->getStatusCode(), 'rate_limit: 2 searches per minute');
    }

    public function testApiErrorsAreLoggedInEveryEnvironment(): void
    {
        if (!(new \ReflectionClass(MondialRelayClient::class))->implementsInterface(LoggerAwareInterface::class)) {
            self::markTestSkipped('ernadoo/mondial-relay without PSR-3 logging support');
        }

        $this->kernel = new BundleTestKernel('test', false);
        $this->kernel->boot();
        $client = $this->kernel->getContainer()->get('test.service_container')->get(MondialRelayClient::class);

        foreach (['shipmentClient', 'parcelShopClient'] as $property) {
            $inner = (new \ReflectionProperty($client, $property))->getValue($client);
            self::assertInstanceOf(LoggerInterface::class, (new \ReflectionProperty($inner, 'logger'))->getValue($inner), $property);
        }
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
                'brand_code' => 'BDTEST  ',
                'api_login' => 'login',
                'api_password' => 'password',
                'private_key' => 'secret',
            ],
            'sandbox' => true,
            'relay_point_picker' => ['mode' => 'api', 'rate_limit' => 2],
        ]);

        // "stub" environment: the relay point search answers without calling Mondial Relay
        if ('stub' === $this->environment) {
            $container->services()
                ->set(MondialRelayClientInterface::class, StubRelayPointClient::class)
                ->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@ErnadooMondialRelayBundle/config/routes.php')->prefix('/mondial-relay');
    }
}

final class StubRelayPointClient implements MondialRelayClientInterface
{
    public function createShipment(ShipmentRequest $request): ShipmentResponse
    {
        throw new \LogicException('Not used');
    }

    public function searchParcelShops(ParcelShopSearchRequest $request): array
    {
        return [new ParcelShop('018332', 'LOCKER', '95 ZONE', '', '29170', 'FOUESNANT', 'FR', 47.88, -4.02, 5.1)];
    }
}
