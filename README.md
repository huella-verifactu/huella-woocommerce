# Huella – Verifactu para WooCommerce

[![CI](https://github.com/huella-verifactu/huella-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/huella-verifactu/huella-woocommerce/actions/workflows/ci.yml)
[![Licence: GPL-2.0-or-later](https://img.shields.io/badge/licence-GPL--2.0--or--later-blue)](LICENSE)

WordPress plugin that adds **Verifactu** compliance on top of *PDF Invoices & Packing Slips for
WooCommerce*. You keep your invoice numbering, templates and PDFs; the plugin registers each
invoice with the AEAT through the Huella service and prints the AEAT QR code on the PDF.

> **Status: in development, not yet released.** The open-source core it builds on is
> [huella](https://github.com/huella-verifactu/huella).

## Requirements

PHP 7.4–8.3, WordPress 6.4+, WooCommerce 8.9+ (HPOS supported) and
[PDF Invoices & Packing Slips for WooCommerce](https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/).

## Development

```sh
composer install
vendor/bin/phpunit                    # unit tests, no WordPress needed
vendor/bin/phpstan analyse            # level 8, with WordPress and WooCommerce stubs
```

End to end, in a real WordPress with WooCommerce and PDF Invoices (Docker + Node):

```sh
npm ci --ignore-scripts
npx wp-env start                      # http://localhost:8888 (admin / password)
npm run e2e
```

The QR library (`chillerlan/php-qrcode` 5.0.5, the last line that supports PHP 7.4) is copied
into `vendor-prefixed/` under the `Huella\Verifactu\Vendor\` namespace with
[Strauss](https://github.com/BrianHenryIE/strauss), so it never clashes with another plugin's
copy. `vendor-prefixed/` is committed; after changing that dependency, regenerate it with
Strauss 0.30.0 (it needs PHP 8, so on Windows run it in a container):

```sh
curl -fsSL -o bin/strauss.phar https://github.com/BrianHenryIE/strauss/releases/download/0.30.0/strauss.phar
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli php bin/strauss.phar
```

`tests/fixtures/nif_cases.json` and `qr_cases.json` are copies of the
[huella core](https://github.com/huella-verifactu/huella) fixtures: the NIF checks and the QR
URL must give exactly the same results in PHP and Python. CI compares them with the pinned core
release (`CORE_REF` in `.github/workflows/ci.yml`); change them in core first.

## Disclaimer

This project is **not affiliated with, endorsed by or supported by the Agencia Tributaria
(AEAT)**. The software is provided "as is", without warranty of any kind (see the licence).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Report security problems privately as described in
[SECURITY.md](SECURITY.md).

## Licence

Copyright 2026 Samuil Vinitsiy Petrov. Licensed under the
[GNU General Public License v2.0 or later](LICENSE).
