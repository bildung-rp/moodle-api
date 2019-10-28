# Einrichtung der Moodle-API mit Laravel

***Quelle:*** 
https://github.com/plierschpl/moodle-api

Die Dateien in ein beliebiges Verzeichnis entpacken/kopieren.

## Voraussetzungen
Betriebssystem (empfohlen): Linux (Debian)

Bitte prüfen Sie ob folgende Pakete installiert sind.
Ansonsten mit "root" Rechten, diese nachträglich installieren.

**Pakete**

- mariadb-server
- composer
- npm
- php7.3
- php7.3-ldap
- php7.3-curl 
- php7.3-zip 
- php7.3-xml 
- php7.3-bcmath

Zudem ist es wichtig mindestens eine lauffähige Moodle Template Instanz vorliegen zu haben.

Die Datenbank für unsere Laravel Application muss vorher angelegt werden.
Außerdem ist es für die API wichtig, einen speziellen Nutzer anzulegen, der weitere Datenbanken anlegen und verändern darf.

**Beispiel:**

```mysql
CREATE DATABASE moodle-api;
```

```mysql
CREATE USER 'myuser'@localhost IDENTIFIED BY 'mypassword';
GRANT ALL privileges ON *.* TO 'myuser'@localhost;
```

Wir wechseln in das Verzeichnis mit den Dateien.
Dort führen wir die nachfolgenden Befehle/Schritte aus.

## Die einzelnen Schritte

### Step 1.

Wir kopieren die .env.example in .env und ändern die Einstellungen, die für unser System zutreffend sind.
```php
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=moodle-api
DB_USERNAME=root
DB_PASSWORD=root
```

Weiter unten in der .env Datei, sind noch Moodle spezifische Einstellungen, die ebenfalls angepasst werden sollten.
Die dortigen Einstellungen sind wichtig, damit die Moodle Instanzen auch richtig angelegt werden können.

### Step 2. 

Fehlende Pakete nachinstallieren

```
composer install
```

Alte Reste entfernen und den Autoload neu generieren
```shell
composer dump-autoload
php artisan optimize
```

Installiert die notwendigen Abhängigkeiten zu den installierten Paketen in den Ordner node_modules
```shell
npm install
```

Die weiteren Pakete vorbereiten mit `npm run` für die jeweilige Umgebung, zur Auswahl stehen folgende Parameter:
**DEV** => `npm run development`

- Run all Mix tasks...

**WATCH** => `npm run development -- --watch`

* The npm run watch command will continue running in your terminal and watch all relevant files for changes. Webpack will then automatically recompile your assets when it detects a change.

**PROD** => `npm run production`

* Run all Mix tasks and minify output...

### Step 3.
Application Key erzeugen
```shell
c
```

Datenbanktabellen erzeugen
```shell
php artisan migrate --seed
```

### Step 4.

OAuth2 clients anlegen
```shell
php artisan passport:install
```

### Step 5.

Starten des Artisan Webservers (Standardmäßig Port 8000)

```shell
php artisan serve
```

### Step 6.
Aufrufen im Webbrowser (OpenApi Documentation Endpoint)

http://localhost:8000/api/documentation

**<u>Login:</u>**

**username:** moodle-admin@pl.rlp.de
**password:** password
