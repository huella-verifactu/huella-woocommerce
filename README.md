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
