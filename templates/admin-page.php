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

    <div class="giwp-logs-section">
        <h2>Logs du plugin</h2>
        <div class="giwp-logs-container">
            <?php
            $logs = array_reverse(GIWP()->get_logs());
            if (empty($logs)) :
                ?>
                <p class="giwp-no-logs">Aucun log disponible</p>
            <?php else : ?>
                <table class="giwp-logs-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <tr class="giwp-log-entry giwp-log-<?php echo esc_attr($log['type']); ?>">
                                <td class="giwp-log-time">
                                    <?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($log['time']))); ?>
                                </td>
                                <td class="giwp-log-type">
                                    <?php echo esc_html(ucfirst($log['type'])); ?>
                                </td>
                                <td class="giwp-log-message">
                                    <?php echo esc_html($log['message']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="giwp-modules-section">
        <h2>Modules</h2>
        <?php
        $modules = GIWP()->modules;
        foreach ($modules as $module) :
            $is_active = $module->is_active();
            ?>
            <div class="giwp-module-card">
                <div class="giwp-module-header">
                    <h3><?php echo esc_html($module->get_name()); ?></h3>
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
    margin-bottom: 30px;
    text-align: right;
}

.giwp-admin-header-actions .button {
    margin-left: 10px;
}

.giwp-logs-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.giwp-logs-container {
    max-height: 300px;
    overflow-y: auto;
}

.giwp-logs-table {
    width: 100%;
    border-collapse: collapse;
}

.giwp-logs-table th,
.giwp-logs-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.giwp-log-entry {
    font-size: 13px;
}

.giwp-log-time {
    white-space: nowrap;
    color: #666;
}

.giwp-log-type {
    white-space: nowrap;
}

.giwp-log-success .giwp-log-type {
    color: #46b450;
}

.giwp-log-error .giwp-log-type {
    color: #dc3232;
}

.giwp-log-info .giwp-log-type {
    color: #00a0d2;
}

.giwp-module-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.giwp-module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.giwp-module-header h3 {
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

.giwp-module-settings {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

@media screen and (max-width: 782px) {
    .giwp-admin-header {
        text-align: center;
    }
    
    .giwp-admin-header-actions .button {
        margin: 5px;
        width: calc(50% - 10px);
    }

    .giwp-logs-table th:first-child,
    .giwp-logs-table td:first-child {
        display: none;
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