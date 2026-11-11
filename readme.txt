=== Demfilz Sync for MYOB ===
Contributors: demfilz
Tags: woocommerce, myob, accounting, invoices, inventory
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.3.7
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync WooCommerce orders, customers and stock to MYOB. Free tier is bring-your-own MYOB developer key; Pro adds one-click connect and automation.

== Description ==

Demfilz Sync for MYOB connects your WooCommerce store to your MYOB accounting file so you stop re-keying sales. It supports both **MYOB Business (Essentials)** and **MYOB AccountRight**.

**Please read first: this free version is bring-your-own MYOB developer key**

The free version connects to MYOB using **your own MYOB developer key** (the API Key and Secret from an app you register in your own MYOB developer account). You paste that key on the Connection tab and connect. Nothing works until you supply your own key - that is by design.

MYOB does not offer free API access: holding a MYOB developer account carries a monthly fee charged by MYOB (currently A$110/month including GST for their "Developer Access" level, at the time of writing). That fee is paid to MYOB, not to us, and we have no control over it.

So the free, bring-your-own-key version is best suited to developers, agencies and merchants who **already hold (or are happy to hold) their own MYOB developer key**.

Do not want to manage your own MYOB developer key? The separate **Pro add-on** includes a managed one-click connection that uses our MYOB developer access instead, so you connect with no developer key and no MYOB API fee of your own. See https://demfilz.com/demfilz-woo-to-myob

**Free features (bring your own MYOB developer key)**

* Connect with **your own MYOB developer key** (your API Key and Secret) - supports MYOB Business and AccountRight
* OAuth connection to your MYOB company file
* Push a WooCommerce order to MYOB as a sale (Invoice / Order / Quote) with a button on the order screen
* Create or match the customer/contact in MYOB automatically (de-duplicated by email)
* Map a default income account and GST tax codes
* Pull current stock-on-hand from MYOB for a product (button on the product screen)
* Sync log of every attempt

**Pro features (A$19.95/month or A$199/year)**

* **Managed one-click MYOB connection** - connect without your own MYOB developer account or MYOB API fee
* Automatic order sync when an order reaches a chosen status
* Bulk / historical order backfill
* Scheduled inventory sync across all products
* Push products to MYOB as items
* Refunds / credit notes to MYOB
* Per-product / per-tax-class tax mapping
* Auto-retry of failed syncs with email alerts
* Multiple stores / company files
* Supplier purchases: when the Demfilz Auto Order plugin emails a supplier a purchase order, post a matching supplier Bill (at cost) to MYOB

Pro is delivered as a separate add-on plugin after subscribing at https://demfilz.com/demfilz-woo-to-myob

== External services ==

This plugin connects to external services. It sends data only when you explicitly connect and sync.

1. **MYOB API** (https://secure.myob.com and https://api.myob.com)
   * Purpose: authenticate with MYOB (OAuth 2.0) at secure.myob.com, then read and write your accounting data at api.myob.com.
   * Data sent: your store's order details (customer name, billing address, email, phone, line items, totals, tax), product SKUs, and your MYOB developer-app credentials/tokens.
   * When: only when you connect your MYOB account, push an order, or pull stock.
   * Terms: https://www.myob.com/au/legal - Privacy: https://www.myob.com/au/privacy

This plugin does not contact any Demfilz server. Pro licensing is handled entirely by the separate Pro add-on plugin.

== Installation ==

1. Install and activate the plugin (WooCommerce must be active).
2. Go to **WooCommerce → MYOB Sync**.
3. Create a developer app in the MYOB developer portal and paste the API Key and Secret, using the redirect URI shown on the Connection tab.
4. Click **Save & Connect to MYOB**, approve access, then choose your company file.
5. Set your default income account and tax codes on the Mapping tab.
6. Open any order and click **Sync to MYOB**.

== Frequently Asked Questions ==

= Do I need my own MYOB developer key for the free version? =
Yes. The free version is bring-your-own-key: you register your own app in your MYOB developer account, then paste its API Key and Secret on the plugin's Connection tab to connect. There is no shared or built-in key in the free version. If you would rather not manage your own key, the Pro add-on connects through our MYOB developer access in one click (no key of your own, no MYOB API fee of your own).

= How do I get a MYOB developer key? =
Sign in to my.myob.com.au, open the Developer tab, register an app, set its redirect URI to the one shown on the plugin's Connection tab, and copy the API Key (client id) and Secret into the plugin. Note MYOB charges for developer access (see below).

= Does it work with MYOB Business and AccountRight? =
Yes. Choose your platform on the Connection tab, then connect with your own MYOB developer key.

= Is the free version time-limited? =
No. Manual order, customer and stock sync are free forever. Pro adds automation and scale.

= Where is my data stored? =
OAuth tokens are stored encrypted in your WordPress database. Order data is only sent to MYOB when you sync.

= Do I need a paid licence to test it? =
The plugin itself is free. However MYOB charges for API access, so the free version needs your own MYOB developer account (currently A$110/month inc GST, paid to MYOB). If you would rather not hold one, the Pro add-on includes a managed connection that uses our MYOB developer access instead.

= Why do I need a MYOB developer account at all? =
MYOB requires every application that talks to its API to be registered as a developer app, and it charges a monthly fee for that access. There is no free tier for the MYOB API. This plugin cannot change or waive that - it is MYOB's policy and pricing.

= What does the Pro add-on include? =
Pro adds the managed one-click MYOB connection (no MYOB developer account needed), automatic order sync on status change, scheduled inventory sync, bulk and historical backfill, refunds as credit notes, per-tax-class mapping and supplier bill sync. A$19.95/month or A$199/year.

= Does it handle gift cards and store credit? =
Yes, with Demfilz Gift Cards and Store Credit for WooCommerce. A gift card is a payment, not a discount, so on the Mapping tab choose a gift card account (a liability account such as "Gift card liability") and a tax code with no tax, such as N-T. Gift cards sold post to that account instead of income. When an order is paid partly by gift card or store credit, the sale keeps every product and shipping line with its GST, and the amount paid by card is a negative line against the gift card account, so the sale total matches what the customer paid at checkout. Until both are chosen, orders that use gift cards are not synced and the log says why.

== Screenshots ==

1. The Connection tab - choose MYOB Business or AccountRight and connect with OAuth.
2. Mapping - pick your default income account and GST tax codes.
3. The MYOB Sync box on an order, with the one-click Sync to MYOB button.
4. The sync log of every push.

== Changelog ==

= 1.3.7 =
* Hardening: the placeholder used to send exact decimal amounts is now letters and digits only. If another plugin changed WordPress's random password generator to add symbols, an amount could have been sent as placeholder text and the sync rejected.

= 1.3.6 =
* Fix: orders from a customer who is not yet in MYOB failed with "SellingDetails is required". New customer cards now carry the tax code and freight tax code from the Mapping tab, as MYOB requires, so the customer is created and the sale syncs.
* Fix: on hosts whose PHP sets serialize_precision to 17 or 100 (common on cPanel), amounts such as 15.76 were sent as 15.7599999999999997868... and MYOB rejected the sale with "CommonRules_DecimalPrecision". Request bodies are now encoded with exact decimals regardless of the host setting.
* Fix: sales are now sent to MYOB tax inclusive, so the MYOB sale total always equals what the customer paid. Before, each line went out before tax and MYOB added GST on top, which overstated the sale by the GST amount whenever WooCommerce did not calculate tax itself (prices that already include GST).
* Fix: syncing an order that is already in MYOB again (for example when it moves from processing to completed) failed with "Terms is required". The update now starts from the sale stored in MYOB, keeping its terms and other details, and is skipped when nothing in the order changed since the last sync.

= 1.3.5 =
* The Sync tab now offers both Pro plans directly: monthly (A$19.95) and yearly (A$199). Each button opens Stripe Checkout for this site, so the licence is issued for the right store.

= 1.3.4 =
* New: gift card and store credit support for Demfilz Gift Cards and Store Credit for WooCommerce. Gift cards sold post to a gift card liability account instead of income, and amounts paid by gift card or store credit post as a negative line against that account, so revenue and GST stay complete and the sale total matches the order total. Choose the account and tax code on the Mapping tab. Developers can report another gift card plugin's payments with the dwtm_gift_card_payments filter.
* A gift card sold below its value (on sale or with a coupon) posts its full value to the gift card account and the difference as a discount on income, so the account always holds what customers can spend.
* Fix: order fees (for example a payment surcharge) are now synced as lines on the income account with the default tax code, so an order with a fee no longer syncs below its order total. Negative (discount-style) fees post as negative lines.

= 1.3.3 =
* Clearer upgrade path: the Sync tab now spells out the three steps to go Pro (subscribe, install the separate Pro add-on, paste your licence key) with a Get Pro button. Also shows the annual price.

= 1.3.2 =
* Housekeeping for the WordPress.org release: tidied text, removed the redundant text-domain loader (translations are loaded automatically), and hardened the uninstall routine. No functional change.

= 1.3.1 =
* Documentation: made it clear up front that MYOB charges for API access, so this free version needs your own paid MYOB developer account. The Connection tab now says so before you begin, and points to the Pro managed connection as the alternative.
* Pro is now also available annually (A$199/year).

= 1.3.0 =
* Fix (important): refreshing the MYOB access token always failed, so syncing stopped working roughly 20 minutes after connecting and asked you to reconnect. Token refresh now works.
* Added extension points so the Pro add-on can offer a one-click managed MYOB connection (no MYOB developer app needed). The free plugin still contacts no Demfilz server.

= 1.2.3 =
* Fix: tax code rates were mis-displayed - a 10% GST code showed as "1%" and a 0% (GST-free) code showed no rate at all.

= 1.2.2 =
* Fix: company file is now captured automatically from the MYOB connection. For API keys created after 12 March 2025 MYOB no longer lists company files over the API, which left the selector empty.
* The Connection tab now shows which company file is in use, explains an empty list instead of showing a blank dropdown, and allows the company file ID to be entered manually.
* OAuth now requests MYOB's granular scopes (the legacy CompanyFile scope is deprecated and stops working for all keys on 1 September 2026) and sends prompt=consent.
* The connect button now reads "Connected" once connected, with a separate "Reconnect" button.
* Corrected the external services disclosure to the host actually used (api.myob.com).

= 1.2.0 =
* Compliance: the free plugin no longer contains any premium or licensing code. All Pro gating, licence handling and supplier-bill sync now live entirely in the separate Pro add-on.
* The free plugin exposes small, generic MYOB API helpers (get/create) that the Pro add-on builds on; no Pro-only endpoints ship in the free plugin.
* No functional change for free users.

= 1.1.0 =
* Pro: post a MYOB supplier Bill (at cost) when the Demfilz Auto Order plugin emails a supplier purchase order.
* Added MYOB Supplier, Bill and expense/cost-of-sales account API support (used by Pro).
* Fix: "Save & Connect to MYOB" now correctly redirects to the MYOB consent screen.
* Pro subscriptions are now validated through the Demfilz billing service, and "Get Pro" links to live checkout.

= 1.0.0 =
* Initial release: OAuth connection (MYOB Business + AccountRight), manual order push, customer sync, manual stock pull, account/GST mapping, sync log, and the Pro licence framework.

== Upgrade Notice ==

= 1.3.7 =
Hardens the exact-decimal amounts added in 1.3.6 against plugins that change the random password generator.

= 1.3.6 =
Important sync fixes: new customers ("SellingDetails is required"), amounts on some hosts ("CommonRules_DecimalPrecision"), re-syncs ("Terms is required"), and sales now go to MYOB tax inclusive so the MYOB total matches the order total. Update recommended.

= 1.3.5 =
Sync tab now links both the monthly and the yearly Pro plan.

= 1.3.4 =
Adds gift card and store credit support. If you use Demfilz Gift Cards and Store Credit, choose a gift card account and tax code on the Mapping tab.

= 1.3.3 =
Clarifies how to upgrade to Pro from inside the plugin. No functional change.

= 1.3.2 =
Housekeeping release for WordPress.org. No action needed.

= 1.3.0 =
Important fix: MYOB token refresh previously failed, which stopped syncing about 20 minutes after connecting. Update recommended for all users.

= 1.2.3 =
Corrects the tax code rates shown on the Mapping tab. Please re-check your selected GST codes after updating.

= 1.2.2 =
Important: fixes an empty company file selector for MYOB API keys created after 12 March 2025, and moves OAuth to MYOB's granular scopes. Reconnect to MYOB after updating.

= 1.2.0 =
Housekeeping release: all premium/licensing code is removed from the free plugin. No action needed. Pro features remain available via the separate Pro add-on.

= 1.1.0 =
Adds supplier-bill sync with Demfilz Auto Order and fixes the connect redirect.

= 1.0.0 =
Initial release.
