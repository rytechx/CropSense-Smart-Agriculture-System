(() => {
    "use strict";

    const header = document.querySelector("[data-public-header]");
    const navigation = document.getElementById("publicNavigation");
    const navigationLinks = document.querySelectorAll(".public-nav-links a[href^='#']");
    const observedLinks = new Map();

    const updateHeader = () => {
        if (header) {
            header.classList.toggle("is-scrolled", window.scrollY > 16);
        }
    };

    navigationLinks.forEach((link) => {
        const targetId = link.getAttribute("href").slice(1);
        const target = targetId === "home"
            ? document.querySelector(".public-hero")
            : document.getElementById(targetId);

        if (target) {
            observedLinks.set(target, link);
        }

        link.addEventListener("click", () => {
            if (!navigation || !navigation.classList.contains("show") || !window.bootstrap) {
                return;
            }

            window.bootstrap.Collapse.getOrCreateInstance(navigation).hide();
        });
    });

    if ("IntersectionObserver" in window && observedLinks.size > 0) {
        const sectionObserver = new IntersectionObserver((entries) => {
            const visibleEntry = entries
                .filter((entry) => entry.isIntersecting)
                .sort((left, right) => right.intersectionRatio - left.intersectionRatio)[0];

            if (!visibleEntry) {
                return;
            }

            navigationLinks.forEach((link) => {
                link.classList.remove("active");
                link.removeAttribute("aria-current");
            });

            const activeLink = observedLinks.get(visibleEntry.target);
            activeLink?.classList.add("active");
            activeLink?.setAttribute("aria-current", "page");
        }, {
            rootMargin: "-32% 0px -55%",
            threshold: [0, 0.15, 0.35]
        });

        observedLinks.forEach((_link, target) => sectionObserver.observe(target));
    }

    updateHeader();
    window.addEventListener("scroll", updateHeader, { passive: true });
})();
