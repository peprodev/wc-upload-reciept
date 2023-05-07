<?php
/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2022/08/15 21:03:32
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2023/05/07 11:33:35
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('WC_Email')) {
    return;
}

class WC_peproDev_ApprovedReceipt_Admin extends WC_Email {
    /**
     * Create an instance of the class.
     *
     * @access public
     * @return void
     */
    public function __construct() {
        // Email slug we can use to filter other data.
        $this->id             = 'wc_peprodev_admin_receipt_approved';
        $this->title          = __('Approved Receipt to Admin', 'receipt-upload');
        $this->description    = __('An email sent to the Admin when a receipt is approved.', 'receipt-upload');
        $this->heading        = __('Receipt Approved', 'receipt-upload');
        $this->subject        = sprintf(_x('[%s] Receipt Approved', 'receipt-approved-admin-subject', 'receipt-upload'), '{blogname}');

        // Template paths.
        $this->template_base  = PEPRODEV_RECEIPT_UPLOAD_EMAIL_PATH . 'templates/';
        $this->template_html  = 'admin-approved-receipt-template.php';
        $this->template_plain = 'admin-approved-receipt-template-plain.php';

        parent::__construct();
        $this->recipient = $this->get_option('recipient', get_option('admin_email'));
    }

    /**
     * Trigger Function that will send this email to the customer.
     *
     * @access public
     * @return void
     */
    public function trigger($order_id) {
        $this->object = wc_get_order($order_id);
        if (!$this->is_enabled() || !$this->get_recipient()) {
            return;
        }
        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }
    /**
     * Get content html.
     *
     * @access public
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html($this->template_html, array(
            'order'              => $this->object,
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'sent_to_admin'      => false,
            'plain_text'         => false,
            'email'              => $this
        ), '', $this->template_base);
    }

    /**
     * Get content plain.
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html($this->template_plain, array(
            'order'              => $this->object,
            'email_heading'      => $this->get_heading(),
            'additional_content' => $this->get_additional_content(),
            'sent_to_admin'      => false,
            'plain_text'         => true,
            'email'              => $this
        ), '', $this->template_base);
    }

    /**
     * Initialise settings form fields.
     */
    public function init_form_fields() {
        /* translators: %s: list of placeholders */
        $placeholder_text  = sprintf(__('Available placeholders: %s', 'woocommerce'), '<code>' . implode('</code>, <code>', array_keys($this->placeholders)) . '</code>');
        $this->form_fields = array(
            'enabled'            => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable this email notification', 'woocommerce'),
                'default' => 'yes',
            ),
            'recipient'          => array(
                'title'       => __('Recipient(s)', 'woocommerce'),
                'type'        => 'text',
                /* translators: %s: WP admin email */
                'description' => sprintf(__('Enter recipients (comma separated) for this email. Defaults to %s.', 'woocommerce'), '<code>' . esc_attr(get_option('admin_email')) . '</code>'),
                'placeholder' => '',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'subject'            => array(
                'title'       => __('Subject', 'woocommerce'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __('Email heading', 'woocommerce'),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __('Additional content', 'woocommerce'),
                'description' => __('Text to appear below the main email content.', 'woocommerce') . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => __('N/A', 'woocommerce'),
                'type'        => 'textarea',
                'default'     => $this->get_default_additional_content(),
                'desc_tip'    => true,
            ),
            'email_type'         => array(
                'title'       => __('Email type', 'woocommerce'),
                'type'        => 'select',
                'description' => __('Choose which format of email to send.', 'woocommerce'),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }
    
}
