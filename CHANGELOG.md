# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.0] - 2026-09-26

### Added

- Symfony 8 support (8.0 → 8.2) alongside 6.4 and 7.x. Symfony 8 requires PHP 8.4+.
- Relay point picker: the official Mondial Relay widget as a Stimulus controller
  (`relay-point-picker`), packaged like the Symfony UX components. It is exposed to AssetMapper
  automatically and added to `controllers.json` by Symfony Flex. It loads jQuery (if missing),
  Leaflet and the Mondial Relay plugin on demand, fills optional `id`, `name`, `address` and
  `summary` targets, and dispatches a `select` event describing the relay point.
- `selected` option to highlight and prefill a previously saved relay point.

### Changed

- Requires `ernadoo/mondial-relay` ^4.0: the REST client uses PSR-18, wired to
  `Symfony\Component\HttpClient\Psr18Client` (calls show up in the Profiler's HTTP panel).
- `mondial_relay_widget()` now renders the markup of the picker and takes
  `postCode, inputName, country, city, mode, selected`. The hidden input receives the full
  relay point ID expected by `ShipmentRequest::$deliveryLocation` (e.g. `FR-066974`).
  The previous implementation never initialised the widget nor filled its input.

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

[3.1.0]: https://github.com/ErnadoO/mondial-relay-bundle/compare/v3.0.0...v3.1.0
[3.0.0]: https://github.com/ErnadoO/mondial-relay-bundle/releases/tag/v3.0.0
