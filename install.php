<?php

/**
 * gitMD2CMS
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/gitMD2CMS
 * @copyright 2012 phpManufaktur by Ralf Hertsch
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

// include class.secure.php to protect this file and the whole CMS!
if (defined('WB_PATH')) {
    if (defined('LEPTON_VERSION')) include(WB_PATH.'/framework/class.secure.php');
} else {
    $oneback = "../";
    $root = $oneback;
    $level = 1;
    while (($level < 10) && (!file_exists($root.'/framework/class.secure.php'))) {
        $root .= $oneback;
        $level += 1;
    }
    if (file_exists($root.'/framework/class.secure.php')) {
        include($root.'/framework/class.secure.php');
    } else {
        trigger_error(sprintf("[ <b>%s</b> ] Can't include class.secure.php!",
                $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
    }
}
// end include class.secure.php

global $database;
global $admin;

$table_prefix = TABLE_PREFIX;
if (file_exists(WB_PATH.'/modules/gitMD2CMS/config.json')) {
  // read the configuration file into an array
  $config = json_decode(file_get_contents(WB_PATH.'/modules/gitMD2CMS/config.json'), true);
  // check the TABLE_PREFIX
  if (isset($config['table_prefix']) && !empty($config['table_prefix']))
    $table_prefix = $config['table_prefix'];
}

// create the WYSIWYG archive table
$SQL = "CREATE TABLE IF NOT EXISTS `".$table_prefix."mod_gitmd2cms_contents` ( ".
    "`id` INT(11) NOT NULL AUTO_INCREMENT, ".
    "`name` VARCHAR(255) NOT NULL DEFAULT '', ".
    "`root` VARCHAR(255) NOT NULL DEFAULT '', ".
    "`file_name` VARCHAR(255) NOT NULL DEFAULT '', ".
    "`file_path` TEXT NOT NULL, ".
    "`file_sha` VARCHAR(64) NOT NULL DEFAULT '', ".
    "`directory` TEXT NOT NULL, ".
    "`title` VARCHAR(255) NOT NULL DEFAULT '', ".
    "`description` TEXT NOT NULL DEFAULT '', ".
    "`keywords` TEXT NOT NULL DEFAULT '', ".
    "`position` INT(11) NOT NULL DEFAULT '9999', ".
    "`content_md` LONGTEXT NOT NULL, ".
    "`content_html` LONGTEXT NOT NULL, ".
    "`timestamp` TIMESTAMP, ".
    "PRIMARY KEY (`id`) ".
    ") ENGINE=MyIsam AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

if (!$database->query($SQL))
  $admin->print_error($database->get_error());