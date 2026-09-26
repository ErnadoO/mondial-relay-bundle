# Relay point picker

The bundle ships the official Mondial Relay relay point widget as a
[Stimulus](https://stimulus.hotwired.dev/) controller, packaged like the Symfony UX
components. It loads jQuery (if your page does not expose `window.jQuery`), Leaflet and
the Mondial Relay plugin on demand, only on pages that display a picker.

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
            "relay-point-picker": { "enabled": true, "fetch": "lazy" }
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
    brand: mondial_relay_customer_id(),
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

## `mondial_relay_customer_id()`

Returns the configured customer ID (brand code). Without real credentials, `BDTEST  ` (with two
trailing spaces) is Mondial Relay's public test brand: the widget then shows a demo warning.
