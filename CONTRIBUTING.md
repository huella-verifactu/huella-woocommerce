# Contributing to Huella – Verifactu para WooCommerce

Thanks for helping. Bug reports and pull requests are welcome.

## Before you start

- **Security problems:** do not open a public issue. Follow [SECURITY.md](SECURITY.md).
- **Larger changes:** open an issue first so we can agree on the approach.
- Questions about the AEAT specification itself belong in the core library:
  [huella](https://github.com/huella-verifactu/huella/issues).

## Development

Requirements: PHP 7.4–8.3, Composer, Docker (for `wp-env`). Setup instructions arrive with the
plugin skeleton.

Install the pre-commit hooks once. They run [gitleaks](https://github.com/gitleaks/gitleaks) so a
certificate, key or password can never be committed by accident:

```sh
pipx install pre-commit   # or: uv tool install pre-commit
pre-commit install
```

## Rules

- Money is never a float: format amounts with `number_format($x, 2, '.', '')`.
- Read and write order data only through `WC_Order` methods (HPOS compatibility).
- Escape all output, sanitise all input, check nonces and capabilities on every admin action.
- Never commit certificates, keys, passwords, API keys or real customer data.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/).

## Licence and sign-off

Contributions are accepted under the project's licence, GPL-2.0-or-later (inbound = outbound).
There is no CLA. Instead, every commit must carry a
[Developer Certificate of Origin](https://developercertificate.org/) sign-off:

```sh
git commit -s
```
