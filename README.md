# Personal Board

Dashboard personnel hébergé sur Railway, en grille « bento ». Il affiche :
- la newsletter finance et tech publiée chaque matin par une routine Claude ;
- le plan d'entraînement de course à pied publié par un chat Claude via un connecteur.

Stack : PHP 8.3 sans framework ni dépendance, SQLite sur un volume Railway.

## Structure

```
public/                 racine web
  index.php             accueil : grille de widgets
  newsletter.php        une newsletter : ?date=AAAA-MM-JJ
  archives.php          liste des newsletters gardées
  entrainement.php      plan de la semaine : ?lundi=AAAA-MM-JJ
  login.php, logout.php
  api/newsletter.php    POST protégé par un jeton (utilisé par la routine)
  mcp.php               connecteur Claude (serveur MCP)
  style.css
src/
  secrets.php           lit les variables d'environnement, ou .env en local
  db.php                connexion SQLite, tables, fuseau horaire
  newsletters.php       requêtes des newsletters
  entrainement.php      requêtes et aides du plan d'entraînement
  auth.php              mot de passe unique + cookie signé
  markdown.php          Markdown → HTML (sûr : le HTML brut est échappé)
  layout.php            en-tête, pied de page, dates, icônes
  widgets.php           liste, ordre et taille des widgets
  widgets/              un fichier par widget
docs/
  routine-newsletter.md prompt de la routine Claude
```

## En local

```bash
php -S localhost:8080 -t public  # puis ouvre http://localhost:8080
```

Le fichier `.env` (à la racine, ignoré par git et Docker) contient :

```
MOT_DE_PASSE_HASH=...   # php -r 'echo password_hash("ton-mot-de-passe", PASSWORD_DEFAULT), "\n";'
API_TOKEN=...           # php -r 'echo bin2hex(random_bytes(32)), "\n";'
MCP_SECRET=...          # php -r 'echo bin2hex(random_bytes(24)), "\n";'
DB_PATH=data/board.sqlite
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
   - `MCP_SECRET` = la valeur copiée de ton `.env`
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

## Connecteur Claude (entraînement)

`public/mcp.php` est un serveur MCP : ton chat Claude l'utilise pour publier ton plan de course à pied.

- Adresse à ajouter dans claude.ai (Paramètres → Connecteurs → Ajouter un connecteur personnalisé) :
  `https://dip-board.up.railway.app/mcp.php/<MCP_SECRET>`. Le secret dans l'adresse sert de mot de passe, garde-la pour toi.
- Outils : `publier_semaine` (lundi, objectif, séances `{date, type, detail}`, course visée) et `lire_semaine`.
- Seules la semaine en cours et les suivantes sont gardées.
- Affichage : case « Entraînement » sur l'accueil (séance du jour) et page `/entrainement.php` (semaine complète).

## Ajouter un widget

1. Crée `src/widgets/meteo.php` avec une fonction `widget_meteo(): void` qui affiche le contenu de la case : `<section class="carte">…</section>`, ou `<a class="carte lien" href="…">…</a>` pour une case cliquable. Si elle n'affiche rien, la case est retirée de la grille.
2. Ajoute `'meteo' => 'petit'` dans `WIDGETS` (`src/widgets.php`), à la position voulue. Tailles : `petit` (1 × 1), `large` (2 × 1), `grand` (2 × 2).
