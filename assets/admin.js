jQuery(function ($) {
  const $btn = $("#sst-test-btn");
  const $zip = $("#sst-test-zip");
  const $spinner = $("#sst-test-spinner");
  const $result = $("#sst-test-result");

  function show(type, html) {
    const styles = {
      success: "padding:10px; border-left:4px solid #46b450; background:#fff; border:1px solid #ccd0d4;",
      error: "padding:10px; border-left:4px solid #dc3232; background:#fff; border:1px solid #ccd0d4;",
      info: "padding:10px; border-left:4px solid #007cba; background:#fff; border:1px solid #ccd0d4;"
    };
    $result.html('<div style="' + (styles[type] || styles.info) + '">' + html + "</div>");
  }

  $btn.on("click", function () {
    const rawZip = ($zip.val() || "").toString().trim();
    if (!rawZip || rawZip.replace(/\D/g, "").length < 5) {
      show("error", SST_Admin.i18n.missingZip);
      return;
    }

    $result.empty();
    $spinner.addClass("is-active");
    $btn.prop("disabled", true);

    show("info", SST_Admin.i18n.testing);

    $.post(SST_Admin.ajaxUrl, {
      action: SST_Admin.action,
      nonce: $btn.data("nonce"),
      zip: rawZip
    })
      .done(function (resp) {
        if (!resp || !resp.success) {
          const msg = resp && resp.data && resp.data.message ? resp.data.message : "Unknown error.";
          show("error", msg);
          return;
        }

        const d = resp.data;
        const html =
          "<strong>" + d.message + "</strong><br/>" +
          "Example on $10.00: <code>$" + d.example_tax + "</code> tax";

        show("success", html);
      })
      .fail(function (xhr) {
        let msg = "Request failed.";
        if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          msg = xhr.responseJSON.data.message;
        }
        show("error", msg);
      })
      .always(function () {
        $spinner.removeClass("is-active");
        $btn.prop("disabled", false);
      });
  });
});
