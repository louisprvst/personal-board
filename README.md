# Personal Board

Dashboard personnel hébergé sur Railway. Pour l'instant, il affiche une newsletter finance et tech publiée chaque matin par une routine Claude.

Stack : PHP 8.3 sans framework ni dépendance, SQLite sur un volume Railway.

## Structure

```
public/                 racine web
  index.php             dashboard (affiche les widgets)
  newsletter.php        une newsletter : ?date=AAAA-MM-JJ
  archives.php          historique
  login.php, logout.php
  api/newsletter.php    POST protégé par un jeton (utilisé par la routine)
  style.css
src/
  secrets.php           lit les variables d'environnement, ou .env en local
  auth.php              mot de passe unique + cookie signé
  db.php                SQLite + requêtes
  markdown.php          Markdown → HTML (sûr : le HTML brut est échappé)
  layout.php            en-tête, pied de page, affichage d'une newsletter
  widgets.php           liste et ordre des widgets
  widgets/              un fichier par widget
docs/
  routine-newsletter.md prompt de la routine Claude
```

## En local

```bash
php bin/creer-env.php            # demande le mot de passe, crée .env (hash + jeton)
php -S localhost:8080 -t public  # puis ouvre http://localhost:8080
```

Pour publier une newsletter de test :

```bash
API_TOKEN=$(grep '^API_TOKEN=' .env | cut -d= -f2)
curl -X POST http://localhost:8080/api/newsletter.php \
  -H "Authorization: Bearer $API_TOKEN" \
  -d '{"titre": "Test", "contenu": "# Finance\n**Sujet** : texte\n\n# Tech\n- point"}'
```

`.env` est ignoré par git et par Docker. Sur Railway, crée les mêmes variables en copiant les valeurs du `.env`, sans guillemets, sauf `DB_PATH`.

## Déploiement sur Railway

1. Crée un projet à partir de ce repo GitHub. Railway utilise le `Dockerfile`.
2. Ajoute un **volume** monté sur `/data`.
3. Définis les variables :
   - `DB_PATH` = `/data/board.sqlite`
   - `MOT_DE_PASSE_HASH` = la valeur copiée de ton `.env`
   - `API_TOKEN` = la valeur copiée de ton `.env`
4. Dans Settings → Networking, génère un domaine public.

## API

```bash
curl -X POST https://ton-app.up.railway.app/api/newsletter.php \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"titre": "Titre", "contenu": "# Finance\n**Sujet** — texte", "date": "2026-11-22"}'
```

- `date` est facultative. Par défaut, c'est aujourd'hui, à l'heure de Paris.
- Republier à une date existante remplace la newsletter de ce jour.
- Seules les 7 newsletters les plus récentes sont gardées : à chaque publication, les plus anciennes sont supprimées. Pour changer ce nombre, modifie `NEWSLETTERS_GARDEES` dans `src/db.php`.
- Codes de réponse : `201` créée, `200` remplacée, `401` jeton invalide, `422` données invalides.

## Ajouter un widget

1. Crée `src/widgets/meteo.php` avec une fonction `widget_meteo(): void` qui affiche une `<section class="widget">…</section>`.
2. Ajoute `'meteo'` dans `WIDGETS` (`src/widgets.php`), à la position voulue.
