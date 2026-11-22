# Prompt de la routine « Newsletter du matin »

Le texte ci-dessous est le prompt de la routine Claude planifiée (via `/schedule`).
Exemple de planning : tous les jours à 6 h 30, heure de Paris.

Configuration de l'environnement cloud de la tâche (ici « DipBoard ») :
- **Accès réseau** : `dip-board.up.railway.app` doit être autorisé.
- **Secret** de type Bearer pour `dip-board.up.railway.app`, préfixe de chemin `/api/`,
  en-tête `Authorization`, préfixe `Bearer`, valeur = `API_TOKEN` (celle de Railway).
  Le jeton est ajouté automatiquement à la requête : le prompt ne l'envoie pas lui-même.

---

Tu rédiges ma newsletter du matin, en français, sur la **finance** et la **tech**. Je la lis en 5 à 8 minutes au réveil.

## Recherche
Avec la recherche web, regarde ce qui s'est passé ces dernières 24 h (72 h le lundi, pour couvrir le week-end). Privilégie les sources primaires et les médias reconnus : Reuters, Bloomberg, Les Échos, Financial Times, The Verge, Ars Technica, TechCrunch, les blogs officiels des entreprises. Vérifie chaque chiffre dans une source. Ne présente jamais une rumeur comme un fait.

## Contenu
- **Finance** (4 à 5 sujets) : les marchés de la veille (CAC 40, S&P 500, Nasdaq, EUR/USD, pétrole, or, Bitcoin, avec niveaux et variations), la macro et les banques centrales, les résultats d'entreprises marquants, l'agenda du jour.
- **Tech** (4 à 5 sujets) : l'IA, les grandes entreprises tech, le développement logiciel, la sécurité, les lancements importants.
- Pour chaque sujet : un titre court en gras, 2 à 4 phrases qui disent ce qui s'est passé et *pourquoi ça compte*, puis le lien vers la source.

## Format (Markdown)
```
Une phrase d'accroche qui résume la journée.

# Finance
**Titre du sujet** — explication. [Source](https://…)

# Tech
**Titre du sujet** — explication. [Source](https://…)

# À surveiller aujourd'hui
- événement 1
- événement 2
```
Syntaxe disponible : `#` / `##` pour les titres, listes `-` ou `1.`, `**gras**`, `*italique*`, `[lien](https://…)`, `>` pour une citation. Pas de HTML ni de tableaux.

## Publication
Écris le JSON dans un fichier, puis envoie-le avec curl. N'ajoute **pas** d'en-tête `Authorization` : l'environnement l'ajoute lui-même.

```bash
python3 - <<'PY'
import json, pathlib
pathlib.Path("/tmp/newsletter.json").write_text(json.dumps({
    "titre": "TITRE DU JOUR",
    "contenu": pathlib.Path("/tmp/newsletter.md").read_text(),
}, ensure_ascii=False))
PY
curl -sS -X POST "https://dip-board.up.railway.app/api/newsletter.php" \
  -H "Content-Type: application/json" \
  --data-binary @/tmp/newsletter.json
```

Le titre est une accroche courte (60 caractères max) sur le fait marquant du jour. La date est mise automatiquement, à l'heure de Paris.

Une réponse `{"ok":true,...}` confirme la publication. Si l'appel échoue, montre la réponse complète du serveur et réessaie une fois. Une erreur 401 (« Jeton invalide ») vient du secret de l'environnement : ne cherche pas de jeton ailleurs.
