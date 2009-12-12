<?php
/******************************************************************************
 * RSS - Feed fuer das Gaestebuch
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer Gaestebucheintraege
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/rss.php');
require_once('../../system/classes/table_guestbook.php');

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if ($g_preferences['enable_rss'] != 1)
{
    $g_message->setForwardUrl($g_homepage);
    $g_message->show($g_l10n->get('SYS_PHR_RSS_DISABLED'));
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

// die 10 letzten Eintraege aus der DB fischen...
$sql = 'SELECT * FROM '. TBL_GUESTBOOK. '
        WHERE gbo_org_id = '. $g_current_organization->getValue('org_id'). '
        ORDER BY gbo_timestamp DESC
        LIMIT 10 ';
$result = $g_db->query($sql);


// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss = new RSSfeed('http://'. $g_current_organization->getValue('org_homepage'), $g_current_organization->getValue('org_longname'). ' - Gaestebuch', 'Die 10 neuesten Gaestebucheintraege');
$guestbook = new TableGuestbook($g_db);

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while ($row = $g_db->fetch_object($result))
{
    // ausgelesene Gaestebuchdaten in Guestbook-Objekt schieben
    $guestbook->clear();
    $guestbook->setArray($row);

    // Die Attribute fuer das Item zusammenstellen
    $title = $guestbook->getValue('gbo_name');
    $link  = $g_root_path.'/adm_program/modules/guestbook/guestbook.php?id='. $guestbook->getValue('gbo_id');
    $description = '<b>'.$guestbook->getValue('gbo_name').' schrieb am '. $guestbook->getValue('gbo_timestamp', $g_preferences['system_date'].' '.$g_preferences['system_time']).'</b>';

    // Beschreibung und Link zur Homepage ausgeben
    $description = $description. '<br /><br />'. $guestbook->getText('HTML'). 
                   '<br /><br /><a href="'.$link.'">Link auf '. $g_current_organization->getValue('org_homepage'). '</a>';

    $pubDate = date('r', strtotime($guestbook->getValue('gbo_timestamp')));


    // Item hinzufuegen
    $rss->addItem($title, $description, $pubDate, $link);
}


// jetzt nur noch den Feed generieren lassen
$rss->buildFeed();

?>