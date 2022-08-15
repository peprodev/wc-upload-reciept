<?php

# @Author: amirhp-com
# @Email:  its@amirhp.com
# @Last modified time: 2022/08/15 16:47:13

if (! defined('ABSPATH')) {
    exit;
}
if (! class_exists('WC_Email')) {
    return;
}

class WC_peproDev_ApprovedReceipt_Admin extends WC_Email
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
        $this->id             = 'wc_peprodev_admin_receipt_approved';
        $this->title          = __('Approved Receipt to Admin', 'receipt-upload');
        $this->description    = __('An email sent to the Admin when a receipt is approved.', 'receipt-upload');
        $this->heading        = __('Receipt Approved', 'receipt-upload');
        $this->subject        = sprintf(_x('[%s] Receipt Approved', 'receipt-approved-admin-subject', 'receipt-upload'), '{blogname}');

        // Template paths.
        $this->template_base  = CUSTOM_WC_EMAIL_PATH . 'templates/';
        $this->template_html  = 'admin-approved-receipt-template.php';
        $this->template_plain = 'admin-approved-receipt-template-plain.php';

        // Action to which we hook onto to send the email.
        add_action('woocommerce_receipt_approved_notification', array( $this, 'trigger' ));

        parent::__construct();

        $this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
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
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => true,
            'email'         => $this
        ), '', $this->template_base);
    }
}
