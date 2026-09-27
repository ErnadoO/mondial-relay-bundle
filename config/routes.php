<?php

declare(strict_types=1);

use Ernadoo\MondialRelayBundle\Controller\RelayPointSearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Routes of the "api" relay point picker. Import them in your application:
 *
 *   # config/routes/ernadoo_mondial_relay.yaml
 *   ernadoo_mondial_relay:
 *       resource: '@ErnadooMondialRelayBundle/config/routes.php'
 *       prefix: /mondial-relay
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('ernadoo_mondial_relay_relay_points', '/relay-points')
        ->controller(RelayPointSearchController::class)
        ->methods(['GET']);
};
