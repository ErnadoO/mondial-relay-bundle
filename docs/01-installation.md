# Installation & Configuration

## Install

```bash
composer require ernadoo/mondial-relay-bundle
```

## Register the bundle

With Symfony Flex this is automatic. Without Flex, add to `config/bundles.php`:

```php
Ernadoo\MondialRelayBundle\ErnadooMondialRelayBundle::class => ['all' => true],
```

## Configure

`config/packages/ernadoo_mondial_relay.yaml`:

```yaml
ernadoo_mondial_relay:
    credentials:
        login:       '%env(MR_LOGIN)%'
        password:    '%env(MR_PASSWORD)%'
        customer_id: '%env(MR_CUSTOMER_ID)%'  # 8-char brand ID
        secret_key:  '%env(MR_SECRET_KEY)%'   # SOAP secret key (relay point search)
    sandbox: false
```

`.env`:

```dotenv
MR_LOGIN=your-v2-login
MR_PASSWORD=your-v2-password
MR_CUSTOMER_ID=BDTEST
MR_SECRET_KEY=your-soap-key
```

## Sandbox

Set `sandbox: true` to use `https://connect-api-sandbox.mondialrelay.com/api/shipment`
for label creation. Relay point search always uses the production SOAP endpoint.

```yaml
# config/packages/dev/ernadoo_mondial_relay.yaml
ernadoo_mondial_relay:
    sandbox: true
```
