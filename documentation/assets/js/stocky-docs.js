/* =========================================================
   Stocky SaaS — Docs UX layer
   Adds on top of doc.js: theme toggle, collapsible sidebar
   groups, search filter, back-to-top button.
   ========================================================= */
(function ($) {
   "use strict";

   /* ---------- Theme (light / dark) ---------- */
   var THEME_KEY = "stocky-docs-theme";
   var savedTheme = localStorage.getItem(THEME_KEY) || "light";
   document.documentElement.setAttribute("data-theme", savedTheme);

   $(document).on("click", ".theme-toggle", function () {
      var cur = document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
      var next = cur === "dark" ? "light" : "dark";
      document.documentElement.setAttribute("data-theme", next);
      localStorage.setItem(THEME_KEY, next);
   });

   /* ---------- Collapsible sidebar groups ---------- */
   $(function () {
      // Click the group label (.sidebar__list > li > a) to collapse/expand
      $(".sidebar__list > li > a").on("click", function (e) {
         e.preventDefault();
         $(this).parent().toggleClass("collapsed");
      });

      // Auto-open the group containing the initially active link
      var $active = $(".sidebar__list > li > ul > li > a.active").first();
      if ($active.length) {
         $active.closest(".sidebar__list > li").removeClass("collapsed");
      }
   });

   /* ---------- Sidebar search filter ---------- */
   $(function () {
      $(".sidebar-search input").on("input", function () {
         var q = ($(this).val() || "").trim().toLowerCase();

         $(".sidebar__list > li").each(function () {
            var $group = $(this);
            var anyVisible = false;

            $group.find("ul > li").each(function () {
               var txt = $(this).text().toLowerCase();
               var match = !q || txt.indexOf(q) !== -1;
               $(this).toggleClass("is-hidden", !match);
               if (match) anyVisible = true;
            });

            if (q) {
               // Hide groups with no match
               $group.toggle(anyVisible);
               if (anyVisible) $group.removeClass("collapsed");
            } else {
               $group.show();
            }
         });
      });
   });

   /* ---------- Back-to-top button ---------- */
   $(function () {
      var $btn = $(
         '<button type="button" class="back-to-top" aria-label="Back to top">' +
         '<i class="fas fa-arrow-up"></i></button>'
      ).appendTo("body");

      $(window).on("scroll", function () {
         $btn.toggleClass("visible", $(window).scrollTop() > 400);
      });

      $btn.on("click", function () {
         $("html, body").animate({ scrollTop: 0 }, 400);
      });
   });

   /* ---------- Close mobile sidebar after tap ---------- */
   $(function () {
      $(".sidebar__list > li > ul > li > a").on("click", function () {
         if (window.innerWidth <= 768) {
            $(".wrapper").removeClass("sidebar-open");
         }
      });
   });

})(jQuery);
