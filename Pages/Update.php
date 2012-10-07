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

class UpdatePage {

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
   * Sanitize variables and prepare them for saving in a MySQL record
   *
   * @param mixed $item
   * @return mixed
   */
  public static function sanitizeVariable($item) {
    if (!is_array($item)) {
      // undoing 'magic_quotes_gpc = On' directive
      if (get_magic_quotes_gpc())
        $item = stripcslashes($item);
      $item = self::sanitizeText($item);
    }
    return $item;
  } // sanitizeVariable()

  /**
   * Sanitize a text variable and prepare ist for saving in a MySQL record
   *
   * @param string $text
   * @return string
   */
  protected static function sanitizeText($text) {
    $text = str_replace(array("<",">","\"","'"), array("&lt;","&gt;","&quot;","&#039;"), $text);
    $text = mysql_real_escape_string($text);
    return $text;
  } // sanitizeText()

  /**
   * Unsanitize a text variable and prepare it for output
   *
   * @param string $text
   * @return string
   */
  public static function unsanitizeText($text) {
    $text = stripcslashes($text);
    $text = str_replace(array("&lt;","&gt;","&quot;","&#039;"), array("<",">","\"","'"), $text);
    return $text;
  } // unsanitizeText()


  public function Update($page_id, $page_title, $menu_title, $description, $keywords, $text, $html, $md_id) {
    global $database;

    $page_title = mysql_real_escape_string(strip_tags($page_title));
    $description = mysql_real_escape_string(strip_tags($description));
    $keywords = mysql_real_escape_string(strip_tags($keywords));
    $text = self::sanitizeVariable($text);
    $html = self::sanitizeVariable($html);

    // update the page informations
    $SQL = "UPDATE `".self::$table_prefix."pages` SET `page_title`='$page_title', ".
      "`description`='$description', `keywords`='$keywords', `menu_title`='$menu_title' ".
      "WHERE `page_id`='$page_id'";
    $database->query($SQL);
    if ($database->is_error())
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    // get the ID for the WYSIWYG section
    $SQL = "SELECT `section_id` FROM `".self::$table_prefix."sections` WHERE `page_id`='$page_id' AND `module`='wysiwyg' AND `position`= '1'";
    $section_id = $database->get_one($SQL, MYSQL_ASSOC);
    if ($database->is_error())
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    if (is_null($section_id) || ($section_id < 1))
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, "There exists no WYSIWYG section for the page ID $page_id"));

    // update the WYSIWYG section
    $SQL = "UPDATE `".self::$table_prefix."mod_wysiwyg` SET `text`='$text', `content`='$html' WHERE `section_id`='$section_id'";
    $database->query($SQL);
    if ($database->is_error())
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    // update the gitMD2CMS table
    $SQL = "SELECT `parent`,`root_parent`,`level` FROM `".self::$table_prefix."pages` WHERE `page_id`='$page_id'";
    if (null == ($query = $database->query($SQL)))
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
    $page = $query->fetchRow(MYSQL_ASSOC);

    $SQL = "UPDATE `".self::$table_prefix."mod_gitmd2cms_contents` SET `page_id`='$page_id', ".
      "`page_parent`='{$page['parent']}', `page_root_parent`='{$page['root_parent']}', ".
      "`page_level`='{$page['level']}' WHERE `id`='$md_id'";
    $database->query($SQL);
    if ($database->is_error())
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

  } // Update()


} // class UpdatePage
