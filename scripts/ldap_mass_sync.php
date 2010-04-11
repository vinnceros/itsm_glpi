<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2010 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Walid Nouh
// Purpose of file:
// ----------------------------------------------------------------------

if ($argv) {
   for ($i=1;$i<count($argv);$i++)
   {
      //To be able to use = in search filters, enter \= instead in command line
      //Replace the \= by ° not to match the split function
      $arg=str_replace('\=','°',$argv[$i]);
      $it = explode("=",$arg);
      $it[0] = preg_replace('/^--/','',$it[0]);

      //Replace the ° by = the find the good filter
      $it=str_replace('°','=',$it);
      $_GET[$it[0]] = $it[1];
   }
}

if ((isset($argv) && in_array('help',$argv)) || isset($_GET['help'])) {
   echo "Usage : php -q -f ldap_mass_sync.php [action=<option>]  [ldapservers_id=ID]\n";
   echo "Options values :\n";
   echo "0 : import users only\n";
   echo "1 : synchronize existing users only\n";
   echo "2 : import & synchronize users\n";
   exit (0);
}

define('GLPI_ROOT', '..');
include (GLPI_ROOT . "/inc/includes.php");

// Default action : synchro
// - possible option :
//   - 0 : import new users
//  - 1 : synchronize users
//  - 2 : force synchronization of all the users (even if ldap timestamp wasn't modified)
$options['action'] = AuthLDAP::ACTION_SYNCHRONIZE;
$options['ldapservers_id'] = NOT_AVAILABLE;
$options['filter'] = '';
foreach ($_GET as $key => $value) {
   $options[$key] = $value;
}

if (!canUseLdap() || !countElementsInTable('glpi_authldaps')) {
   echo "LDAP extension is not active or no LDAP directory defined";
}

$sql = "SELECT `id`, `name` FROM  `glpi_authldaps`";
//Get the ldap server's id by his name
if ($_GET["ldapservers_id"] != '') {
   $sql.= " WHERE id=" . $options['ldapservers_id'];
}

$result = $DB->query($sql);
if ($DB->numrows($result) == 0 && $_GET["ldapservers_id"] != NOT_AVAILABLE) {
   echo "LDAP Server not found";
}
else
{
   foreach($DB->request($sql) as $datas) {
      $options['ldapservers_id'] = $datas['id'];
      import ($options);
   }
}

/**
 * Function to import or synchronise all the users from an ldap directory
 * @param action the action to perform (add/sync)
 * @param datas the ldap connection's datas
 */
function import($options)
{
   //The ldap server id is passed in the script url (parameter server_id)
   foreach (AuthLdap::getAllUsers($options) as $user) {
      AuthLdap::ldapImportUserByServerId(array('method'=>AuthLDAP::IDENTIFIER_LOGIN,
                                               'value'=>$user["user"]),
                                         $options['action'],
                                         $options['ldapservers_id']);
      echo ".";
   }
}
?>
