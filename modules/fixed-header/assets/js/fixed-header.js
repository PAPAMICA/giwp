jQuery(document).ready(function($) {
    // Fonction pour mettre à jour les informations de debug
    function updateDebugInfo(message, selector, height) {
        if (!window.giwpFixedHeader || !window.giwpFixedHeader.debug) return;
        
        $('#giwp-header-status').text('Statut: ' + message);
        $('#giwp-header-selector').text('Sélecteur: ' + selector);
        if (height) {
            $('#giwp-header-height').text('Hauteur: ' + height + 'px');
        }
    }

    // Fonction pour initialiser le header fixe
    function initFixedHeader() {
        // Utiliser le sélecteur des paramètres
        const headerSelector = window.giwpFixedHeader ? window.giwpFixedHeader.headerSelector : '.elementor-section[data-id="1640de85"]';
        const $header = $(headerSelector);
        
        if ($header.length === 0) {
            updateDebugInfo('Header non trouvé', headerSelector);
            console.error('GIWP: Header non trouvé avec le sélecteur:', headerSelector);
            return;
        }

        // Ajouter la classe pour le style fixe
        $header.addClass('giwp-fixed-header');

        // Calculer et appliquer la hauteur du header
        const headerHeight = $header.outerHeight();
        document.documentElement.style.setProperty('--header-height', headerHeight + 'px');
        $('body').css('padding-top', headerHeight + 'px');

        updateDebugInfo('Initialisé avec succès', headerSelector, headerHeight);

        // Gérer le scroll
        let lastScroll = 0;
        $(window).scroll(function() {
            const currentScroll = $(this).scrollTop();
            
            // Si on scrolle vers le bas et qu'on a dépassé la hauteur du header
            if (currentScroll > lastScroll && currentScroll > headerHeight) {
                $header.css('transform', 'translateY(-100%)');
                updateDebugInfo('Masqué', headerSelector, headerHeight);
            } else {
                $header.css('transform', 'translateY(0)');
                updateDebugInfo('Visible', headerSelector, headerHeight);
            }
            
            lastScroll = currentScroll;
        });

        // Gérer le redimensionnement de la fenêtre
        $(window).resize(function() {
            const newHeaderHeight = $header.outerHeight();
            document.documentElement.style.setProperty('--header-height', newHeaderHeight + 'px');
            $('body').css('padding-top', newHeaderHeight + 'px');
            updateDebugInfo('Redimensionné', headerSelector, newHeaderHeight);
        });

        console.log('GIWP: Header fixe initialisé avec succès');
    }

    // Initialiser après un court délai pour s'assurer que tout est chargé
    setTimeout(initFixedHeader, 500);
}); 