=== MainWP GI-Web Extension ===
Contributors: genevois-informatique
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
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
   - GI-Toolkit >= 2.20.0 (le bridge MainWP est inclus dans GI-Toolkit, aucun plugin supplémentaire)

== Utilisation ==

MainWP > Extensions > GI-Toolkit Manager

1. **Vue d’ensemble** : synchronisez les statuts, importez une config depuis un site.
2. **Modules** : activez/désactivez des modules dans la configuration de travail.
3. **Modèles** : enregistrez des snapshots nommés.
4. **Déploiement** : sélectionnez les sites et déployez.
5. **Exclusions** : modules à ne pas écraser par site.
6. **Historique** : suivi des déploiements.

== Tests manuels (checklist) ==

- [ ] Site sans GI-Toolkit : statut erreur clair
- [ ] Import config site A → bundle de travail rempli
- [ ] Déploiement vers site B : modules/options identiques
- [ ] Exclusion module sur site C : module inchangé après push
- [ ] Historique : succès/échec par site
- [ ] Version GI-Toolkit < 2.19 : avertissement api_compatible false

== Changelog ==

= 1.0.0 =
* Version initiale
