# Relay point picker

The bundle ships two relay point pickers as [Stimulus](https://stimulus.hotwired.dev/) controllers,
packaged like the Symfony UX components:

- **`widget`** (default): the official Mondial Relay widget. It loads jQuery (if your page does not
  expose `window.jQuery`), Leaflet and the Mondial Relay plugin on demand, and only needs the brand code.
- **`api`**: a list and a Leaflet map rendered by the bundle from the relay point search API
  (see [below](#the-api-picker)). It needs the private key and the bundle routes.

Both load their scripts only on pages that display a picker.

## Installation

The picker is optional and needs `symfony/stimulus-bundle` (the rest of the bundle does not).
Without it, `mondial_relay_widget()` renders an empty block.

```bash
composer require symfony/stimulus-bundle
```

With **AssetMapper**, the controller is exposed automatically and Symfony Flex adds it to
`assets/controllers.json`. With **Webpack Encore**, Flex adds
`"@ernadoo/mondial-relay-bundle": "file:vendor/ernadoo/mondial-relay-bundle/assets"`
to your `package.json`: run `npm install --force` then rebuild your assets.

If Flex is not used, add the controller to `assets/controllers.json` yourself:

```json
{
    "controllers": {
        "@ernadoo/mondial-relay-bundle": {
            "relay-point-picker": { "enabled": true, "fetch": "lazy" },
            "relay-point-api-picker": {
                "enabled": true,
                "fetch": "lazy",
                "autoimport": { "@ernadoo/mondial-relay-bundle/dist/relay_point_api_picker.css": true }
            }
        }
    }
}
```

## `mondial_relay_widget()`

Renders the picker: a map, the list of nearby relay points and a hidden input that receives
the selected relay point ID, ready for `ShipmentRequest::$deliveryLocation` (e.g. `"FR-066974"`).

```twig
<form method="post">
    {{ mondial_relay_widget(postCode: order.shippingPostCode, inputName: 'relay_point_id') }}
    <button>Ship here</button>
</form>
```

| Parameter | Default | Description |
|---|---|---|
| `postCode` | `''` | Postal code to center the search on |
| `inputName` | `'relay_point_id'` | Name of the hidden input |
| `country` | `'FR'` | ISO 2-letter country code |
| `city` | `''` | City to center the search on |
| `mode` | `'24R'` | Delivery mode (`24R` relay point, `24L` locker…) |
| `selected` | `''` | ID of a relay point to highlight and prefill, e.g. the saved one (`'FR-066974'`) |
| `picker` | `relay_point_picker.mode` | `'widget'` (official Mondial Relay widget) or `'api'` (list + Leaflet map, see below) |

### Showing the saved relay point

Pass the saved ID as `selected`: the widget highlights it in the list and on the map (Mondial Relay
`AutoSelect` option), and the hidden input is prefilled so the form can be resubmitted unchanged.
The relay point is only highlighted if it is part of the results, so center the search on its
postal code:

```twig
{{ mondial_relay_widget(postCode: customer.relayPointPostCode, selected: customer.relayPointId) }}
```

## Using the controller directly

To store more than the ID, put the controller on your own markup with `stimulus_controller()`
and mark the fields to fill with `stimulus_target()`. Every target is optional: `map` (defaults
to the controller element), `id`, `name`, `address` and `summary` (text content).

```twig
{% set picker = 'ernadoo/mondial-relay-bundle/relay-point-picker' %}

<div {{ stimulus_controller(picker, {
    brand: mondial_relay_brand_code(),
    postCode: app.user.postCode,
    city: app.user.city,
    selected: app.user.relayPointId,
}) }}>
    <div {{ stimulus_target(picker, 'map') }}></div>
    <p>Selected: <span {{ stimulus_target(picker, 'summary') }}>—</span></p>
    <input type="hidden" name="relay_point[id]" {{ stimulus_target(picker, 'id') }}>
    <input type="hidden" name="relay_point[name]" {{ stimulus_target(picker, 'name') }}>
    <input type="hidden" name="relay_point[address]" {{ stimulus_target(picker, 'address') }}>
</div>
```

On selection, the controller also dispatches an
`ernadoo--mondial-relay-bundle--relay-point-picker:select` event whose `detail` describes the
relay point:

```js
{ id: 'FR-066974', number: '066974', name: 'TABAC DU PORT', address: '2 QUAI DE L\'ODET, 29950 BENODET',
  postCode: '29950', city: 'BENODET', country: 'FR' }
```

Mondial Relay data comes with doubled apostrophes (`D''ACTIVITE`): the controller cleans them.

## The `api` picker

A list and a Leaflet map, rendered by the bundle from the relay point search API: no jQuery and no
Mondial Relay script in your pages, and your own look and feel. The private key stays on the server.

1. Configure the private key (`MONDIAL_RELAY_PRIVATE_KEY`, see the README).
2. Import the routes of its search endpoint:

   ```yaml
   # config/routes/ernadoo_mondial_relay.yaml
   ernadoo_mondial_relay:
       resource: '@ErnadooMondialRelayBundle/config/routes.php'
       prefix: /mondial-relay
   ```

3. Choose it for the whole application (`relay_point_picker.mode: api`) or per call:

   ```twig
   {{ mondial_relay_widget(postCode: customer.postCode, selected: customer.relayPointId, picker: 'api') }}
   ```

The endpoint (`GET /mondial-relay/relay-points?postCode=29950&country=FR&mode=24R`) caches each search
(`relay_point_picker.cache_ttl`, 3600 seconds by default) and, when `symfony/rate-limiter` is installed,
allows `relay_point_picker.rate_limit` searches per minute and per IP address (30 by default; 0
disables it). Mondial Relay errors are logged, never shown to visitors. Protect the route with your
own access rules if needed (e.g. logged-in users only).

The picker offers a postal code search, the list of relay points (name, address, distance, opening
hours) and the map; choosing a point in the list or on the map selects it. It uses the same targets
and the same `select` event as the widget, with `latitude`, `longitude`, `distanceKm`, `openingHours`
and `pictureUrl` in addition. To use the controller directly:

```twig
{% set picker = 'ernadoo/mondial-relay-bundle/relay-point-api-picker' %}

<div {{ stimulus_controller(picker, {
    url: path('ernadoo_mondial_relay_relay_points'),
    postCode: app.user.postCode,
    selected: app.user.relayPointId,
    labels: mondial_relay_picker_labels(),
}) }}>
    <div {{ stimulus_target(picker, 'panel') }}></div>
    <input type="hidden" name="relay_point[id]" {{ stimulus_target(picker, 'id') }}>
    <input type="hidden" name="relay_point[name]" {{ stimulus_target(picker, 'name') }}>
</div>
```

**Labels** are translated with `symfony/translation` (domain `ErnadooMondialRelayBundle`, English and
French provided); override them in your own `translations/ErnadooMondialRelayBundle.<locale>.xlf`.

**Theming**: the stylesheet is imported automatically; override its custom properties, e.g.

```css
.mr-picker {
    --mr-picker-accent: #2b6cb0;
    --mr-picker-selected-bg: #ebf4ff;
    --mr-picker-map-height: 420px;
}
```

## `mondial_relay_brand_code()`

Returns the configured brand code ("code enseigne"), e.g. to configure the widget controller yourself.
`mondial_relay_customer_id()`, its former name, still works but is deprecated.
