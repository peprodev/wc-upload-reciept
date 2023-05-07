/*
 * @Author: Amirhossein Hosseinpour <https://amirhp.com>
 * @Date Created: 2022/08/15 21:03:32
 * @Last modified by: amirhp-com <its@amirhp.com>
 * @Last modified time: 2023/05/07 12:10:28
 */

(function ($) {
  $(document).ready(function () {
    var _upload_receipt_ajax = null;
    var $success_color = "rgba(21, 139, 2, 0.8)";
    var $error_color = "rgba(139, 2, 2, 0.8)";
    var $info_color = "rgba(2, 133, 139, 0.8)";
    $(document.body).append($("<toast>"));
    $(document).on("change", "#receipt-file", function (e) {
      e.preventDefault();
      const size = (this.files[0].size / 1024 / 1024).toFixed(2);
      if (size > _upload_receipt.max_size) {
        show_toast(_upload_receipt.max_alert.replace("##", _upload_receipt.max_size), $error_color);
        $("#receipt-file").val("");
        $(document).trigger("peprodev_receipt_uploader_ajax_prevented");
        return false;
      }
    });
    $(document).on("click", ".start-upload", function (e) {
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
      $("#uploadreceiptfileimage input, #uploadreceiptfileimage button, #uploadreceiptfileimage").prop("disabled", true);
      $el = show_toast(_upload_receipt.loading, $info_color, 100000000000);
      if (_upload_receipt_ajax != null) { _upload_receipt_ajax.abort(); }
      _upload_receipt_ajax = $.ajax({
        url: _upload_receipt.ajax_url,
        type: "post",
        contentType: false,
        processData: false,
        data: form_data,
        success: function (response) {
          if (response.success) {
            $(document).trigger("peprodev_receipt_uploader_ajax_success");
            show_toast(response.data.msg, $success_color);
            handle_success(response);
            if ($.trim(_upload_receipt.redirect_url) !== "") {
              setTimeout(function () { window.location.assign($.trim(_upload_receipt.redirect_url)); }, 1000);
            }
          } else {
            $(document).trigger("peprodev_receipt_uploader_ajax_failed");
            handle_err(response);
          }
        },
        error: function (response) {
          $(document).trigger("peprodev_receipt_uploader_ajax_failed");
          show_toast(_upload_receipt.unknown_error, $error_color);
          $("#receipt-file").val("");
        },
        complete: function () {
          $(document).trigger("peprodev_receipt_uploader_ajax_completed");
          $("#uploadreceiptfileimage input, #uploadreceiptfileimage button, #uploadreceiptfileimage").prop("disabled", false);
        },
        xhr: function () {
          var xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener("progress", function (evt) {
            if (evt.lengthComputable) {
              var percentComplete = evt.loaded / evt.total;
              percentComplete = parseInt(percentComplete * 100);
              $("toast").html(_upload_receipt.precent.replace("##", percentComplete));
              if (percentComplete === 100) {
                // $("toast").html(_upload_receipt.done);
              }
            }
          }, false);
          return xhr;
        },
      });
    });

    function handle_success(e) {
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
      $(".woocommerce-table--upload-receipt tr.date-uploaded").removeClass("hide");
      $("td.receipt-upload-date").html(`<bdi dir="ltr">${e.data.date}</bdi>`);
      $(".receipt-img-upload").parents("tr").first().remove();
      $(".receipt-admin-note").parents("tr").first().remove();
    }

    function handle_err(e) {
      show_toast(e.data.msg, $error_color);
      $("#receipt-file").val("");
    }

    function show_toast(data = "Sample Toast!", bg = "", delay = 6000) {
      if (!$("toast").length) {
        $(document.body).append($("<toast>"));
      } else {
        $("toast").removeClass("active");
      }
      setTimeout(function () {
        $("toast").css("--toast-bg", bg).html(data).stop().addClass("active").delay(delay).queue(function () {
          $(this).removeClass("active").dequeue().off("click tap");
        }).on("click tap", function (e) {
          e.preventDefault();
          $(this).stop().removeClass("active");
        });
      }, 200);
    }
    function hide_toast() {
      $("toast").stop().removeClass("active").html("");
    }
    function scroll_element(element, offset = 90) {
      $("html, body").animate(
        { scrollTop: element.offset().top - offset },
        500
      );
    }
  });
})(jQuery);
