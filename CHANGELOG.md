# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.

Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).

## [1.0.1] - 2026-06-10

### Behoben

- **LiveController:** PHP-Notice „Only variables should be passed by reference"
  im `/liveJson`-Endpoint behoben. `Renderer::renderRoot()` erwartet das
  Render-Array per Referenz; die Render-Arrays werden jetzt vorher in lokale
  Variablen geschrieben.

### Upgrade

Reines Bugfix-Release. Keine Schema-Änderungen, kein `drush updb` nötig.
Nach dem Update genügt `lando drush cr`.

## [1.0.0] - 2026-04-11

Erste stabile Version nach den Beta-Releases.
