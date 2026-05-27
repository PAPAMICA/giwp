=== MainWP GI-Web Extension ===
Contributors: genevois-informatique
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Gérez et déployez GI-Toolkit sur tous vos sites WordPress via MainWP.

== Description ==

Cette extension MainWP permet de :

* Voir l’état GI-Toolkit de chaque site enfant
* Importer une configuration depuis un site
* Modifier les modules actifs sur le dashboard
* Enregistrer des modèles de configuration
* Déployer en masse (secrets inclus)
* Définir des exclusions par site
* Consulter l’historique des déploiements

== Installation ==

1. Installez et activez **MainWP Dashboard** sur ce site.
2. Copiez le dossier `mainwp_giwp/` dans `wp-content/plugins/`.
3. Activez le plugin **MainWP GI-Toolkit Manager** (`mainwp-giwp.php`) dans Extensions WordPress, puis dans **MainWP > Extensions**.
4. Sur **chaque site enfant** :
   - MainWP Child actif
   - GI-Toolkit >= 2.20.1 (le bridge MainWP est inclus dans GI-Toolkit, aucun plugin supplémentaire)

== Utilisation ==

MainWP > Extensions > GI-Toolkit Manager

1. **Vue d’ensemble** : synchronisez les statuts, importez une config depuis un site.
2. **Modules** : activez/désactivez des modules dans la configuration de travail.
3. **Modèles** : enregistrez des snapshots nommés.
4. **Déploiement** : sélectionnez les sites et déployez.
5. **Exclusions** : modules à ne pas écraser par site.
6. **Historique** : suivi des déploiements.
7. **Réglages** : profil par défaut, options d’onboarding.
8. **Ajout de site MainWP** : installer GI-Toolkit depuis le ZIP monorepo et appliquer un profil (défaut : Default).

== Tests manuels (checklist) ==

- [ ] Site sans GI-Toolkit : statut erreur clair
- [ ] Import config site A → bundle de travail rempli
- [ ] Déploiement vers site B : modules/options identiques
- [ ] Exclusion module sur site C : module inchangé après push
- [ ] Historique : succès/échec par site
- [ ] Version GI-Toolkit < 2.19 : avertissement api_compatible false
- [ ] Ajout site : cocher install + profil Default → GI-Toolkit installé et config déployée
- [ ] Modèle « Default » défini comme profil par défaut dans Réglages

== Changelog ==

= 1.2.0 =
* Add: section GI-Toolkit sur le formulaire MainWP « Ajouter un site » (install ZIP + profil).
* Add: génération ZIP depuis wordpress_giwp, installation distante via MainWP (install_plugin_theme).
* Add: profil par défaut (modèle « Default » ou réglage), onglet Réglages, journal d’onboarding.

= 1.1.4 =
* Fix: déploiement en AJAX avec modale de progression (plus d’AbortError MainWP).

= 1.1.3 =
* Add: « Voir les réglages » par module (aperçu JSON, ex. SMTP) sur l’onglet Modules.
* Fix: enregistrement/suppression de modèles et sauvegarde modules en AJAX (plus d’AbortError MainWP).

= 1.1.1 =
* Fix: messages d’erreur MainWP en français (connexion enfant) + test statut avant export.
* Fix: timeout HTTP 120s pour l’export ; export enfant plus robuste (GI-Toolkit 2.20.2).

= 1.1.0 =
* Fix: AbortError MainWP — plus de submit par case à cocher ; formulaire unique + Enregistrer.
* Add: onglet Modules avec l’interface GI-Toolkit (settings.css, toggles, groupes, recherche).
* Add: logs console [GIWP] + script inline (fetch) pour « Importer config ».

= 1.0.9 =
* Fix: chargement JS/CSS sur les pages MainWP (admin_enqueue_scripts + impression en pied de page).
* Fix: « Importer config » — formulaire POST de secours + config AJAX en data-attributes.

= 1.0.8 =
* Fix: erreurs AJAX renvoyées en HTTP 200 (plus de faux « 500 » dans la console).
* Fix: import — normalisation du bundle, limite mémoire/temps, réinit clé MainWP sur admin-ajax.
* Fix: marge `.mainwp-ui .wrap` → 20px pour l’extension.

= 1.0.7 =
* Fix: import de configuration (« Importer config ») — les formulaires POST conservent désormais le paramètre page MainWP.
* Add: import AJAX avec message de confirmation et mise à jour de la section « Configuration de travail ».
* Fix: messages flash après déploiement, modèles, exclusions, etc.

= 1.0.6 =
* Add: modale de synchronisation avec barre de progression et journal en temps réel (AJAX site par site).
* Update: le tableau des sites se met à jour sans rechargement de page.

= 1.0.5 =
* Fix: erreur de syntaxe PHP (parenthèse manquante sur add_action).

= 1.0.4 =
* Fix: titre page MainWP (filtre mainwp_extensions_page_top_header) — plus de « _giwp » / nom de dossier.
* Update: slug extension Mainwp-Gi-Toolkit-Manager, identifiant API mainwp-giwp.

= 1.0.3 =
* Fix: mode sombre MainWP (détection default-dark, styles sans fond blanc forcé).

= 1.0.2 =
* Fix: compatibilité thème sombre MainWP (variables CSS).
* Fix: liste des sites (mainwp_getsites renvoie des tableaux).
* Fix: communication enfant via extra_execution / mainwp_child_extra_execution.
* Update: prérequis GI-Toolkit >= 2.20.1.

= 1.0.1 =
* Bump de version (en-tête plugin / constante).

= 1.0.0 =
* Version initiale
