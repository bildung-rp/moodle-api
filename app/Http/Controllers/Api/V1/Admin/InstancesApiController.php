<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Instance;

class InstancesApiController extends Controller {

    private $dbcon;

    function __construct() {
        $this->dbcon = DB::connection()->getPdo();
    }

    /* Liefert die Liste aller Instanzen */
    /* Response in JSON */

    public function index() {

        $liste = $this->listInstances();

        //return json_encode($liste);
        return response(json_encode($liste), 201);
    }

    /* Ist für das anlegen zuständig */
    /* Response in JSON */

    public function store() {

        /* Daten fürs Logging */
        $datum = time();
        $funktion = "moodle-instance-create";
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $params = $this->filteredRequest();

        // Überprüfen der Parameter
        $checkParams = $this->checkParams($params);

        if (!empty($checkParams)) {
            return response(json_encode($checkParams), 409);
        }

        /* CN umwandeln in ORG und KDNR */
        $convert = $this->convertCN($params['CN']);
        if (!empty($convert['error'])) {
            return response(json_encode($convert['error']), 409);
        }

        $CN = $params['CN'];
        $long = $params['rpIdmOrgShortName'];
        $template = $convert['template'];
        $short = $convert['short'];


        /* Überprüfen ob DB bereits existiert */
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  :short";
        $db = DB::select($query, ['short' => $short]);
        if (!empty($db)) {
            return response(json_encode("Datenbank existiert bereits"), 409);
        }

        $moodledir = env("MOODLE_DIR");
        $moodledatadir = env("MOODLE_DATA_DIR");
        /* Überprüfen ob Ordner bereits existieren */
        if (is_dir($moodledir . "/" . $short) || is_link($moodledir . "/" . $short) || is_dir($moodledatadir . "/" . $short)) {
            return response(json_encode("Ordner existiert bereits"), 409);
        }

        /* Instanz anlegen */
        $created = $this->createInstanz($template, $short, $long, $CN);

        $resp_logger = $this->logger($datum, $funktion, $ip, $created);

        $status = json_decode($created);

        if (!empty($status->error)) {
            return response(json_encode($status->error), 409);
        } else {

            /* Ausführliche Response zurückliefern */
            /*

              Zur Provisionierung
              o URL für Moodle-Webservice API
              o Moodle-Token für Webservice-Benutzer
              Zur Speicherung im Metadirectory
              o Hostname der Moodle-Instanz
              o Installationsverzeichnis der Moodle-Instanz
              o Datenverzeichnis der Moddle-Instanz
              o Aufruf-URL der Moodle-Instanz
             */

            // WS Token erzeugen
//            $webservice_token = $this->createWSToken($short);
//            $token = "";
//
//            $ws_data = json_decode($webservice_token);
//            if (isset($ws_data->token)) {
//                $token = $ws_data->token;
//            }

            $hostname = env("APP_URL");

            $url_webservice = $hostname . "/" . $short . "/webservice/rest/simpleserver.php";
            $webservice_token = $token;
            $realpath = env("MOODLE_DIR") . "/" . $short;
            $realdatapath = env("MOODL_DATA_DIR") . "/" . $short;

            $url_instance = $hostname . "/" . $short;

            $success_resp = ["url_webservice" => $url_webservice,
                "hostname" => $hostname,
                "realpath" => $realpath,
                "realdatapath" => $realdatapath,
                "url_instance" => $url_instance];


            return response(json_encode($success_resp), 201);
        }
    }

    /* Erzeugt den WS Token */

//
//    public function createWSToken(String $short) {
//
//        $ws_user = config("instance.ws_user");
//        $ws_pass = config("instance.ws_pass");
//        $ws_service = config("instance.ws_service");
//        $baseurl = config("instance.baseurl");
//
//        $params = ["username" => $ws_user, "password" => $ws_pass, "service" => $ws_service];
//        $url_params = http_build_query($params);
//        $curl_url = $baseurl . "/" . $short . "/login/token.php?" . $url_params;
//
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $curl_url);
//        curl_setopt($ch, CURLOPT_ENCODING, '');
//        curl_setopt($ch, CURLOPT_PROXY, config("instance.proxy"));
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//        curl_setopt($ch, CURLOPT_HEADER, false);
//        curl_setopt($ch, CURLOPT_TIMEOUT, '360');  //
//        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, '480'); // Zeit für Verbindungsaufbau in Sekunden
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
//        curl_setopt($ch, CURLOPT_VERBOSE, false);
//
//        $data = curl_exec($ch);
//
//        return $data;
//    }

    /* Löschen einer Instanz */

    public function destroy($instance) {

        $params['CN'] = $instance;

        // Überprüfen der Parameter
        $checkParams = $this->checkParams($params);

        if (!empty($checkParams)) {
            return response(json_encode($checkParams), 409);
        }

        /* CN umwandeln in ORG und KDNR */
        $convert = $this->convertCN($instance);
        if (!empty($convert['error'])) {
            return response(json_encode($convert['error']), 409);
        }

        $short = $convert['short'];

        # der eigentliche Löschvorgang
        $deleted = $this->deleteInstanz($short);

        $status = json_decode($deleted);

        if (!empty($status->error)) {
            return response(json_encode($status->error), 409);
        } else {
            return response(json_encode(["success" => 1]), 201);
        }
    }

    protected function filteredRequest() {
        return array_filter(request()->all()); //filter to ignore fields with null values
    }

    protected function checkParams($params) {

        $error = [];

        // für rpIdmOrgShortName zugelassene Zeichen        
        $stringFilter = env('STRING_FILTER');

        // für CN zugelassene Zeichen und Aufbau - Beispiel: DE-RP-SN-12345
        $CNFilter = env('CN_FILTER');

        if (isset($params['CN'])) {
            if (!preg_match($CNFilter, $params['CN'])) {
                $error[] = "Der Parameter 'CN' hat ein falsches Format!";
            }
        }

        if (isset($params['rpIdmOrgShortName'])) {
            if (!preg_match("/^[" . $stringFilter . "+]+$/", $params['CN'])) {
                $error[] = "Der Parameter 'rpIdmOrgShortName' hat ein falsches Format!";
            }
        }

        return $error;
    }

    // Wandelt den CN entsprechend um
    public function convertCN($CN) {

        $templates = [];

        $CN_split = preg_match("/^[A-Z]{1,2}-[A-Z]{1,2}-([A-Z]{1,5})-([0-9]{1,10})+$/", $CN, $treffer);
        $ORG = $treffer[1];
        $KDNR = $treffer[2];

        $template = env("TEMPLATE_" . $ORG);

        if (empty($template)) {
            return ["error" => "Kein passendes Template zu dieser Organisation gefunden! CN: " . $CN . " - ORG: " . $ORG];
        }

        $short = $ORG . "-" . $KDNR;


        return ["template" => $template, "short" => $short];
    }

    public function logger($datum, $funktion, $ip, $status) {

        $info = "";
        $error = "";
        $success = "";

        $status = json_decode($status);

        if ($status->info) {
            $info = implode(";", $status->info);
        }
        if ($status->error) {
            $error = implode(";", $status->error);
        }
        if ($status->success) {
            $success = implode(",", $status->success);
        }

        $logger_table = env("LOGGER_TABLE");

        $insert_params = ['datum' => $datum,
            'funktion' => $funktion,
            'ip' => $ip,
            'statusInfo' => $info,
            'statusError' => $error,
            'statusSuccess' => $success];

        DB::table($logger_table)->insert($insert_params);

        return;
    }

    // führt die eigentliche Aufgabe durch und kümmert sich um das anlegen der Ordner und Datenbank
    public function createInstanz($template, $short, $long, $CN) {

        $output['debug'] = [];
        $output['info'] = [];
        $output['success'] = [];
        $output['error'] = [];

        $debug = env("APP_DEBUG", false);

        $moodledir = env("MOODLE_DIR");

        $info = "Methode wurde aufgerufen mit folgenden Parametern:<br />";
        $info .= "Template: " . $template . "<br />";
        $info .= "Short: " . $short . "<br />";
        array_push($output['info'], $info);

        # Alles OK, die Instanz kann angelegt werden
        $info = "Neue Moodle-Instanz [" . $short . "] wird angelegt...";
        array_push($output['info'], $info);

        $info = "Lege Moodle Datenbank $short an...";
        array_push($output['info'], $info);
        $response_moodle_db = $this->add_moodle_db($template, $short, $long, $CN);
        if (!empty($response_moodle_db['error'])) {
            array_push($output['error'], "Datenbank [" . $short . "] wurde nicht erfolgreich angelegt");
        } else {
            array_push($output['success'], "Datenbank [" . $short . "] wurde angelegt");
        }

        $info = "Erzeuge Moodle-Verzeichnis...";
        array_push($output['info'], $info);
        $response_moodle_filesystem = $this->add_moodle_filesystem($template, $short);
        if (!empty($response_moodle_filesystem['error'])) {
            array_push($output['error'], "Dateisystem [" . $short . "] wurde nicht erfolgreich angelegt");
        } else {
            array_push($output['success'], "Dateisystem [" . $short . "] wurde angelegt");
        }

        # CLI Upgrade
        $info = "CLI Upgrade durchfuehren...";
        array_push($output['info'], $info);
        $cmd = "php " . $moodledir . "/" . $short . "/admin/cli/upgrade.php --non-interactive --allow-unstable";
        $cmd_upgrade = shell_exec($cmd);
        if ($debug) {
            array_push($output['debug'], $cmd);
        }

        if (!$cmd_upgrade) {
            array_push($output['success'], "Aktualisierung der Moodle Instanz wurde erfolgreich durchgeführt!");
        } else {
            array_push($output['error'], "Aktualisierung der Moodle Instanz konnte nicht durchgeführt werden!");
        }

        # Cache leeren
        $info = "Cache leeren durchfuehren...";
        array_push($output['info'], $info);
        $cmd = "php " . $moodledir . "/" . $short . "/admin/cli/purge_caches.php";
        $cmd_purge = shell_exec($cmd);
        if ($debug) {
            array_push($output['debug'], $cmd);
        }

        if (!$cmd_purge) {
            array_push($output['success'], "Cache wurde erfolgreich geleert!");
        } else {
            array_push($output['error'], "Cache konnte nicht geleert werden!");
        }

        $merged_output = array_merge_recursive($response_moodle_db, $response_moodle_filesystem);
        $out = array_merge_recursive($output, $merged_output);

        $response = json_encode($out);

        return $response;
    }

    public function deleteInstanz($short) {

        $dbcon = $this->dbcon;

        $output['debug'] = [];
        $output['info'] = [];
        $output['success'] = [];
        $output['error'] = [];

        $debug = env("APP_DEBUG", false);

        $moodledir = env("MOODLE_DIR");
        $moodledatadir = env("MOODLE_DATA_DIR");
        $moodlecachedir = env("MOODLE_CACHE_DIR");
        $phpmyadminhost = env("PHPMYADMINHOST");
        $storage_table = env("STORAGE_TABLE");

        $dbhost = env("DB_HOST");

        $info = "Methode wurde aufgerufen mit folgenden Parametern<br />";
        $info .= "Short: " . $short;
        array_push($output['info'], $info);

        if ($short == "root" || $short == "mysql") {
            die("Ungültiger Parameter übergeben!");
        }

        $moodlepath = $moodledir . "/" . $short;
        $moodledatapath = $moodledatadir . "/" . $short;
        $moodlecachepath = $moodlecachedir . "/" . $short;
        
        $info = "Lösche Moodle-Verzeichnis $moodlepath ...";
        array_push($output['info'], $info);
        if (is_dir($moodlepath)) {
            if (file_exists($moodlepath . "/version.php")) {
                $cmd = "rm -rf " . escapeshellarg($moodlepath);
                $remove_dir = shell_exec($cmd);
                if ($debug) {
                    array_push($output['debug'], $cmd);
                }

                if (!$remove_dir) {
                    array_push($output['success'], "Ordner " . $moodlepath . " wurde gelöscht!");
                } else {
                    array_push($output['error'], "Ordner " . $moodlepath . " konnte nicht gelöscht werden!");
                }
            }
        } else {
            array_push($output['error'], "Es konnte kein Moodle Ordner gefunden werden!");
        }

        $info = "Lösche Moodle-Data-Verzeichnis $moodledatapath ...";
        array_push($output['info'], $info);
        if (is_dir($moodledatapath) && is_dir($moodledatapath . "/cache")) {
            $cmd = "rm -rf " . escapeshellarg($moodledatapath);
            $remove_dir = shell_exec($cmd);
            if ($debug) {
                array_push($output['debug'], $cmd);
            }

            if (!$remove_dir) {
                array_push($output['success'], "Ordner " . $moodledatapath . " wurde gelöscht!");
            } else {
                array_push($output['error'], "Ordner " . $moodledatapath . " konnte nicht gelöscht werden!");
            }
        } else {
            array_push($output['error'], "Es konnte kein MoodleData Ordner gefunden werden!");
        }

        $info = "Lösche Moodle-Cache-Verzeichnis $moodlecachepath ...";
        array_push($output['info'], $info);
        if (is_dir($moodledatapath)) {
            $cmd = "rm -rf " . escapeshellarg($moodlecachepath);
            $remove_dir = shell_exec($cmd);
            if ($debug) {
                array_push($output['debug'], $cmd);
            }

            if (!$remove_dir) {
                array_push($output['success'], "Ordner " . $moodlecachepath . " wurde gelöscht!");
            } else {
                array_push($output['error'], "Ordner " . $moodlecachepath . " konnte nicht gelöscht werden!");
            }
        } else {
            array_push($output['error'], "Es konnte kein $moodlecachepath Ordner gefunden werden!");
        }

        $info = "Lösche Datenbank $short ...";
        array_push($output['info'], $info);
        $sql = "DROP DATABASE IF EXISTS `" . $short . "`";
        if ($debug) {
            array_push($output['debug'], $sql);
        }
        DB::statement(\DB::raw($sql));

        $info = "Lösche Datenbank-User $short ...";
        array_push($output['info'], $info);
        $sql = "DROP USER IF EXISTS `$short`@'$dbhost';";
        if ($debug) {
            array_push($output['debug'], $sql);
        }       
        DB::statement(\DB::raw($sql));

//        $sth = $dbcon->prepare($sql);
//        if ($sth) {
//            $sth->execute();
//            array_push($output['success'], "Der Datenbankbenutzer wurde erfolgreich gelöscht!");
//        } else {
//            array_push($output['error'], "Der Datenbankbenutzer konnte nicht gelöscht werden: " . $dbcon->error . "!");
//        }

        $dbcon->query("FLUSH PRIVILEGES");

        # existieren DB Daten
        $sql = "SELECT `Host`,`Db`,`User` FROM mysql.db WHERE `User` = 'useradmin' AND `Host` = '" . $dbhost . "' AND `Db` = '" . $short . "'";
        if ($debug) {
            array_push($output['debug'], $sql);
        }
        $result = DB::select($sql);

        if ($result) {
            $sql = "REVOKE ALL PRIVILEGES ON `$short`.* FROM 'useradmin'@'" . $dbhost . "';";
            if ($debug) {
                array_push($output['debug'], $sql);
            }
            $sth = $dbcon->prepare($sql);
            if ($sth) {
                $sth->execute();
                $success = "Entfernen der Rechte für den User 'useradmin@." . $dbhost . "' war erfolgreich!";
                array_push($output['success'], $success);
            } else {
                $error = "Entfernen der Rechte für den User 'useradmin@." . $dbhost . "' war nicht erfolgreich!";
                array_push($output['error'], $error);
            }
        } else {
            array_push($output['error'], "Es wurden keine DB Daten gefunden!");
        }

        if ($dbhost != $phpmyadminhost) {
            $sql = "SELECT `Host`,`Db`,`User` FROM mysql.db WHERE `User` = 'useradmin' AND `Host` = '" . $phpmyadminhost . "' AND `Db` = '" . $short . "'";
            if ($debug) {
                array_push($output['debug'], $sql);
            }
            $result = DB::select($sql);

            if ($result) {
                $sql = "REVOKE ALL PRIVILEGES ON `$short`.* FROM 'useradmin'@'" . $phpmyadminhost . "';";
                if ($debug) {
                    array_push($output['debug'], $sql);
                }
                $sth = $dbcon->prepare($sql);
                if ($sth) {
                    $sth->execute();
                    $success = "Entfernen der Rechte für den User 'useradmin@." . $phpmyadminhost . "' war erfolgreich!";
                    array_push($output['success'], $success);
                } else {
                    $error = "Entfernen der Rechte für den User 'useradmin@." . $phpmyadminhost . "' war nicht erfolgreich: " . $dbcon->error . "!";
                    array_push($output['error'], $error);
                }
            }
        }

        $info = "Lösche Datenbank-Infos: " . $storage_table . "...";
        array_push($output['info'], $info);

        DB::table($storage_table)->where(["dbname" => $short])->delete();

        $response = json_encode($output, JSON_UNESCAPED_UNICODE);

        return $response;
    }

    private function add_moodle_db($template, $short, $long, $CN) {

        $dbcon = $this->dbcon;

        $output['debug'] = [];
        $output['info'] = [];
        $output['success'] = [];
        $output['error'] = [];

        $debug = env("APP_DEBUG");

        $storage_table = env("STORAGE_TABLE");
        $phpmyadminhost = env("PHPMYADMINHOST");
        $baseurl = env("APP_URL");
        $templatedir = env("TEMPLATE_DIR");
        $tempdir = env("TEMP_DIR");

        # wird es noch gebraucht?
        $dbhost = env("DB_HOST");
        $dbuser = env('DB_USERNAME');
        $dbpass = env('DB_PASSWORD');

        if (empty($dbuser) || empty($dbpass)) {
            array_push($output['error'], "Kein DB User oder Passwort gefunden!");
            return $output;
        }

        # Zufallspasswort generieren
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[$i] = $alphabet[$n];
        }
        $mysql_password = implode($pass);

        /* BEGIN TRANSACTION */
        DB::beginTransaction();

        # Datenbank anlegen
        $sql = "CREATE DATABASE `" . $short . "`";
        if ($debug) {
            array_push($output['debug'], $sql);
        }
        DB::statement(\DB::raw($sql));

        # Auf UTF-8 umstellen
        $sql = "SET NAMES UTF8MB4 COLLATE 'utf8mb4_unicode_ci'";
        if ($debug) {
            array_push($output['debug'], $sql);
        }
        try {
            DB::statement(\DB::raw($sql));
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim SET NAMES: " . $ex->getMessage());
            DB::rollback();
        }

        // VERBUGGT!
        //$sql = "ALTER DATABASE `" . $short . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        //DB::statement(\DB::raw($sql));
        
        
        # Rechte vergeben
        # Instanz User > Instanz
        $sql = "GRANT CREATE, DROP, REFERENCES, ALTER, DELETE, INDEX, INSERT, SELECT, UPDATE, CREATE TEMPORARY TABLES, LOCK TABLES ON `$short`.* TO '$short'@'$dbhost' IDENTIFIED BY '$mysql_password';";
        if ($debug) {
            array_push($output['debug'], $sql);
        }
        try {
            DB::statement(\DB::raw($sql));
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim GRANT: " . $ex->getMessage());
            DB::rollback();
        }

        # Useradmin > Instanz
        $sql = "GRANT SELECT, INSERT, UPDATE, DELETE ON `$short`.* TO 'useradmin'@'$dbhost';";
        if ($debug) {
            array_push($output['debug'], $sql);
        }
        try {
            DB::statement(\DB::raw($sql));
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim GRANT: " . $ex->getMessage());
            DB::rollback();
        }

        if ($dbhost != $phpmyadminhost) {
            $sql = "GRANT SELECT, INSERT, UPDATE, DELETE ON `$short`.* TO 'useradmin'@'$phpmyadminhost';";
            if ($debug) {
                array_push($output['debug'], $sql);
            }
            try {
                DB::statement(\DB::raw($sql));
            } catch (\Illuminate\Database\QueryException $ex) {
                print("Fehler beim GRANT: " . $ex->getMessage());
                DB::rollback();
            }
        }

        $dbcon->query("FLUSH PRIVILEGES");

        # Moodle-Datenbank anhand der Template Instanz befüllen
        $gruppe_export = $tempdir . "/" . $template . ".sql";
        $sql_cmd = "mysqldump -h$dbhost -u$dbuser -p$dbpass $template > $gruppe_export";
        if ($debug) {
            array_push($output['debug'], $sql_cmd);
        }
        $resp = shell_exec($sql_cmd);

        $sql_cmd = "mysql -h$dbhost -u$dbuser -p$dbpass $short < $gruppe_export";
        if ($debug) {
            array_push($output['debug'], $sql_cmd);
        }
        $resp = shell_exec($sql_cmd);

        # Dump File löschen
        unlink($gruppe_export);

        $act_time = time();

        $match = ['id' => '1'];
        $updates = ['fullname' => $long,
            'shortname' => $short,
            'timecreated' => $act_time,
            'timemodified' => $act_time];
        try {
            DB::table($short . ".mdl_course")
                    ->where($match)
                    ->update($updates);
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim UPDATE: " . $ex->getMessage());
            DB::rollback();
        }

        # Path für MRBS
        $match = ['name' => 'serverpath', 'plugin' => 'block/mrbs_rlp'];
        try {
            DB::table($short . ".mdl_config_plugins")
                    ->where($match)
                    ->update(['value' => $baseurl . "/" . $short . "/blocks/mrbs_rlp/web"]);
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim UPDATE: " . $ex->getMessage());
            DB::rollback();
        }

        # Path für mimeTex
        $match = ['name' => 'pathlatex', 'plugin' => 'filter_tex'];
        try {
            DB::table($short . ".mdl_config_plugins")
                    ->where($match)
                    ->update(['value' => "/srv/www/moodle/" . $short . "/filter/tex/mimetex.linux"]);
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim UPDATE: " . $ex->getMessage());
            DB::rollback();
        }

        $match = ['name' => 'pathmimetex', 'plugin' => 'filter_tex'];
        try {
            DB::table($short . ".mdl_config_plugins")
                    ->where($match)
                    ->update(['value' => "/srv/www/moodle/" . $short . "/filter/tex/mimetex.linux"]);
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim UPDATE: " . $ex->getMessage());
            DB::rollback();
        }


        # TRUNCATE auf die neue mdl_log
        $sql = "TRUNCATE `" . $short . "`.mdl_log";
        try {
            DB::statement(\DB::raw($sql));
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim TRUNCATE: " . $ex->getMessage());
            DB::rollback();
        }

        # TRUNCATE auf die neue mdl_config_log
        $sql = "TRUNCATE `" . $short . "`.mdl_config_log";
        try {
            DB::statement(\DB::raw($sql));
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim TRUNCATE: " . $ex->getMessage());
            DB::rollback();
        }

        # TRUNCATE auf die neue mdl_upgrade_log
        $sql = "TRUNCATE `" . $short . "`.mdl_upgrade_log";
        try {
            DB::statement(\DB::raw($sql));
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim TRUNCATE: " . $ex->getMessage());
            DB::rollback();
        }

        # TRUNCATE auf die neue mdl_log_display
        $sql = "TRUNCATE `" . $short . "`.mdl_log_display";
        try {
            DB::statement(\DB::raw($sql));
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim TRUNCATE: " . $ex->getMessage());
            DB::rollback();
        }

        # MNET Host anpassen
        $match = ['id' => '1'];
        try {
            DB::table($short . ".mdl_mnet_host")
                    ->where($match)
                    ->update(['wwwroot' => $baseurl . "/" . $short]);
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim UPDATE: " . $ex->getMessage());
            DB::rollback();
        }

        # DB Informationen in der Datenbank ablegen
        $inserts = ["CN" => $CN,
            "rpIdmOrgShortName" => $long,
            "shortname" => $short,
            "dbname" => $short,
            "dbuser" => $short,
            "dbpass" => $mysql_password];

        try {
            DB::table($storage_table)->insert($inserts);
        } catch (\Illuminate\Database\QueryException $ex) {
            print("Fehler beim INSERT: " . $ex->getMessage());
            DB::rollback();
        }



        /* COMMIT TRANSACTION */
        DB::commit();

        return $output;
    }

    private function add_moodle_filesystem($template, $short) {

        $output['info'] = [];
        $output['success'] = [];
        $output['error'] = [];

        $moodledir = env("MOODLE_DIR");
        $moodledatadir = env("MOODLE_DATA_DIR");
        $templatedir = env("TEMPLATE_DIR");

        $moodlepath = $moodledir . "/" . $short;
        $moodledatapath = $moodledatadir . "/" . $short;

        /* Nicht unter Windows */
        # SymLink erzeugen
        $out = "Moodle Ordner wird erstellt: " . $moodlepath;
        array_push($output['info'], $out);
        $target_pointer = $templatedir . "/" . $template;

        $result = symlink($target_pointer, $moodlepath);
        if ($result) {
            array_push($output['success'], "Moodle Symlink wurde erstellt!");
        } else {
            array_push($output['error'], "Moodle Symlink konnte nicht erstellt werden!");
        }

        /* Alternative *
          # SymLink erzeugen
          #$cmd = "ln -s ".$templatedir."/".$gruppe." ".$moodledir."/".$short;
          #echo $cmd."<br />";
          #system($cmd) == 0 or print "$cmd failed: $?";

          /*
          echo "<br />Moodle Ordner wird erstellt: " . $moodlepath . "<br />";
          $result = mkdir($moodlepath, 0755, true);
          if ($result) {
          echo "Ordner wurde erstellt!<br />";
          } else {
          echo "Ordner NICHT erstellt werden!<br />";
          }
         */

        // Create Moodledata
        $out = "Moodledata Ordner wird erstellt: " . $moodledatapath;
        array_push($output['info'], $out);
        $result = mkdir($moodledatapath, 0777, true);
        if ($result) {
            array_push($output['success'], "Moodledata Ordner wurde erstellt!");
        } else {
            array_push($output['error'], "Moodledata Ordner konnte nicht erstellt werden!");
        }

        # weitere Verzeichnis in Moodledata anlegen
        mkdir($moodledatapath . "/cache");
        mkdir($moodledatapath . "/filedir");
        mkdir($moodledatapath . "/lang");
        mkdir($moodledatapath . "/sessions");
        mkdir($moodledatapath . "/temp");
        mkdir($moodledatapath . "/trashdir");
        mkdir($moodledatapath . "/upgradelogs");

        # Rechte setzen
        #chmod_fix($datadir);
        //$cmd = "chgrp -R www-data $moodledatapath";
        //$result = shell_exec($cmd);
        $cmd = "chmod -R 777 $moodledatapath";
        $result = shell_exec($cmd);

        # htaccess in Moodledata
        #$out .= "htaccess Datei anlegen...<br />";
        #$htaccess = "deny from all\n";
        #$htaccess .= "AllowOverride None";
        #open( $fh, '>', "$datadir/.htaccess" );
        #print $fh $htaccess;
        #close $fh;
        # Sprachpakete
        $out = "Kopieren vom Lang Ordner\n";
        array_push($output['info'], $out);
        $cmd = "rsync -rultWp " . $moodledatadir . "/" . $template . "/lang " . $moodledatadir . "/" . $short . "/";
        array_push($output['info'], $cmd);
        $result = shell_exec($cmd);
        if (!$result) {
            array_push($output['success'], "Sprachdateien wurden kopiert!");
        } else {
            array_push($output['error'], "Sprachdateien konnten nicht kopiert werden!");
        }

        return $output;
    }

    /* Liefert eine Liste aller Instanzen, die der CN Erkennung entsprechen */

    public function listInstances() {

        $dbcon = $this->dbcon;

        $storage_table = env("STORAGE_TABLE");
        $debug = env("APP_DEBUG", false);

        $output['debug'] = [];
        $output['info'] = [];
        $output['success'] = [];
        $output['error'] = [];
        $output['liste'] = [];

        $select_qry = ['CN', 'rpIdmOrgShortName', 'shortname'];
        $where = ["CN", "!=", ''];
        $instanzen = DB::table($storage_table)->select($select_qry)->where('CN', '!=', '')->get();

        $ext_infos = [];

        foreach ($instanzen as $instanz) {

            $CN = $instanz->CN;
            $short = $instanz->shortname;
            $rpIdmOrgShortName = $instanz->rpIdmOrgShortName;

            $hostname = env("APP_URL");
            $url_webservice = $hostname . "/" . $short . "/webservice/rest/simpleserver.php";
            $realpath = env("MOODLE_DIR") . "/" . $short;
            $realdatapath = env("MOODLE_DATA_DIR") . "/" . $short;
            $url_instance = $hostname . "/" . $short;

            $ext_infos[] = ["CN" => $CN,
                "rpIdmOrgShortName" => $rpIdmOrgShortName,
                "shortname" => $short,
                "url_webservice" => $url_webservice,
                "realpath" => $realpath,
                "realdatapath" => $realdatapath,
                "url_instance" => $url_instance];
        }

        return $ext_infos;
    }

}
