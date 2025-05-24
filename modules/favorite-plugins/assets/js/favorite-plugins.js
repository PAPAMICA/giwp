jQuery(document).ready(function($) {
    // Sauvegarde des paramètres des plugins
    $('#save-plugin-settings').on('click', function() {
        const plugins = {};
        
        $('.giwp-plugin-card').each(function() {
            const card = $(this);
            const slug = card.data('plugin');
            const enabled = card.find('.giwp-plugin-enabled').prop('checked');
            
            plugins[slug] = {
                enabled: enabled,
                config: {}
            };
            
            // Récupérer la configuration de chaque option
            card.find('.giwp-config-option').each(function() {
                const option = $(this);
                const optionName = option.find('.giwp-config-enabled').data('option');
                plugins[slug].config[optionName] = {
                    enabled: option.find('.giwp-config-enabled').prop('checked')
                };
            });
        });
        
        $.ajax({
            url: giwpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'giwp_save_plugin_list',
                plugins: JSON.stringify(plugins),
                nonce: giwpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Paramètres sauvegardés avec succès !');
                } else {
                    alert('Erreur lors de la sauvegarde des paramètres');
                }
            },
            error: function() {
                alert('Erreur de connexion');
            }
        });
    });

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
        const applyConfig = card.find('.giwp-config-enabled:checked').length > 0;

        button.prop('disabled', true);
        status.html('Activation en cours...');

        $.ajax({
            url: giwpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'giwp_activate_plugin',
                plugin: plugin,
                apply_config: applyConfig,
                nonce: giwpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.html('Activation' + (applyConfig ? ' et configuration' : '') + ' réussie !');
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