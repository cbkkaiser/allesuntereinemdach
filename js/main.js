document.addEventListener("DOMContentLoaded", function () {
  // Mobile navigation toggle
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.querySelector(".main-nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      nav.classList.toggle("open");
      var expanded = nav.classList.contains("open");
      toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    });
    nav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        nav.classList.remove("open");
      });
    });
  }

  // Footer year
  var yearEl = document.getElementById("year");
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // Contact form: send to kontakt.php, which emails the chosen company.
  // Without JavaScript the form posts normally and kontakt.php redirects back with ?status=.
  var form = document.getElementById("contact-form");
  if (form) {
    var notice = document.getElementById("form-success");
    var button = form.querySelector('button[type="submit"]');
    var messages = {
      ok: "Vielen Dank! Ihre Nachricht wurde gesendet. Wir melden uns so bald wie möglich bei Ihnen.",
      error: "Die Nachricht konnte leider nicht gesendet werden. Bitte schreiben Sie uns direkt per E-Mail oder rufen Sie uns an."
    };

    function showNotice(ok, text) {
      if (!notice) return;
      notice.textContent = text || (ok ? messages.ok : messages.error);
      notice.classList.toggle("error", !ok);
      notice.classList.add("show");
    }

    var status = new URLSearchParams(window.location.search).get("status");
    if (status === "ok" || status === "error") showNotice(status === "ok");

    if (window.fetch && window.FormData) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (button) button.disabled = true;
        if (notice) notice.classList.remove("show");

        fetch(form.action, {
          method: "POST",
          body: new FormData(form),
          headers: { Accept: "application/json" }
        })
          .then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
              return { ok: response.ok && data.ok, message: data.message };
            });
          })
          .then(function (result) {
            showNotice(result.ok, result.message);
            if (result.ok) form.reset();
          })
          .catch(function () { showNotice(false); })
          .then(function () { if (button) button.disabled = false; });
      });
    }
  }
});
