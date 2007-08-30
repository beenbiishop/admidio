<?php
/******************************************************************************
 * Meldet den User bei Admidio an, wenn sich dieser einloggen darf
 * Cookies setzen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once("common.php");
require_once("auto_login_class.php");

// Variablen initialisieren
$user_found = 0;
$b_auto_login = false;

// Uebergabevariablen filtern
$req_password_crypt = md5($_GET['usr_password']);

if(strlen($_GET['usr_login_name']) == 0)
{
    $g_message->show("feld", "Benutzername");
}

if($g_preferences['enable_auto_login'] == 1
&& isset($_GET['auto_login']) && $_GET['auto_login'] == 1)
{
    $b_auto_login = true;
}

// Name und Passwort pruefen
// Rolle muss mind. Mitglied sein

$sql    = "SELECT usr_id
             FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
            WHERE usr_login_name LIKE '". $_GET['usr_login_name']. "'
              AND usr_valid      = 1
              AND mem_usr_id     = usr_id
              AND mem_rol_id     = rol_id
              AND mem_valid      = 1
              AND rol_valid      = 1 
              AND rol_cat_id     = cat_id
              AND cat_org_id     = ". $g_current_organization->getValue("org_id");
$result = $g_db->query($sql);

$user_found = $g_db->num_rows($result);
$user_row   = $g_db->fetch_array($result);

if ($user_found >= 1)
{
    $act_date = date("Y-m-d H:i:s", time());
    
    // Userobjekt anlegen
    $g_current_user = new User($g_db, $user_row['usr_id']);
    
    if($g_current_user->getValue("usr_number_invalid") >= 3)
    {
        // wenn innerhalb 15 min. 3 falsche Logins stattfanden -> Konto 15 min. sperren
        if(time() - mysqlmaketimestamp($g_current_user->getValue("usr_date_invalid")) < 900)
        {
            $g_message->show("login_failed");
        }
    }

    if($g_current_user->getValue("usr_password") == $req_password_crypt)
    {
        $g_current_session->setValue("ses_usr_id", $g_current_user->getValue("usr_id"));
        $g_current_session->save();

        // Cookies fuer die Anmeldung setzen und evtl. Ports entfernen
        $domain = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
        // soll der Besucher automatisch eingeloggt bleiben, dann verfaellt das Cookie erst nach einem Jahr
        if($b_auto_login == true)
        {
            $timestamp_expired = time() + 60*60*24*365;
            
            $auto_login = new AutoLogin($g_db);
            $auto_login->setValue("atl_session_id", $g_session_id);
            $auto_login->setValue("atl_usr_id", $user_row['usr_id']);            
            $auto_login->save();
        }
        else
        {
            $timestamp_expired = 0;
            $g_current_user->setValue("usr_last_session_id", NULL);
        }
        setcookie("admidio_session_id", $g_session_id , $timestamp_expired, "/", $domain, 0);
        // User-Id und Autologin auch noch als Cookie speichern
        // vorher allerdings noch serialisieren, damit der Inhalt nicht so einfach ausgelesen werden kann
        setcookie("admidio_data", $b_auto_login. ";". $g_current_user->getValue("usr_id") , $timestamp_expired, "/", $domain, 0);

        // Logins zaehlen und aktuelles Login-Datum aktualisieren
        $g_current_user->setValue("usr_last_login",   $g_current_user->getValue("usr_actual_login"));
        $g_current_user->setValue("usr_number_login", $g_current_user->getValue("usr_number_login") + 1);
        $g_current_user->setValue("usr_actual_login", $act_date);
        $g_current_user->setValue("usr_date_invalid", NULL);
        $g_current_user->setValue("usr_number_invalid", 0);
        $g_current_user->b_set_last_change = false;
        $g_current_user->save();

        // Paralell im Forum einloggen, wenn g_forum gesetzt ist
        if($g_forum_integriert)
        {
            $g_forum->userLogin($g_current_user->getValue("usr_id"), $_GET['usr_login_name'], $req_password_crypt, 
                                $g_current_user->getValue("usr_login_name"), $g_current_user->getValue("usr_password"), 
                                $g_current_user->getValue("E-Mail"));

            $login_message = $g_forum->message;
        }
        else
        {
            // User gibt es im Forum nicht, also eine reine Admidio anmeldung.
            $login_message = "login";
        }

        // falls noch keine Forward-Url gesetzt wurde, dann nach dem Login auf
        // die Startseite verweisen
        if(isset($_SESSION['login_forward_url']) == false)
        {
            $_SESSION['login_forward_url'] = "home";
        }

        // bevor zur entsprechenden Seite weitergeleitet wird, muss noch geprueft werden,
        // ob der Browser Cookies setzen darf -> sonst kein Login moeglich
        $location = "Location: $g_root_path/adm_program/system/cookie_check.php?message_code=$login_message";
        header($location);
        exit();
    }
    else
    {
        // ungueltige Logins werden mitgeloggt
        
        if($g_current_user->getValue("usr_number_invalid") >= 3)
        {
            $g_current_user->setValue("usr_number_invalid", 1);
        }
        else
        {
            $g_current_user->setValue("usr_number_invalid", $g_current_user->getValue("usr_number_invalid") + 1);
        }
        $g_current_user->setValue("usr_date_invalid", $act_date);
        $g_current_user->b_set_last_change = false;
        $g_current_user->save();

        if($g_current_user->getValue("usr_number_invalid") >= 3)
        {
            $g_message->show("login_failed");
        }
        else
        {
            $g_message->show("password_unknown");
        }
    }
}
else
{
    $g_message->show("login_unknown");
}

?>