jQuery(document).ready(function($) {
    // Installation d'un plugin
    $('.install-plugin').on('click', function() {
        const button = $(this);
        const card = button.closest('.giwp-plugin-card');
        const plugin = card.data('plugin');
        const status = card.find('.giwp-plugin-status');

        button.prop('disabled', true);
        status.html('Installation en cours...');

        $.ajax({
            url: giwpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'giwp_install_plugin',
                plugin: plugin,
                nonce: giwpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.html('Installation réussie !');
                    button.hide();
                    card.find('.activate-plugin').show();
                } else {
                    status.html('Erreur : ' + response.data);
                    button.prop('disabled', false);
                }
            },
            error: function() {
                status.html('Erreur de connexion');
                button.prop('disabled', false);
            }
        });
    });

    // Activation d'un plugin
    $('.activate-plugin').on('click', function() {
        const button = $(this);
        const card = button.closest('.giwp-plugin-card');
        const plugin = card.data('plugin');
        const status = card.find('.giwp-plugin-status');

        button.prop('disabled', true);
        status.html('Activation en cours...');

        $.ajax({
            url: giwpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'giwp_activate_plugin',
                plugin: plugin,
                nonce: giwpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.html('Activation et configuration réussies !');
                    button.text('Activé').addClass('button-disabled');
                } else {
                    status.html('Erreur : ' + response.data);
                    button.prop('disabled', false);
                }
            },
            error: function() {
                status.html('Erreur de connexion');
                button.prop('disabled', false);
            }
        });
    });

    // Vérification initiale de l'état des plugins
    function checkPluginStatus() {
        $('.giwp-plugin-card').each(function() {
            const card = $(this);
            const installButton = card.find('.install-plugin');
            const activateButton = card.find('.activate-plugin');
            
            // Par défaut, on cache le bouton d'activation
            activateButton.hide();

            // Vérifier si le plugin est déjà installé
            if (typeof giwpPluginStatus !== 'undefined' && giwpPluginStatus[card.data('plugin')]) {
                const status = giwpPluginStatus[card.data('plugin')];
                
                if (status.installed) {
                    installButton.hide();
                    activateButton.show();
                    
                    if (status.active) {
                        activateButton.text('Activé').addClass('button-disabled');
                    }
                }
            }
        });
    }

    // Exécuter la vérification au chargement
    checkPluginStatus();
}); 