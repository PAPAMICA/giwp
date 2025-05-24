<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap giwp-admin">
    <h1>GIWP - Gestionnaire d'Installation WordPress</h1>
    
    <div class="giwp-admin-header">
        <div class="giwp-admin-header-actions">
            <button id="giwp-export-settings" class="button">
                <span class="dashicons dashicons-download"></span>
                Exporter les paramètres
            </button>
            <button id="giwp-import-settings" class="button">
                <span class="dashicons dashicons-upload"></span>
                Importer les paramètres
            </button>
            <input type="file" id="giwp-import-file" style="display: none;" accept=".json">
        </div>
    </div>

    <div class="giwp-modules-grid">
        <?php
        $modules = GIWP()->modules;
        foreach ($modules as $module) :
            $is_active = $module->is_active();
            ?>
            <div class="giwp-module-card">
                <div class="giwp-module-header">
                    <h2><?php echo esc_html($module->get_name()); ?></h2>
                    <label class="giwp-switch">
                        <input type="checkbox" 
                               class="giwp-module-toggle" 
                               data-module="<?php echo esc_attr($module->get_id()); ?>"
                               <?php checked($is_active); ?>>
                        <span class="giwp-slider"></span>
                    </label>
                </div>
                
                <p class="giwp-module-description">
                    <?php echo esc_html($module->get_description()); ?>
                </p>
                
                <?php if ($is_active) : ?>
                    <div class="giwp-module-settings">
                        <?php $module->render_settings_page(); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.giwp-admin {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
}

.giwp-admin-header {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 30px;
}

.giwp-admin-header-actions .button {
    margin-left: 10px;
}

.giwp-modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.giwp-module-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.giwp-module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.giwp-module-header h2 {
    margin: 0;
    font-size: 1.4em;
}

.giwp-module-description {
    color: #666;
    margin-bottom: 20px;
}

/* Switch Toggle */
.giwp-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.giwp-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.giwp-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.giwp-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .giwp-slider {
    background-color: #2196F3;
}

input:checked + .giwp-slider:before {
    transform: translateX(26px);
}

/* Module Settings */
.giwp-module-settings {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .giwp-modules-grid {
        grid-template-columns: 1fr;
    }
    
    .giwp-admin-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .giwp-admin-header-actions .button {
        margin: 5px 0;
        width: 100%;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Gestion de l'activation/désactivation des modules
    $('.giwp-module-toggle').on('change', function() {
        const moduleId = $(this).data('module');
        const isActive = $(this).prop('checked');
        const card = $(this).closest('.giwp-module-card');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'giwp_toggle_module',
                module: moduleId,
                active: isActive,
                nonce: giwpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erreur lors de la modification du statut du module');
                    $(this).prop('checked', !isActive);
                }
            },
            error: function() {
                alert('Erreur de connexion');
                $(this).prop('checked', !isActive);
            }
        });
    });

    // Export des paramètres
    $('#giwp-export-settings').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'giwp_export_settings',
                nonce: giwpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Créer un lien de téléchargement
                    const blob = new Blob([JSON.stringify(response.data)], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'giwp-settings.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                } else {
                    alert('Erreur lors de l\'export des paramètres');
                }
            }
        });
    });

    // Import des paramètres
    $('#giwp-import-settings').on('click', function() {
        $('#giwp-import-file').click();
    });

    $('#giwp-import-file').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const settings = JSON.parse(e.target.result);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'giwp_import_settings',
                        settings: settings,
                        nonce: giwpAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Erreur lors de l\'import des paramètres');
                        }
                    }
                });
            } catch (error) {
                alert('Fichier de paramètres invalide');
            }
        };
        reader.readAsText(file);
    });
});
</script> 