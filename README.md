# GIWP - Gestionnaire d'Installation WordPress

GIWP est un plugin WordPress modulaire conçu pour faciliter la gestion et la création de sites WordPress. Il propose différents modules qui peuvent être activés ou désactivés selon vos besoins.

## Fonctionnalités

### Système de Modules
- Architecture modulaire permettant d'activer/désactiver les fonctionnalités selon les besoins
- Import/Export des paramètres de configuration
- Interface d'administration intuitive

### Module Fixed Header
- Fixe automatiquement le header en haut de la page
- Compatible avec Elementor et les thèmes standards WordPress
- Animation fluide lors du défilement
- Options de personnalisation du comportement

### Module Favorite Plugins
- Installation en un clic des plugins favoris
- Configuration automatique post-installation
- Liste personnalisable de plugins pré-configurés
- Plugins par défaut inclus :
  - Elementor (avec configuration optimisée)
  - Yoast SEO (avec paramètres sociaux pré-configurés)
  - WP Super Cache (avec configuration de base)

## Installation

1. Téléchargez le plugin depuis le répertoire GitHub
2. Décompressez l'archive dans le dossier `/wp-content/plugins/`
3. Activez le plugin dans le menu "Extensions" de WordPress

## Configuration

### Configuration Générale
1. Accédez au menu "GIWP" dans le tableau de bord WordPress
2. Activez ou désactivez les modules selon vos besoins
3. Configurez chaque module activé selon vos préférences

### Module Fixed Header
1. Activez le module "Fixed Header"
2. Configurez le sélecteur CSS si nécessaire (par défaut compatible avec la plupart des thèmes)
3. Personnalisez l'apparence via les options disponibles

### Module Favorite Plugins
1. Activez le module "Favorite Plugins"
2. Accédez à la page des plugins favoris
3. Cliquez sur "Installer" pour les plugins souhaités
4. Les plugins seront automatiquement configurés après l'activation

## Import/Export des Paramètres

### Export
1. Accédez aux paramètres GIWP
2. Cliquez sur "Exporter les paramètres"
3. Sauvegardez le fichier JSON généré

### Import
1. Accédez aux paramètres GIWP
2. Cliquez sur "Importer les paramètres"
3. Sélectionnez votre fichier JSON
4. Confirmez l'importation

## Développement

### Ajouter un Nouveau Module
1. Créez un nouveau dossier dans `/modules/`
2. Créez une classe qui étend `GIWP_Module`
3. Implémentez les méthodes requises :
   - `init_module()`
   - `init()`
   - `render_settings_page()`

Exemple de structure pour un nouveau module :

```php
class GIWP_New_Module extends GIWP_Module {
    protected function init_module() {
        $this->id = 'new-module';
        $this->name = 'Nouveau Module';
        $this->description = 'Description du module';
    }

    public function init() {
        if (!$this->is_active()) {
            return;
        }
        // Initialisation du module
    }

    public function render_settings_page() {
        // Rendu de la page de paramètres
    }
}
```

## Support

Pour toute question ou problème :
1. Consultez les [Issues GitHub](https://github.com/papamica/giwp/issues)
2. Créez une nouvelle issue si nécessaire

## Contribution

Les contributions sont les bienvenues !
1. Forkez le projet
2. Créez une branche pour votre fonctionnalité
3. Committez vos changements
4. Poussez vers la branche
5. Créez une Pull Request

## Licence

Ce projet est sous licence GPL v2 ou ultérieure - voir le fichier [LICENSE](LICENSE) pour plus de détails.
