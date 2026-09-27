# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.3.0] - 2026-09-27

### Added

- `api` relay point picker: a list and a Leaflet map fed by the relay point search API, as an
  alternative to the official Mondial Relay widget (no jQuery, no Mondial Relay script, your own
  styles). Its search endpoint (`@ErnadooMondialRelayBundle/config/routes.php`) caches results and
  rate-limits searches per IP address. Choose it with `relay_point_picker.mode: api` or
  `mondial_relay_widget(picker: 'api')`. Labels translated in English and French.
- `mondial_relay_brand_code()` Twig function.
- `MondialRelayErrorMessage::fromException()`: turns a Mondial Relay failure into a message for
  users (`TranslatableInterface`, `ErnadooMondialRelayBundle` domain, English and French): invalid
  phone number, unusable relay point, parcel weight, postal code, country, credentials, Mondial
  Relay unavailable, other rejections. Based on the codes returned by the sandbox; unknown codes
  fall back to a generic message. Requires `symfony/translation-contracts`.
- Mondial Relay API errors, warnings and created shipments are logged in every environment, on the
  `mondial_relay` channel.

### Changed

- Credentials are named after MR Connect: `brand_code` (formerly `customer_id`), `api_login`
  (`login`), `api_password` (`password`), `private_key` (`secret_key`). The former options still
  work but are deprecated, and so is `mondial_relay_customer_id()`. The recipe uses
  `MONDIAL_RELAY_BRAND_CODE`, `MONDIAL_RELAY_API_LOGIN`, `MONDIAL_RELAY_API_PASSWORD` and
  `MONDIAL_RELAY_PRIVATE_KEY`.
- Only the brand code is required: the API user is needed to create labels, the private key to
  search relay points through the API. A missing credential fails the feature that needs it with
  a clear message.
- Requires `ernadoo/mondial-relay` 4.1: label creation did not work with 4.0.x, and 4.1 brings the
  logging and the clear messages about missing credentials.
- Service ids follow the Symfony bundle best practices: they are prefixed with the bundle alias
  (`ernadoo_mondial_relay.client`, `ernadoo_mondial_relay.shipment_client`,
  `ernadoo_mondial_relay.parcel_shop_client`, `ernadoo_mondial_relay.http_client`…) instead of
  class names, and private. Inject `MondialRelayClientInterface` (the autowiring alias, unchanged);
  fetching `MondialRelayClient::class`, `RestShipmentClient::class`, `SoapParcelShopClient::class`
  or `Psr18Client::class` from the container, or the interface with `$container->get()`, no longer
  works. The bundle no longer registers `Symfony\Component\HttpClient\Psr18Client`, which could
  collide with the application's own service.

## [3.2.0] - 2026-09-26

### Added

- Relay point picker: the official Mondial Relay widget as a Stimulus controller
  (`relay-point-picker`), packaged like the Symfony UX components. It is exposed to AssetMapper
  automatically and added to `controllers.json` by Symfony Flex. It loads jQuery (if missing),
  Leaflet and the Mondial Relay plugin on demand, fills optional `id`, `name`, `address` and
  `summary` targets, and dispatches a `select` event describing the relay point.
- `selected` option to highlight and prefill a previously saved relay point.
- Mondial Relay calls appear in the Profiler's Performance timeline (category `mondial_relay`)
  when `symfony/stopwatch` is installed.

### Changed

- `mondial_relay_widget()` now renders the markup of the picker and takes
  `postCode, inputName, country, city, mode, selected`; it requires `symfony/stimulus-bundle`.
  The hidden input receives the full relay point ID expected by
  `ShipmentRequest::$deliveryLocation` (e.g. `FR-066974`). The previous implementation never
  initialised the widget nor filled its input, so no working integration can break.

### Fixed

- Applications without Twig could not boot: the Twig helpers are now registered only when Twig
  is installed, as `suggest` implies.
- The profiling decorator was active in production and kept every call in memory, which grew
  without bound in long-running processes (workers). It is now registered in debug mode only
  and reset between requests.
- The Profiler now records HTTP failures and incomplete responses as errors (not only
  `ApiException`), and shows the actual `sandbox` setting.

### Documentation

- Installation and usage docs are merged into the README; the picker has its own page,
  `docs/relay-point-picker.md`.

### Internal

- Requires `nyholm/psr7` ^1.8.2 (older versions trigger deprecations on PHP 8.4+).
- CI: Symfony 8.0, PHP 8.5, a job with the lowest allowed dependencies and
  `composer validate --strict`; `symfony/asset-mapper` and `symfony/stopwatch` follow the tested version.

## [3.1.0] - 2026-09-26

### Added

- Symfony 8 support (8.0 → 8.2) alongside 6.4 and 7.x. Symfony 8 requires PHP 8.4+.

### Changed

- Requires `ernadoo/mondial-relay` ^4.0: the REST client uses PSR-18, wired to
  `Symfony\Component\HttpClient\Psr18Client` (calls show up in the Profiler's HTTP panel).

### Fixed

- The container could not be built with `ernadoo/mondial-relay` 3.0: the bundle already
  injected a PSR-18 client that only 4.0 accepts. A test now boots a real kernel with the bundle.

## [3.0.0] - 2026-04-19

### Changed (breaking)

Rewrite for Symfony 6.4 / 7.x, on top of `ernadoo/mondial-relay` ^3.0.

- `AbstractBundle`: `configure()` + `loadExtension()`, no more separate Extension/Configuration classes.
- `ProfilingMondialRelayClient`: custom Profiler panel (duration, parameters, errors).
- Flex recipe installed automatically from `ernadoo/recipes`.

Older versions: see the [tags](https://github.com/ErnadoO/mondial-relay-bundle/tags).

[Unreleased]: https://github.com/ErnadoO/mondial-relay-bundle/compare/v3.3.0...master
[3.3.0]: https://github.com/ErnadoO/mondial-relay-bundle/compare/v3.2.0...v3.3.0
[3.2.0]: https://github.com/ErnadoO/mondial-relay-bundle/compare/v3.1.0...v3.2.0
[3.1.0]: https://github.com/ErnadoO/mondial-relay-bundle/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/ErnadoO/mondial-relay-bundle/releases/tag/v3.0.0
