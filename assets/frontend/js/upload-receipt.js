/**
 * @Last modified by:   Amirhosseinhpv
 * @Last modified time: 2022/01/19 10:30:46
 */

(function($) {
  $(document).ready(function() {
    var $success_color = "rgba(21, 139, 2, 0.8)";
    var $error_color   = "rgba(139, 2, 2, 0.8)";
    var $info_color    = "rgba(2, 133, 139, 0.8)";
    $(document.body).append($("<toast>"));
    $(document).on("change", "#receipt-file", function(e) {
      e.preventDefault();
      const size = (this.files[0].size / 1024 / 1024).toFixed(2);
      if (size > _upload_receipt.max_size) {
        show_toast(_upload_receipt.max_alert.replace("##", _upload_receipt.max_size), $error_color);
        $("#receipt-file").val("");
        $(document).trigger("peprodev_receipt_uploader_ajax_prevented");
        return false;
      }
    });
    $(document).on("click", ".start-upload", function(e) {
      var file_data = $("#receipt-file").prop("files")[0];
      if (!file_data) {
        show_toast(_upload_receipt.select_file, $error_color);
        $(document).trigger("peprodev_receipt_uploader_ajax_prevented");
        return false;
      }
      var form_data = new FormData();
      form_data.append("file", file_data);
      form_data.append("action", "upload-payment-receipt");
      form_data.append("order", _upload_receipt.order_id);
      form_data.append("nonce", $("input[name=uniqnonce]").val());
      $(".receipt_uploading-loader").css("display", "inline-block");
      $("#uploadreceiptfileimage input, #uploadreceiptfileimage button, #uploadreceiptfileimage").prop("disabled", true);
      show_toast(_upload_receipt.loading, $info_color);
      $.ajax({
        url: _upload_receipt.ajax_url,
        type: "post",
        contentType: false,
        processData: false,
        data: form_data,
        success: function(response) {
          if (response.success) {
            show_toast(response.data.msg, $success_color);
            handle_succ(response);
            if ($.trim(_upload_receipt.redirect_url) !== ""){
              setTimeout(function () {
                window.location.assign($.trim(_upload_receipt.redirect_url));
              }, 1000);
            }
            $(document).trigger("peprodev_receipt_uploader_ajax_success");
          } else {
            handle_err(response);
            $(document).trigger("peprodev_receipt_uploader_ajax_failed");
          }
        },
        error: function(response) {
          show_toast(_upload_receipt.unknown_error, $error_color);
          $(document).trigger("peprodev_receipt_uploader_ajax_failed");
          $("#receipt-file").val("");
        },
        complete: function() {
          $(".receipt_uploading-loader").hide();
          $("#uploadreceiptfileimage input, #uploadreceiptfileimage button, #uploadreceiptfileimage").prop("disabled", false);
          $(document).trigger("peprodev_receipt_uploader_ajax_completed");
        },
      });
    });

    function handle_succ(e) {
      if ($("img.receipt-preview").length === 0) {
        $("td.receipt-img-preview").prepend(`<img src="" title="" class="receipt-preview" alt="reciept-img">`);
      }
      $(".receipt-img-preview").parents("tr").find("th,td").show();
      $("img.receipt-preview")
        .attr("src", e.data.url)
        .attr("title", e.data.statustx)
        .removeClass("pending approved rejected")
        .addClass(e.data.status);
      $("p.receipt-status")
        .text(e.data.statustx)
        .removeClass("pending approved rejected")
        .addClass(e.data.status);
      $("td.receipt-uplaod-date>span").text(e.data.date);
      $(".receipt-img-upload").parents("tr").first().remove();
      $(".receipt-admin-note").parents("tr").first().remove();
    }

    function handle_err(e) {
      show_toast(e.data.msg, $error_color);
      $("#receipt-file").val("");
    }

    function show_toast(data = "Sample Toast!", bg = "", delay = 4500) {
      if (!$("toast").length) {
        $(document.body).append($("<toast>"));
      } else {
        $("toast").removeClass("active");
      }
      setTimeout(function() {
        $("toast").css("--toast-bg", bg).html(data).stop().addClass("active").delay(delay).queue(function() {
          $(this).removeClass("active").dequeue().off("click tap");
        }).on("click tap", function(e) {
          e.preventDefault();
          $(this).stop().removeClass("active");
        });
      }, 200);
    }
  });
})(jQuery);
