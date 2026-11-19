/* ================================================================
   GONCA LÖK — Ortak site betiği
   Header ve footer'ı tek noktadan üretir, tüm sayfalarda birebir
   aynı görünmesini garanti eder. Nav aktif sayfayı body[data-page]
   üzerinden belirler.
================================================================ */
(function () {
  "use strict";

  function initHamburger() {
    var ham = document.getElementById("hamburger");
    var menu = document.getElementById("mobileMenu");
    if (!ham || !menu) return;
    ham.addEventListener("click", function () {
      var open = ham.classList.toggle("open");
      menu.classList.toggle("open");
      ham.setAttribute("aria-expanded", open ? "true" : "false");
    });
    menu.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        ham.classList.remove("open");
        menu.classList.remove("open");
        ham.setAttribute("aria-expanded", "false");
      });
    });
  }

  function initStickyHeader() {
    var header = document.getElementById("siteHeader");
    if (!header) return;
    var onScroll = function () {
      header.classList.toggle("scrolled", window.scrollY > 20);
    };
    window.addEventListener("scroll", onScroll);
    onScroll();
  }

  function initTestimonialCarousel() {
    var track = document.getElementById("tTrack");
    var prev = document.getElementById("tPrev");
    var next = document.getElementById("tNext");
    if (!track || !prev || !next) return;
    var prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    function cardStep() {
      var card = track.querySelector(".testimonial-card");
      if (!card) return 320;
      return card.getBoundingClientRect().width + 16;
    }
    function update() {
      var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth - 2);
      prev.disabled = track.scrollLeft <= 2;
      next.disabled = track.scrollLeft >= maxScroll;
      prev.style.visibility = track.scrollWidth > track.clientWidth + 2 ? "visible" : "hidden";
      next.style.visibility = track.scrollWidth > track.clientWidth + 2 ? "visible" : "hidden";
    }
    prev.addEventListener("click", function () { track.scrollBy({ left: -cardStep(), behavior: prefersReduced ? "auto" : "smooth" }); });
    next.addEventListener("click", function () { track.scrollBy({ left: cardStep(), behavior: prefersReduced ? "auto" : "smooth" }); });
    track.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update);
    update();
  }

  function initFaq() {
    var buttons = document.querySelectorAll(".faq-q");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        var item = btn.closest(".faq-item");
        var isOpen = item.classList.contains("open");
        document.querySelectorAll(".faq-item.open").forEach(function (i) {
          i.classList.remove("open");
          var b = i.querySelector(".faq-q");
          if (b) b.setAttribute("aria-expanded", "false");
        });
        if (!isOpen) {
          item.classList.add("open");
          btn.setAttribute("aria-expanded", "true");
        }
      });
    });
  }

  function escapeHtml(value) {
    var map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
    return String(value === null || value === undefined ? "" : value).replace(/[&<>"']/g, function (ch) {
      return map[ch];
    });
  }

  function clearError(fieldId) {
    var el = document.getElementById(fieldId);
    if (el) el.classList.remove("has-error");
  }

  function setSlotFeedback(message, available) {
    var feedback = document.getElementById("slotFeedback");
    if (!feedback) return;
    feedback.textContent = message || "";
    feedback.className = "slot-feedback" + (message ? (available ? " available" : " unavailable") : "");

    var dateField = document.getElementById("field-appointment_date");
    var timeField = document.getElementById("field-appointment_time");
    var mark = !!message && !available;
    if (dateField) dateField.classList.toggle("slot-unavailable", mark);
    if (timeField) timeField.classList.toggle("slot-unavailable", mark);
  }

  function setSubmitFeedback(message, ok) {
    var el = document.getElementById("submitFeedback");
    if (!el) return;
    if (!message) {
      el.className = "submit-feedback";
      el.textContent = "";
      return;
    }
    el.textContent = message;
    el.className = "submit-feedback show " + (ok ? "ok" : "error");
  }

  function checkSlotAvailability(showAvailableMessage) {
    var dateInput = document.getElementById("appointment_date");
    var timeInput = document.getElementById("appointment_time");
    if (!dateInput || !timeInput) return Promise.resolve(true);
    if (!dateInput.value || !timeInput.value) {
      setSlotFeedback("", true);
      return Promise.resolve(true);
    }

    var url = "admin.php?action=appointments-check-slot&appointment_date=" +
      encodeURIComponent(dateInput.value) + "&appointment_time=" + encodeURIComponent(timeInput.value);

    return fetch(url, { headers: { "X-Requested-With": "fetch" } })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.ok) {
          var checkError = data.error || "Uygunluk kontrolü yapılamadı.";
          setSlotFeedback(checkError, false);
          setSubmitFeedback(checkError, false);
          return false;
        }
        if (!data.available) {
          var slotError = data.message || "Bu tarih ve saat dolu. Lütfen farklı bir saat seçin.";
          setSlotFeedback(slotError, false);
          setSubmitFeedback(slotError, false);
          return false;
        }
        setSlotFeedback(showAvailableMessage ? (data.message || "Bu saat müsait.") : "", true);
        return true;
      })
      .catch(function () {
        setSlotFeedback("", true);
        return true;
      });
  }

  function slotPillClass(slot) {
    if (slot.status === "available") return "available";
    if (slot.status === "busy") return "busy";
    if (slot.status === "past") return "past";
    return "taken";
  }

  function setAdminSlotFeedback(message, ok) {
    var feedback = document.getElementById("adminSlotFeedback");
    if (!feedback) return;
    feedback.textContent = message || "";
    feedback.className = "slot-feedback admin-slot-feedback" + (message ? (ok ? " available" : " unavailable") : "");
  }

  // Kullanıcıda randevu olan, adminin kapattığı ve geçmiş saatler "Dolu" görünür.
  function weekCellInfo(slot, isAdmin) {
    if (slot.status === "available") {
      return { label: "Müsait", css: "available", clickable: true, mode: "block" };
    }
    if (slot.status === "busy") {
      return isAdmin
        ? { label: "Dolu", css: "busy", clickable: true, mode: "unblock" }
        : { label: "Dolu", css: "busy", clickable: false, mode: "" };
    }
    if (slot.status === "booked") {
      return { label: "Dolu", css: "booked", clickable: false, mode: "" };
    }
    return { label: isAdmin ? "Geçmiş" : "Dolu", css: "past", clickable: false, mode: "" };
  }

  function shiftWeek(anchor, days) {
    var parts = String(anchor || "").split("-");
    var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    if (isNaN(date.getTime())) return anchor;
    date.setDate(date.getDate() + days);
    return date.getFullYear() + "-" +
      ("0" + (date.getMonth() + 1)).slice(-2) + "-" +
      ("0" + date.getDate()).slice(-2);
  }

  function loadWeekTable(context) {
    var isAdmin = context === "admin";
    var head = document.getElementById(isAdmin ? "adminWeekTableHead" : "userWeekTableHead");
    var body = document.getElementById(isAdmin ? "adminWeekTableBody" : "userWeekTableBody");
    var anchorInput = document.getElementById(isAdmin ? "admin_week_anchor" : "week_anchor");
    var label = document.getElementById(isAdmin ? "adminWeekLabel" : "weekLabel");
    if (!head || !body || !anchorInput) return;

    var selectedDate = "";
    var selectedTime = "";
    if (!isAdmin) {
      var dateInput = document.getElementById("appointment_date");
      var timeInput = document.getElementById("appointment_time");
      selectedDate = dateInput ? dateInput.value : "";
      selectedTime = timeInput ? timeInput.value : "";
    }

    fetch("admin.php?action=appointments-week&date=" + encodeURIComponent(anchorInput.value), { headers: { "X-Requested-With": "fetch" } })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.ok || !Array.isArray(data.days) || !data.days.length) {
          head.innerHTML = '<tr><th colspan="7" class="public-table-empty">Tablo yüklenemedi.</th></tr>';
          body.innerHTML = '<tr><td colspan="7" class="public-table-empty">Haftalık tablo yüklenemedi.</td></tr>';
          return;
        }

        if (label) {
          label.textContent = data.days[0].full_label + " - " + data.days[6].full_label;
        }

        head.innerHTML = "<tr>" + data.days.map(function (day) {
          return '<th class="' + (day.is_today ? "week-today" : "") + '" title="' + escapeHtml(day.day_name) + '">' +
            '<span class="week-day-name">' + escapeHtml(day.day_short) + "</span>" +
            '<span class="week-day-date">' + escapeHtml(day.date_label) + "</span>" +
            "</th>";
        }).join("") + "</tr>";

        var hours = data.days[0].slots;
        body.innerHTML = hours.map(function (slot, index) {
          var cells = data.days.map(function (day) {
            var cellSlot = day.slots[index] || slot;
            var info = weekCellInfo(cellSlot, isAdmin);
            var selected = !isAdmin && selectedDate === day.date && selectedTime === cellSlot.time;
            var classes = "week-cell week-" + info.css +
              (info.clickable ? " week-clickable" : "") +
              (selected ? " selected" : "");
            var attrs = info.clickable
              ? ' data-slot-date="' + escapeHtml(day.date) + '" data-slot-time="' + escapeHtml(cellSlot.time) + '"' +
                (isAdmin ? ' data-slot-mode="' + info.mode + '"' : "") + ' role="button" tabindex="0"'
              : ' aria-disabled="true"';
            var text = cellSlot.status === "available"
              ? escapeHtml(cellSlot.label)
              : escapeHtml(info.label);
            var title = day.day_name + " " + day.date_label + " · " + cellSlot.label + " · " + info.label;
            return '<td class="' + classes + '"' + attrs + ' title="' + escapeHtml(title) + '">' + text + "</td>";
          }).join("");
          return "<tr>" + cells + "</tr>";
        }).join("");
      })
      .catch(function () {
        head.innerHTML = '<tr><th colspan="7" class="public-table-empty">Tablo yüklenemedi.</th></tr>';
        body.innerHTML = '<tr><td colspan="7" class="public-table-empty">Haftalık tablo yüklenemedi.</td></tr>';
      });
  }

  function initWeekNav(context) {
    var isAdmin = context === "admin";
    var anchorInput = document.getElementById(isAdmin ? "admin_week_anchor" : "week_anchor");
    if (!anchorInput) return;

    var prev = document.getElementById(isAdmin ? "adminWeekPrev" : "weekPrev");
    var today = document.getElementById(isAdmin ? "adminWeekToday" : "weekToday");
    var next = document.getElementById(isAdmin ? "adminWeekNext" : "weekNext");

    function go(days) {
      if (days === 0) {
        anchorInput.value = anchorInput.getAttribute("data-today") || shiftWeek(anchorInput.value, 0);
      } else {
        anchorInput.value = shiftWeek(anchorInput.value, days);
      }
      loadWeekTable(context);
    }

    if (prev) prev.addEventListener("click", function () { go(-7); });
    if (today) today.addEventListener("click", function () { go(0); });
    if (next) next.addEventListener("click", function () { go(7); });
  }

  function toggleAdminSlot(cell) {
    var reasonInput = document.getElementById("admin_slot_reason");
    var csrfInput = document.getElementById("admin_csrf_token");
    var formData = new FormData();
    if (csrfInput) formData.append("csrf", csrfInput.value);
    formData.append("date", cell.getAttribute("data-slot-date"));
    formData.append("time", cell.getAttribute("data-slot-time"));
    formData.append("mode", cell.getAttribute("data-slot-mode") || "block");
    formData.append("reason", reasonInput ? reasonInput.value.trim() : "");

    setAdminSlotFeedback("Kaydediliyor...", true);

    fetch("admin.php?action=appointments-busy-toggle", { method: "POST", body: formData })
      .then(function (response) {
        return response.json()
          .catch(function () { return { ok: false, error: "İşlem kaydedilemedi." }; })
          .then(function (data) { return { status: response.status, data: data }; });
      })
      .then(function (result) {
        if (!result.data.ok) {
          setAdminSlotFeedback(result.data.error || "İşlem kaydedilemedi.", false);
          return;
        }
        setAdminSlotFeedback(result.data.message || "Güncellendi.", true);
        loadWeekTable("admin");
      })
      .catch(function () {
        setAdminSlotFeedback("İşlem kaydedilemedi.", false);
      });
  }

  function initAdminSlotTable() {
    var body = document.getElementById("adminWeekTableBody");
    if (!body) return;

    function handle(e) {
      var cell = e.target.closest("td.week-cell[data-slot-date]");
      if (!cell) return;
      if (e.type === "keydown") {
        if (e.key !== "Enter" && e.key !== " ") return;
        e.preventDefault();
      }
      toggleAdminSlot(cell);
    }

    body.addEventListener("click", handle);
    body.addEventListener("keydown", handle);

    initWeekNav("admin");
    loadWeekTable("admin");
  }

  function initContactForm() {
    var form = document.getElementById("contactForm");
    if (!form) return;

    ["name", "phone", "email"].forEach(function (id) {
      var input = document.getElementById(id);
      if (input) input.addEventListener("input", function () { clearError("field-" + id); });
    });
    var privacyInput = document.getElementById("privacy");
    if (privacyInput) privacyInput.addEventListener("change", function () { clearError("field-privacy"); });

    var userWeekBody = document.getElementById("userWeekTableBody");
    if (userWeekBody) {
      function applySelection(date, time, cell) {
        var dateInput = document.getElementById("appointment_date");
        var timeInput = document.getElementById("appointment_time");
        if (dateInput) dateInput.value = date;
        if (timeInput) timeInput.value = time;

        // Metin değişmez (başlangıç saati kalır); sadece sınıf değişir.
        // Böylece seçimde hücre/satır boyutu oynamaz.
        var previous = userWeekBody.querySelector("td.week-cell.selected");
        if (previous) previous.classList.remove("selected");
        cell.classList.add("selected");

        var selectedLabel = document.getElementById("selectedSlotLabel");
        if (selectedLabel) selectedLabel.textContent = "Seçilen: " + date + " " + String(time).substring(0, 5);

        clearError("field-appointment_time");
        setSlotFeedback("Seçildi: " + date + " " + String(time).substring(0, 5) + " saati seçildi.", true);
      }

      // Sayfa açık kaldıysa admin sonradan bir saati dolu yapmış olabilir:
      // tıklamada sunucudan tekrar doğrula, doluysa tabloyu yenile.
      function selectWeekCell(cell) {
        if (!cell.classList.contains("week-available")) return;

        var date = cell.getAttribute("data-slot-date");
        var time = cell.getAttribute("data-slot-time");
        var url = "admin.php?action=appointments-check-slot&appointment_date=" +
          encodeURIComponent(date) + "&appointment_time=" + encodeURIComponent(time);

        fetch(url, { headers: { "X-Requested-With": "fetch" } })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data.ok || !data.available) {
              setSlotFeedback(data.message || "Bu saat artık dolu. Lütfen farklı bir saat seçin.", false);
              loadWeekTable("user");
              return;
            }
            applySelection(date, time, cell);
          })
          .catch(function () {
            applySelection(date, time, cell);
          });
      }

      function handleWeekClick(e) {
        var cell = e.target.closest("td.week-cell[data-slot-date]");
        if (!cell) return;
        if (e.type === "keydown") {
          if (e.key !== "Enter" && e.key !== " ") return;
          e.preventDefault();
        }
        selectWeekCell(cell);
      }

      userWeekBody.addEventListener("click", handleWeekClick);
      userWeekBody.addEventListener("keydown", handleWeekClick);
    }

    initWeekNav("user");
    loadWeekTable("user");

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var valid = true;
      var missing = [];

      var name = document.getElementById("name").value.trim();
      if (!name || name.length < 2) { document.getElementById("field-name").classList.add("has-error"); missing.push("Ad Soyad"); valid = false; }

      var phone = document.getElementById("phone").value.trim();
      if (!phone || phone.replace(/\D/g, "").length < 10) { document.getElementById("field-phone").classList.add("has-error"); missing.push("Telefon"); valid = false; }

      var email = document.getElementById("email").value.trim();
      var emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email || !emailRx.test(email)) { document.getElementById("field-email").classList.add("has-error"); missing.push("E-posta"); valid = false; }

      var privacy = document.getElementById("privacy").checked;
      if (!privacy) { document.getElementById("field-privacy").classList.add("has-error"); missing.push("Gizlilik politikası onayı"); valid = false; }

      var apptDate = document.getElementById("appointment_date").value;
      var apptTime = document.getElementById("appointment_time").value;
      var slotField = document.getElementById("field-appointment_time");
      if (!apptDate || !apptTime) {
        if (slotField) slotField.classList.add("has-error");
        missing.push("Randevu tarihi ve saati (tablodan seçim)");
        valid = false;
      }

      if (!valid) {
        setSubmitFeedback("Lütfen eksik bilgileri tamamlayın: " + missing.join(", ") + ".", false);
        return;
      }
      setSubmitFeedback("", true);

      checkSlotAvailability(false).then(function (available) {
        if (!available) return;

        var coachingType = document.getElementById("coaching-type").value;
        var typeLabel = coachingType === "egitim" ? "Öğrenci Koçluğu" : coachingType === "kariyer" ? "Kariyer Koçluğu" : "Koçluk hizmetleri";
        var msg = "Merhaba Gonca Hanım, ben " + name + ". " + typeLabel + " hakkında ücretsiz ön görüşme talep etmek istiyorum. Telefon: " + phone;

        var formData = new FormData(form);
        var messageInput = document.getElementById("message");
        formData.append("notes", messageInput ? messageInput.value.trim() : "");

        fetch("admin.php?action=appointments-public-save", { method: "POST", body: formData })
          .then(function (response) {
            return response.json()
              .catch(function () { return { ok: false, error: "Randevu talebi kaydedilemedi." }; })
              .then(function (data) { return { status: response.status, data: data }; });
          })
          .then(function (result) {
            if (!result.data.ok) {
              var saveError = result.data.error || "Randevu talebi kaydedilemedi.";
              setSlotFeedback(saveError, false);
              setSubmitFeedback(saveError, false);
              loadWeekTable("user");
              return;
            }
            form.style.display = "none";
            document.getElementById("formSuccess").classList.add("show");
            loadWeekTable("user");
            window.open(waLink(msg), "_blank");
          })
          .catch(function () {
            var networkError = "Randevu talebi gönderilemedi. Lütfen tekrar deneyin.";
            setSlotFeedback(networkError, false);
            setSubmitFeedback(networkError, false);
          });
      });
    });
  }

  function waLink(text) {
    return "https://wa.me/905436839902?text=" + encodeURIComponent(text);
  }

  document.addEventListener("DOMContentLoaded", function () {
    initHamburger();
    initStickyHeader();
    initFaq();
    initTestimonialCarousel();
    initContactForm();
    initAdminSlotTable();
  });
})();
