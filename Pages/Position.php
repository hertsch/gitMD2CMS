<?php

/**
 * gitMD2CMS
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/gitMD2CMS
 * @copyright 2012 phpManufaktur by Ralf Hertsch
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace gitMD2CMS\Pages;

class PagePositions {

  // error message
  private static $error = '';

  // the name of the configuration file
  protected static $config_file = 'config.json';
  // set the table prefix
  protected static $table_prefix = TABLE_PREFIX;

  /**
   * The constructor for the PagePositions class
   */
  public function __construct() {
    // check if we need another table prefix
    if (file_exists(WB_PATH.'/modules/gitMD2CMS/'.self::$config_file)) {
      // read the configuration file into an array
      $config = json_decode(file_get_contents(WB_PATH.'/modules/gitMD2CMS/'.self::$config_file), true);
      // check the TABLE_PREFIX
      if (isset($config['table_prefix']) && !empty($config['table_prefix']))
        self::$table_prefix = $config['table_prefix'];
    }
  } // __construct()

  /**
   * Set self::$error to $error
   *
   * @param string $error
   */
  protected static function setError($error) {
    self::$error = $error;
    if (self::isError()) exit(self::getError());
  } // setError()

  /**
   * Get Error from self::$error;
   *
   * @return string $this->error
   */
  public static function getError() {
    return self::$error;
  } // getError()

  /**
   * Check if self::$error is empty
   *
   * @return boolean
   */
  public static function isError() {
    return (bool) !empty(self::$error);
  } // isError

  public function Process($config) {
    global $database;
    // first step: get the actual page informations
    $SQL = "SELECT `page_parent` FROM `".self::$table_prefix."mod_gitmd2cms_contents` WHERE `name`='{$config['repository']['name']}' AND `root`='{$config['repository']['root']}' ORDER BY `page_parent`";
    $query = $database->query($SQL);
    if ($database->is_error())
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    while (false !== ($parent = $query->fetchRow(MYSQL_ASSOC))) {
      $SQL = "SELECT `page_id` FROM `".self::$table_prefix."mod_gitmd2cms_contents` WHERE `page_parent`='{$parent['page_parent']}' ORDER BY `position` ASC";
      $pos_query = $database->query($SQL);
      if ($database->is_error())
        self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      $i = 1;
      while (false !== ($pos = $pos_query->fetchRow(MYSQL_ASSOC))) {
        $SQL = "UPDATE `".self::$table_prefix."pages` SET `position`='$i' WHERE `page_id`='{$pos['page_id']}'";
        $database->query($SQL);
        $i++;
      }
    }
    return true;
  } // Process

} // class PagePositions
