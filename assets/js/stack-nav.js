document.addEventListener("DOMContentLoaded", function () {
    var currentPath = window.location.pathname.replace(/\/$/, "");
    var links = document.querySelectorAll(".stack-nav a[href]");
    links.forEach(function (link) {
        var linkPath = link.pathname.replace(/\/$/, "");
        if (linkPath === currentPath) link.classList.add("active");
    });
});
