# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Registers every PDF Invoices & Packing Slips invoice with the AEAT (Verifactu) through
  Huella Cloud, in the background, with retries; never blocks checkout.
- Order mapping: tax grouping by rate, simplified (F2) or full (F1) invoice from the customer's
  NIF, rounding reconciliation, simplified-invoice limit.
- Optional NIF/CIF field for the classic and block checkout, or an existing meta key.
- AEAT QR code with the required legends in the invoice PDF, even before registration.
- Settings page with connection check, environment badge and issuer choice.
- Orders list column, order meta box with the AEAT state and a retry button, and admin notices.
- Order states by signed webhook from Huella Cloud, with polling every 15 minutes as fallback.
- Release workflow: a version tag attaches the plugin ZIP to a GitHub Release.
- Repository setup: licence, contribution and security policies, secret scanning.
