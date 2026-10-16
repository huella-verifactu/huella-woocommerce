# Contract run: the plugin against a real Huella Cloud

The e2e suite (`tests/e2e/`) runs against a fake API. This manual run proves that the plugin and
the real Huella Cloud agree:

- the connection panel reads `/v1/me` and `/v1/issuers`;
- the real `POST /v1/invoices` accepts the plugin's payload for a real WP Overnight invoice;
- the cloud registers the same number and total, and its huella is the one stored on the order;
- **the QR URL printed in the PDF is byte-identical to the cloud's `qr_url`** (D7);
- a repeated send returns the same record (idempotency).

It does not reach the AEAT: the worker sends a record only when the issuer has a certificate.

## Steps

You need the private `huella-cloud` repository next to this one, Docker and the wp-env site
(`npx wp-env start`).

```sh
# 1. The cloud, with its API published on 127.0.0.1:8010 (8000 may be taken).
cd ../cloud
printf 'services:\n  api:\n    ports:\n      - "127.0.0.1:8010:8000"\n' > /tmp/api-port.yml
docker compose -f docker-compose.yml -f /tmp/api-port.yml up -d

# 2. An account (prints a test key) and an issuer.
docker compose exec api python -m app.admin create-account "Tienda Staging" staging@example.com
curl -X POST http://127.0.0.1:8010/v1/issuers -H "Authorization: Bearer hk_test_..." \
  -H "Content-Type: application/json" -d '{"nif":"B12345674","legal_name":"TIENDA STAGING SL"}'

# 3. Point the plugin at it (wp-env is a "local" site, so http is allowed), run, point it back.
cd ../woocommerce
npx wp-env run cli wp config set HUELLA_API_URL http://host.docker.internal:8010 --type=constant
npx wp-env run cli wp eval "update_option('huella_verifactu_settings', array('api_key' => 'hk_test_...', 'issuer_id' => 'iss_...'));"
npx wp-env run cli wp eval-file wp-content/plugins/huella-verifactu/tests/contract/against-cloud.php
npx wp-env run cli wp config set HUELLA_API_URL https://huella.fake.test --type=constant
```

First green run: 16 Oct 2026 (WordPress 7.1.3, WooCommerce 11.2.1, PDF Invoices 5.16.4, cloud
`main` with huella 0.2.0).
