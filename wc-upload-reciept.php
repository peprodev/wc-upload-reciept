<?php
/*
Plugin Name: PeproDev WooCommerce Receipt Uploader
Description: Upload Receipt for Any Payment method in WooCommerce. Customers will Upload the receipt (image/pdf) and Shop Managers will approve/reject it manually
Contributors: peprodev, amirhpcom, blackswanlab
Tags: WooCommerce, Upload Receipt, eCommerce solution
Author: Pepro Dev. Group
Developer: amirhp.com
Developer URI: https://amirhp.com
Author URI: https://pepro.dev/
Plugin URI: https://pepro.dev/receipt-upload
Version: 2.6.3
Stable tag: 2.6.3
Requires at least: 5.0
Tested up to: 6.4.2
Requires PHP: 5.6
WC requires at least: 4.0
WC tested up to: 8.5.1
Text Domain: receipt-upload
Domain Path: /languages
Copyright: (c) Pepro Dev. Group, All rights reserved.
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2024/02/05 01:52:13
*/

use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

defined("ABSPATH") or die("<h2>Unauthorized Access!</h2><hr><small>PeproDev WooCommerce Receipt Uploader :: Developed by Pepro Dev. Group (<a href='https://pepro.dev/'>https://pepro.dev/</a>)</small>");

if (!class_exists("peproDev_UploadReceiptWC")) {
  class peproDev_UploadReceiptWC {
    public $td;
    public $url;
    public $version;
    public $title;
    public $title_w;
    public $db_slug;
    private $plugin_dir;
    private $folder_name;
    private $assets_url;
    private $status_order_placed;
    private $status_receipt_awaiting_upload;
    private $status_receipt_awaiting_approval;
    private $status_receipt_rejected;
    private $status_receipt_approved;
    private $html_before;
    private $html_after;
    private $use_secure_link;
    private $defaultImg;
    public function __construct() {
      $this->td                               = "receipt-upload";
      $this->db_slug                          = "wcuploadrcp";
      $this->plugin_dir                       = plugin_dir_path(__FILE__);
      $this->assets_url                       = plugins_url("/assets/", __FILE__);
      $this->url                              = admin_url("admin.php?page=wc-settings&tab=checkout&section=upload_receipt");
      $this->version                          = "2.6.3";
      $this->title                            = __("WooCommerce Upload Receipt", $this->td);
      $this->title_w                          = sprintf(__("%2\$s ver. %1\$s", $this->td), $this->version, $this->title);
      $this->folder_name                      = apply_filters("pepro_upload_receipt_folder_name", "receipt_upload");
      $this->status_order_placed              = get_option("peprobacsru_auto_change_status", "none");
      $this->status_receipt_awaiting_upload   = get_option("peprobacsru_status_on_receipt_awaiting_upload", "none");
      $this->status_receipt_awaiting_approval = get_option("peprobacsru_status_on_receipt_awaiting_approval", "none");
      $this->status_receipt_rejected          = get_option("peprobacsru_status_on_receipt_rejected", "none");
      $this->status_receipt_approved          = get_option("peprobacsru_status_on_receipt_approved", "none");
      $this->html_before                      = get_option("peprobacsru_html_before_form", "");
      $this->html_after                       = get_option("peprobacsru_html_after_form", "");
      $this->use_secure_link                  = "yes" === (string) get_option("peprobacsru_use_secure_link", "no");
      $this->defaultImg                       = "{$this->assets_url}backend/images/NoImageLarge.png";
      define("PEPRODEV_RECEIPT_UPLOAD_EMAIL_PATH", plugin_dir_path(__FILE__));
      add_action("init", array($this, "init_plugin"));
      add_filter("woocommerce_email_classes", array($this, "register_email"), 1, 1);
      add_action("woocommerce_receipt_uploaded_notification", array($this, "trigger_receipt_uploaded_notification"));
      add_action("woocommerce_receipt_approved_notification", array($this, "trigger_receipt_approved_notification"));
      add_action("woocommerce_receipt_rejected_notification", array($this, "trigger_receipt_rejected_notification"));
      add_action("woocommerce_receipt_await_upload_notification", array($this, "trigger_receipt_await_upload_notification"));
      add_filter("wc_order_statuses", array($this, "add_wc_order_statuses"), 10000, 1);
      add_action("plugin_row_meta", array($this, "plugin_row_meta"), 10, 4);
      add_filter("plugin_action_links", array($this, "plugin_action_links"), 10, 2);
      /**
      * add compatibility with new woocommerce hpos
      */
      add_action("before_woocommerce_init", function(){
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
          \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
      });
    }
    public function init_plugin() {
      load_plugin_textdomain("receipt-upload", false, dirname(plugin_basename(__FILE__)) . "/languages/");
      $this->add_wc_prebuy_status();
      add_action("admin_init", array($this, "admin_init"));
      add_action("pre_get_posts", array($this, "media_custom_filter"));
      add_action("admin_enqueue_scripts", array($this, "enqueue_admin_script"));
      add_filter("manage_upload_columns", array($this, "add_column_upload_receipt"));
      add_action("manage_media_custom_column", array($this, "column_upload_receipt"), 10, 2);
      add_action("woocommerce_thankyou", array($this, "woocommerce_thankyou"), -1);
      add_action("woocommerce_order_details_before_order_table", array($this, "order_details_before_order_table"), -1000);
      add_action("add_meta_boxes", array($this, "receipt_upload_add_meta_box"));
      add_action("admin_menu", array($this, "admin_menu"), 1000);

      if (OrderUtil::custom_orders_table_usage_is_enabled()) {
        // HPOS usage is enabled.
        add_filter("manage_woocommerce_page_wc-orders_columns", array($this, "column_header"));
        add_action("manage_woocommerce_page_wc-orders_custom_column", array($this, "column_content"), 20, 2);
      } else {
        // Traditional CPT-based orders are in use.
        add_filter("manage_edit-shop_order_columns", array($this, "column_header"));
        add_action("manage_shop_order_posts_custom_column", array($this, "column_content"), 20, 2);
      }

      add_filter("woocommerce_get_sections_checkout", array($this, "add_wc_section"));
      add_filter("woocommerce_get_settings_checkout", array($this, "add_wc_settings"), 10, 2);
      add_filter("woocommerce_valid_order_statuses_for_payment", array($this, "valid_order_statuses_for_payment"), 10, 2);
      add_action("admin_enqueue_scripts", array($this, "admin_enqueue_scripts"));
      add_shortcode("receipt-preview", array($this, "receipt_preview_shortcode"), 10, 2);
      add_shortcode("receipt-form", array($this, "receipt_form_shortcode"), 10, 2);
      add_action("wp_ajax_upload-payment-receipt", array($this, "handel_ajax_req"));
      add_action("wp_ajax_nopriv_upload-payment-receipt", array($this, "handel_ajax_req"));
      add_action("save_post", array($this, "receipt_upload_save"));

      if (isset($_GET["secure_preview"]) && !empty($_GET["secure_preview"])) {
        $secureWallet = $_GET["secure_preview"];
        $secureWallet = explode(",", $secureWallet);
        $mid          = sqrt($secureWallet[2]);
        $uid          = $secureWallet[0] / $mid;
        $oid          = $secureWallet[1] / $secureWallet[2];
        $_image_src = wp_get_attachment_image_src($mid);
        $order = wc_get_order($oid);
        $error = false;
        if (!$order) $error = 1;
        if (!$_image_src) $error = 2;
        if ($uid != get_current_user_id()) $error = 3;
        if ($uid != $order->get_customer_id()) $error = 4;
        if ($error !== false) die("<h2>Unauthorized Access! (0x001$error)</h2><hr><small>PeproDev WooCommerce Receipt Uploader :: Developed by Pepro Dev. Group (<a href='https://pepro.dev/'>https://pepro.dev/</a>)</small>");
        $_image_src = $_image_src ? $_image_src[0] : $this->defaultImg;
        header("Content-type: image/jpeg");
        $data = file_get_contents($this->attachment_url_to_path($_image_src));
        echo $data;
        exit;
      }
    }
    public function add_column_upload_receipt($columns) {
      $columns['upload_receipt'] = __("Attached Order", $this->td);
      return $columns;
    }
    public function column_upload_receipt($column_name, $attachment_id) {
      if ("upload_receipt" == $column_name) {
        $filesize = filesize(get_attached_file($attachment_id));
        $meta = get_post_meta($attachment_id, "_attached_order", true);
        if (!empty($meta)) {
          echo "<a href='" . add_query_arg(["receipt_attached" => $meta]) . "' title='" . esc_attr__("Filter Receipts uploaded to this Order", $this->td) . "'>#{$meta}</a> | " . size_format($filesize, 2);
        } else {
          echo size_format($filesize, 2);
        }
      }
    }
    public function enqueue_admin_script($hook) {
      if ("upload.php" != $hook) return;
      $url = add_query_arg(["receipt_attached" => "yes",]);
      $title = esc_attr__("Filter Receipts", $this->td);
      wp_register_script("upload_receipt-js", "", ["jquery"]);
      wp_enqueue_script("upload_receipt-js");
      wp_add_inline_script('upload_receipt-js', "(function ($) {
        $(document).ready(function () {
          $('.filter-items .actions').append('<a class=\"button button-secondary\" href=\"$url\">$title</a>');
        });
      })(jQuery);
      ");
    }
    public function media_custom_filter($wp_query_obj) {
      if (isset($_GET['receipt_attached']) && !empty($_GET['receipt_attached'])) {
        if ("yes" == $_GET['receipt_attached']) {
          $meta_query = array(array(
            "key"     => "_attached_order",
            "compare" => "NOT EMPTY",
          ));
        } else {
          $meta_query = array(array(
            "key"     => "_attached_order",
            "value"   => sanitize_text_field($_GET['receipt_attached']),
            "compare" => "=",
          ));
        }
        $wp_query_obj->set("meta_query", $meta_query);
        $wp_query_obj->set("orderby", "meta_value_num");
        $wp_query_obj->set("order", "ASC");
        return $wp_query_obj;
      }
    }
    public function plugin_row_meta($links_array, $plugin_file_name, $plugin_data, $status) {
      if (strpos($plugin_file_name, basename(__FILE__))) {
        $links_array[] = "<a href='mailto:support+ReceiptUploader@pepro.dev?subject=PeproDev WooCommerce Receipt Uploader'>" . __("Support", $this->td) . "</a>";
      }
      return $links_array;
    }
    public function plugin_action_links($actions, $plugin_file) {
      if (plugin_basename(__FILE__) == $plugin_file) {
        $actions["{$this->db_slug}_1"] = "<a href='$this->url'>" . __("Setting", $this->td) . "</a>";
        $actions["{$this->db_slug}_2"] = "<a href='" . admin_url("admin.php?page=wc-settings&tab=email") . "'>" . __("WC Emails", $this->td) . "</a>";
      }
      return $actions;
    }
    public function valid_order_statuses_for_payment($status) {
      $status[] = "receipt-upload";
      $status[] = "receipt-approval";
      $status[] = "receipt-rejected";
      return $status;
    }
    public function attachment_url_to_path($url) {
      $parsed_url = parse_url($url);
      if (empty($parsed_url['path'])) return false;
      $file = ABSPATH . ltrim($parsed_url['path'], '/');
      if (file_exists($file)) return $file;
      return false;
    }
    public function trigger_receipt_uploaded_notification($order_id) {
      global $woocommerce;
      if (function_exists('WC')) {
        $mailer   = WC()->mailer();
        $WC_Email = new WC_Email();
        if (class_exists('WC_peproDev_UploadReceipt_Customer')) {
          (new \WC_peproDev_UploadReceipt_Customer)->trigger($order_id);
        }
        if (class_exists('WC_peproDev_UploadReceipt_Admin')) {
          (new \WC_peproDev_UploadReceipt_Admin)->trigger($order_id);
        }
      }
    }
    public function trigger_receipt_approved_notification($order_id) {
      global $woocommerce;
      if (function_exists('WC')) {
        $mailer   = WC()->mailer();
        $WC_Email = new WC_Email();
        if (class_exists('WC_peproDev_ApprovedReceipt_Customer')) {
          (new \WC_peproDev_ApprovedReceipt_Customer)->trigger($order_id);
        }
        if (class_exists('WC_peproDev_ApprovedReceipt_Admin')) {
          (new \WC_peproDev_ApprovedReceipt_Admin)->trigger($order_id);
        }
      }
    }
    public function trigger_receipt_rejected_notification($order_id) {
      global $woocommerce;
      if (function_exists('WC')) {
        $mailer   = WC()->mailer();
        $WC_Email = new WC_Email();
        if (class_exists('WC_peproDev_RejectedReceipt_Customer')) {
          (new \WC_peproDev_RejectedReceipt_Customer)->trigger($order_id);
        }
        if (class_exists('WC_peproDev_RejectedReceipt_Admin')) {
          (new \WC_peproDev_RejectedReceipt_Admin)->trigger($order_id);
        }
      }
    }
    public function trigger_receipt_await_upload_notification($order_id) {
      global $woocommerce;
      if (function_exists('WC')) {
        $mailer   = WC()->mailer();
        $WC_Email = new WC_Email();
        if (class_exists('WC_peproDev_UploadReceipt_Customer')) {
          (new \WC_peproDev_UploadReceipt_Customer)->trigger($order_id);
        }
        if (class_exists('WC_peproDev_UploadReceipt_Admin')) {
          (new \WC_peproDev_UploadReceipt_Admin)->trigger($order_id);
        }
      }
    }
    public function receipt_preview_shortcode($atts = array(), $content = "") {
      global $post;
      $atts = extract(shortcode_atts(array("order_id" => "", "email" => ""), $atts));
      if (empty($order_id)) $order_id = $post->ID;
      $is_email = "yes" == strtolower($email) ? true : false;
      $order_id = (int) sanitize_text_field($order_id);
      $order = wc_get_order($order_id);
      if (!$order) return sprintf(__("Wrong ORDER_ID, Order #%s not found!", $this->td), $order_id);
      ob_start();
      if ($this->is_payment_method_allowed($order->get_payment_method())) {
        if ($is_email) {
          echo "<br>";
        }
        $attachment_id = $this->get_meta('receipt_uploaded_attachment_id', $order->get_id());
        if (!$attachment_id) {
          $attachment_id = $this->get_meta('receipt_uplaoded_attachment_id', $order->get_id());
        }
        $status        = $this->get_meta('receipt_upload_status', $order->get_id());
        $url           = $this->defaultImg;
        $date_uploaded = $this->get_meta('receipt_upload_date_uploaded', $order->get_id());
        $noteadded     = $this->get_meta('receipt_upload_admin_note', $order->get_id());
        if (!empty($attachment_id)) {
          $url = wp_get_attachment_image_src($attachment_id, 'full');
          $url = $url ? $url[0] : "";
        }
        if (!empty($attachment_id)) {
          echo "<p><img src='" . $this->generate_secure_preview_src($attachment_id, $order, $is_email) . "' class='receipt-preview $status' alt='receipt-img' /></p>";
        } else {
          echo "<p><img src='$this->defaultImg' class='receipt-preview $status' alt='receipt-img' /></p>";
        }
        if ("approved" != $status && "pending" != $status) {
          echo "<p><a href=\"" . $order->get_view_order_url() . "#upload_receipt\" target='_blank'>" . __("View Order Details", $this->td) . "</a></p>";
        }
        if ($date_uploaded && !empty($date_uploaded)) {
          echo "<p>" . __("Date Uploaded: ", $this->td) . "<bdi dir='ltr'>" . date_i18n("Y-m-d l H:i:s", strtotime($date_uploaded)) . "</bdi></p>";
        }
        if ($noteadded and ("approved" === $status or "rejected" === $status)) {
          echo "<p>" . __("Admin Note: ", $this->td) . "<span>" . nl2br($noteadded) . "</span></p>";
        }
        if ($is_email) {
          echo "<br>";
        }
      }
      $htmloutput = ob_get_contents();
      ob_end_clean();
      return $htmloutput;
    }
    public function receipt_form_shortcode($atts = array(), $content = "") {
      global $post, $wp;
      $atts = extract(shortcode_atts(array("order_id" => "", "email" => "",), $atts));
      $is_email = "yes" == strtolower($email) ? true : false;
      if (empty($order_id) && isset($wp->query_vars['order-received'])) {
        $order_id = absint($wp->query_vars['order-received']);
      }
      if (empty($order_id)) $order_id = $post->ID;

      $order_id = (int) sanitize_text_field($order_id);
      $order = wc_get_order($order_id);
      if (!$order) return sprintf(__("Wrong ORDER_ID, Order #%s not found!", $this->td), $order_id);
      ob_start();
      if ($this->is_payment_method_allowed($order->get_payment_method())) {
        ?>
        <div class="peprodev_woocommerce_receipt_uploader shortcode_wrapper">
          <?php
          $attachment_id = $this->get_meta('receipt_uploaded_attachment_id', $order->get_id());
          if (!$attachment_id) {
            $attachment_id = $this->get_meta('receipt_uplaoded_attachment_id', $order->get_id());
          }
          $status        = $this->get_meta('receipt_upload_status', $order->get_id());
          $status_text   = $this->get_status($status);
          wp_enqueue_style("upload-receipt.css",  "$this->assets_url/frontend/css/wc-receipt.css", array(), time());
          wp_register_script("upload-receipt.js", "$this->assets_url/frontend/js/upload-receipt.js", array("jquery"), time());
          wp_localize_script("upload-receipt.js", "_upload_receipt", array(
            "ajax_url"      => admin_url("admin-ajax.php"),
            "order_id"      => $order->get_id(),
            "max_size"      => $this->_allowed_file_size(),
            // translators: ## is file size in MB
            "max_alert"     => _x("Error! File size should be less than ## MB", "js-translate", $this->td),
            "loading"       => _x("Please wait ...", "js-translate", $this->td),
            "precent"       => _x('Please wait, Uploading ## % ...', "js-translate", $this->td),
            "done"          => _x('Uploading Done Successfully', "js-translate", $this->td),
            "select_file"   => _x("Error! You should choose a file first.", "js-translate", $this->td),
            "redirect_url"  => get_option("peprobacsru_redirect_after_upload", ""),
            "unknown_error" => _x("Unknown Server Error Occured! Try again.", "js-translate", $this->td),
          ));
          wp_enqueue_script("upload-receipt.js");
          echo "<h2 class='woocommerce-order-details__title upload_receipt'>" . __("Upload receipt", $this->td) . "</h2>";
          echo $is_email ? "" : do_shortcode($this->html_before);
          ?>
          <table class="woocommerce-table woocommerce-table--upload-receipt upload_receipt" style="width: 100%;background: #f5f5f5;position: relative;">
            <tbody>
              <tr>
                <th scope="row"><?= __("Current receipt: ", $this->td); ?></th>
                <td class="receipt-img-preview">
                  <?php
                  if (!empty($attachment_id)) {
                    echo "<img src='" . $this->generate_secure_preview_src($attachment_id, $order, $is_email) . "' title='$status_text' class='receipt-preview $status' alt='receipt-img' />";
                  } else {
                    echo "<img src='$this->defaultImg' title='$status_text' class='receipt-preview $status' alt='receipt-img' />";
                  }
                  echo "<p class='receipt-status $status'>" . $status_text . "</p>";
                  ?>
                </td>
              </tr>
              <?php
              if ("approved" != $status && "pending" != $status) {
              ?>
                <tr>
                  <th scope="row"><?= __("Upload Receipt: ", $this->td); ?></th>
                  <td class="receipt-img-upload">
                    <form id="uploadreceiptfileimage" enctype="multipart/form-data"><?php wp_nonce_field($this->db_slug, 'uniqnonce'); ?>
                      <div style="display: inline-block;">
                        <input type="file" id="receipt-file" name="upload" autocomplete="off" required accept="<?= implode(",", $this->_allowed_file_types_array()); ?>" style="width: auto;" />
                        <button class="start-upload button" type="button"><?= __("Upload Receipt", $this->td); ?></button>
                      </div>
                    </form>
                  </td>
                </tr>
              <?php
              }
              ?>
            </tbody>
            <tfoot>
              <?php
              $date_uploaded = $this->get_meta('receipt_upload_date_uploaded', $order->get_id());
              ?>
              <tr class="date-uploaded <?= ($date_uploaded && !empty($date_uploaded)) ? "show" : "hide"; ?>">
                <th scope="row"><?= __("Date Uploaded: ", $this->td); ?></th>
                <td class="receipt-upload-date">
                  <?php
                  if ($date_uploaded && !empty($date_uploaded)) {
                  ?>
                    <bdi dir="ltr"><?= date_i18n("Y-m-d l H:i:s", strtotime($date_uploaded)); ?></bdi>
                  <?php
                  }
                  ?>
                </td>
              </tr>
              <?php
              $noteadded = $this->get_meta('receipt_upload_admin_note', $order->get_id());
              if ($noteadded and ("approved" === $status or "rejected" === $status)) {
              ?>
                <tr>
                  <th scope="row"><?= __("Admin Note: ", $this->td); ?></th>
                  <td class="receipt-admin-note"><span><?= nl2br($noteadded); ?></span></td>
                </tr>
              <?php
              }
              ?>
            </tfoot>
          </table>
          <?php
          echo $is_email ? "" : do_shortcode($this->html_after);
          ?>
        </div>
        <?php
      }
      $htmloutput = ob_get_contents();
      ob_end_clean();
      return $htmloutput;
    }
    public function generate_secure_preview_src($id = 0, $order = null, $email = false) {
      $url = $this->defaultImg;
      if ($email || false == $this->use_secure_link) {
        if (!empty($id)) {
          $url = wp_get_attachment_image_src($id, 'full');
          $url = $url ? $url[0] : "";
        }
        return $url;
      }
      if ($order && $order !== false && method_exists($order, "get_customer_id")) {
        $uid = $order->get_customer_id();
        if (!$uid || $uid < 1) $uid = 1;
        $secureWallet = ($uid * $id) . "," . ($order->get_id() * ($id * $id)) . "," . ($id * $id);
        return home_url("?secure_preview=$secureWallet");
      }
    }
    public function admin_menu() {
      add_submenu_page("woocommerce", $this->title, __("Upload Receipt", $this->td), "manage_options", $this->url);
      $v230 = get_option("peprobacsru_allowed_gatewawys", null);
      if ($v230 !== "" && $v230 !== null && !empty($v230)) {
        update_option("peprobacsru_allowed_gateways", $v230);
        delete_option("peprobacsru_allowed_gatewawys");
      }
    }
    public function register_email($emails) {
      require_once "{$this->plugin_dir}/include/class-wc-email-admin-uploaded.php";
      $emails['WC_peproDev_UploadReceipt_Admin'] = new WC_peproDev_UploadReceipt_Admin;

      require_once "{$this->plugin_dir}/include/class-wc-email-admin-approved.php";
      $emails['WC_peproDev_ApprovedReceipt_Admin'] = new WC_peproDev_ApprovedReceipt_Admin;

      require_once "{$this->plugin_dir}/include/class-wc-email-admin-rejected.php";
      $emails['WC_peproDev_RejectedReceipt_Admin'] = new WC_peproDev_RejectedReceipt_Admin;

      require_once "{$this->plugin_dir}/include/class-wc-email-customer-uploaded.php";
      $emails['WC_peproDev_UploadReceipt_Customer'] = new WC_peproDev_UploadReceipt_Customer;

      require_once "{$this->plugin_dir}/include/class-wc-email-customer-approved.php";
      $emails['WC_peproDev_ApprovedReceipt_Customer'] = new WC_peproDev_ApprovedReceipt_Customer;

      require_once "{$this->plugin_dir}/include/class-wc-email-customer-rejected.php";
      $emails['WC_peproDev_RejectedReceipt_Customer'] = new WC_peproDev_RejectedReceipt_Customer;

      return $emails;
    }
    public function add_wc_section($sections) {
      $sections['upload_receipt'] = __("Upload Receipt", $this->td);
      return $sections;
    }
    public function get_wc_gateways() {
      $all_gateways = WC()->payment_gateways->payment_gateways();
      $gateways     = array();
      foreach ($all_gateways as $gateway_id => $gateway)
        $gateways[$gateway_id] = wp_kses_post($gateway->method_title);
      return $gateways;
    }
    public function add_wc_settings($settings, $current_section) {
      if ('upload_receipt' === $current_section) {
        $order_statuses = array("none" => __("Disabled (do nothing)", $this->td),);
        if (function_exists("wc_get_order_statuses")) {
          $standard = wc_get_order_statuses();
          if (is_array($standard)) {
            $order_statuses = array_merge($order_statuses, $standard);
          }
        }
        return array(
          array(
            'type'  => 'title',
            'id'    => 'upload_receipt_settings_section',
            'title' => "",
            'desc'  => "<h3>" . __("PeproDev WooCommerce Receipt Uploader", $this->td) . "</h3>"
          ),
          array(
            'title'             => __("Payment methods", $this->td),
            'desc_tip'          => __("Select Payment methods you wish to activate receipt uploading feature", $this->td),
            'id'                => 'peprobacsru_allowed_gateways',
            'default'           => 'bacs',
            'type'              => 'multiselect',
            'class'             => 'wc-enhanced-select',
            'css'               => 'min-width: 400px;',
            'options'           => $this->get_wc_gateways(),
            'custom_attributes' => array(
              'multiple'        => 'multiple',
            ),
          ),
          array(
            'type'     => 'multiselect',
            'class'    => "wc-enhanced-select",
            'id'       => 'peprobacsru_show_on_statuses',
            'title'    => __("Show on Statuses", $this->td),
            'desc_tip' => __("Select Statues you wish to show Upload Receipt form", $this->td),
            'default'  => array("wc-pending", "wc-processing", "wc-receipt-upload", "wc-receipt-approval", "wc-receipt-rejected"),
            'options'  => $order_statuses,
          ),
          array(
            'type'              => 'textarea',
            'id'                => 'peprobacsru_allowed_file_types',
            'title'             => __("Allowed File MIME-Types", $this->td),
            'desc_tip'          => sprintf(__("Enter file MIME-Types per Each Line, e.g. add application/pdf to support uploading PDF files.<br>%s", $this->td), ""),
            'desc'              => "<a href='https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types' target='_blank'>" . __("Learn more", $this->td) . "</a>",
            'default'           => "image/jpeg" . PHP_EOL . "image/png" . PHP_EOL . "application/pdf",
            'custom_attributes' => array(
              'dir'  => 'ltr',
              'lang' => 'en_US',
              'rows' => '5',
            ),
          ),
          array(
            'type'              => 'number',
            'id'                => 'peprobacsru_allowed_file_size',
            'title'             => __("Maximum file size (MB)", $this->td),
            'desc_tip'          => __("Maximum allowed uploading size in Megabytes (MB)", $this->td),
            'default'           => "4",
            'custom_attributes' => array(
              'dir'  => 'ltr',
              'lang' => 'en_US',
              'type' => 'number',
              'min'  => '1',
              'step' => '1',
            ),
          ),
          array(
            'type'              => 'text',
            'id'                => 'peprobacsru_redirect_after_upload',
            'title'             => __("Redirect After Upload", $this->td),
            'desc_tip'          => __("Redirect to given URL after Successful Upload, leave empty to disable", $this->td),
            'default'           => "",
            'custom_attributes' => array(
              'dir'  => 'ltr',
              'lang' => 'en_US',
              'type' => 'url',
            ),
          ),
          array(
            'type'     => "checkbox",
            'id'       => "peprobacsru_use_secure_link",
            'title'    => __("Use Secure Link", $this->td),
            'desc'     => __("Output uploaded receipt by a secure link (not in emails)", $this->td),
            'desc_tip' => false,
            'default'  => "no",
          ),
          array(
            'type' => 'sectionend',
            'id'   => 'upload_receipt_1',
          ),
          array(
            'type'  => 'title',
            'id'    => 'peprobacsru_heading_1',
            'title' => __("Auto-Change Order Status", $this->td),
          ),

          array(
            'type'     => 'select',
            'id'       => 'peprobacsru_auto_change_status',
            'title'    => __("When Order Placed", $this->td),
            'desc_tip' => __("Auto-Change Order Status After Order Placed (this will overwrite default WooCommerce behavior)", $this->td),
            'default'  => "none",
            'options'  => $order_statuses,
          ),
          array(
            'type'     => 'select',
            'id'       => 'peprobacsru_status_on_receipt_awaiting_upload',
            'title'    => __("On Awaiting Receipt Upload", $this->td),
            'desc_tip' => __("Change Order Status when Order Awaits for Receipt Upload by Customer", $this->td),
            'default'  => "wc-receipt-upload",
            'options'  => $order_statuses,
          ),
          array(
            'type'     => 'select',
            'id'       => 'peprobacsru_status_on_receipt_awaiting_approval',
            'title'    => __("On Awaiting Receipt Approval", $this->td),
            'desc_tip' => __("Change Order Status when Receipt is Uploaded and Pending Approval by Admin", $this->td),
            'default'  => "wc-receipt-approval",
            'options'  => $order_statuses,
          ),
          array(
            'type'     => 'select',
            'id'       => 'peprobacsru_status_on_receipt_rejected',
            'title'    => __("On Receipt Rejected", $this->td),
            'desc_tip' => __("Change Order Status When Admin Rejected Receipt and Did not Approve it", $this->td),
            'default'  => "wc-receipt-rejected",
            'options'  => $order_statuses,
          ),
          array(
            'type'     => 'select',
            'id'       => 'peprobacsru_status_on_receipt_approved',
            'title'    => __("On Receipt Approved", $this->td),
            'desc_tip' => __("Change Order Status When Admin Approved Receipt", $this->td),
            'default'  => "none",
            'options'  => $order_statuses,
          ),
          array(
            'type'              => 'textarea',
            'id'                => 'peprobacsru_html_before_form',
            'title'             => __("Content Before Form (HTML)", $this->td),
            'desc_tip'          => __("Enter content to be shown Before Form (Accepts HTML and Shortcodes)", $this->td),
            'default'           => "",
            'custom_attributes' => array(
              "dir"  => "ltr",
              "lang" => "en_US",
              "rows" => "5",
            ),
            "css" => "width: 100%; min-height: 250px; font-family: Monospace;",
          ),
          array(
            'type'              => 'textarea',
            'id'                => 'peprobacsru_html_after_form',
            'title'             => __("Content After Form (HTML)", $this->td),
            'desc_tip'          => __("Enter content to be shown After Form (Accepts HTML and Shortcodes)", $this->td),
            'default'           => "",
            "custom_attributes" => array(
              "dir"  => "ltr",
              "lang" => "en_US",
              'rows' => "5",
            ),
            "css" => "width: 100%; min-height: 250px; font-family: Monospace;",
          ),
          array(
            'type' => 'sectionend',
            'id'   => 'upload_receipt_3',
          ),
          array(
            'type'  => 'title',
            'id'    => 'peprobacsru_heading_3',
            'title' => __("Miscellaneous", $this->td),
            'desc'  => "

            <h4>" . __("Shortcodes", $this->td) . "</h4>
            <table>
              <tr>
                <td><code>[receipt-preview order_id=15]</code></td>
                <td>" . __("Show Receipt Preview for Order ID #15", $this->td) . "</td>
              </tr>
              <tr>
                <td><code>[receipt-form order_id=15]</code></td>
                <td>" . __("Show Upload Receipt Form for Order ID #15", $this->td) . "</td>
              </tr>
            </table>
            <hr>

            <h4>" . __("Notification", $this->td) . "</h4>" .
              sprintf(
                __("Since version 1.9, We added new Emails in WooCommerce Setting to support sending notifications using built-in WooCommerce feature.<br>You can manage WooCommerce Emails %s, if you need to Customize Emails see %s.", $this->td),
                "<a href='" . admin_url("admin.php?page=wc-settings&tab=email") . "' target='_blank'>" . _x("here", "link", $this->td) . "</a>",
                "<a href='https://woocommerce.com/posts/how-to-customize-emails-in-woocommerce/' target='_blank'>" . _x("this guide", "link", $this->td) . "</a>"
              ) . "

            <hr>
            <h4>" . __("Useful Resources", $this->td) . "</h4>
            <a href='https://wordpress.org/support/plugin/pepro-bacs-receipt-upload-for-woocommerce/reviews/#new-post' target='_blank'>" . _x("Rate 5-star", "link", $this->td) . "</a>&nbsp;/&nbsp;" .
              "<a href='https://wordpress.org/plugins/pepro-bacs-receipt-upload-for-woocommerce/#developers' target='_blank'>" . _x("Changelog", "link", $this->td) . "</a>&nbsp;/&nbsp;" .
              "<a href='https://pepro.dev/' target='_blank'>" . _x("Developer Site", "link", $this->td) . "</a>&nbsp;/&nbsp;" .
              "<a href='https://github.com/peprodev/wc-upload-reciept' target='_blank'>" . _x("Contribute", "link", $this->td) . "</a>&nbsp;/&nbsp;" .
              "<a href='mailto:support@pepro.dev?subject={$this->title}' target='_blank'>" . _x("Report Bug", "link", $this->td) . "</a>"
          ),
          array(
            'type' => 'sectionend',
            'id'   => 'upload_receipt_4',
          ),
        );
      }
      return $settings;
    }
    public function admin_enqueue_scripts($hook) {
      if (isset($_GET["page"]) && "wc-settings" == $_GET["page"] && isset($_GET["section"]) && "upload_receipt" == $_GET["section"]) {
        $uid = uniqid($this->td);
        wp_register_style($uid, false);
        wp_enqueue_style($uid);
        wp_add_inline_style($uid, "#tiptip_content a{color: skyblue;}");
      }
      $uid = uniqid($this->td);
      wp_register_style($uid, false);
      wp_enqueue_style($uid);
      wp_add_inline_style($uid, ".wcuploadrcp.column-wcuploadrcp > * {border-radius: 2px;}");
    }
    public function column_header($columns) {
      $new_columns = array();
      foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;
        if ('order_status' === $column_name) {
          $new_columns['wcuploadrcp'] = __('Payment Receipt', $this->td);
        }
      }
      if (!isset($new_columns['wcuploadrcp'])) {
        $new_columns['wcuploadrcp'] = __('Payment Receipt', $this->td);
      }
      return $new_columns;
    }
    public function column_content($column, $order_id) {
      global $post;
      if ('wcuploadrcp' !== $column) {
        return;
      }
      $order = wc_get_order($order_id);
      if ($this->is_payment_method_allowed($order->get_payment_method())) {
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
        $attachment_id = $this->get_meta('receipt_uploaded_attachment_id', $order->get_id());
        if (!$attachment_id) {
          $attachment_id = $this->get_meta('receipt_uplaoded_attachment_id', $order->get_id());
        }
        $status        = $this->get_meta('receipt_upload_status', $order->get_id());
        $status_text     = $this->get_status($status);
        $src           = $this->defaultImg;
        $src_org       = false;
        if ($attachment_id) {
          $src_org = wp_get_attachment_image_src($attachment_id);
          $src = $src_org ? $src_org[0] : $this->defaultImg;
        }
        if ($src_org) {
          echo "<img src='$src' class='receipt-preview $status' alt='$status_text' title='$status_text' />";
        } else {
          echo "<span style='box-shadow: 0 0 0 3px #009fff;text-align: center;padding: 0.5rem;'>" . __("Awaiting Upload", $this->td) . "</span>";
        }
      } else {
        echo $order->get_payment_method_title();
      }
    }
    public function _allowed_file_types($file_mime) {
      $whitelisted_mimes = get_option("peprobacsru_allowed_file_types", "image/jpeg" . PHP_EOL . "image/png" . PHP_EOL . "image/bmp");
      $whitelisted_mimes = array_map("trim", explode("\n", $whitelisted_mimes));
      $allowed = in_array($file_mime, $whitelisted_mimes);
      return apply_filters("pepro_upload_receipt_allowed_file_mimes", $allowed);
    }
    public function _allowed_file_types_array() {
      $mimes = get_option("peprobacsru_allowed_file_types", "image/jpeg" . PHP_EOL . "image/png" . PHP_EOL . "image/bmp");
      return array_map("trim", explode("\n", $mimes));
    }
    public function is_payment_method_allowed($method) {
      $gateways = (array) get_option("peprobacsru_allowed_gateways", "");
      foreach ($gateways as $key => $value) {
        if ($value == $method) return true;
      }
      return false;
    }
    public function _allowed_file_size() {
      $size = get_option("peprobacsru_allowed_file_size", 4);
      return apply_filters("pepro_upload_receipt_max_upload_size", $size);
    }
    public function get_meta($meta = "", $post_id = false) {
      global $post;
      if (!$post_id) $post_id = $post->ID;
      $field = get_post_meta($post_id, $meta, true);
      if (!empty($field)) {
        return is_array($field) ? stripslashes_deep($field) : stripslashes(wp_kses_decode_entities($field));
      } else {
        return false;
      }
    }
    public function receipt_upload_add_meta_box() {
      $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
      ? wc_get_page_screen_id( 'shop-order' )
      : 'shop_order';
      add_meta_box('receipt_upload-receipt-upload', __('Upload Receipt', $this->td), array($this, 'receipt_upload_html'), $screen, 'side', 'high');
    }
    public function receipt_upload_html($post) {
      wp_nonce_field('_receipt_upload_nonce', 'receipt_upload_nonce');
      wp_enqueue_media();
      add_thickbox();
      wp_enqueue_style("wc-orders.css", "{$this->assets_url}/backend/css/wc-orders.css", array(), current_time("timestamp"));
      wp_enqueue_script("wc-orders.js", "{$this->assets_url}/backend/js/wc-orders.js", array("jquery"), current_time("timestamp"));
      $src = $this->defaultImg;
      $attachment_id = $this->get_meta('receipt_uploaded_attachment_id');
      if (!$attachment_id) {
        $attachment_id = $this->get_meta('receipt_uplaoded_attachment_id');
      }
      if ($attachment_id) {
        $src = wp_get_attachment_image_src($attachment_id, 'full');
        $src = $src ? $src[0] : $this->defaultImg;
      }
      ?>
      <div style="display: flex;flex-direction: column;width: 100%;">
        <img data-def="<?= $this->defaultImg; ?>" id="change_receipt_attachment_id" title="<?= esc_attr__("Click to change", $this->td); ?>" src="<?= $src ?>" style="width: 100%;min-height: 90px;border-radius: 4px;border: 1px solid #ccc;">
        <p class="hidden"><input title="<?= esc_attr__("Receipt Attachment ID", $this->td); ?>" type="text" name="receipt_uploaded_attachment_id" id="receipt_uploaded_attachment_id" value="<?= esc_attr($attachment_id); ?>"></p>
      </div>
      <p>
        <span><?php _e('Uploaded at:', $this->td); ?> <date><?= $this->get_meta('receipt_upload_date_uploaded'); ?></date></span>
      </p>
      <p>
        <a href="#" class="button button-secondary widebutton changefile"><span style="margin: 4px;" class="dashicons dashicons-format-image"></span> <?= esc_attr__("Change Receipt Image", $this->td); ?></a>
        <a href="#" class="button button-secondary widebutton removefile"><span style="margin: 4px;" class="dashicons dashicons-editor-unlink"></span> <?= esc_attr__("Unlink Receipt Image", $this->td); ?></a>
        <a href="#" class="button button-secondary widebutton changedate" id="receipt_upload_date_btn"><span style="margin: 4px;" class="dashicons dashicons-calendar-alt"></span> <?= esc_attr__("Change Upload Date", $this->td); ?></a>
      </p>
      <p>
        <input type="text" dir="ltr" style="display: none;" autocomplete="off" name="receipt_upload_date_uploaded" id="receipt_upload_date_uploaded" value="<?php echo $this->get_meta('receipt_upload_date_uploaded'); ?>">
      </p>
      <p>
        <label for="receipt_upload_status"><?php _e('Receipt Approval Status', $this->td); ?></label>
        <select autocomplete="off" id="receipt_upload_status" name="receipt_upload_status">
          <option value="upload" <?php selected($this->get_meta('receipt_upload_status'), "upload", 1); ?>><?= __("Awaiting Upload", $this->td) ?></option>
          <option value="pending" <?php selected($this->get_meta('receipt_upload_status'), "pending", 1); ?>><?= __("Pending", $this->td) ?></option>
          <option value="approved" <?php selected($this->get_meta('receipt_upload_status'), "approved", 1); ?>><?= __("Approved", $this->td) ?></option>
          <option value="rejected" <?php selected($this->get_meta('receipt_upload_status'), "rejected", 1); ?>><?= __("Rejected", $this->td) ?></option>
        </select>
      </p>
      <p>
        <label for="receipt_upload_admin_note"><?php _e('Admin Note', $this->td); ?></label>
        <textarea rows="5" autocomplete="off" name="receipt_upload_admin_note" id="receipt_upload_admin_note"><?php echo $this->get_meta('receipt_upload_admin_note'); ?></textarea>
      </p>
      <?php
      $all_previous = (array) get_attached_media("");
      if (!empty($all_previous)) {
        echo "<hr><p>" . __("Previously Uploaded Receipts", $this->td) . "</p>";
      }
      ?>
      <div class="prev-items-uploaded">
        <?php
        foreach ($all_previous as $attached) {
          $src = wp_get_attachment_image_src($attached->ID, 'thumbnail');
          $src_full = wp_get_attachment_image_src($attached->ID, 'full');
          $url = admin_url("upload.php?item={$attached->ID}");
          $src = isset($src[0]) ? $src[0] : $this->defaultImg;
          $src_full = isset($src_full[0]) ? $src_full[0] : $this->defaultImg;
          echo "<div class='prev-uploaded-item'>
                    <a href='$url' target='_blank'><img src='$src' width='75' /></a>
                    <a href='$src_full' target='_blank' class='button button-small' style='margin-top: 0.5rem;'><span class='dashicons dashicons-external' style='margin: 2px 0;'></span> " . __("View", $this->td) . "</a>
                </div>";
        }
        ?>
      </div>
      <p>
        <small style="text-align: end;display: block;">
          <a target="_blank" class="text-small" href="<?= esc_attr($this->url); ?>"><?= __("Change Upload Receipt Plugin Setting", $this->td); ?></a>
        </small>
      </p>
      <?php
    }
    public function receipt_upload_save($post_id) {
      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
      }
      if (!isset($_POST['receipt_upload_nonce']) || !wp_verify_nonce($_POST['receipt_upload_nonce'], '_receipt_upload_nonce')) {
        return;
      }
      if (!current_user_can('edit_post', $post_id)) {
        return;
      }
      if (isset($_POST['receipt_uploaded_attachment_id'])) {
        update_post_meta($post_id, 'receipt_uploaded_attachment_id', sanitize_text_field($_POST['receipt_uploaded_attachment_id']));
      }
      if (isset($_POST['receipt_upload_date_uploaded'])) {
        update_post_meta($post_id, 'receipt_upload_date_uploaded', sanitize_text_field($_POST['receipt_upload_date_uploaded']));
      }
      $order = wc_get_order($post_id);
      if (!$order) return;
      if (isset($_POST['receipt_upload_status'])) {
        do_action("peprodev_uploadreceipt_save_receipt", $post_id, $order, $_POST);
        $prev = $this->get_meta("receipt_upload_status", $post_id);
        $new  = sanitize_text_field($_POST['receipt_upload_status']);
        do_action("peprodev_uploadreceipt_{$prev}_to_{$new}", $order->get_id(), $order, $_POST);
        update_post_meta($post_id, 'receipt_upload_status', $new);

        if ($new !== $prev) {
          update_post_meta($post_id, 'receipt_upload_last_change', current_time("Y-m-d H:i:s"));
          do_action("peprodev_uploadreceipt_receipt_status_changed", $order->get_id(), $order, $prev, $new);
        }

        if ("approved" == $new && $new != $prev) {
          if ("none" != $this->status_receipt_approved) {
            $order->update_status($this->status_receipt_approved);
          }
          do_action("woocommerce_receipt_approved_notification", $order->get_id());
          do_action("peprodev_uploadreceipt_receipt_approved", $order->get_id(), $order, $prev, $new);
        }
        if ("rejected" == $new && $new != $prev) {
          if ("none" != $this->status_receipt_rejected) {
            $order->update_status($this->status_receipt_rejected);
          }
          do_action("woocommerce_receipt_rejected_notification", $order->get_id());
          do_action("peprodev_uploadreceipt_receipt_rejected", $order->get_id(), $order, $prev, $new);
        }
        if ("upload" == $new && $new != $prev) {
          if ("none" != $this->status_receipt_awaiting_upload) {
            $order->update_status($this->status_receipt_awaiting_upload);
          }
          do_action("woocommerce_receipt_await_upload_notification", $order->get_id());
          do_action("peprodev_uploadreceipt_receipt_awaiting_upload", $order->get_id(), $order, $prev, $new);
        }
        if ("pending" == $new && $new != $prev) {
          if ("none" != $this->status_receipt_awaiting_approval) {
            $order->update_status($this->status_receipt_awaiting_approval);
          }
          do_action("woocommerce_receipt_pending_approval_notification", $order->get_id());
          do_action("peprodev_uploadreceipt_receipt_awaiting_approval", $order->get_id(), $order, $prev, $new);
        }

        // do_action("woocommerce_order_edit_status", $order->get_id(), sanitize_text_field( $_POST["order_status"] ) );

      }
      if (isset($_POST['receipt_upload_admin_note'])) {
        update_post_meta($post_id, 'receipt_upload_admin_note', sanitize_text_field($_POST['receipt_upload_admin_note']));
        do_action("peprodev_uploadreceipt_receipt_attached_note", $order->get_id(), $order, $prev, $new);
      }
    }
    public function get_status($status) {
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
    public function woocommerce_thankyou($order) {
      if (!$order) {
        return;
      }
      $order = wc_get_order($order);
      if ($this->is_payment_method_allowed($order->get_payment_method())) {
        $ran_before = get_post_meta($order->get_id(), "receipt_upload_status", true);
        if ((!$ran_before || empty($ran_before)) && "yes" !== $ran_before) {
          $order->update_status($this->status_order_placed);
          update_post_meta($order->get_id(), "receipt_upload_status", "upload");
          update_post_meta($order->get_id(), "peprodev_uploadreceipt_action_run_once", "yes");
          do_action("peprodev_uploadreceipt_order_placed", $order);
        }
      }
    }
    public function order_details_before_order_table(\WC_Order $order) {
      if (!$order) {
        return;
      }
      $order_id = $order->get_id();
      $array    = (array) get_option("peprobacsru_show_on_statuses");
      $array    = array_map(function ($i) {
        return str_replace("wc-", "", $i);
      }, $array);
      if ($order->has_status($array)) {
        echo do_shortcode("[receipt-form order_id=$order_id]");
      }
    }
    public function add_wc_prebuy_status() {
      register_post_status(
        "wc-receipt-upload",
        array(
          "label"                     => __("Awaiting Upload", $this->td),
          "public"                    => true,
          "exclude_from_search"       => false,
          "show_in_admin_all_list"    => true,
          "show_in_admin_status_list" => true,
          "label_count"               => _n_noop("Awaiting Receipt Upload (%s)", "Awaiting Receipts Upload (%s)", $this->td)
        )
      );
      register_post_status(
        "wc-receipt-approval",
        array(
          "label"                     => __("Awaiting Approval", $this->td),
          "public"                    => true,
          "exclude_from_search"       => false,
          "show_in_admin_all_list"    => true,
          "show_in_admin_status_list" => true,
          "label_count"               => _n_noop("Awaiting Receipt Approval (%s)", "Awaiting Receipts Approval (%s)", $this->td)
        )
      );
      register_post_status(
        "wc-receipt-rejected",
        array(
          "label"                     => _x("Receipt Rejected", "pst", $this->td),
          "public"                    => true,
          "exclude_from_search"       => false,
          "show_in_admin_all_list"    => true,
          "show_in_admin_status_list" => true,
          "label_count"               => _n_noop("Receipt Rejected (%s)", "Receipt Rejected (%s)", $this->td)
        )
      );
    }
    public function add_wc_order_statuses($order_statuses) {
      $new_order_statuses = array();
      foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ("wc-pending" === $key) {
          $new_order_statuses["wc-receipt-upload"]   = _x("Awaiting Receipt Upload", "pst", $this->td);
          $new_order_statuses["wc-receipt-approval"] = _x("Awaiting Receipt Approval", "pst", $this->td);
          $new_order_statuses["wc-receipt-rejected"] = _x("Receipt Rejected", "pst", $this->td);
        }
      }
      return $new_order_statuses;
    }
    public function get_setting_options() {
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
    public function update_footer_info() {
      $f = "pepro_temp_stylesheet." . current_time("timestamp");
      wp_register_style($f, null);
      wp_add_inline_style($f, " #footer-left b a::before { content: ''; background: url('{$this->assets_url}backend/images/peprodev.svg') no-repeat; background-position-x: center; background-position-y: center; background-size: contain; width: 60px; height: 40px; display: inline-block; pointer-events: none; position: absolute; -webkit-margin-before: calc(-60px + 1rem); margin-block-start: calc(-60px + 1rem); -webkit-filter: opacity(0.0);
      filter: opacity(0.0); transition: all 0.3s ease-in-out; }#footer-left b a:hover::before { -webkit-filter: opacity(1.0); filter: opacity(1.0); transition: all 0.3s ease-in-out; }[dir=rtl] #footer-left b a::before {margin-inline-start: calc(30px);}");
      wp_enqueue_style($f);
      add_filter('admin_footer_text', function () {
        return sprintf(_x("Thanks for using %s products", "footer-copyright", $this->td), "<b><a href='https://pepro.dev/' target='_blank' >" . __("Pepro Dev", $this->td) . "</a></b>");
      }, 11000);
      add_filter('update_footer', function () {
        return sprintf(_x("%s — Version %s", "footer-copyright", $this->td), $this->title, $this->version);
      }, 1100);
    }
    public function handel_ajax_req() {
      if (wp_doing_ajax() && $_POST['action'] == "upload-payment-receipt") {
        if (!wp_verify_nonce($_POST["nonce"], $this->db_slug)) {
          wp_send_json_error(array("msg" => __("Unauthorized Access!", $this->td)));
        }

        // These files need to be included as dependencies when on the front end.
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Check if there's a valid non-zero-sized file
        if (isset($_FILES['file']['size']) && $_FILES['file']['size'] > 0) {
          if ($this->_allowed_file_types(mime_content_type($_FILES['file']["tmp_name"])) && $_FILES['file']['size'] <= $this->_allowed_file_size() * 1024 * 1024) {
            $postOrder     = sanitize_text_field($_POST["order"]);
            add_filter("upload_dir", array($this, "change_receipt_upload_dir"));
            $attachment_id = media_handle_upload('file', $postOrder);
            remove_filter("upload_dir", array($this, "change_receipt_upload_dir"));
            $datetime = current_time("Y-m-d H:i:s");
            if (!is_wp_error($attachment_id) && is_numeric($attachment_id)) {
              update_post_meta($postOrder, "receipt_uploaded_attachment_id", $attachment_id);
              update_post_meta($postOrder, "receipt_upload_date_uploaded", $datetime);
              update_post_meta($postOrder, "receipt_upload_status", "pending");
              do_action("woocommerce_receipt_uploaded_notification", $postOrder);
              $order       = wc_get_order($postOrder);
              $status      = $this->get_meta("receipt_upload_status", $postOrder);
              $status_text = $this->get_status($status);
              $order->update_status($this->status_receipt_awaiting_approval);
              update_post_meta($attachment_id, "_attached_order", $postOrder);
              $url = wp_get_attachment_image_url($attachment_id, [50, 50]);
              $order->add_order_note("<strong>{$this->title}</strong><br>" . sprintf(__("Customer uploaded payment receipt image. %s", $this->td), "<br><a target='_blank' href='" . wp_get_attachment_url($attachment_id) . "'><img src=\"$url\" style='height: 50px; border-radius: 4px; border: 1px solid #eee;' /></a>"));
              do_action("peprodev_uploadreceipt_customer_uploaded_receipt", $postOrder, $attachment_id);
              wp_send_json_success(
                array(
                  "msg"      => __("Upload completed successfully.", $this->td),
                  "date"     => date_i18n("Y-m-d l H:i:s", $datetime),
                  "status"   => $status,
                  "statustx" => $status_text,
                  "url"      => $this->generate_secure_preview_src($attachment_id, $order, false),
                )
              );
            } else {
              // The image was NOT uploaded successfully!
              wp_send_json_error(array("msg" => $attachment_id->get_error_message(),));
            }
          } else {
            // Validation Error
            wp_send_json_error(array(
              "msg" => __("There was an error uploading your file. Please check file type and size.", $this->td),
              // "mime_type"          => mime_content_type($_FILES['file']["tmp_name"]),
              // "filtered_file_type" => $this->_allowed_file_types(mime_content_type($_FILES['file']["tmp_name"])),
            ));
          }
        } else {
          // Check if there's a valid non-zero-sized file FAILED!
          wp_send_json_error(array(
            "msg" => __("There was an error uploading your file.", $this->td),
          ));
        }
        die();
      }
    }
    public function change_receipt_upload_dir($param) {
      $param['subdir'] = $this->folder_name;
      $param['url'] = $param['baseurl'] . "/" . $this->folder_name;
      $param['path'] = $param['basedir'] . "/" . $this->folder_name;
      if (!file_exists($param['path'] . "/.htaccess")) {
        $file = fopen($param['path'] . "/.htaccess", "w");
        if ($file) {
          fwrite($file, "# Created by PeproDev WooCommerce Receipt Uploader v.{$this->version}\n\nOptions All -Indexes\n<Files .htaccess>\n Order allow,deny\n Deny from all\n</Files>");
          fclose($file);
        }
      }
      return $param;
    }
    public function admin_init($hook) {
      if (!$this->_wc_activated()) {
        add_action(
          'admin_notices',
          function () {
            echo "<div class=\"notice error\"><p>" . sprintf(
              _x('%1$s needs %2$s in order to function', "required-plugin", "$this->td"),
              "<strong>" . $this->title . "</strong>",
              "<a href='" . admin_url("plugin-install.php?s=woocommerce&tab=search&type=term") . "' style='text-decoration: none;' target='_blank'><strong>" .
                _x("WooCommerce", "required-plugin", "$this->td") . "</strong> </a>"
            ) . "</p></div>";
          }
        );
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        deactivate_plugins(plugin_basename(__FILE__));
      }
      $Pepro_Upload_Receipt_class_options = $this->get_setting_options();
      foreach ($Pepro_Upload_Receipt_class_options as $sections) {
        foreach ($sections["data"] as $id => $def) {
          add_option($id, $def);
          register_setting($sections["name"], $id);
        }
      }
    }
    public function _wc_activated() {
      if (!function_exists('is_woocommerce') || !class_exists('woocommerce')) {
        return false;
      }
      return true;
    }
    public function read_opt($mc, $def = "") {
      return get_option($mc) <> "" ? get_option($mc) : $def;
    }
  }
  /**
   * load plugin and load textdomain then set a global variable to access plugin class!
   *
   * @version 1.0.0
   * @since   1.0.0
   * @license https://pepro.dev/license Pepro.dev License
   */
  add_action("plugins_loaded", function () {
    global $Pepro_Upload_Receipt;
    $Pepro_Upload_Receipt = new peproDev_UploadReceiptWC;
  });
}
