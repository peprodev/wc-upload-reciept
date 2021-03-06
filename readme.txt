=== WooCommerce Upload Receipt ===
Contributors: peprodev,amirhosseinhpv
Donate link: https://pepro.dev/donate
Tags: functionality, woocommmerce, payment, bacs, transfer money, upload receipt, receipt upload
Requires at least: 5.0
Tested up to: 5.7.2
Stable tag: 1.4.0
Requires PHP: 5.6
WC requires at least: 4.0
WC tested up to: 5.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload Receipt for BACS Payments in WooCommerce. Allow customers to transfer money to your account and upload the receipt (image/pdf) for approval

== Description ==

## No Configuration Required but Options are added since v.1.4 !

### **Upload Receipt for BACS Payments in WooCommerce. Allow customers to transfer money to your bank account, then upload its receipt from order screen and admin can approve/reject it.**

### Made by Developers for Developers

- RTL-ready
- Fully Woo-commerce integration
- Hook for Developers to run actions on receipt upload by user
- Hook for Developers to run actions on receipt approve/reject by admin
- Fully Compatible with Pepro Profile (premium)
- Fully Compatible with Pepro Ultimate Invoice for WooCommerce
- Fully Compatible with Pepro Delivery Stages for WooCommerce
- Admin can change Size limit option
- Admin can change File type option (can use PDF as receipt, just add application/pdf as Mimes)
---

#### Made by love in [Pepro Development Center](https://pepro.dev/).

#### *[Pepro Dev](https://pepro.dev/) is a registered trademark of [Pepro Co](https://pepro.co/).*

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

= 1.4 =

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


= 1.3 =

- WP-5.6 compatible
- Error handling during upload fix
- text-domain change

= 1.2.1 =

- Fixed Translation and some small errors

= 1.0 =

- Initial release

== About Us ==

***PEPRO DEV*** is a premium supplier of quality WordPress plugins, services and support. Join us at [https://pepro.dev/](https://pepro.dev/) and also don't forget to check our [free plugins](http://profiles.wordpress.org/peprodev/), we hope you enjoy them!

== Upgrade Notice ==

= 1.4 =

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

= 1.3 =

- WP-5.6 compatible
- Error handling during upload fix
- text-domain change

= 1.2.1 =

- Fixed Translation and some small errors

= 1.0 =

- Initial release
