<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2022/08/15 21:03:32
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2023/05/07 11:31:35
 */

if (! defined('ABSPATH')) { exit; }
if (! class_exists('WC_Email')) { return; }

class WC_peproDev_ApprovedReceipt_Customer extends WC_Email
{
	/**
	* Create an instance of the class.
	*
	* @access public
	* @return void
	*/
	public function __construct()
	{
		// Email slug we can use to filter other data.
		$this->customer_email = true;
		$this->id             = 'wc_peprodev_customer_receipt_approved';
		$this->title          = __('Approved Receipt to Customer', 'receipt-upload');
		$this->description    = __('An email sent to the customer when a receipt is approved.', 'receipt-upload');
		$this->heading        = __('Receipt Approved', 'receipt-upload');
		$this->subject        = sprintf(_x('[%s] Receipt Approved', 'receipt-approved-customer-subject', 'receipt-upload'), '{blogname}');

		// // Template paths.
		$this->template_base  = PEPRODEV_RECEIPT_UPLOAD_EMAIL_PATH . 'templates/';
		$this->template_html  = 'customer-approved-receipt-template.php';
		$this->template_plain = 'customer-approved-receipt-template-plain.php';

		// Action to which we hook onto to send the email.
		// add_action('woocommerce_receipt_approved_notification', array( $this, 'trigger' ));

		parent::__construct();
	}

	/**
	* Trigger Function that will send this email to the customer.
	*
	* @access public
	* @return void
	*/
	public function trigger($order_id)
	{
		$this->object = wc_get_order($order_id);
		if (version_compare('3.0.0', WC()->version, '>')) {
			$order_email = $this->object->billing_email;
		} else {
			$order_email = $this->object->get_billing_email();
		}
		$this->recipient = $order_email;

		if (! $this->is_enabled() || ! $this->get_recipient()) { return; }
		$this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
	}
	/**
	* Get content html.
	*
	* @access public
	* @return string
	*/
	public function get_content_html()
	{
		return wc_get_template_html($this->template_html, array(
			'order'         => $this->object,
			'additional_content' => $this->get_additional_content(),
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $this
		), '', $this->template_base);
	}

	/**
	* Get content plain.
	*
	* @return string
	*/
	public function get_content_plain()
	{
		return wc_get_template_html($this->template_plain, array(
			'order'         => $this->object,
			'additional_content' => $this->get_additional_content(),
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true,
			'email'         => $this
		), '', $this->template_base);
	}
}
