# Changelog

## Version 2.0.0 //

* Umstellung auf FriendsOfREDAXO-Namespace. @alxndr-w
* Sprachdateien aktualisiert  @skerbis
* Von rexstan gemeldete Code-Verbesserungen umgesetzt. @alxndr-w @christophboecker
* Konfiguration per JSON möglich, ersetzt das Height-Feld  @skerbis
  * style und class Attribute
  * Initiale Kartenposition (data-init-lat, data-init-lng)
  * Initiales Zoom-Level (data-init-zoom)
* Dark Mode Unterstützung verbessert:  @skerbis
  * Integration der REDAXO Standard CSS-Variablen
  * Optimierte Kartendarstellung
  * Verbesserte Kontraste
* UX Verbesserungen:  @skerbis
  * Marker wird nicht mehr initial angezeigt
  * Erscheint erst bei Positionsauswahl
  * Verbesserte Transitionen
* Neue Funktionen für einzelne Adressabfragen  @skerbis
* Verbesserte `geo_search`-Klasse mit drei Betriebsmodi:  @skerbis
  * Einzelabfrage
  * Batch-Geokodierung
  * PLZ-Umkreissuche
* Erweiterte Dokumentation und Beispiele.  @skerbis
* Karten im YForm-Value optimieren @christophboecker
* Kleinere Optimierungen im YForm-Value und seinem YTemplate @christophboecker
* RexStan eingesetzt, Value mit Validierung der Feldkonfiguration @christophboecker
* no_db-Option und zugehörige Doku  @christophboecker
* DB-Feldtyp varchar statt text / Speicherverhalten geändert  @christophboecker

## Version 1.3.0 // 27.12.2024

* Live-Suche mit Adressvorschlägen
* Browser-Standortbestimmung
* Verbesserte Such-UI mit Modal-Dialog
* Font Awesome 6 Icons
* Dark Mode Support
* Sprachdateien aktualisiert (DE/EN)

## Version 1.2.3 // 17.08.2020

* Massencodierung über Geoapify hinzugefügt (@dtpop)
