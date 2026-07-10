# GitProfilerBundle

Bundle Symfony qui expose les informations Git du dépôt courant — **branche**, **commit court**
et **état *dirty*** — dans un panneau dédié du **Web Profiler**.

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
debug et dans le profiler, indiquant :

- la **branche** courante (ou `HEAD` si détachée) ;
- le **commit court** ;
- si le *working tree* a des **modifications locales** (staged, non-staged ou fichiers non suivis).

Si le répertoire n'est pas un dépôt Git (ou si `git` est indisponible), le panneau l'indique
proprement — aucune exception n'est levée.

## Architecture

- `Git\GitRepositoryInterface` — port : `read(): ?GitInfo` (`null` = dégradation).
- `Git\GitRepository` — adapter s'appuyant sur `gitonomy/gitlib`.
- `Git\GitInfo` — value object immuable (`branch`, `shortCommit`, `isDirty`).
- `DataCollector\GitDataCollector` — collecteur *sans logique*, délègue au port et expose les
  données au template `@GitProfiler/Collector/git.html.twig`.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Licence

MIT © 2026 Julien Bohy
