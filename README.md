# Instalace skautského Wordpressu v Lebodě na jeden klik 

## Status: rozděláno, ještě nepoužívat

## Manuál na instalaci 

Jak zpřístupnit na Lebedě autoinstalaci Wordpressu: 

* Jako základ používáme https://github.com/GeekPress/WP-Quick-Install (místní fork)
* **Nastavení** je v souboru `data.ini` (včetně instalovaných pluginů a všeho dalšího)
* Zrušili jsme GUI a JS z původního skriptu a všechno nasypali do `WordpressService.php`
* Chtěné **DSW téma** se získá pomocí automatického stánutí z DWS githubu. Alternativně je možnost umístit soubor vedle skriptu a pojmenovat ho `theme.zip` 
* Potřebné parametry pro instalaci jsou jako paramtery metody, return zatím nemáme žádný (vzhledem k tomu, že se skript bude provádět jindy (cronem) než ho uživatel zadává)
* Potřebné **parametry** pro service: $dbName, $dbUserName, $dbPassword, $dbHost, $websiteTitle, $userLogin, $adminPassword
* Pozor, skript běží dlouho (přeci jen stahuje, dekomprimuje a instaluje zaráz), takže je potřeba hlídat timeout chyb (na průměrném NB to běželo skoro minutu)

## TODO shortterm

#### stahovat češtinu napřímo

https://cs.wordpress.org/

#### automaticky přidat obrázky z theme

spočívá v poslání issue do DSW, aby byly obrázky includované dynamicky (teď si neporadí s subfolder v adresářové cestě) - https://github.com/skaut/dsw-oddil/issues/129


#### v nových odkazech používat absolutní

#### automaticky přidat menu z theme


## TODO longterm

#### Přidat automaticky češtinu

Postup pro execute: 

* je potřeba vytvořit ve složce `wp-content` složku `languages`
* do ní nakopírovat obsah s language soubory
* ve wp-config přepsat řádek na `define ('WPLANG', 'cs_CZ');`

Návod k tomuto postupu je na adrese http://www.cwordpress.cz/navody/instalace-cestiny-do-wordpressu.html

#### Plugin pro bazar

na vyžádání includenout plugin pro bazar (https://wordpress.org/plugins/skaut-bazar/)

#### Return užitečné info

Vracet bychom mohli např. heslo pro admina nebo nějaký success message, to bude věcí další domluvy

#### Umožnit instalaci i přes existující web

Inspired by literat v issue:  

* na začátku se automatika pouze zeptá na login a heslo pro administrátorský účet
* musí proběhnout kontrola FTP a databáze, tj. pokud je zde nějaký obsah, bude smazán a uživatel na to musí být upozorněn a potvrdit to
* veškerý obsah před instalací bude z FTP i DB smazán
* jako základní šablona společně s Wordpressem bude instalována DSW Oddíl