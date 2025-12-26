=== Simple Sales Taxes ===
Contributors: perodriguezl
Tags: woocommerce, tax, sales tax, zip code, rapidapi
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight WooCommerce extension that calculates estimated sales tax by US ZIP code using a RapidAPI subscription key.

== Description ==

Simple Sales Taxes dynamically sets the WooCommerce tax rate at checkout based on the customer ZIP code.

Features:
* Uses a RapidAPI endpoint to fetch the estimated combined tax rate.
* Caches ZIP lookups to reduce API calls.
* Optional debug logging via WooCommerce logs.
* Built-in "Test Connection" tool in settings.
* Minimal settings UI (WooCommerce → Settings → Tax → Simple Sales Taxes).

Important: This plugin provides estimated ZIP-based rates. Some jurisdictions require rooftop/address-level accuracy.

== External Services ==

This plugin connects to an external service to retrieve estimated sales tax rates.

Service: RapidAPI / "U.S.A Sales Taxes per Zip Code"
Purpose: Retrieve the estimated combined sales tax rate for a provided ZIP code.
Data sent: Customer ZIP/postcode (first 5 digits) and RapidAPI authentication headers.
When sent: Only when enabled and the customer address country is US.
API Listing: https://rapidapi.com/perodriguezl/api/u-s-a-sales-taxes-per-zip-code

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce → Settings → Tax → Simple Sales Taxes.
4. Enable the plugin and paste your RapidAPI key.
5. Use “Test Connection” to validate credentials.
6. Test checkout with a US ZIP code.

== Frequently Asked Questions ==

= Do I need a RapidAPI key? =
Yes. You must subscribe to the API on RapidAPI and paste your key in the plugin settings.

= Why do I only see a single “Taxes” line? =
Some checkout templates (especially block checkout) show taxes as a single total. You can adjust tax display in WooCommerce settings.

= Does it support non-US countries? =
Not currently. The plugin applies only when the shipping country is US.

== Screenshots ==

1. Settings page under WooCommerce → Settings → Tax → Simple Sales Taxes.
2. Checkout showing the calculated Taxes line item.
3. Test Connection tool output.

== Changelog ==

= 0.2.0 =
* Added Test Connection tool (AJAX) on settings page.
* Added External Services disclosure on settings page.
* Improved error handling and i18n wrappers.

= 0.1.0 =
* Initial release: ZIP-based tax rate lookup via RapidAPI, caching, settings UI.

== Upgrade Notice ==

= 0.2.0 =
Adds Test Connection and improves compliance disclosures.
