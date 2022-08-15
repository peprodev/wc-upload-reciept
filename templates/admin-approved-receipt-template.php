<?php
/**
 * Admin Approved Receipt
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-approved-receipt-template.php
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the webmaster/developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @version 1.9.0
 */
# @Last modified by:   amirhp-com <its@amirhp.com>
# @Last modified time: 2022/08/15 16:59:55

if ( ! defined( 'ABSPATH' ) ) {exit;}
do_action( 'woocommerce_email_header', $email_heading, $email );
echo "<p>" . sprintf( __( 'The order #%d uploaded receipt has been approved.', 'receipt-upload' ), $order->get_order_number() ) . "</p>";
echo do_shortcode( "[receipt-preview email='yes' order_id={$order->get_id()}]");
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_footer', $email );
