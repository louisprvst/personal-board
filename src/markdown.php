<?php
// Petit convertisseur Markdown → HTML, volontairement limité et sûr.
//
// Tout le texte est échappé AVANT d'appliquer la mise en forme : le HTML brut
// envoyé dans une newsletter s'affiche comme du texte, jamais comme du code.
// Les liens n'acceptent que http(s).
//
// Supporté : titres (# à ####), paragraphes, listes à puces et numérotées,
// citations (>), séparateurs (---), **gras**, *italique*, `code`, [liens](https://…).

function markdown(string $source): string
{
    $lignes = preg_split('/\R/', $source);
    $html = [];
    $paragraphe = [];
    $liste = null;        // 'ul' ou 'ol' quand une liste est ouverte
    $citation = [];

    $fermer = function () use (&$html, &$paragraphe, &$liste, &$citation) {
        if ($paragraphe) {
            $html[] = '<p>' . md_inline(implode(' ', $paragraphe)) . '</p>';
            $paragraphe = [];
        }
        if ($liste) {
            $html[] = "</$liste>";
            $liste = null;
        }
        if ($citation) {
            $html[] = '<blockquote><p>' . md_inline(implode(' ', $citation)) . '</p></blockquote>';
            $citation = [];
        }
    };

    foreach ($lignes as $ligne) {
        $ligne = rtrim($ligne);
        $nette = trim($ligne);

        if ($nette === '') {
            $fermer();
        } elseif (preg_match('/^(#{1,4})\s+(.+)$/', $nette, $m)) {
            $fermer();
            $niveau = strlen($m[1]) + 1; // # → h2 : le h1 est réservé au titre de la page
            $niveau = min($niveau, 5);
            $html[] = "<h$niveau>" . md_inline($m[2]) . "</h$niveau>";
        } elseif (preg_match('/^([-*_])(\s*\1){2,}$/', $nette)) {
            $fermer();
            $html[] = '<hr>';
        } elseif (preg_match('/^>\s?(.*)$/', $nette, $m)) {
            if (!$citation) $fermer();
            $citation[] = $m[1];
        } elseif (preg_match('/^(?:[-*+]|(\d+)[.)])\s+(.+)$/', $nette, $m)) {
            $type = $m[1] !== '' ? 'ol' : 'ul';
            if ($liste !== $type) {
                $fermer();
                $html[] = "<$type>";
                $liste = $type;
            }
            $html[] = '<li>' . md_inline($m[2]) . '</li>';
        } elseif ($liste && preg_match('/^\s{2,}\S/', $ligne)) {
            // Ligne indentée sous un élément de liste : on la rattache à cet élément.
            $dernier = array_pop($html);
            $html[] = substr($dernier, 0, -5) . ' ' . md_inline($nette) . '</li>';
        } else {
            if ($liste || $citation) $fermer();
            $paragraphe[] = $nette;
        }
    }
    $fermer();

    return implode("\n", $html);
}

// Retire la syntaxe Markdown d'une ligne pour obtenir du texte brut.
function md_texte(string $ligne): string
{
    $ligne = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $ligne); // [texte](url) → texte
    return trim(str_replace(['**', '`'], '', preg_replace('/(?<!\w)[*_](\S.*?\S|\S)[*_](?!\w)/', '$1', $ligne)));
}

// Premier paragraphe (l'accroche), en texte brut, coupé proprement s'il est trop long.
function resume_markdown(string $source, int $max = 220): string
{
    foreach (preg_split('/\R/', $source) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || preg_match('/^(#|[-*+>]|\d+[.)]|[-*_]{3})/', $ligne)) continue;
        $texte = md_texte($ligne);
        if (strlen($texte) <= $max) return $texte;
        $coupe = substr($texte, 0, $max);
        $coupe = substr($coupe, 0, strrpos($coupe, ' ') ?: $max);
        return rtrim($coupe, " ,;:—-") . '…';
    }
    return '';
}

function md_inline(string $texte): string
{
    $texte = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');

    // Le code est mis de côté pour que son contenu ne soit pas reformaté.
    $codes = [];
    $texte = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x00" . (count($codes) - 1) . "\x00";
    }, $texte);

    // Liens : [texte](https://…) — seuls http et https sont acceptés.
    $texte = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', function ($m) {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
    }, $texte);

    $texte = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $texte);
    $texte = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $texte);
    $texte = preg_replace('/(?<![\w])_(?!\s)(.+?)(?<!\s)_(?![\w])/', '<em>$1</em>', $texte);

    return preg_replace_callback("/\x00(\d+)\x00/", fn($m) => $codes[(int)$m[1]], $texte);
}
