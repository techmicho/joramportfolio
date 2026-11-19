/* =========================================
   Joram Rusiana — Personal Portfolio
   script.js
   ========================================= */

document.addEventListener("DOMContentLoaded", function () {
  highlightActiveNavLink();
  setUpMobileNav();
  setUpThemeToggle();
  setUpScrollToTop();
  setUpTypingEffect();
  setUpContactForm();
});

function highlightActiveNavLink() {
  var currentPage = window.location.pathname.split("/").pop() || "index.php";
  document.querySelectorAll(".nav-link").forEach(function (link) {
    var href = link.getAttribute("href") || "";
    if (href.replace(/^\.\//, "") === currentPage) link.classList.add("active");
  });
}

function setUpMobileNav() {
  var navToggle = document.getElementById("nav-toggle");
  var mainNav = document.getElementById("main-nav");
  if (!navToggle || !mainNav) return;

  navToggle.addEventListener("click", function () {
    mainNav.classList.toggle("open");
    navToggle.setAttribute("aria-expanded", mainNav.classList.contains("open"));
  });

  mainNav.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () {
      mainNav.classList.remove("open");
      navToggle.setAttribute("aria-expanded", "false");
    });
  });
}

function setUpThemeToggle() {
  var themeToggle = document.getElementById("theme-toggle");
  if (!themeToggle) return;

  updateThemeButtonIcon();
  themeToggle.addEventListener("click", function () {
    var isDark = document.documentElement.getAttribute("data-theme") === "dark";
    var newTheme = isDark ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", newTheme);
    localStorage.setItem("portfolio-theme", newTheme);
    updateThemeButtonIcon();
  });
}

function updateThemeButtonIcon() {
  var themeToggle = document.getElementById("theme-toggle");
  if (!themeToggle) return;
  themeToggle.textContent = document.documentElement.getAttribute("data-theme") === "dark" ? "Light" : "Dark";
}

function setUpScrollToTop() {
  var scrollBtn = document.getElementById("scroll-top-btn");
  if (!scrollBtn) return;

  window.addEventListener("scroll", function () {
    scrollBtn.classList.toggle("show", window.scrollY > 300);
  });
  scrollBtn.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
}

function setUpTypingEffect() {
  var typingEl = document.getElementById("typing-role");
  if (!typingEl) return;

  var roles = ["BSIT Student", "Web Developer", "Aspiring Software Engineer"];
  var roleIndex = 0;
  var charIndex = 0;
  var isDeleting = false;

  function tick() {
    var currentRole = roles[roleIndex];
    charIndex += isDeleting ? -1 : 1;
    typingEl.textContent = currentRole.substring(0, charIndex);
    var typingSpeed = isDeleting ? 45 : 90;

    if (!isDeleting && charIndex === currentRole.length) {
      typingSpeed = 1400;
      isDeleting = true;
    } else if (isDeleting && charIndex === 0) {
      isDeleting = false;
      roleIndex = (roleIndex + 1) % roles.length;
      typingSpeed = 400;
    }
    setTimeout(tick, typingSpeed);
  }
  tick();
}

function setUpContactForm() {
  var form = document.getElementById("contact-form");
  if (!form) return;

  form.addEventListener("submit", function (event) {
    var isNameValid = validateRequired("name", "Please enter your name.");
    var isEmailValid = validateEmail("email");
    var isMessageValid = validateRequired("message", "Please enter a message.");
    if (!isNameValid || !isEmailValid || !isMessageValid) event.preventDefault();
  });

  ["name", "email", "message"].forEach(function (fieldId) {
    var field = document.getElementById(fieldId);
    if (!field) return;
    field.addEventListener("input", function () {
      if (fieldId === "email") validateEmail(fieldId);
      else validateRequired(fieldId, "This field is required.");
    });
  });
}

function validateRequired(fieldId, message) {
  var field = document.getElementById(fieldId);
  var errorEl = document.getElementById(fieldId + "-error");
  if (!field) return true;
  var value = field.value.trim();
  if (value === "") {
    showFieldError(field, errorEl, message);
    return false;
  }
  clearFieldError(field, errorEl);
  return true;
}

function validateEmail(fieldId) {
  var field = document.getElementById(fieldId);
  var errorEl = document.getElementById(fieldId + "-error");
  if (!field) return true;
  var value = field.value.trim();
  var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (value === "") {
    showFieldError(field, errorEl, "Please enter your email.");
    return false;
  }
  if (!emailPattern.test(value)) {
    showFieldError(field, errorEl, "Please enter a valid email address.");
    return false;
  }
  clearFieldError(field, errorEl);
  return true;
}

function showFieldError(field, errorEl, message) {
  field.classList.add("invalid");
  if (errorEl) errorEl.textContent = message;
}

function clearFieldError(field, errorEl) {
  field.classList.remove("invalid");
  if (errorEl) errorEl.textContent = "";
}
