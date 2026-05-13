// ------------------------------------------------------
// OktoDark Modern Script (Bootstrap 5)
// ------------------------------------------------------

document.addEventListener("DOMContentLoaded", function() {

    // --------------------------------------------------
    // Smooth scroll for internal links (exclude modal link)
    // --------------------------------------------------
    document.querySelectorAll('a[href^="#"]:not([data-bs-toggle]):not([id="open-x1"])')
        .forEach(function(anchor) {
            anchor.addEventListener("click", function (e) {
                var target = document.querySelector(this.getAttribute("href"));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: "smooth" });
                }
            });
        });

    // --------------------------------------------------
    // Bootstrap tooltips
    // --------------------------------------------------
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el);
    });

    // --------------------------------------------------
    // Bootstrap popovers
    // --------------------------------------------------
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
        new bootstrap.Popover(el);
    });

    // --------------------------------------------------
    // Fade-in animation on scroll
    // --------------------------------------------------
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
            }
        });
    });

    document.querySelectorAll(".fade-in").forEach(function(el) { observer.observe(el); });

    // --------------------------------------------------
    // Preloader
    // --------------------------------------------------
    var preloader = document.getElementById("preloader");
    if (preloader) {
        setTimeout(function() {
            preloader.classList.add("hidden");
        }, 300);
    }

    // --------------------------------------------------
    // Hover dropdowns (desktop only)
    // --------------------------------------------------
    var dropdowns = document.querySelectorAll(".navbar .dropdown");

    dropdowns.forEach(function(drop) {
        drop.addEventListener("mouseenter", function() {
            if (window.innerWidth > 992) {
                var menu = drop.querySelector(".dropdown-menu");
                if (menu) menu.classList.add("show");
            }
        });

        drop.addEventListener("mouseleave", function() {
            if (window.innerWidth > 992) {
                var menu = drop.querySelector(".dropdown-menu");
                if (menu) menu.classList.remove("show");
            }
        });
    });

    // --------------------------------------------------
    // Hero Slider (auto-slide)
    // --------------------------------------------------
    var slides = document.querySelectorAll(".hero-slider .slide");
    console.log("Slides found:", slides.length);

    if (slides.length > 1) {
        var index = 0;

        function showSlide(i) {
            slides.forEach(function(slide) { slide.classList.remove("active"); });
            slides[i].classList.add("active");
        }

        showSlide(0);

        setInterval(function() {
            index = (index + 1) % slides.length;
            showSlide(index);
        }, 5000);
    }

    // --------------------------------------------------
    // STEALTH PREFERENCES BOX
    // --------------------------------------------------
    var box = document.getElementById("a9f2");
    var boxSave = document.getElementById("a9f2-save");
    var boxOpen = document.getElementById("open-x1");
    var boxClose = document.getElementById("a9f2-close");

    // Load saved preferences
    var savedPrefs = localStorage.getItem("prefsA1");

    if (savedPrefs) {
        var prefs = JSON.parse(savedPrefs);
        document.getElementById("opt-a1").checked = prefs.a1;
        document.getElementById("opt-a2").checked = prefs.a2;
    }

    // Auto-open if no preferences saved
    if (!savedPrefs) {
        box.style.display = "flex";
    }

    // Open from footer link
    if (boxOpen) {
        boxOpen.addEventListener("click", function(e) {
            e.preventDefault();
            box.style.display = "flex";
        });
    }

    // Save preferences
    boxSave.addEventListener("click", function() {
        var a1 = document.getElementById("opt-a1").checked;
        var a2 = document.getElementById("opt-a2").checked;

        localStorage.setItem("prefsA1", JSON.stringify({ a1: a1, a2: a2 }));
        box.style.display = "none";
    });

    // Close with X
    boxClose.addEventListener("click", function() {
        box.style.display = "none";
    });

    // Close when clicking outside
    box.addEventListener("click", function(e) {
        if (e.target === box) {
            box.style.display = "none";
        }
    });

    // --------------------------------------------------
    // Flatpickr Date Picker Initialization
    // --------------------------------------------------
    if (typeof flatpickr !== 'undefined') {
        var datePickers = document.querySelectorAll('[data-date-format]');
        datePickers.forEach(function(picker) {
            var format = picker.getAttribute('data-date-format') || 'Y-m-d H:i';
            var locale = picker.getAttribute('data-date-locale') || 'en';
            
            flatpickr(picker, {
                enableTime: true,
                dateFormat: format,
                time_24hr: true,
                locale: locale,
                altInput: true,
                altFormat: "F j, Y H:i",
                allowInput: true
            });
        });
    }

});

// ------------------------------------------------------
// Navbar scroll effect
// ------------------------------------------------------
document.addEventListener("scroll", function() {
    var nav = document.querySelector(".modern-nav");
    if (!nav) return;

    if (window.scrollY > 50) {
        nav.classList.add("scrolled");
    } else {
        nav.classList.remove("scrolled");
    }
});