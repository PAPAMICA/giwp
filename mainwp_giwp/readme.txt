=== MainWP GI-Web Extension ===
Contributors: genevois-informatique
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.3
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

= 1.7.3 =
* Fix: après déploiement, ne resynchronise Matomo / Uptime Kuma que s’ils ont échoué à l’import (évite le double hit et le rate-limit « Too frequently ») ; logs d’import allégés.

= 1.7.2 =
* Update: Onglet Déploiement — bouton « dernière version » déplacé ici ; nouveau bouton combiné (mise à jour plugin puis configuration sur les sites sélectionnés).

= 1.7.1 =
* Update: Après chaque déploiement de configuration, resynchronise Matomo et Uptime Kuma sur le site enfant (liaison site/monitor forcée).

= 1.6.12 =
* Update: Mail Catcher — agrégation et affichage du statut spam/RBL (hors alertes d’échec) ; payload API enrichi (`spam`, `chart_spam`, `recent_spam`).

= 1.6.11 =
* Add: REST API v1/v2 — `GET /gi-toolkit-backup/get-network` (v1) et `GET /gi-toolkit-backup` (v2) : statut backup de tous les sites en un appel (date, taille, localisation, statut).

= 1.6.10 =
* Fix: REST API v1 — authentification `curl -u` (Basic Auth) via le validateur MainWP v2 ; injection consumer_key/secret pour le repli legacy.
* Fix: REST API v2 — propriété `$title` manquante sur le contrôleur (warning PHP).

= 1.6.9 =
* Add: REST API MainWP v1 — `GET /gi-toolkit-mail/get-network` et `GET /gi-toolkit-mail/get-site-mail` (consumer_key + consumer_secret).

= 1.6.8 =
* Add: REST API MainWP v2 — `GET /gi-toolkit-mail` (réseau) et `GET /gi-toolkit-mail/{id_ou_domaine}` (site, paramètres `refresh`, `failures_limit`).

= 1.6.7 =
* Add: API mail — `MainWP_GIWeb_API::get_mail()`, `get_mail_network()`, `resolve_site_mail()` et endpoints AJAX `mainwp_giweb_get_site_mail` / `mainwp_giweb_get_mail_network`.

= 1.6.6 =
* Fix: onboarding à l’ajout de site — installation + déploiement du profil en arrière-plan (ne bloque plus l’AJAX MainWP ; le white label et le profil sont appliqués après la réponse).
* Fix: attente plus longue de l’API GI-Toolkit avant le déploiement du profil (évite les échecs juste après l’installation).

= 1.6.5 =
* Fix: onboarding à l’ajout de site — options GI-Toolkit transmises via le conteneur MainWP `mainwp_addition_fields_addsite` (AJAX « Add Site »).
* Add: création du dossier FTP à l’ajout de site (install seule ou si le déploiement de profil échoue ; déjà géré après un déploiement réussi).

= 1.6.4 =
* Fix: sync globale MainWP — fusion systématique payload sync + API GI-Toolkit, agrégats conservés pendant le batch, collecte sans exiger l’activation UI de l’extension.
* Fix: score santé des widgets affiché en rouge lorsqu’il est inférieur à 100 %.

= 1.6.3 =
* Add: bouton Actualiser (↻) sur les widgets Mails, Backups et Uptime Kuma — sync forcée des données enfant + rechargement AJAX du widget.
* Update: refonte widgets dashboard — tooltips enrichis sur le bandeau statut, tableaux lisibles clair/sombre, bascule cartes/tableau, légende donut scrollable.
* Update: vue d’ensemble — colonne URL retirée (lien sous le nom), colonnes Mails/Backup élargies, badge statut compact.
* Update: page Réglages — navigation par ancres, sections en cartes, barre Enregistrer sticky.
* Fix: masquage widgets MainWP (décocher dans Options de l’écran) — correction du préfixe ID `advanced-`.
* Fix: score backup « no backup » sur le total MainWP ; pourcentages affichés sans décimales.

= 1.6.2 =
* Update: sync globale MainWP — mails, backups et Uptime Kuma alimentés automatiquement (payload GI-Toolkit injecté côté enfant, repli API, refresh Kuma à la fin du batch).

= 1.6.1 =
* Fix: backups UpdraftPlus synchronisés avec la sync globale MainWP (`mainwp_site_synced`) — sans blocage de droits UI, agrégat réinitialisé par batch.

= 1.6.0 =
* Add: remontée UpdraftPlus via API enfant — widget dashboard, colonne Manage Sites / vue d’ensemble, statut date/taille/remote (<10 j = vert, ≥10 j = rouge).

= 1.5.9 =
* Add: backup FTP — réglages connexion + chemin avec %siteurl% / %sitename%, création auto à chaque déploiement, vérification globale avec taille et date du dernier fichier.

= 1.5.8 =
* Add: intégration Zabbix 7.4 — réglages URL + clé API, création auto des hosts à l’ajout de site, boutons test et provisionnement massif.
* Fix: test connexion sans header Authorization sur `apiinfo.version` ; création host avec champ `ip` requis par l’API.

= 1.5.7 =
* Add: colonnes Uptime Kuma et Mails dans la liste Manage Sites MainWP.
* Add: widgets Uptime Kuma et Mails filtrés par site sur l’Overview individuel (`managesites&dashboard=ID`).
* Update: cache Uptime Kuma rafraîchi à la sync MainWP et à la sync GI-Toolkit (debounce fin de batch).

= 1.5.6 =
* Update: widget Uptime Kuma — champ recherche stylisé (thème sombre), cartes sans URL dupliquée ni lien « Ouvrir le site ».

= 1.5.5 =
* Update: refonte complète widget Uptime Kuma — score santé, bandeau par site, cartes avec dispo 24 h / 30 j, filtres et recherche.

= 1.5.4 =
* Fix: widget Uptime Kuma — liste des sites via mainwp_getsites (comme le reste de l’extension), pas MainWP_DB.
* Fix: rapprochement URL (www/http/https) ; repli sur les monitors Kuma si besoin.
* Update: tableau compact, thème clair/sombre MainWP, messages d’erreur explicites.

= 1.5.3 =
* Add: widget dashboard Uptime Kuma — sync au chargement, bouton Actualiser (AJAX), vue compacte multi-sites.
* Update: API get_monitors_overview (stats légères sans heartbeats par monitor).

= 1.5.2 =
* Add: widget MainWP Uptime Kuma, cron 5 min, cache et assets CSS/JS.

= 1.5.1 =
* Add: réglages Uptime Kuma centralisés, merge_into_bundle et déploiement des identifiants.

= 1.5.0 =
* Add: intégration Connect Uptime Kuma (déploiement vers sites enfants, messages API).

= 1.4.2 =
* Fix: déploiement plugin — mise à jour forcée (`overwrite`) pour installer la dernière version sans perdre la config.
* Add: version cible (ZIP) en tête des logs ; version installée `[vX]` sur chaque site.

= 1.4.1 =
* Fix: déploiement plugin — appel MainWP Child `installplugintheme` (au lieu de `install_plugin_theme`).
* Fix: succès reconnu via `installation: SUCCESS` (format réponse MainWP standard).

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
