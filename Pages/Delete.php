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

  public function Delete($page_link) {
    global $database;

    $SQL = "SELECT `page_id` FROM `".self::$table_prefix."pages` WHERE `link`='$page_link'";
    $page_id = $database->get_one($SQL, MYSQL_ASSOC);
    if ($database->is_error())
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    if (!is_null($page_id) && ($page_id > 0)) {
      // page exists, delete it...
      require_once WB_PATH.'/framework/functions.php';
      delete_page($page_id);
    }
    return true;
  } // Delete()
} // class DeletePage

/*
private function deletePage($page_id) {
  global $database;
  $dbPages = new db_wb_pages();
  $where = array();
  $where[db_wb_pages::field_page_id] = $page_id;
  $pages = array();
  if (!$dbPages->sqlSelectRecord($where, $pages)) {
  		$this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $dbPages->getError()));
  		return false;
  }
  if (sizeof($pages) == 0) {
  		$this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf(kit_error_page_not_found, $page_id)));
  		return false;
  }
  $parent = $pages[0][db_wb_pages::field_parent];
  $link = $pages[0][db_wb_pages::field_link];

  $dbSections = new db_wb_sections();
  $where = array();
  $where[db_wb_sections::field_page_id] = $page_id;
  $sections = array();
  if (!$dbSections->sqlSelectRecord($where, $sections)) {
  		$this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $dbSections->getError()));
  		return false;
  }
  foreach ($sections as $section) {
  		$section_id = $section[db_wb_sections::field_section_id];
  		// Include the modules delete file if it exists
  		if(file_exists(WB_PATH.'/modules/'.$section[db_wb_sections::field_module].'/delete.php')) {
  		  require(WB_PATH.'/modules/'.$section[db_wb_sections::field_module].'/delete.php');
  		}
  }

  $where = array();
  $where[db_wb_pages::field_page_id] = $page_id;
  if (!$dbPages->sqlDeleteRecord($where)) {
  		$this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $dbPages->getError()));
  		return false;
  }

  $where = array();
  $where[db_wb_sections::field_page_id] = $page_id;
  if (!$dbSections->sqlDeleteRecord($where)) {
  		$this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $dbSections->getError()));
  		return false;
  }

  // Include the ordering class or clean-up ordering
  $order = new order(TABLE_PREFIX.'pages', 'position', 'page_id', 'parent');
  $order->clean($parent);

  // Unlink the page access file and directory
  $directory = WB_PATH. PAGES_DIRECTORY. $link;
  $filename = $directory. PAGE_EXTENSION;
  $directory .= '/';
  if (file_exists($filename)) {
    if (!is_writable(WB_PATH .PAGES_DIRECTORY .'/')) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf(kit_error_delete_access_file, $filename)));
      return false;
    }
    else {
      unlink($filename);
      if (file_exists($directory) && rtrim($directory,'/') != WB_PATH .PAGES_DIRECTORY && substr($link, 0, 1) != '.') {
        rm_full_dir($directory);
      }
    }
  }
  return true;
} // deletePage()
*/