(function($) {

  $(document).ready(function() {

    $(document).on("change","#receipt-file",function(e){
      e.preventDefault();
      const size = (this.files[0].size / 1024 / 1024).toFixed(2);

      if (size > 4 ) {
        alert("File size must be between less than 4MB");
        $("#receipt-file").val("");
        return false;
      }
    });

    $(document).on('click', '.start-upload', function(e) {
      var file_data = $('#receipt-file').prop('files')[0];
      if (!file_data) {
        alert("Select a file first.");
        return false;
      }
      var form_data = new FormData();
      form_data.append("file", file_data);
      form_data.append("action", "upload-payment-receipt");
      form_data.append("order", _r.o);
      form_data.append("nonce", $("input[name=uniqnonce]").val());
      $(".receipt_uploading-loader").css("display","inline-block");
      $("#uploadreceiptfileimage input, #uploadreceiptfileimage button, #uploadreceiptfileimage").prop("disabled", true);
      $.ajax({
        url: _r.u,
        type: 'post',
        contentType: false,
        processData: false,
        data: form_data,
        success: function(response) {
          if (response.success) {
            handle_succ(response);
          } else {
            handle_err(response);
          }
        },
        error: function(response) {
          handle_err(response);
        },
        complete: function() {
          $(".receipt_uploading-loader").hide();
          $("#uploadreceiptfileimage input, #uploadreceiptfileimage button, #uploadreceiptfileimage").prop("disabled", false);
        }

      });
    });

    function handle_succ(e) {

      if ($("img.receipt-preview").length === 0){$("td.receipt-img-preview").prepend(`<img src="" title="" class="receipt-preview" alt="reciept-img">`);}
      $(".receipt-img-preview").parents("tr").find("th,td").show();
      $("img.receipt-preview").attr("src",e.data.url).attr("title",e.data.statustx).removeClass("pending approved rejected").addClass(e.data.status);
      $("p.receipt-status").text(e.data.statustx).removeClass("pending approved rejected").addClass(e.data.status);
      $("td.receipt-uplaod-date>span").text(e.data.date);
      $(".receipt-img-upload").parents("tr").first().remove();
      $(".receipt-admin-note").parents("tr").first().remove();

    }

    function handle_err(e) {
      alert(e.data.msg);
      $("#receipt-file").val("");
    }

  });
})(jQuery);
