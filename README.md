# Instalace skautské Wordpress šablony DSW v Lebedě na jeden klik 

## Status: v1.0

## Manuál na instalaci 

Jak zpřístupnit na Lebedě autoinstalaci Wordpressu: 

* Jako základ používáme https://github.com/GeekPress/WP-Quick-Install (fork)
* **Nastavení** je v souboru `data.ini` (včetně instalovaných pluginů a všeho dalšího)
* Zrušili jsme GUI a JS z původního skriptu a všechno nasypali do `WordpressService.php`
* **Requirement** je v composer.json, stačí spustit `composer install`
* Chtěné **DSW téma** se získá pomocí automatického stánutí z DWS githubu. Alternativně je možnost umístit soubor vedle skriptu a pojmenovat ho `theme.zip` 
* Potřebné parametry pro instalaci jsou jako paramtery metody, return zatím nemáme žádný (vzhledem k tomu, že se skript bude provádět jindy (cronem) než ho uživatel zadává)
* Potřebné **parametry** pro service: $dbName, $dbUserName, $dbPassword, $dbHost, $websiteTitle, $userLogin, $adminPassword, $adminEmail
* Pozor, skript běží dlouho (přeci jen stahuje, dekomprimuje a instaluje zaráz), takže je potřeba hlídat timeout chyb (na průměrném NB to běželo skoro minutu)

#### stahování češtiny napřímo
https://cs.wordpress.org/latest-cs_CZ.zip

## TODO shortterm

#### automaticky přidat obrázky z theme

spočívá v poslání issue do DSW, aby byly obrázky includované dynamicky (teď si neporadí s subfolder v adresářové cestě) - https://github.com/skaut/dsw-oddil/issues/129

#### automaticky přidat menu z theme

řešíme s Kalichem


## TODO longterm

#### Volitelný plugin pro bazar

na vyžádání includenout plugin pro bazar (https://wordpress.org/plugins/skaut-bazar/)

#### Umožnit instalaci i přes existující web

@kalich5 - nevhodné dělat automaticky, protože se tím naseká moc problémů - lepší kontrolovat, zda je složka prázdná a pokud ne, rovnou to nepovolit (jak to dělá např. `git clone` nebo velcí hostingové)

Inspired by literat v issue:  

* na začátku se automatika pouze zeptá na login a heslo pro administrátorský účet
* musí proběhnout kontrola FTP a databáze, tj. pokud je zde nějaký obsah, bude smazán a uživatel na to musí být upozorněn a potvrdit to
* veškerý obsah před instalací bude z FTP i DB smazán
* jako základní šablona společně s Wordpressem bude instalována DSW Oddíl
