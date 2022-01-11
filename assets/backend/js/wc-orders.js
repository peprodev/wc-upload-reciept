/**
 * @Last modified by:   Amirhosseinhpv
 * @Last modified time: 2022/01/11 18:20:56
 */


(function($) {
  $(document).ready(function() {

    setTimeout(function() {
      e = $("#receipt_upload_status").find("[selected]").attr("value");
      $("#receipt_upload_status").val(e).trigger("change");
    }, 200);

    $(document).on("click tap change", "#receipt_upload_status", function(e) {
      e.preventDefault();
      var me = $(this);
      me.removeAttr("class").addClass(me.val());
    });

    $(document).on("click tap", ".changefile", function(e) {
      e.preventDefault();
      $("#change_receipt_attachment_id").trigger("click");
    });
    $(document).on("click tap", ".removefile", function(e) {
      e.preventDefault();
      $("#receipt_uplaoded_attachment_id").val("").trigger("change");
      $("#change_receipt_attachment_id").attr("src", $("#change_receipt_attachment_id").data("def"));
    });
    $(document).on("click tap", ".changedate", function(e) {
      e.preventDefault();
      $("#receipt_upload_date_uploaded").toggle();
    });

    $(document).on("click tap", "#change_receipt_attachment_id", function(e) {
      e.preventDefault();
      var image_frame, me = $(this);
      if (image_frame) {
        image_frame.open();
      }
      image_frame = wp.media({
        title: '',
        multiple: false,
        library: {}
      });
      image_frame.on('select', function() {
        if (image_frame.state().get('selection').first()) {
          var selection = image_frame.state().get('selection').first().toJSON();
          $("#receipt_uplaoded_attachment_id").val(selection.id).trigger("change");
          $("#change_receipt_attachment_id").attr("src", `${selection.sizes.thumbnail.url}`);
          console.log(selection);
        }
      });
      image_frame.on('open', function() {
        var selection = image_frame.state().get('selection');
        var id = $("#receipt_uplaoded_attachment_id").val();
        var attachment = wp.media.attachment(id);
        attachment.fetch();
        selection.add(attachment ? [attachment] : []);
      });
      image_frame.open();
    });
  });
})(jQuery);
