<?php
/*
Plugin Name: WooCommerce Upload Receipt
Description: Upload Receipt for BACS Payments in WooCommerce. Allow customers to transfer money to your account and upload the receipt (image/pdf) for approval
Contributors: amirhosseinhpv, peprodev
Tags: functionality, woocommmerce, payment, bacs, transfer money, upload receipt, receipt upload
Author: Pepro Dev. Group
Developer: Amirhosseinhpv
Author URI: https://pepro.dev/
Developer URI: https://hpv.im/
Plugin URI: https://pepro.dev/receipt-upload
Version: 1.4.0
Stable tag: 1.4.0
Requires at least: 5.0
Tested up to: 5.7.2
Requires PHP: 5.6
WC requires at least: 4.0
WC tested up to: 5.4.1
Text Domain: receipt-upload
Domain Path: /languages
Copyright: (c) 2020 Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
# @Last modified by:   Amirhosseinhpv
# @Last modified time: 2021/07/09 17:34:31

if (!class_exists("Pepro_Upload_Receipt_WooCommerce")) {
  class Pepro_Upload_Receipt_WooCommerce
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
      $this->td = "receipt-upload";
      self::$_instance = $this;
      $this->db_slug = "wcuploadrcp";
      $this->db_table = $wpdb->prefix . $this->db_slug;
      $this->plugin_dir = plugin_dir_path(__FILE__);
      $this->plugin_url = plugins_url("", __FILE__);
      $this->assets_url = plugins_url("/assets/", __FILE__);
      $this->plugin_basename = plugin_basename(__FILE__);
      $this->url = admin_url("admin.php?page={$this->db_slug}");
      $this->plugin_file = __FILE__;
      $this->version = "1.4.0";
      $this->deactivateURI = null;
      $this->deactivateICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-dismiss" aria-hidden="true"></span> ';
      $this->versionICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-plugins" aria-hidden="true"></span> ';
      $this->authorICON = '<span style="font-size: larger; line-height: 1rem; display: inline; vertical-align: text-top;" class="dashicons dashicons-admin-users" aria-hidden="true"></span> ';
      $this->settingURL = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-admin-settings dashicons-small" aria-hidden="true"></span> ';
      $this->submitionURL = '<span style="display: inline;float: none;padding: 0;" class="dashicons dashicons-images-alt dashicons-small" aria-hidden="true"></span> ';
      $this->title = __("WooCommerce Upload Receipt", $this->td);
      $this->title_w = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title);
      $this->defaultImg = "{$this->assets_url}backend/images/NoImageLarge.png";
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
      add_action( "save_post",                                    array( $this, 'receipt_upload_save' ));
      add_filter( "manage_edit-shop_order_columns",               array( $this, 'column_header'), 20);
      add_action( "manage_shop_order_posts_custom_column",        array( $this, 'column_content'));
      add_filter( "woocommerce_get_sections_checkout",            array( $this, 'add_my_products_section') );
      add_filter( "woocommerce_get_settings_checkout",            array( $this, 'add_my_products_settings'), 10, 2 );
      add_action( "admin_enqueue_scripts",                        array( $this, "admin_enqueue_scripts"));

    }
    public function add_my_products_section( $sections )
    {
    	$sections['upload_receipt'] = __("Upload Receipt", $this->td);
    	return $sections;
    }
    public function add_my_products_settings( $settings, $current_section )
    {
    	if ( 'upload_receipt' === $current_section ) {
        return array(
          array(
            'type'     => 'title',
            'id'       => 'upload_receipt_settings_section',
          ),
          array(
            'id'                => 'peprobacsru_allowed_file_types',
            'title'             => __("Allowed File Types", $this->td),
            'type'              => 'textarea',
            'custom_attributes' => array(
              'dir'  => 'ltr',
              'rows' => '5',
            ),
            'default'           => "image/jpeg".PHP_EOL."image/png".PHP_EOL."image/bmp",
            'desc_tip'          => sprintf(__("Add allowed file MIME-Types, one per each line, e.g. add application/pdf to support PDF file. %s", $this->td), "(<a href='https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types' target='_blank'>".__("Learn more",$this->td)."</a>)"),
          ),
          array(
            'id'                => 'peprobacsru_allowed_file_size',
            'title'             => __("Maximum file size", $this->td),
            'type'              => 'number',
            'custom_attributes' => array(
               'dir'  => 'ltr',
               'type' => 'number',
               'min' => '1',
               'max' => '1024',
              ),
            'default'           => "4",
            'desc_tip'          => __("Change maximum upload file size in Megabytes (MB)", $this->td),
          ),
          array(
            'type'     => 'sectionend',
            'id'       => 'upload_receipt_settings_section',
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
      if ("bacs" == $order->get_payment_method()) {
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
        $status = $this->get_meta('receipt_upload_status', $order->get_id());
        $statustxt = $this->get_status($status);
        $src = $this->defaultImg;
        $src_org = false;
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
      } else {
        return false;
      }
    }
    public function get_meta($value, $postID)
    {
      $field = get_post_meta($postID, $value, true);
      if (! empty($field)) {
        return is_array($field) ? stripslashes_deep($field) : stripslashes(wp_kses_decode_entities($field));
      } else {
        return false;
      }
    }
    public function receipt_upload_add_meta_box()
    {
      add_meta_box(
        'receipt_upload-receipt-upload',
        __('Upload Receipt', $this->td),
        array($this, 'receipt_upload_html'),
        'shop_order',
        'side',
        'high'
      );
    }
    public function receipt_upload_html($post)
    {
      wp_nonce_field('_receipt_upload_nonce', 'receipt_upload_nonce');
      wp_enqueue_media(); add_thickbox();
      wp_enqueue_style("wc-orders.css", "{$this->assets_url}/backend/css/wc-orders.css");
      wp_enqueue_script("wc-orders.js", "{$this->assets_url}/backend/js/wc-orders.js", array("jquery"), current_time("timestamp"));
      $src = $this->defaultImg;
      $uploaded_id = $this->receipt_upload_get_meta('receipt_uplaoded_attachment_id');
      if ($uploaded_id) {
        $src = wp_get_attachment_image_src($uploaded_id, 'full');
        $src = $src ? $src[0] : $this->defaultImg;
      }
      ?>
        <div style="display: flex;flex-direction: column;width: 100%;">
          <img id="change_receipt_attachment_id" title="<?=esc_attr__("Click to change", $this->td); ?>" src="<?=$src?>" style="width: 100%;min-height: 90px;border-radius: 4px;border: 1px solid #ccc;">
          <p class="hidden"><input title="<?=esc_attr__("Receipt Attachment ID", $this->td); ?>" type="text" name="receipt_uplaoded_attachment_id" id="receipt_uplaoded_attachment_id" value="<?=esc_attr( $uploaded_id );?>"></p>
        </div>
        <p>
          <label for="receipt_upload_status"><?php _e('Receipt Approval Status', $this->td); ?></label>
          <select id="receipt_upload_status" name="receipt_upload_status">
            <option value="pending" <?php selected($this->receipt_upload_get_meta('receipt_upload_status'), "pending", 1); ?>><?=__("Pending Approval", $this->td)?></option>
            <option value="approved" <?php selected($this->receipt_upload_get_meta('receipt_upload_status'), "approved", 1); ?>><?=__("Receipt Approved", $this->td)?></option>
            <option value="rejected" <?php selected($this->receipt_upload_get_meta('receipt_upload_status'), "rejected", 1); ?>><?=__("Receipt Rejected", $this->td)?></option>
          </select>
        </p>
        <p>
          <label for="receipt_upload_date_uploaded"><?php _e('Receipt Approval Upload Date', $this->td); ?></label>
          <input type="text" dir="ltr" name="receipt_upload_date_uploaded" id="receipt_upload_date_uploaded" value="<?php echo $this->receipt_upload_get_meta('receipt_upload_date_uploaded'); ?>">
        </p>
        <p>
          <label for="receipt_upload_last_change"><?php _e('Last Receipt Approval Status Change Date', $this->td); ?></label>
          <input type="text" dir="ltr" name="receipt_upload_last_change" id="receipt_upload_last_change" value="<?php echo $this->receipt_upload_get_meta('receipt_upload_last_change'); ?>">
        </p>
        <p>
          <label for="receipt_upload_admin_note"><?php _e('Admin Note', $this->td); ?></label>
          <textarea rows="5" name="receipt_upload_admin_note" id="receipt_upload_admin_note"><?php echo $this->receipt_upload_get_meta('receipt_upload_admin_note'); ?></textarea>
        </p>
        <p>
          <a target="_blank" class="button button-secondary" href="<?=esc_attr( admin_url("admin.php?page=wc-settings&tab=checkout&section=upload_receipt") );?>"><?=__("Upload Setting",$this->td);?></a>
        </p>
      <?php
    }
    public function receipt_upload_save($post_id)
    {
      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
      }
      if (! isset($_POST['receipt_upload_nonce']) || ! wp_verify_nonce($_POST['receipt_upload_nonce'], '_receipt_upload_nonce')) {
        return;
      }
      if (! current_user_can('edit_post', $post_id)) {
        return;
      }
      if (isset($_POST['receipt_uplaoded_attachment_id'])) {
        update_post_meta($post_id, 'receipt_uplaoded_attachment_id', sanitize_text_field($_POST['receipt_uplaoded_attachment_id']));
      }
      if (isset($_POST['receipt_upload_date_uploaded'])) {
        update_post_meta($post_id, 'receipt_upload_date_uploaded', sanitize_text_field($_POST['receipt_upload_date_uploaded']));
      }
      do_action("woocommerce_admin_saved_receipt_approval", $post_id, $_POST);
      if (isset($_POST['receipt_upload_status'])) {
        $prev = $this->get_meta("receipt_upload_status", $post_id);
        $new = sanitize_text_field($_POST['receipt_upload_status']);
        if ($new !== $prev) {
          update_post_meta($post_id, 'receipt_upload_last_change', current_time("Y-m-d H:i:s"));
          do_action("woocommerce_admin_changed_receipt_approval_status", $post_id, $prev, $new);
        }
        if ("rejected" == $new) {
          $order = wc_get_order($post_id);
          $order->update_status('receipt-rejected');
        }
        update_post_meta($post_id, 'receipt_upload_status', $new);
      }
      if (isset($_POST['receipt_upload_admin_note'])) {
        update_post_meta($post_id, 'receipt_upload_admin_note', sanitize_text_field($_POST['receipt_upload_admin_note']));
      }
    }
    public function get_status($status)
    {
      switch ($status) {
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
        if ("bacs" == $order->get_payment_method()) {
          $order->update_status('receipt-approval'); // Status without the "wc-" prefix
          do_action("woocommerce_customer_purchased_bacs_order", $order);
        }
      }

      if ("woocommerce_thankyou" !== current_filter() && "bacs" == $order->get_payment_method()) {
        wp_enqueue_style("wc-recipt.css",       "$this->assets_url/frontend/css/wc-recipt.css");
        wp_register_script("upload-receipt.js", "$this->assets_url/frontend/js/upload-receipt.js", array("jquery"));
        wp_localize_script("upload-receipt.js", "_upload_receipt", array(
          "ajax_url" => admin_url("admin-ajax.php"),
          "order_id" => $order->get_id(),
          "max_size" => $this->_allowed_file_size(),
          // translators: ## is file size in MB
          "max_alert"     => _x("File size must be less than ## MB", "js-translate", $this->td),
          "unknown_error" => _x("Unknown Server Error Occured! Try again.", "js-translate", $this->td),
          "select_file"   => _x("Select a file first.", "js-translate", $this->td),
        ));
        wp_enqueue_script("upload-receipt.js");
        echo "<h2 class='woocommerce-order-details__title upload_receipt'>".__("Upload receipt", $this->td)."</h2>";
        ?>
          <table class="woocommerce-table woocommerce-table--upload-receipt upload_receipt" style="width: 100%;background: #f5f5f5;position: relative;">
            <tbody>
              <?php
              $attachment_id = $this->get_meta('receipt_uplaoded_attachment_id', $order->get_id());
              $status = $this->get_meta('receipt_upload_status', $order->get_id());
              $statustxt = $this->get_status($status);
              $url = $this->defaultImg;
              if ($attachment_id) {
                $url = wp_get_attachment_image_src($attachment_id, 'full');
                $url = $url ? $url[0] : "";
              } ?>
              <tr>
                <th scope="row" style="<?=($attachment_id ? "" : "display:none"); ?>"><?=__("Current receipt: ", $this->td); ?></th>
                <td class="receipt-img-preview" style="<?=($attachment_id ? "" : "display:none"); ?>">
                  <?php
                  if ($attachment_id) {
                    echo "<img src='$url' title='$statustxt' class='receipt-preview $status' alt='reciept-img' />";
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
                        <input type="file" id="receipt-file" name="upload" required accept="<?=implode(",", $this->_allowed_file_types_array());?>" style="width: auto;" />
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
        'wc-receipt-approval',
        array(
          'label'                     => __('Awaiting Receipt Approval', $this->td),
          'public'                    => true,
          'exclude_from_search'       => false,
          'show_in_admin_all_list'    => true,
          'show_in_admin_status_list' => true,
          'label_count'               => _n_noop('Awaiting Receipt Approval (%s)', 'Awaiting Receipt Approval (%s)', $this->td)
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
            "{$this->db_slug}-clearunistall" => "no",
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
        'support'       => array(
          'title'       => __('Support', $this->td),
          'description' => __('Support', $this->td),
          'icon'        => 'dashicons-admin-site',
          'target'      => '_blank',
          'url'         => "mailto:support@pepro.dev?subject={$this->title}",
        ),
        'upload_setting'      => array(
          'title'       => __('Upload Setting', $this->td),
          'description' => __('Upload Setting', $this->td),
          'icon'        => 'dashicons-admin-generic',
          'target'      => '_blank',
          'url'         => admin_url("admin.php?page=wc-settings&tab=checkout&section=upload_receipt"),
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
              $status = $this->get_meta('receipt_upload_status', $postOrder);
              $statustxt = $this->get_status($status);
              $_image_src = wp_get_attachment_image_src($attachment_id, 'full');
              $_image_src = $_image_src ? $_image_src[0] : $this->defaultImg;
              $order = wc_get_order($postOrder);
              $order->update_status('receipt-approval');
              $order->add_order_note(sprintf(__("Customer uploaded payment receipt image. %s", $this->td), "<a target='_blank' href='".wp_get_attachment_url($attachment_id)."'><span class='dashicons dashicons-visibility'></span></a>"));
              do_action("woocommerce_customer_uploaded_receipt", $postOrder, $attachment_id);
              wp_send_json_success(
                array(
                  "msg"               => __("Upload completed successfully.", $this->td),
                  "date"              => date_i18n("Y-m-d l H:i:s", $datetime),
                  "status"            => $status,
                  "statustx"          => $statustxt,
                  "url"               => $_image_src,
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
              "mime_type"          => mime_content_type($_FILES['file']["tmp_name"]),
              "filtered_file_type" => $this->_allowed_file_types(mime_content_type($_FILES['file']["tmp_name"])),
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
      if (!is_plugin_active('woocommerce/woocommerce.php') || !function_exists('is_woocommerce') || !class_exists('woocommerce') ) {
        return false;
      } else {
        return true;
      }
    }
    public function read_opt($mc, $def="")
    {
      return get_option($mc) <> "" ? get_option($mc) : $def;
    }
    public function plugin_row_meta($links, $file)
    {
      if ($this->plugin_basename === $file) {
        // unset($links[1]);
        unset($links[2]);
        $icon_attr = array(
          'style' => array(
            'font-size: larger;',
            'line-height: 1rem;',
            'display: inline;',
            'vertical-align: text-top;',
          ),
        );
        foreach ($this->get_meta_links() as $id => $link) {
          $title = (!empty($link['icon'])) ? self::do_icon($link['icon'], $icon_attr) . ' ' . esc_html($link['title']) : esc_html($link['title']);
          $links[ $id ] = '<a href="' . esc_url($link['url']) . '" title="'.esc_attr($link['description']).'" target="'.(empty($link['target']) ? "_blank" : $link['target']).'">' . $title . '</a>';
        }
        $links[0] = $this->versionICON . $links[0];
        $links[1] = $this->authorICON . $links[1];
      }
      return $links;
    }
    public static function do_icon($icon, $attr = array(), $content = '')
    {
      $class = '';
      if (false === strpos($icon, '/') && 0 !== strpos($icon, 'data:') && 0 !== strpos($icon, 'http')) {
        // It's an icon class.
        $class .= ' dashicons ' . $icon;
      } else {
        // It's a Base64 encoded string or file URL.
        $class .= ' vaa-icon-image';
        $attr   = self::merge_attr(
          $attr,
          array(
            'style' => array( 'background-image: url("' . $icon . '") !important' ),
          )
        );
      }

      if (! empty($attr['class'])) {
        $class .= ' ' . (string) $attr['class'];
      }
      $attr['class']       = $class;
      $attr['aria-hidden'] = 'true';

      $attr = self::parse_to_html_attr($attr);
      return '<span ' . $attr . '>' . $content . '</span>';
    }
    public static function parse_to_html_attr($array)
    {
      $str = '';
      if (is_array($array) && ! empty($array)) {
        foreach ($array as $attr => $value) {
          if (is_array($value)) {
            $value = implode(' ', $value);
          }
          $array[ $attr ] = esc_attr($attr) . '="' . esc_attr($value) . '"';
        }
        $str = implode(' ', $array);
      }
      return $str;
    }
  }
  /**
  * load plugin and load textdomain then set a global varibale to access plugin class!
  *
  * @version 1.0.0
  * @since   1.0.0
  * @license https://pepro.dev/license Pepro.dev License
  */
  add_action( "plugins_loaded", function () { global $Pepro_Upload_Receipt; $Pepro_Upload_Receipt = new Pepro_Upload_Receipt_WooCommerce; } );
}
