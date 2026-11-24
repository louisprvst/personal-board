# Prompt de la routine « Newsletter du matin »

Le texte ci-dessous est le prompt de la routine Claude planifiée (via `/schedule`).
Exemple de planning : tous les jours à 6 h 30, heure de Paris.

Configuration de l'environnement cloud de la tâche (ici « DipBoard ») :
- **Accès réseau** : `dip-board.up.railway.app` doit être autorisé.
- **Secret** de type Bearer pour `dip-board.up.railway.app`, préfixe de chemin `/api/`,
  en-tête `Authorization`, préfixe `Bearer`, valeur = `API_TOKEN` (celle de Railway).
  Le jeton est ajouté automatiquement à la requête : le prompt ne l'envoie pas lui-même.

---

Tu rédiges ma newsletter **finance** du matin, en français. Je suis un investisseur particulier : je veux comprendre ce qui fait bouger les marchés, pas seulement voir des chiffres. Je la lis en 10 à 15 minutes au réveil, donc tu peux développer.

## Recherche
Avec la recherche web, regarde ce qui s'est passé depuis la dernière séance (le lundi, couvre aussi le week-end). Privilégie les sources primaires et les médias financiers reconnus : Reuters, Bloomberg, Les Échos, Financial Times, Wall Street Journal, CNBC, Boursorama, les communiqués officiels (banques centrales, entreprises, INSEE, BLS…).

Règles de rigueur :
- Vérifie chaque chiffre dans une source et indique-la. Pour un cours, précise s'il s'agit d'une clôture ou d'un cours en séance.
- Si tu ne trouves pas un chiffre de façon fiable, écris « non disponible » plutôt que d'estimer.
- Ne présente jamais une rumeur comme un fait.
- Reste factuel et pédagogique : explique les mécanismes, mais ne donne pas de conseil d'achat ou de vente.

## Contenu, dans cet ordre

### 1. Accroche
Deux ou trois phrases qui résument l'ambiance des marchés et le fait marquant.

### 2. Les marchés en bref
Une liste avec le niveau de clôture et la variation du jour en % :
- Actions : CAC 40, Euro Stoxx 50, DAX, S&P 500, Nasdaq, Nikkei
- Taux : OAT 10 ans, Bund 10 ans, Treasury 10 ans US
- Devises et matières premières : EUR/USD, pétrole (Brent), or, Bitcoin

Termine par une phrase qui explique le mouvement principal.

### 3. Mes positions
Deux sous-parties (`##`), toujours présentes, même les jours calmes :
- **iShares MSCI World Swap PEA (WPEA, Euronext Paris, en €)** : cours de clôture et variation du jour. Puis 3 à 5 phrases sur ce qui l'a fait bouger : l'indice MSCI World est composé à environ 70 % d'actions américaines, très pondéré en grandes valeurs tech, et l'ETF est coté en euros. Les variations de Wall Street et de l'EUR/USD pèsent donc directement sur son cours. Explique la part de chacun quand c'est possible.
- **IBM** : clôture à Wall Street en dollars et variation du jour, plus l'équivalent en euros au taux EUR/USD du jour (ou le cours sur Xetra en € si tu le trouves). Puis l'actualité de l'entreprise : résultats, annonces, rachats d'actions, dividende, notes d'analystes, contexte du secteur (IA, cloud hybride, logiciel, conseil). S'il n'y a rien de neuf, dis-le en une phrase et explique simplement le mouvement du titre.

### 4. À la une
**5 à 7 articles de fond** sur les sujets financiers les plus importants : macroéconomie, banques centrales, résultats d'entreprises, fusions-acquisitions, politique économique, marchés émergents, matières premières, crypto si c'est significatif. Pour chaque article :
- un titre `##` court et informatif ;
- 2 à 3 paragraphes : ce qui s'est passé (avec les chiffres), le contexte (pourquoi maintenant, qu'est-ce qui a précédé), *pourquoi ça compte* (pour les marchés, pour un investisseur en ETF monde, pour l'économie) et ce qu'il faut surveiller ensuite ;
- la ou les sources en fin d'article : `[Source](https://…)`.

### 5. Agenda
Les rendez-vous qui peuvent faire bouger les marchés, avec l'heure de Paris : publications macro (inflation, emploi, PIB, PMI), décisions et discours de banques centrales, résultats d'entreprises importants. Le lundi, donne l'agenda de toute la semaine.

## Le week-end
Les marchés sont fermés, adapte le contenu :
- **Samedi** : « Bilan de la semaine ». Performance hebdomadaire des indices et de mes deux positions, puis les 4 à 5 faits majeurs de la semaine, en articles de fond.
- **Dimanche** : « La semaine à venir ». Agenda détaillé, enjeux de chaque rendez-vous, ce que le marché anticipe. Point sur mes positions : résultats ou annonces attendus pour IBM, et contexte pour le MSCI World.

## Format (Markdown)
```
Accroche de deux ou trois phrases.

# Les marchés en bref
- **CAC 40** : 7 412 pts (−0,6 %)
- …

Phrase d'explication.

# Mes positions
## iShares MSCI World PEA (WPEA)
…
## IBM
…

# À la une
## Titre de l'article
Paragraphe 1…

Paragraphe 2… [Source](https://…)

# Agenda
- **14 h 30** : …
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

Le titre est une accroche courte (60 caractères max) sur le fait marquant du jour : par exemple « La Fed temporise, Wall Street reprend son souffle ». Le samedi, commence-le par « Bilan de la semaine : », le dimanche par « Semaine à venir : ». La date est mise automatiquement, à l'heure de Paris.

Une réponse `{"ok":true,...}` confirme la publication. Si l'appel échoue, montre la réponse complète du serveur et réessaie une fois. Une erreur 401 (« Jeton invalide ») vient du secret de l'environnement : ne cherche pas de jeton ailleurs.
