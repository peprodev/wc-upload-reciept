=== PeproDev WooCommerce Receipt Uploader ===
Contributors: peprodev,amirhosseinhpv
Donate link: https://pepro.dev/donate
Tags: functionality, woocommmerce, payment, bacs, transfer money, upload receipt, receipt upload
Requires at least: 5.0
Tested up to: 5.9.2
Stable tag: 1.8.0
Requires PHP: 5.6
WC requires at least: 4.0
WC tested up to: 6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload Receipt for Any Payment method in WooCommerce

== Description ==

## ⚠️ No Configuration Required! Install and Use 😍

### **Upload Receipt for Any Payment method in WooCommerce. Customers will Upload the receipt and Shop Managers will approve/reject it manually.**

- 🔥 Since v.1.5 ~> Multiple Gateways Receipt acceptance
- ✅ Hook for Developers to run actions on receipt upload by user
- ✅ Hook for Developers to run actions on receipt status change
- ✅ Optional: Redirect to an Address on Success Receipt upload
- ✅ Admin can change Receipt acceptant Gateways
- ✅ Admin can change Receipt Upload size limit
- ✅ Admin can change Receipt File types (e.g. to accept PDF ~> add application/pdf)
- 😍 RTL-ready, Persian Translation included by default
- 😍 Fully Compatible with Pepro Ultimate Profile Solutions
- 😍 Fully Compatible with Pepro Ultimate Invoice for WooCommerce
- 😍 Fully Compatible with Pepro Delivery Stages for WooCommerce
- 😍 Fully Compatible with LocoTranslate to have your own translation

---

#### Made with Love in [Pepro Dev. Group](https://pepro.dev/)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.

2. Activate the plugin through the 'Plugins' screen in WordPress

3. Set up WooCommerce BACS Payments

4. Users will upload receipt after transferring money in BACS payment

5. From WooCommerce Orders screen, view/change/delete/approve/reject/comment on transaction receipt


== Frequently Asked Questions ==

= How can I contribute to this plugin? =

You can help us improve our works by committing/requesting your changes to Pepro Dev's GitHub (https://github.com/peprodev/)


== Screenshots ==

1. WooCommerce Orders List and BACS Receipt Status
2. WooCommerce Order Screen and Receipt Settings
3. Customer Orders list and receipt status
4. Customer receipt upload form in order details page
5. Customer receipt uploaded in order details page
6. Customer receipt rejected and admin commented in order details page
7. Customer receipt approved in order details page
8. Options page (added since version 1.4)



== Changelog ==

= 1.8.0 (2022-03-15/1400-12-24) =
- Fixed not showing all gateways
- Fixed only select two gateways

= 1.7.0 (2022-01-19/1400-10-29) =
- Added Option to redirect to an address on success upload
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_prevented`
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_success`
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_failed`
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_completed`

= 1.6.0 (2022-01-15/1400-10-25) =
- Added new Order status, Awaiting Upload
- Added Setting Link to WooCommerce menu
- DEV: Deprecated Hook `woocommerce_customer_purchased_bacs_order`
- DEV: Deprecated Hook `woocommerce_customer_uploaded_receipt`
- DEV: Deprecated Hook `woocommerce_admin_saved_receipt_approval`
- DEV: Deprecated Hook `woocommerce_admin_changed_receipt_approval_status`
- DEV: Added Hook `peprodev_uploadreceipt_order_placed`
- DEV: Added Hook `peprodev_uploadreceipt_save_receipt`
- DEV: Added Hook `peprodev_uploadreceipt_receipt_rejected`
- DEV: Added Hook `peprodev_uploadreceipt_receipt_status_changed`
- DEV: Added Hook `peprodev_uploadreceipt_receipt_attached_note`
- DEV: Added Hook `peprodev_uploadreceipt_customer_uploaded_receipt`

= 1.5.0 (2022-01-11/1400-10-21) =
- 🔥 Multiple Gateways Receipt acceptance
- 😍 New UI at front-end (using toast instead of alert)
- 😍 New UI at back-end (added more tools, changes styles)
- 😍 Show prev. uploaded receipts in Order Metabox

= 1.4.0 =
- Added Settings page: wp-admin/admin.php?page=wc-settings&tab=checkout&section=upload_receipt
- Added Settings page link in plugins meta row
- Added Size Limit Option
- Added File Type Option (can use PDF as receipt, just add application/pdf as Mimes)
- Changed UI in Admin Side, minimal style
- General Bug Fixes and Improvements
- Changed Class name to `Pepro_Upload_Receipt_WooCommerce`
- Changed text-domain to `receipt-upload`
- DEV: added hook: `pepro_upload_receipt_allowed_file_mimes`
- DEV: added hook: `pepro_upload_receipt_max_upload_size`

= 1.3.0 =
- WP-5.6 compatible
- Error handling during upload fix
- text-domain change

= 1.2.1 =
- Fixed Translation and some small errors

= 1.0.0 =
- Initial release

== About Us ==

***PEPRO DEV*** is a premium supplier of quality WordPress plugins, services and support. Join us at [https://pepro.dev/](https://pepro.dev/) and also don't forget to check our [free plugins](http://profiles.wordpress.org/peprodev/), we hope you enjoy them!

== Upgrade Notice ==

= 1.8.0 (2022-03-15/1400-12-24) =
- Fixed not showing all gateways
- Fixed only select two gateways

= 1.7.0 (2022-01-19/1400-10-29) =
- ✅ Added Option: Redirect to an Address on Success Receipt upload
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_prevented`
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_success`
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_failed`
- DEV: added jQuery hook on $(document) ~> `peprodev_receipt_uploader_ajax_completed`
