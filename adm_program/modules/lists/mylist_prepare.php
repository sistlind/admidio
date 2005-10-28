<?
/******************************************************************************
 * Dieses Script setzt das SQL-Statement f�r die myList zusammen und
 * �bergibt es an das allgemeine Listen-Script
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
require("../../../adm_config/config.php");
require("../../system/function.php");
require("../../system/date.php");
require("../../system/string.php");
require("../../system/session_check_login.php");
require("parser.php");

$err_text    = "";
$sql_select  = "";
$sql_where   = "";
$sql_orderby = "";

if(strlen($_POST["column1"]) == 0)
   $err_text = "Feld 1";

if(strlen($_POST["rolle"]) == 0)
   $err_text = "Rolle";

if(strlen($err_text) != 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=feld&err_text=$err_text";
   header($location);
   exit();
}

// als erstes wird die Rolle �bergeben
$rolle = $_POST["rolle"];

// Ehemalige
if(!array_key_exists("former", $_POST))
   $act_members = 1;
else
   $act_members = 0;

$value = reset($_POST);
$key   = key($_POST);
$i     = 0;
   
// Felder zusammenstringen
for($i = 0; $i < count($_POST); $i++)
{
   if(strlen($value) > 0)
   {
      if(substr_count($key, "column") > 0)
      {
         if(strlen($sql_select) > 0) $sql_select = $sql_select. ", ";
         $sql_select = $sql_select. $value;
         $act_field  = $value;
      }
      elseif(substr_count($key, "sort") > 0)
      {
         if(strlen($sql_orderby) > 0) $sql_orderby = $sql_orderby. ", ";
         $sql_orderby = $sql_orderby. $act_field. " ". $value;
      }
      elseif(substr_count($key, "condition") > 0)
      {
         $sql = "SELECT $act_field FROM adm_user LIMIT 1, 1 ";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
         $type   = mysql_field_type($result, 0);

         $parser    = new CParser;
         $sql_where = $sql_where. $parser->makeSqlStatement($value, $act_field, $type);
      }
   }
   else
   {
      if(substr_count($key, "column") > 0)
         $act_field = "";
   }
   
   $value    = next($_POST);
   $key      = key($_POST);
}

$main_sql = "SELECT au_id, $sql_select
               FROM adm_rolle, adm_mitglieder, adm_user
              WHERE ar_ag_shortname = \'$g_organization\'
                AND ar_funktion     = \'$rolle\'
                AND ar_valid        = 1
                AND am_ar_id        = ar_id
                AND am_valid        = $act_members
                AND am_leiter       = 0
                AND am_au_id        = au_id
                    $sql_where ";
                
if(strlen($sql_orderby) > 0)
   $main_sql = $main_sql. " ORDER BY $sql_orderby ";
   
//echo $main_sql; exit();

$sql    = "UPDATE adm_session SET as_list_sql = '$main_sql' 
            WHERE as_session = '$g_session_id' ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);

// weiterleiten zur allgemeinen Listeseite
$location = "location: $g_root_path/adm_program/moduls/lists/lists_show.php?typ=mylist&mode=html&rolle=$rolle";
header($location);
exit();

?>