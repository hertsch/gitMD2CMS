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

use gitMD2CMS\Pages\UpdatePage;

require_once 'Pages/Update.php';

// we need the CMS functions
require_once(WB_PATH.'/framework/class.admin.php');
require_once(WB_PATH.'/framework/functions.php');
require_once(WB_PATH.'/framework/class.order.php');

class AddPage {

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

  public function Add($title, $parent, $description, $keywords, $text, $html, $visibility='public', $admin_groups=array(1), $viewing_groups=array(1)) {
    global $database;

    $admin = new \admin('Pages', 'pages_add', false, false);
    $title = htmlspecialchars($title);

    // ensure that the admin is in the $admin_groups and the $viewing groups
    if (!in_array(1, $admin_groups)) $admin_groups[] = 1;
    if (!in_array(1, $viewing_groups)) $viewing_groups[] = 1;

    // empty title?
    if (($title == '') || (substr($title, 0, 1) == '.')) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'The title must contain some text!'));
      return false;
    }

    // check the rights for the page
    if (!in_array(1, $admin->get_groups_id())) {
      $admin_perm_ok = false;
      foreach ($admin_groups as $adm_group) {
        if (in_array($adm_group, $admin->get_groups_id())) {
      				$admin_perm_ok = true;
        }
      }
      if ($admin_perm_ok == false) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'The given admin rights are insufficient!'));
        return false;
      }
      $admin_perm_ok = false;
      foreach ($viewing_groups as $view_group) {
        if (in_array($view_group, $admin->get_groups_id())) {
      				$admin_perm_ok = true;
        }
      }
      if ($admin_perm_ok == false) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'The given viewing rights are insufficient!'));
        return false;
      }
    }

    $admin_groups = implode(',', $admin_groups);
    $viewing_groups = implode(',', $viewing_groups);

    // create the filename
    if ($parent == '0') {
      $link = '/'.page_filename($title);
      // Dateinamen 'index' und 'intro' umbenennen um Kollisionen zu vermeiden
      if (($link == '/index') || ($link == '/intro')) {
        $link .= '_0';
        $filename = WB_PATH .PAGES_DIRECTORY .'/'. page_filename($title). '_0'. PAGE_EXTENSION;
      }
      else {
        $filename = WB_PATH. PAGES_DIRECTORY. '/'. page_filename($title). PAGE_EXTENSION;
      }
    }
    else {
      $parent_section = '';
      $parent_titles = array_reverse(get_parent_titles($parent));
      foreach ($parent_titles as $parent_title) {
        $parent_section .= page_filename($parent_title).'/';
      }
      if ($parent_section == '/') $parent_section = '';
      $page_filename = page_filename($title);
      $page_filename = str_replace('_', '-', $page_filename);
      $link = '/'. $parent_section. $page_filename;
      $filename = WB_PATH. PAGES_DIRECTORY. '/'. $parent_section. $page_filename. PAGE_EXTENSION;
      make_dir(WB_PATH. PAGES_DIRECTORY. '/'. $parent_section);
    }

    // check if an identical page file exists
    $SQL = "SELECT `page_id` FROM `".self::$table_prefix."pages` WHERE `link`='$link'";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
    }
    if (($query->numRows() > 0) ||
        (file_exists(WB_PATH. PAGES_DIRECTORY. $link. PAGE_EXTENSION)) ||
        (file_exists(WB_PATH. PAGES_DIRECTORY. $link. '/'))) {
      // the page already exists, update the contents!
      $data = $query->fetchRow(MYSQL_ASSOC);
      $updatePage = new UpdatePage();
      $updatePage->Update($data['page_id'], $title, $description, $keywords, $text, $html);
      return true;
    }

    // include the ordering class
    $order = new \order(self::$table_prefix.'pages', 'position', 'page_id', 'parent');
    // clean order
    $order->clean($parent);
    // get the new order
    $position = $order->get_new($parent);

    // get template and language of the parent page
    $SQL = "SELECT `template`, `language` FROM `".self::$table_prefix."pages` WHERE `page_id`='$parent'";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
    }
    if ($query->numRows() > 0) {
      // use the values from parent
      $data = $query->fetchRow(MYSQL_ASSOC);
      $template = $data['template'];
      $language = $data['language'];
    }
    else {
      $template = '';
      $language = DEFAULT_LANGUAGE;
    }

    $new_page = array(
        'page_title' => $title,
        'menu_title' => $title,
        'parent' => $parent,
        'template' => $template,
        'target' => '_top',
        'position' => $position,
        'visibility' =>$visibility,
        'searching' => 1,
        'menu' => 1,
        'language' => $language,
        'admin_groups' => $admin_groups,
        'viewing_groups' => $viewing_groups,
        'modified_when' => time(),
        'modified_by' => 1
        );

    $start = true;
    $keys = '';
    $values = '';
    foreach ($new_page as $key => $value) {
      if ($start)
        $start = false;
      else {
        $keys .= ',';
        $values .= ',';
      }
      $keys .= sprintf("`%s`", $key);
      $values .= sprintf("'%s'", $value);
    }
    $SQL = "INSERT INTO `".self::$table_prefix."pages` ($keys) VALUES ($values)";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
    }
    $page_id = mysql_insert_id();

    // work out the level
    $level = level_count($page_id);
    // work out root parent
    $root_parent = root_parent($page_id);
    // work out page trail
    $page_trail = get_page_trail($page_id);

    $SQL = "UPDATE `".self::$table_prefix."pages` SET `link`='$link', `level`='$level', `root_parent`='$root_parent', `page_trail`='$page_trail' WHERE `page_id`='$page_id'";
    $database->query($SQL);
    if ($database->is_error())
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    // create a new file in the /pages directory
    create_access_file($filename, $page_id, $level);

    // add position 1 to new page
    $position = 1;

    // add a new record to section table
    $SQL = "INSERT INTO `".self::$table_prefix."sections` (`page_id`,`position`,`module`,`block`) VALUES ('$page_id','$position','wysiwyg','1')";
    $database->query($SQL);
    if ($database->is_error())
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
    $section_id = mysql_insert_id();

    $content = self::sanitizeVariable($html);
    $text = self::sanitizeVariable($text);
    $SQL = "INSERT INTO `".self::$table_prefix."mod_wysiwyg` (`page_id`, `section_id`, `content`, `text`) VALUES ('$page_id', '$section_id', '$content', '$text')";
    $database->query($SQL);
    if ($database->is_error()) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
    }
    return $page_id;
  } // Add()

} // class AddPage
