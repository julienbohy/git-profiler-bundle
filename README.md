# GitProfilerBundle

Bundle Symfony qui expose l'état Git du dépôt courant — **branche**, **commit court**, **fichiers
modifiés** et **commits non pushés** — dans un panneau dédié du **Web Profiler**.

> 🚧 En cours de développement — pas encore publié sur Packagist.

## Prérequis

- PHP **8.3+**
- Symfony **6.4**, **7.x** ou **8.x**
- Le binaire `git` disponible dans l'environnement d'exécution
- La lecture Git s'appuie sur [`gitonomy/gitlib`](https://github.com/gitonomy/gitlib)

## Installation

Tant que le bundle n'est pas sur Packagist, on le consomme via un dépôt Composer de type `path`.

Dans le `composer.json` de l'application :

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../GitProfilerBundle",
            "options": { "symlink": true }
        }
    ]
}
```

Puis :

```bash
composer require --dev julienbohy/git-profiler-bundle:@dev
```

> Une fois publié : `composer require --dev julienbohy/git-profiler-bundle`.

### Enregistrement du bundle

Avec Symfony Flex, le bundle est enregistré automatiquement. Sinon, dans `config/bundles.php` :

```php
return [
    // ...
    JulienBohy\GitProfilerBundle\GitProfilerBundle::class => ['dev' => true, 'test' => true],
];
```

Le bundle n'a d'intérêt qu'en environnement de développement (Web Profiler) : `dev` (et `test`)
suffisent.

## Utilisation

Aucune configuration. Dès que le profiler est actif, un panneau **Git** apparaît dans la barre de
debug et dans le profiler.

Dans la **toolbar** (compacte), on trouve la **branche** courante suivie de **deux compteurs** :
le nombre de **fichiers modifiés localement** (✎) et le nombre de **commits non pushés** (↑).

Le **panneau détaillé** affiche en plus :

- le **commit court** de `HEAD` ;
- la **liste des fichiers du *working tree*** non commités (indexés, non indexés, non suivis) avec
  leur statut (ajouté, modifié, supprimé, renommé…) ;
- la **liste des commits locaux en avance sur le remote** (non pushés) — hash court, message,
  auteur, date — **ainsi que la liste des fichiers qu'ils touchent**.

Détection des commits non pushés :

- elle se base sur la branche **upstream** (`@{u}`, ex. `origin/main`) ; sans upstream configuré,
  la section l'indique proprement et les compteurs affichent `–` ;
- la liste des fichiers non pushés correspond au **diff net** `@{u}..HEAD` (un fichier créé puis
  supprimé dans l'intervalle n'apparaît donc pas).

Si le répertoire n'est pas un dépôt Git (ou si `git` est indisponible), le panneau l'indique
proprement — aucune exception n'est levée.

## Architecture

- `Git\GitRepositoryInterface` — port : `read(): ?GitInfo` (`null` = dégradation).
- `Git\GitRepository` — adapter s'appuyant sur `gitonomy/gitlib`.
- `Git\GitInfo` — value object immuable (`branch`, `shortCommit`, `isDirty`, `workingFiles`,
  `hasUpstream`, `unpushedCommits`, `unpushedFiles`).
- `Git\ChangedFile` — value object immuable d'un fichier modifié (`path`, `status`, `stage`,
  `oldPath`, `additions`, `deletions`).
- `Git\FileStatus`, `Git\FileStage` — enums *backed string* du statut (ajouté, modifié, supprimé,
  renommé, non suivi) et de l'emplacement (indexé, non indexé, non suivi, commité) d'un fichier,
  avec leurs libellés d'affichage (`label()`).
- `Git\UnpushedCommit` — value object immuable d'un commit non poussé (`shortHash`, `subject`,
  `author`, `date`).
- `DataCollector\GitDataCollector` — collecteur *sans logique*, délègue au port, aplatit les VO
  en scalaires (sérialisables par le profiler) et expose les données au template
  `@GitProfiler/Collector/git.html.twig`.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Licence

MIT © 2026 Julien Bohy
