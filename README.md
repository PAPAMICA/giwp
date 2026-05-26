# GIWP — Monorepo Genevois Informatique

Dépôt regroupant **GI-Toolkit** (sites clients) et l’extension **MainWP GI-Toolkit Manager** (dashboard central).

## Structure

| Dossier | Rôle | Où l’installer |
|---------|------|----------------|
| `wordpress_giwp/` | Plugin **GI-Toolkit** (modules + bridge MainWP intégré) | Chaque site WordPress / enfant MainWP |
| `mainwp_giwp/` | Extension **MainWP GI-Toolkit Manager** | Site MainWP Dashboard uniquement |

## Prérequis sites enfants

1. [MainWP Child](https://mainwp.com/) actif  
2. **GI-Toolkit** ≥ 2.20.0 actif (aucun plugin bridge séparé)

## Prérequis dashboard

1. [MainWP Dashboard](https://mainwp.com/) actif  
2. Extension `mainwp_giwp/` activée dans WordPress puis dans **MainWP → Extensions**

## Déploiement

Copiez chaque dossier dans `wp-content/plugins/` :

```
wp-content/plugins/gi-toolkit/          ← contenu de wordpress_giwp/
wp-content/plugins/mainwp-giwp/         ← contenu de mainwp_giwp/
```

En développement depuis ce monorepo, le catalogue modules du dashboard lit `wordpress_giwp/` via un chemin relatif (`../wordpress_giwp/`).

## Communication MainWP

```
Dashboard → mainwp_fetchurlauthed(…, 'gi_toolkit', …)
         → GI-Toolkit (bridge) → Gi_Toolkit_MainWP_API
```

## Documentation

- Changelog GI-Toolkit : `wordpress_giwp/changelog.txt`
- Extension MainWP : `mainwp_giwp/readme.txt`
- Fiche plugin WordPress.org : `wordpress_giwp/readme.txt`
