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

// we need the CMS functions
require_once WB_PATH.'/framework/functions.php';

class DeletePage {

  // error message
  private static $error = '';

  // the name of the configuration file
  protected static $config_file = 'config.json';
  // set the table prefix
  protected static $table_prefix = TABLE_PREFIX;

  /**
   * The constructor for the DeletePage class
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

  /**
   * Use the $page_link to get the $page_id and calls the system function to
   * delete the desired page
   *
   * @param string $page_link
   * @return boolean
   */
  public function Delete($page_link) {
    global $database;

    $SQL = "SELECT `page_id` FROM `".self::$table_prefix."pages` WHERE `link`='$page_link'";
    $page_id = $database->get_one($SQL, MYSQL_ASSOC);
    if ($database->is_error())
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    if (!is_null($page_id) && ($page_id > 0)) {
      // page exists, delete it...
      delete_page($page_id);
    }
    return true;
  } // Delete()

} // class DeletePage
