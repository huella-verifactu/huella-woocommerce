=== Huella – Verifactu para WooCommerce ===
Contributors: TODO-wordpress-org-username
Tags: verifactu, aeat, invoices, woocommerce, spain
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Registers your WooCommerce invoices with the Spanish tax agency (VERI*FACTU) and prints the AEAT QR code on the invoice PDF.

== Description ==

Spain's anti-fraud law requires invoicing software to send every invoice to the Agencia Tributaria (AEAT) as a chained, tamper-evident record: **VERI\*FACTU**. Companies must comply from 1 January 2027, self-employed people (autónomos) from 1 July 2027.

This plugin adds VERI\*FACTU to the shop you already have, on top of **PDF Invoices & Packing Slips for WooCommerce**. You keep your invoice numbers, templates and PDFs. The plugin:

* registers each invoice with the AEAT through the **Huella** service as soon as PDF Invoices & Packing Slips creates it, in the background (checkout is never slowed down);
* prints the **AEAT QR code** ("QR tributario", 35 mm, with the VERI\*FACTU legend) on the invoice PDF, even before the registration reaches the AEAT;
* issues **full invoices (F1)** when the customer gives a valid NIF (or an EU VAT number) in an optional checkout field, and **simplified invoices (F2)** otherwise;
* retries automatically when the service cannot be reached, and shows the state of every order (Pendiente, Enviado, Aceptado, Rechazado…) in the orders list;
* supports the block and classic checkout and High-Performance Order Storage (HPOS).

**A Huella account is required** (test mode is free). The open-source core that builds and hashes the records is available at https://github.com/huella-verifactu/huella.

This plugin is not affiliated with, endorsed by or supported by the Agencia Tributaria.

== External services ==

This plugin connects to **Huella Cloud**, the service that registers your invoices with the AEAT. It is required: without it nothing is registered.

* **What is sent, and when:** when PDF Invoices & Packing Slips creates an invoice, the plugin sends, in the background, the data the AEAT requires for that invoice: your issuer ID, the invoice number and date, the invoice type, the description, the tax lines (rate, base, tax), the total, an order reference (`wc_order_<id>`) and, for full invoices, the customer's name and NIF or EU VAT number. Every 15 minutes it asks the service for the AEAT's answer on recent invoices. The settings page asks for your account and issuers when you open it.
* **What is not sent:** no customer address, email, phone, product names or payment data.
* **Where:** the service URL is set by the `HUELLA_API_URL` constant (default: the Huella service). Huella forwards the records to the AEAT web service.
* Terms of service: TODO-terms-url
* Privacy policy: TODO-privacy-url

The QR code printed on the invoice contains a link to the AEAT's own verification page (prewww2.aeat.es in test mode, www2.agenciatributaria.gob.es in production). The plugin does not call that page; your customer can open it to check the invoice.

== Installation ==

1. Install and activate WooCommerce and PDF Invoices & Packing Slips for WooCommerce, and make sure the invoice document is enabled there.
2. Install and activate this plugin.
3. Create a Huella account, add your company (NIF) and upload its qualified certificate in the Huella dashboard.
4. In WooCommerce → Ajustes → Verifactu, enter your API key (or, better, add `define( 'HUELLA_API_KEY', 'hk_test_...' );` to wp-config.php) and choose the issuer.
5. Place a test order: the invoice gets the QR code and the order shows "Enviado", then "Aceptado".

Start with a test key (`hk_test_`): records go to the AEAT's test environment and have no tax effect. Switch to a live key (`hk_live_`) when you start invoicing for real.

== Frequently Asked Questions ==

= Do I need a digital certificate? =

Yes. The AEAT accepts records only through a qualified certificate of the company (or of its representative), for example the FNMT certificate. You upload it once in the Huella dashboard, where it is stored encrypted.

= Which taxes are supported? =

IVA at 21 %, 10 % and 4 %. Orders with exempt lines, IGIC (Canary Islands), IPSI or the recargo de equivalencia are not registered automatically yet; the order shows why.

= What happens if Huella cannot be reached? =

Nothing changes for your customer. The plugin retries after 1, 2, 5, 10 and 30 minutes and then every hour for 48 hours, and the order shows "Reintentando".

= Can I change an invoice after it was registered? =

No: the AEAT record cannot change. The plugin flags the order if the invoice number or date is edited afterwards; the invoice must be voided and a new one issued.

== Changelog ==

= 0.1.0 =
* First version: background registration of PDF Invoices & Packing Slips invoices, AEAT QR code in the PDF, optional NIF field, settings page, order status column and meta box, automatic retries and status sync.
