# Autoload Inspector – deutsche Kurzanleitung

**Vorabversion 0.1.1:** Offizieller Plugin Check sowie lokale Aktivierung und einfache WP-CLI-Prüfungen abgeschlossen. Die umfassende Integration bleibt ungeprüft.

## Zweck

Zeigt die gespeicherte Gesamtgröße automatisch geladener Einstellungen und die 20 größten Einträge. Hilft bei der Vorbereitung einer gezielten WordPress-Wartung. Es werden keine Einstellungen bereinigt oder geändert.

## Installation

1. Paket mit `python3 tools/package.py` bauen; die installierbare Datei heißt `nakaryu-autoload-inspector-0.1.1.zip`.
2. Auf einer Entwicklungsinstallation unter **Plugins → Installieren → Plugin hochladen** installieren und aktivieren.
3. **Werkzeuge → Autoload Inspector** öffnen und die Prüfung starten.

Vorgesehene Mindestversionen: WordPress 6.0, PHP 7.4. Die Oberfläche ist derzeit englisch. Die Anmeldung braucht `manage_options`.

## Bericht verstehen

- „Bytes“ sind gespeicherte Datenbankbytes, nicht der Speicherverbrauch von PHP.
- Einstellungswerte werden gar nicht abgefragt.
- Namen bleiben standardmäßig verborgen. Die optionale Anzeige kann private Kennungen offenlegen.
- Die Prüfung besteht aus zwei Leseabfragen. Änderungen währenddessen können kleine Abweichungen verursachen.
- Bei Multisite wird nur die ausgewählte Website ausgewertet. Netzwerkoptionen sind nicht enthalten.

Große Einträge können notwendig sein. Vor Änderungen muss geklärt werden, welche Komponente die Einstellung verwendet. Das Werkzeug enthält keine Lösch- oder Optimierungsfunktion.

## Kommandozeile

```sh
wp nakaryu autoload inspect --top=20
wp nakaryu autoload inspect --include-names
```

Die Ausgabe ist JSON. WP-CLI setzt berechtigten Serverzugriff und ein geladenes Plugin voraus. Es wird kein Bericht gespeichert oder an Nakaryu übertragen. Andere Komponenten der Website können eigene Protokolle führen.

Entwickelt von [Nakaryu GmbH](https://nakaryu.de), unabhängig von den Verkaufsplugins. GPL-2.0-or-later.
