<?php
/*
Plugin Name: PeproDev WooCommerce Receipt Uploader
Description: Upload Receipt for Any Payment method in WooCommerce. Customers will Upload the receipt (image/pdf) and Shop Managers will approve/reject it manually
Contributors: amirhosseinhpv, peprodev
Tags: functionality, woocommmerce, payment, transfer money, upload receipt, receipt upload, BACS Payment
Author: Pepro Dev. Group
Developer: Amirhosseinhpv
Author URI: https://pepro.dev/
Developer URI: https://hpv.im/
Plugin URI: https://pepro.dev/receipt-upload
Version: 1.8.0
Stable tag: 1.8.0
Requires at least: 5.0
Tested up to: 5.9.2
Requires PHP: 5.6
WC requires at least: 4.0
WC tested up to: 6.3
Text Domain: receipt-upload
Domain Path: /languages
Copyright: (c) 2022 Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
# @Last modified by:   Amirhosseinhpv
# @Last modified time: 2022/01/19 10:35:41

if (!class_exists("peproDev_UploadReceiptWC")) {
  class peproDev_UploadReceiptWC
  {
    private static $_instance = null;
    public $td;
    public $url;
    public $version;
    public $title;
    public $title_w;
    public $db_slug;
    private $plugin_dir;
    private $plugin_url;
    private $assets_url;
    private $plugin_basename;
    private $plugin_file;
    private $deactivateURI;
    private $deactivateICON;
    private $versionICON;
    private $authorICON;
    private $settingICON;
    private $db_table = null;
    private $manage_links = array();
    private $meta_links = array();
    public function __construct()
    {
      global $wpdb;
      $this->td              = "receipt-upload";
      self::$_instance       = $this;
      $this->db_slug         = "wcuploadrcp";
      $this->db_table        = $wpdb->prefix . $this->db_slug;
      $this->plugin_dir      = plugin_dir_path(__FILE__);
      $this->plugin_url      = plugins_url("", __FILE__);
      $this->assets_url      = plugins_url("/assets/", __FILE__);
      $this->plugin_basename = plugin_basename(__FILE__);
      $this->url             = admin_url("admin.php?page=wc-settings&tab=checkout&section=upload_receipt");
      $this->plugin_file     = __FILE__;
      $this->version         = "1.7.0";
      $this->deactivateURI   = null;
      $this->deactivateICON  = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-dismiss" aria-hidden="true"></span> ';
      $this->versionICON     = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-plugins" aria-hidden="true"></span> ';
      $this->authorICON      = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-users" aria-hidden="true"></span> ';
      $this->settingURL      = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-admin-settings dashicons-small" aria-hidden="true"></span> ';
      $this->submitionURL    = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-images-alt dashicons-small" aria-hidden="true"></span> ';
      $this->title           = __("WooCommerce Upload Receipt", $this->td);
      $this->title_w         = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title);
      $this->defaultImg      = "{$this->assets_url}backend/images/NoImageLarge.png";
      add_filter("wc_order_statuses", array( $this,"add_wc_order_statuses"), 10000, 1);
      add_action("init", array($this, 'init_plugin'));
    }
    public function init_plugin()
    {
      load_plugin_textdomain("receipt-upload",                    false, dirname(plugin_basename(__FILE__))."/languages/");
      $this->add_wc_prebuy_status();
      add_action( "plugin_row_meta",                              array( $this, 'plugin_row_meta' ), 10, 2);
      add_action( "admin_init",                                   array( $this, 'admin_init'));
      add_action( "woocommerce_thankyou",                         array( $this, 'woocommerce_thankyou'), -1);
      add_action( "woocommerce_order_details_before_order_table", array( $this, 'woocommerce_thankyou'), -1000);
      add_action( "wp_ajax_upload-payment-receipt",               array( $this, 'handel_ajax_req'));
      add_action( "wp_ajax_nopriv_upload-payment-receipt",        array( $this, 'handel_ajax_req'));
      add_action( "add_meta_boxes",                               array( $this, 'receipt_upload_add_meta_box' ));
      add_action( "admin_menu",                                   array( $this, "admin_menu"), 1000);
      add_action( "save_post",                                    array( $this, 'receipt_upload_save' ));
      add_filter( "manage_edit-shop_order_columns",               array( $this, 'column_header'), 20);
      add_action( "manage_shop_order_posts_custom_column",        array( $this, 'column_content'));
      add_filter( "woocommerce_get_sections_checkout",            array( $this, 'add_my_products_section') );
      add_filter( "woocommerce_get_settings_checkout",            array( $this, 'add_my_products_settings'), 10, 2 );
      add_action( "admin_enqueue_scripts",                        array( $this, "admin_enqueue_scripts"));
    }
    public function admin_menu()
    {
        add_submenu_page("woocommerce", $this->title, __("Upload Receipt", $this->td), "manage_options", $this->url);
    }
    public function add_my_products_section( $sections )
    {
    	$sections['upload_receipt'] = __("Upload Receipt", $this->td);
    	return $sections;
    }
    public function get_wc_gateways()
    {
      $all_gateways = WC()->payment_gateways->payment_gateways();
      $gateways     = array();
      foreach( $all_gateways as $gateway_id => $gateway )
        $gateways[$gateway_id] = wp_kses_post($gateway->method_title);
      return $gateways;
    }
    public function add_my_products_settings( $settings, $current_section )
    {
    	if ( 'upload_receipt' === $current_section ) {
        return array(
          array(
            'type'              => 'title',
            'id'                => 'upload_receipt_settings_section',
          ),
          array(
            'title'             => __("Payment methods",$this->td),
            'desc'              => __("Select Payment methods you wish to activate receipt uploading feature", $this->td),
            'id'                => 'peprobacsru_allowed_gatewawys',
            'default'           => 'bacs',
            'type'              => 'multiselect',
            'class'             => 'wc-enhanced-select',
            'css'               => 'min-width: 400px;',
            'options'           => $this->get_wc_gateways(),
            'custom_attributes' => array(
              'multiple' => 'multiple',
            ),
          ),
          array(
            'type'              => 'textarea',
            'id'                => 'peprobacsru_allowed_file_types',
            'title'             => __("Allowed File Types", $this->td),
            'desc_tip'          => sprintf(__("Set Allowed file MIME-Types, one per each line, e.g. add application/pdf to support PDF file. %s", $this->td), "(<a href='https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types' target='_blank'>".__("Learn more",$this->td)."</a>)"),
            'default'           => "image/jpeg".PHP_EOL."image/png".PHP_EOL."application/pdf",
            'custom_attributes' => array(
              'dir'             => 'ltr',
              'lang'            => 'en_US',
              'rows'            => '5',
            ),
          ),
          array(
            'type'              => 'number',
            'id'                => 'peprobacsru_allowed_file_size',
            'title'             => __("Maximum file size (MB)", $this->td),
            'desc'              => __("Maximum allowed file size in Megabytes (MB)", $this->td),
            'default'           => "4",
            'custom_attributes' => array(
              'dir'             => 'ltr',
              'lang'            => 'en_US',
              'type'            => 'number',
              'min'             => '1',
              'step'            => '1',
            ),
          ),
          array(
            'type'              => 'text',
            'id'                => 'peprobacsru_redirect_after_upload',
            'title'             => __("Redirect After Upload", $this->td),
            'desc'              => __("Redirect to given URL and successfull upload, leave empty to disable", $this->td),
            'default'           => "",
            'custom_attributes' => array(
              'dir'             => 'ltr',
              'lang'            => 'en_US',
              'type'            => 'url',
            ),
          ),
          array(
            'type'              => 'sectionend',
            'id'                => 'upload_receipt_settings_section',
          ),
        );
    	}
      return $settings;
    }
    public function admin_enqueue_scripts($hook)
    {
        if (isset($_GET["page"]) && "wc-settings" == $_GET["page"] && isset($_GET["section"]) && "upload_receipt" == $_GET["section"]){
          $uid = uniqid($this->td);
          wp_register_style($uid, false); wp_enqueue_style($uid);
          wp_add_inline_style($uid, "#tiptip_content a{color: skyblue;}");
        }
        $uid = uniqid($this->td);
        wp_register_style($uid, false); wp_enqueue_style($uid);
        wp_add_inline_style($uid, ".wcuploadrcp.column-wcuploadrcp > * {border-radius: 2px;}");
    }
    public function column_header($columns)
    {
      $new_columns = array();
      foreach ($columns as $column_name => $column_info) {
        $new_columns[ $column_name ] = $column_info;
        if ('order_status' === $column_name) {
          $new_columns['wcuploadrcp'] = __('Payment Receipt Approval', $this->td);
        }
      }
      return $new_columns;
    }
    public function column_content($column)
    {
      global $post;
      if ('wcuploadrcp' !== $column) {
        return ;
      }
      $order = wc_get_order($post->ID);
      if ($this->is_payment_methode_allowed($order->get_payment_method())) {
        echo '
        <style>
        .receipt-preview.approved {
          box-shadow: 0 0 0 3px green;
          width: 64px;
        }

        .receipt-preview.pending {
          box-shadow: 0 0 0 3px orange;
          width: 64px;
        }

        .receipt-preview.rejected {
          box-shadow: 0 0 0 3px red;
          width: 64px;
        }
        </style>
        ';
        $attachment_id = $this->get_meta('receipt_uplaoded_attachment_id', $order->get_id());
        $status        = $this->get_meta('receipt_upload_status', $order->get_id());
        $statustxt     = $this->get_status($status);
        $src           = $this->defaultImg;
        $src_org       = false;
        if ($attachment_id) {
          $src_org = wp_get_attachment_image_src($this->receipt_upload_get_meta('receipt_uplaoded_attachment_id'));
          $src = $src_org ? $src_org[0] : $this->defaultImg;
        }
        if ($src_org) {
          echo "<img src='$src' class='receipt-preview $status' alt='$statustxt' title='$statustxt' />";
        } else {
          echo "<span style='box-shadow: 0 0 0 3px #009fff;text-align: center;padding: 0.5rem;'>".__("Awaiting Upload", $this->td)."</span>";
        }
      } else {
        echo $order->get_payment_method_title();
      }
    }
    public function _allowed_file_types($file_mime)
    {
      $whitelisted_mimes = get_option( "peprobacsru_allowed_file_types", "image/jpeg".PHP_EOL."image/png".PHP_EOL."image/bmp");
      $whitelisted_mimes = array_map("trim", explode("\n", $whitelisted_mimes));
      $allowed = in_array($file_mime, $whitelisted_mimes);
      return apply_filters( "pepro_upload_receipt_allowed_file_mimes", $allowed);
    }
    public function _allowed_file_types_array()
    {
      $mimes = get_option("peprobacsru_allowed_file_types", "image/jpeg".PHP_EOL."image/png".PHP_EOL."image/bmp");
      return array_map("trim", explode("\n", $mimes));
    }
    public function is_payment_methode_allowed($methode)
    {
      $gatewawys = (array) get_option("peprobacsru_allowed_gatewawys", "");
      foreach ($gatewawys as $key => $value) { if ($value == $methode) return true; }
      return false;
    }
    public function _allowed_file_size()
    {
      $size = get_option( "peprobacsru_allowed_file_size", 4);
      return apply_filters( "pepro_upload_receipt_max_upload_size", $size);
    }
    public function receipt_upload_get_meta($value)
    {
      global $post;
      $field = get_post_meta($post->ID, $value, true);
      if (! empty($field)) {
        return is_array($field) ? stripslashes_deep($field) : stripslashes(wp_kses_decode_entities($field));
      }
      else {
        return false;
      }
    }
    public function get_meta($value, $postID)
    {
      $field = get_post_meta($postID, $value, true);
      if (! empty($field)) {
        return is_array($field) ? stripslashes_deep($field) : stripslashes(wp_kses_decode_entities($field));
      }
      else {
        return false;
      }
    }
    public function receipt_upload_add_meta_box()
    {
      add_meta_box( 'receipt_upload-receipt-upload', __('Upload Receipt', $this->td), array($this, 'receipt_upload_html'), 'shop_order', 'side', 'high' );
    }
    public function receipt_upload_html($post)
    {
      wp_nonce_field('_receipt_upload_nonce', 'receipt_upload_nonce');
      wp_enqueue_media(); add_thickbox();
      wp_enqueue_style("wc-orders.css", "{$this->assets_url}/backend/css/wc-orders.css", array(), current_time("timestamp"));
      wp_enqueue_script("wc-orders.js", "{$this->assets_url}/backend/js/wc-orders.js", array("jquery"), current_time("timestamp"));
      $src = $this->defaultImg;
      $uploaded_id = $this->receipt_upload_get_meta('receipt_uplaoded_attachment_id');
      if ($uploaded_id) {
        $src = wp_get_attachment_image_src($uploaded_id, 'full');
        $src = $src ? $src[0] : $this->defaultImg;
      }
      ?>
        <div style="display: flex;flex-direction: column;width: 100%;">
          <img data-def="<?=$this->defaultImg;?>" id="change_receipt_attachment_id" title="<?=esc_attr__("Click to change", $this->td); ?>" src="<?=$src?>" style="width: 100%;min-height: 90px;border-radius: 4px;border: 1px solid #ccc;">
          <p class="hidden"><input title="<?=esc_attr__("Receipt Attachment ID", $this->td); ?>" type="text" name="receipt_uplaoded_attachment_id" id="receipt_uplaoded_attachment_id" value="<?=esc_attr( $uploaded_id );?>"></p>
        </div>
        <p>
          <span><?php _e('Uploaded at:', $this->td); ?> <date><?=$this->receipt_upload_get_meta('receipt_upload_date_uploaded');?></date></span>
        </p>
        <p>
          <a href="#" class="button button-secondary widebutton changefile"><span style="margin: 4px;" class="dashicons dashicons-format-image"></span> <?=esc_attr__("Change Receipt Image",$this->td);?></a>
          <a href="#" class="button button-secondary widebutton removefile"><span style="margin: 4px;" class="dashicons dashicons-editor-unlink"></span> <?=esc_attr__("Unlink Receipt Image",$this->td);?></a>
          <a href="#" class="button button-secondary widebutton changedate" id="receipt_upload_date_btn"><span style="margin: 4px;" class="dashicons dashicons-calendar-alt"></span> <?=esc_attr__("Change Upload Date",$this->td);?></a>
        </p>
        <p>
          <input type="text" dir="ltr" style="display: none;" autocomplete="off" name="receipt_upload_date_uploaded" id="receipt_upload_date_uploaded" value="<?php echo $this->receipt_upload_get_meta('receipt_upload_date_uploaded'); ?>">
        </p>
        <p>
          <label for="receipt_upload_status"><?php _e('Receipt Approval Status', $this->td); ?></label>
          <select autocomplete="off" id="receipt_upload_status" name="receipt_upload_status">
            <option value="upload" <?php selected($this->receipt_upload_get_meta('receipt_upload_status'), "upload", 1); ?>><?=__("Awaiting Upload", $this->td)?></option>
            <option value="pending" <?php selected($this->receipt_upload_get_meta('receipt_upload_status'), "pending", 1); ?>><?=__("Pending", $this->td)?></option>
            <option value="approved" <?php selected($this->receipt_upload_get_meta('receipt_upload_status'), "approved", 1); ?>><?=__("Approved", $this->td)?></option>
            <option value="rejected" <?php selected($this->receipt_upload_get_meta('receipt_upload_status'), "rejected", 1); ?>><?=__("Rejected", $this->td)?></option>
          </select>
        </p>
        <p>
          <label for="receipt_upload_admin_note"><?php _e('Admin Note', $this->td); ?></label>
          <textarea rows="5" autocomplete="off" name="receipt_upload_admin_note" id="receipt_upload_admin_note"><?php echo $this->receipt_upload_get_meta('receipt_upload_admin_note'); ?></textarea>
        </p>
        <?php
        $allprev = (array) get_attached_media("");
        if (!empty($allprev)){
          echo "<hr><p>".__("Previously Uploaded Receipts",$this->td)."</p>";
        }
        ?>
        <div class="prev-items-uploaded">
          <?php
            foreach ($allprev as $attached) {
              $src = wp_get_attachment_image_src($attached->ID, 'thumbnail');
              $src = isset($src[0]) ? $src[0] : $this->defaultImg;
              echo "<div class='prev-uploaded-item'><a href='".admin_url("upload.php?item={$attached->ID}")."' target='_blank'><img src='$src' width='75' /></a></div>";
            }
          ?>
        </div>
        <p>
          <small style="text-align: end;display: block;">
            <a target="_blank" class="text-small" href="<?=esc_attr($this->url);?>"><?=__("Change Upload Receipt Plugin Setting",$this->td);?></a>
          </small>
        </p>
      <?php
    }
    public function receipt_upload_save($post_id)
    {
      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
      if (! isset($_POST['receipt_upload_nonce']) || ! wp_verify_nonce($_POST['receipt_upload_nonce'], '_receipt_upload_nonce')) { return; }
      if (! current_user_can('edit_post', $post_id)) { return; }
      if (isset($_POST['receipt_uplaoded_attachment_id'])) { update_post_meta($post_id, 'receipt_uplaoded_attachment_id', sanitize_text_field($_POST['receipt_uplaoded_attachment_id'])); }
      if (isset($_POST['receipt_upload_date_uploaded'])) { update_post_meta($post_id, 'receipt_upload_date_uploaded', sanitize_text_field($_POST['receipt_upload_date_uploaded'])); }
      $order = wc_get_order($post_id);
      do_action("peprodev_uploadreceipt_save_receipt", $post_id, $order, $_POST);
      if (isset($_POST['receipt_upload_status'])) {
        $prev = $this->get_meta("receipt_upload_status", $post_id);
        $new  = sanitize_text_field($_POST['receipt_upload_status']);
        if ($new !== $prev) {
          update_post_meta($post_id, 'receipt_upload_last_change', current_time("Y-m-d H:i:s"));
          do_action("peprodev_uploadreceipt_receipt_status_changed", $post_id, $order, $prev, $new);
        }
        if ("rejected" == $new) {
          $order->update_status('receipt-rejected');
          do_action("peprodev_uploadreceipt_receipt_rejected", $post_id, $order, $prev, $new);
        }
        if ("upload" == $new && "upload" !== $prev) {
          $order->update_status('receipt-upload');
        }
        if ("pending" == $new && "pending" !== $prev) {
          $order->update_status('receipt-approval');
        }
        update_post_meta($post_id, 'receipt_upload_status', $new);
      }
      if (isset($_POST['receipt_upload_admin_note'])) {
        update_post_meta($post_id, 'receipt_upload_admin_note', sanitize_text_field($_POST['receipt_upload_admin_note']));
        do_action("peprodev_uploadreceipt_receipt_attached_note", $post_id, $order, $prev, $new);
      }
    }
    public function get_status($status)
    {
      switch ($status) {
        case 'upload':
          return __("Awaiting Upload", $this->td);
          break;
        case 'pending':
          return __("Pending Approval", $this->td);
          break;
        case 'approved':
          return __("Receipt Approved", $this->td);
          break;
        case 'rejected':
          return __("Receipt Rejected", $this->td);
          break;
        default:
          return __("Unknown Status", $this->td);
          break;
      }
    }
    public function woocommerce_thankyou($order)
    {
      if (! $order) { return; }
      if ("woocommerce_thankyou" == current_filter()) {
        $order = wc_get_order($order);
        if ($this->is_payment_methode_allowed($order->get_payment_method())) {
          $ran_before = get_post_meta($order->get_id(), "receipt_upload_status", true);
          if ((!$ran_before || empty($ran_before)) && "yes" !== $ran_before) {
            $order->update_status('receipt-upload');
            update_post_meta($order->get_id(), "receipt_upload_status", "upload");
            update_post_meta($order->get_id(), "peprodev_uploadreceipt_action_run_once", "yes");
            do_action("peprodev_uploadreceipt_order_placed", $order);
          }
        }
      }
      if ("woocommerce_thankyou" !== current_filter() && $this->is_payment_methode_allowed($order->get_payment_method())) {
        wp_enqueue_style("wc-recipt.css",       "$this->assets_url/frontend/css/wc-recipt.css", array(), current_time("timestamp"));
        wp_register_script("upload-receipt.js", "$this->assets_url/frontend/js/upload-receipt.js", array("jquery"), current_time("timestamp"));
        wp_localize_script("upload-receipt.js", "_upload_receipt", array(
          "ajax_url"      => admin_url("admin-ajax.php"),
          "order_id"      => $order->get_id(),
          "max_size"      => $this->_allowed_file_size(),
          // translators: ## is file size in MB
          "max_alert"     => _x("Error! File size should be less than ## MB", "js-translate", $this->td),
          "loading"       => _x("Please wait ...", "js-translate", $this->td),
          "select_file"   => _x("Error! You should choose a file first.", "js-translate", $this->td),
          "redirect_url"  => get_option("peprobacsru_redirect_after_upload", ""),
          "unknown_error" => _x("Unknown Server Error Occured! Try again.", "js-translate", $this->td),
        ));
        wp_enqueue_script("upload-receipt.js");
        echo "<h2 class='woocommerce-order-details__title upload_receipt'>".__("Upload receipt", $this->td)."</h2>";
        ?>
          <table class="woocommerce-table woocommerce-table--upload-receipt upload_receipt" style="width: 100%;background: #f5f5f5;position: relative;">
            <tbody>
              <?php
              $attachment_id = $this->get_meta('receipt_uplaoded_attachment_id', $order->get_id());
              $status        = $this->get_meta('receipt_upload_status', $order->get_id());
              $statustxt     = $this->get_status($status);
              $url           = $this->defaultImg;
              if (!empty($attachment_id)) {
                $url = wp_get_attachment_image_src($attachment_id, 'full');
                $url = $url ? $url[0] : "";
              } ?>
              <tr>
                <th scope="row"><?=__("Current receipt: ", $this->td); ?></th>
                <td class="receipt-img-preview">
                  <?php
                  if (!empty($attachment_id)) {
                    echo "<img src='$url' title='$statustxt' class='receipt-preview $status' alt='reciept-img' />";
                  }
                  else{
                    echo "<img src='$this->defaultImg' title='$statustxt' class='receipt-preview $status' alt='reciept-img' />";
                  }
                  echo "<p class='receipt-status $status'>" . $statustxt . "</p>"; ?>
                </td>
              </tr>
              <?php
              if ("approved" != $status && "pending" != $status) {
                ?>
                <tr>
                  <th scope="row"><?=__("Upload Receipt: ", $this->td); ?></th>
                  <td class="receipt-img-upload">
                    <form id="uploadreceiptfileimage" enctype="multipart/form-data"><?php wp_nonce_field($this->db_slug, 'uniqnonce'); ?>
                      <div style="display: inline-block;">
                        <input type="file" id="receipt-file" name="upload" autocomplete="off" required accept="<?=implode(",", $this->_allowed_file_types_array());?>" style="width: auto;" />
                        <button class="start-upload button" type="button"><?=__("Upload Receipt", $this->td); ?></button>
                        <div class="receipt_uploading-loader">
                          <div class="loadingio-spinner-disk-mnv03m2b0h"><div class="ldio-0r9ic9wjpqu"><div><div></div><div></div></div></div></div>
                        </div>
                      </div>
                    </form>
                  </td>
                </tr>
                <?php
              } ?>
            </tbody>
            <tfoot>
              <tr>
                <th scope="row"><?=__("Date Uploaded: ", $this->td); ?></th>
                <td class="receipt-uplaod-date"><span dir="ltr"><?php if ($this->get_meta('receipt_upload_date_uploaded', $order->get_id())) {
                  echo date_i18n("Y-m-d l H:i:s", strtotime($this->get_meta('receipt_upload_date_uploaded', $order->get_id())));
                } ?></span></td>
              </tr>
              <?php
              if ($this->get_meta('receipt_upload_admin_note', $order->get_id()) and ("approved" === $status or "rejected" === $status)) {
                ?>
                <tr>
                  <th scope="row"><?=__("Admin Note: ", $this->td); ?></th>
                  <td class="receipt-admin-note"><span><?=nl2br($this->get_meta('receipt_upload_admin_note', $order->get_id())); ?></span></td>
                </tr>
                <?php
              } ?>
            </tfoot>
          </table>
        <?php
        }
    }
    public function add_wc_prebuy_status()
    {
      register_post_status(
        'wc-receipt-upload',
        array(
          'label'                     => __('Awaiting Upload', $this->td),
          'public'                    => true,
          'exclude_from_search'       => false,
          'show_in_admin_all_list'    => true,
          'show_in_admin_status_list' => true,
          'label_count'               => _n_noop('Awaiting Receipt Upload (%s)', 'Awaiting Receipts Upload (%s)', $this->td)
        )
      );
      register_post_status(
        'wc-receipt-approval',
        array(
          'label'                     => __('Awaiting Approval', $this->td),
          'public'                    => true,
          'exclude_from_search'       => false,
          'show_in_admin_all_list'    => true,
          'show_in_admin_status_list' => true,
          'label_count'               => _n_noop('Awaiting Receipt Approval (%s)', 'Awaiting Receipts Approval (%s)', $this->td)
        )
      );
      register_post_status(
        'wc-receipt-rejected',
        array(
          'label'                     => _x('Receipt Rejected', "pst", $this->td),
          'public'                    => true,
          'exclude_from_search'       => false,
          'show_in_admin_all_list'    => true,
          'show_in_admin_status_list' => true,
          'label_count'               => _n_noop('Receipt Rejected (%s)', 'Receipt Rejected (%s)', $this->td)
        )
      );
    }
    public function add_wc_order_statuses($order_statuses)
    {
      $new_order_statuses = array();
      foreach ($order_statuses as $key => $status) {
        $new_order_statuses[ $key ] = $status;
        if ('wc-pending' === $key) {
          $new_order_statuses['wc-receipt-upload'] = _x('Awaiting Receipt Upload', "pst", $this->td);
          $new_order_statuses['wc-receipt-approval'] = _x('Awaiting Receipt Approval', "pst", $this->td);
          $new_order_statuses['wc-receipt-rejected'] = _x('Receipt Rejected', "pst", $this->td);
        }
      }
      return $new_order_statuses;
    }
    public function get_setting_options()
    {
      return array(
        array(
          "name" => "{$this->db_slug}_general",
          "data" => array(
            "{$this->db_slug}-clearunistall"   => "no",
            "{$this->db_slug}-cleardbunistall" => "no",
          )
        ),
      );
    }
    public function get_meta_links()
    {
      if (!empty($this->meta_links)) {
        return $this->meta_links;
      }
      $this->meta_links = array(
        'upload_setting' => array(
          'title'        => "<strong>" . __('Setting', $this->td) . "</strong>",
          'description'  => __('Upload Setting', $this->td),
          'target'       => '_blank',
          'url'          => $this->url,
        ),
        'support'        => array(
          'title'        => __('Support', $this->td),
          'description'  => __('Support', $this->td),
          'target'       => '_blank',
          'url'          => "mailto:support@pepro.dev?subject={$this->title}",
        ),
      );
      return $this->meta_links;
    }
    public function update_footer_info()
    {
      $f = "pepro_temp_stylesheet.".current_time("timestamp");
      wp_register_style($f, null);
      wp_add_inline_style($f, " #footer-left b a::before { content: ''; background: url('{$this->assets_url}backend/images/peprodev.svg') no-repeat; background-position-x: center; background-position-y: center; background-size: contain; width: 60px; height: 40px; display: inline-block; pointer-events: none; position: absolute; -webkit-margin-before: calc(-60px + 1rem); margin-block-start: calc(-60px + 1rem); -webkit-filter: opacity(0.0);
      filter: opacity(0.0); transition: all 0.3s ease-in-out; }#footer-left b a:hover::before { -webkit-filter: opacity(1.0); filter: opacity(1.0); transition: all 0.3s ease-in-out; }[dir=rtl] #footer-left b a::before {margin-inline-start: calc(30px);}");
      wp_enqueue_style($f);
      add_filter('admin_footer_text', function () { return sprintf(_x("Thanks for using %s products", "footer-copyright", $this->td), "<b><a href='https://pepro.dev/' target='_blank' >".__("Pepro Dev", $this->td)."</a></b>");
        }, 11000);
      add_filter('update_footer', function () { return sprintf(_x("%s — Version %s", "footer-copyright", $this->td), $this->title, $this->version); }, 1100);
    }
    public function handel_ajax_req()
    {
      if (wp_doing_ajax() && $_POST['action'] == "upload-payment-receipt") {
        if (!wp_verify_nonce($_POST["nonce"], $this->db_slug)) {
          wp_send_json_error(array("msg"=>__("Unauthorized Access!", $this->td)));
        }

        // These files need to be included as dependencies when on the front end.
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Check if there's a valid non-zero-sized file
        if (isset($_FILES['file']['size']) && $_FILES['file']['size'] > 0) {
          if ( $this->_allowed_file_types(mime_content_type($_FILES['file']["tmp_name"])) && $_FILES['file']['size'] <= $this->_allowed_file_size() * 1024 * 1024 ) {
            $postOrder = sanitize_text_field($_POST["order"]);
            $attachment_id = media_handle_upload('file', $postOrder);
            $datetime = current_time("Y-m-d H:i:s");
            if (!is_wp_error($attachment_id)) {
              // There was an error uploading the image.
              update_post_meta($postOrder, "receipt_uplaoded_attachment_id", $attachment_id);
              update_post_meta($postOrder, "receipt_upload_date_uploaded", $datetime);
              update_post_meta($postOrder, "receipt_upload_status", "pending");
              $order = wc_get_order($postOrder);
              $status     = $this->get_meta('receipt_upload_status', $postOrder);
              $statustxt  = $this->get_status($status);
              $_image_src = wp_get_attachment_image_src($attachment_id, 'full');
              $_image_src = $_image_src ? $_image_src[0] : $this->defaultImg;
              $order->update_status('receipt-approval');
              $order->add_order_note(sprintf(__("Customer uploaded payment receipt image. %s", $this->td), "<a target='_blank' href='".wp_get_attachment_url($attachment_id)."'><span class='dashicons dashicons-visibility'></span></a>"));
              do_action("peprodev_uploadreceipt_customer_uploaded_receipt", $postOrder, $attachment_id);
              wp_send_json_success(
                array(
                  "msg"      => __("Upload completed successfully.", $this->td),
                  "date"     => date_i18n("Y-m-d l H:i:s", $datetime),
                  "status"   => $status,
                  "statustx" => $statustxt,
                  "url"      => $_image_src,
                )
              );
            }
            else {
              // The image was NOT uploaded successfully!
              wp_send_json_error(array("msg" => $attachment_id->get_error_message(),));
            }
          }
          else {
            // Validation Error
            wp_send_json_error(array(
              "msg"                => __("There was an error uploading your file. Please check file type and size.", $this->td),
              // "mime_type"          => mime_content_type($_FILES['file']["tmp_name"]),
              // "filtered_file_type" => $this->_allowed_file_types(mime_content_type($_FILES['file']["tmp_name"])),
            ));
          }
        }
        else {
          // Check if there's a valid non-zero-sized file FAILED!
          wp_send_json_error(array(
            "msg" => __("There was an error uploading your file.", $this->td),
          ));
        }
        die();
      }
    }
    public function admin_init($hook)
    {
      if (!$this->_wc_activated()) {
        add_action(
          'admin_notices',
          function () {
            echo "<div class=\"notice error\"><p>".sprintf(
              _x('%1$s needs %2$s in order to function', "required-plugin", "$this->td"),
              "<strong>".$this->title."</strong>",
              "<a href='".admin_url("plugin-install.php?s=woocommerce&tab=search&type=term")."' style='text-decoration: none;' target='_blank'><strong>".
              _x("WooCommerce", "required-plugin", "$this->td")."</strong> </a>"
              )."</p></div>";
            }
          );
          include_once ABSPATH . 'wp-admin/includes/plugin.php';
          deactivate_plugins(plugin_basename(__FILE__));
        }
        $Pepro_Upload_Receipt_class_options = $this->get_setting_options();
        foreach ($Pepro_Upload_Receipt_class_options as $sections) {
          foreach ($sections["data"] as $id=>$def) {
            add_option($id, $def);
            register_setting($sections["name"], $id);
          }
        }
      }
    /* common functions */
    public function _wc_activated()
    {
      if (!is_plugin_active('woocommerce/woocommerce.php') || !function_exists('is_woocommerce') || !class_exists('woocommerce') ) { return false; }
      return true;
    }
    public function read_opt($mc, $def="")
    {
      return get_option($mc) <> "" ? get_option($mc) : $def;
    }
    public function plugin_row_meta($links, $file)
    {
      if ($this->plugin_basename === $file) {
        foreach ($this->get_meta_links() as $id => $link) {
          $links[ $id ] = '<a href="' . esc_url($link['url']) . '" title="'.esc_attr($link['description']).'" target="'.(empty($link['target']) ? "_blank" : $link['target']).'">' . $link['title'] . '</a>';
        }
      }
      return $links;
    }
  }
  /**
  * load plugin and load textdomain then set a global varibale to access plugin class!
  *
  * @version 1.0.0
  * @since   1.0.0
  * @license https://pepro.dev/license Pepro.dev License
  */
  add_action( "plugins_loaded", function () { global $Pepro_Upload_Receipt; $Pepro_Upload_Receipt = new peproDev_UploadReceiptWC; } );
}
