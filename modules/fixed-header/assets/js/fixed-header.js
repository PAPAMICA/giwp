jQuery(document).ready(function($) {
    // Variables pour le scroll
    let lastScrollTop = 0;
    let header = $(document).find('.site-header, header.site-header, .elementor-location-header').first();
    let headerHeight = header.outerHeight();

    // Ajouter la classe de transition
    header.addClass('giwp-header-transition');

    // Définir la variable CSS pour la hauteur du header
    document.documentElement.style.setProperty('--header-height', headerHeight + 'px');

    // Fonction pour gérer le scroll
    function handleScroll() {
        let currentScroll = $(window).scrollTop();
        
        // Ajouter/Retirer la classe fixed en fonction du scroll
        if (currentScroll > headerHeight) {
            header.addClass('giwp-header-fixed');
        } else {
            header.removeClass('giwp-header-fixed');
        }

        // Cacher/Montrer le header en fonction de la direction du scroll
        if (currentScroll > lastScrollTop && currentScroll > headerHeight) {
            // Scroll vers le bas
            header.addClass('giwp-header-hidden');
        } else {
            // Scroll vers le haut
            header.removeClass('giwp-header-hidden');
        }

        lastScrollTop = currentScroll;
    }

    // Écouter l'événement de scroll avec throttle pour les performances
    let scrollTimeout;
    $(window).scroll(function() {
        if (!scrollTimeout) {
            scrollTimeout = setTimeout(function() {
                handleScroll();
                scrollTimeout = null;
            }, 10);
        }
    });

    // Gérer le redimensionnement de la fenêtre
    $(window).resize(function() {
        headerHeight = header.outerHeight();
        document.documentElement.style.setProperty('--header-height', headerHeight + 'px');
    });
}); 