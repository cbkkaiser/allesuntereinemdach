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

  // Contact form: route to the right company mailbox based on the topic chosen,
  // and show a confirmation note (the mail client opens with the message pre-filled).
  var form = document.getElementById("contact-form");
  if (form) {
    var topicSelect = form.querySelector("#topic");
    var mailTargets = {
      signerdach: "info@signerdach.ch",
      tsdach: "info@tsdachundfinanzenag.ch",
      both: "info@signerdach.ch,info@tsdachundfinanzenag.ch"
    };

    function updateAction() {
      var target = mailTargets[topicSelect.value] || mailTargets.both;
      form.action = "mailto:" + target;
    }
    if (topicSelect) {
      topicSelect.addEventListener("change", updateAction);
      updateAction();
    }

    form.addEventListener("submit", function () {
      var success = document.getElementById("form-success");
      if (success) success.classList.add("show");
    });
  }
});
