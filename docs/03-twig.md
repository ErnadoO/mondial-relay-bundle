# Twig Widget

The bundle provides two Twig functions for integrating the Mondial Relay
relay-point selection widget on your checkout pages.

## `mondial_relay_widget()`

Renders the full widget HTML + JavaScript snippet.

```twig
{{ mondial_relay_widget(
    country: 'FR',
    postCode: order.shippingPostCode,
    mode: '24R'
) }}
```

The widget renders a map and list of nearby relay points. When a customer selects
one, the hidden input `relay_point_id` is populated with the relay point ID
(e.g. `"066974"`). Read it in your form submission to build the `deliveryLocation`
for `ShipmentRequest` (`"FR-066974"`).

**Parameters:**

| Parameter | Default | Description |
|---|---|---|
| `country` | `'FR'` | ISO 2-letter country code |
| `postCode` | `''` | Postal code to center the map |
| `mode` | `'24R'` | Delivery mode (`24R` or `24L`) |

## `mondial_relay_customer_id()`

Returns the configured customer ID. Use it if you prefer to initialize the
Mondial Relay JS widget yourself.

```twig
<script>
    MR_params = {
        Brand: "{{ mondial_relay_customer_id() }}",
        Country: "FR",
        Postcode: "{{ order.shippingPostCode }}",
        // ...
    };
</script>
```
