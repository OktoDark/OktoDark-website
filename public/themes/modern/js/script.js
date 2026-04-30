// ------------------------------------------------------
// OktoDark Modern Script (Bootstrap 5)
// ------------------------------------------------------

document.addEventListener("DOMContentLoaded", () => {

    // --------------------------------------------------
    // Smooth scroll for internal links (exclude modal link)
    // --------------------------------------------------
    document.querySelectorAll('a[href^="#"]:not([data-bs-toggle]):not([id="open-x1"])')
        .forEach(anchor => {
            anchor.addEventListener("click", function (e) {
                const target = document.querySelector(this.getAttribute("href"));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: "smooth" });
                }
            });
        });

    // --------------------------------------------------
    // Bootstrap tooltips
    // --------------------------------------------------
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // --------------------------------------------------
    // Bootstrap popovers
    // --------------------------------------------------
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el);
    });

    // --------------------------------------------------
    // Fade-in animation on scroll
    // --------------------------------------------------
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
            }
        });
    });

    document.querySelectorAll(".fade-in").forEach(el => observer.observe(el));

    // --------------------------------------------------
    // Preloader
    // --------------------------------------------------
    const preloader = document.getElementById("preloader");
    if (preloader) {
        setTimeout(() => {
            preloader.classList.add("hidden");
        }, 300);
    }

    // --------------------------------------------------
    // Hover dropdowns (desktop only)
    // --------------------------------------------------
    const dropdowns = document.querySelectorAll(".navbar .dropdown");

    dropdowns.forEach(drop => {
        drop.addEventListener("mouseenter", () => {
            if (window.innerWidth > 992) {
                const menu = drop.querySelector(".dropdown-menu");
                if (menu) menu.classList.add("show");
            }
        });

        drop.addEventListener("mouseleave", () => {
            if (window.innerWidth > 992) {
                const menu = drop.querySelector(".dropdown-menu");
                if (menu) menu.classList.remove("show");
            }
        });
    });

    // --------------------------------------------------
    // Hero Slider (auto-slide)
    // --------------------------------------------------
    const slides = document.querySelectorAll(".hero-slider .slide");
    console.log("Slides found:", slides.length);

    if (slides.length > 1) {
        let index = 0;

        function showSlide(i) {
            slides.forEach(slide => slide.classList.remove("active"));
            slides[i].classList.add("active");
        }

        showSlide(0);

        setInterval(() => {
            index = (index + 1) % slides.length;
            showSlide(index);
        }, 5000);
    }

    // --------------------------------------------------
    // STEALTH PREFERENCES BOX
    // --------------------------------------------------
    const box = document.getElementById("a9f2");
    const boxSave = document.getElementById("a9f2-save");
    const boxOpen = document.getElementById("open-x1");
    const boxClose = document.getElementById("a9f2-close");

    // Load saved preferences
    const savedPrefs = localStorage.getItem("prefsA1");

    if (savedPrefs) {
        const prefs = JSON.parse(savedPrefs);
        document.getElementById("opt-a1").checked = prefs.a1;
        document.getElementById("opt-a2").checked = prefs.a2;
    }

    // Auto-open if no preferences saved
    if (!savedPrefs) {
        box.style.display = "flex";
    }

    // Open from footer link
    if (boxOpen) {
        boxOpen.addEventListener("click", (e) => {
            e.preventDefault();
            box.style.display = "flex";
        });
    }

    // Save preferences
    boxSave.addEventListener("click", () => {
        const a1 = document.getElementById("opt-a1").checked;
        const a2 = document.getElementById("opt-a2").checked;

        localStorage.setItem("prefsA1", JSON.stringify({ a1, a2 }));
        box.style.display = "none";
    });

    // Close with X
    boxClose.addEventListener("click", () => {
        box.style.display = "none";
    });

    // Close when clicking outside
    box.addEventListener("click", (e) => {
        if (e.target === box) {
            box.style.display = "none";
        }
    });

    // --------------------------------------------------
    // Flatpickr Date Picker Initialization
    // --------------------------------------------------
    if (typeof flatpickr !== 'undefined') {
        const datePickers = document.querySelectorAll('[data-date-format]');
        datePickers.forEach(picker => {
            const format = picker.getAttribute('data-date-format') || 'Y-m-d H:i';
            const locale = picker.getAttribute('data-date-locale') || 'en';
            
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
document.addEventListener("scroll", () => {
    const nav = document.querySelector(".modern-nav");
    if (!nav) return;

    if (window.scrollY > 50) {
        nav.classList.add("scrolled");
    } else {
        nav.classList.remove("scrolled");
    }
});