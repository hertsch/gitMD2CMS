<?php

/**
 * gitMD2CMS
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/gitMD2CMS
 * @copyright 2012 phpManufaktur by Ralf Hertsch
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace gitMD2CMS\GitHub;

use gitMD2CMS\Pages\DeletePage;
use gitMD2CMS\Pages\AddPage;
use gitMD2CMS\Pages\PagePositions;

// need Page functions
require_once 'Pages/Delete.php';
require_once 'Pages/Add.php';
require_once 'Pages/Position.php';

// need the libMarkdown
require_once WB_PATH.'/modules/lib_markdown/standard/markdown.php';

class Access {

  // error message
  private static $error = '';
  // the name of the configuration file
  protected static $config_file = 'config.json';
  // array for the configuration settings
  protected static $config = null;
  // active repository: name
  protected static $repository_name = null;
  // active repository: root
  protected static $repository_root = null;
  // active repository: already existing pages
  protected static $repository_pages = null;

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

  /**
   * Get the configuration from the config.json file
   *
   * @return boolean
   */
  protected function getConfiguration() {
    if (!file_exists(WB_PATH.'/modules/gitMD2CMS/config.json')) {
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Missing the configuration file /modules/gitMD2CMS/config.json!'));
      return false;
    }
    // read the configuration file into an array
    self::$config = json_decode(file_get_contents(WB_PATH.'/modules/gitMD2CMS/config.json'), true);

    // check the TABLE_PREFIX
    if (!isset(self::$config['table_prefix']) || (isset(self::$config['table_prefix']) && empty(self::$config['table_prefix'])))
      self::$config['table_prefix'] = TABLE_PREFIX;

    // check if the main configuration entries exists
    if (!isset(self::$config['content']) || !isset(self::$config['content'][0])) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Missing the configuration group "content" or the first entry "0", please check the config.json!'));
      return false;
    }
    return true;
  } // getConfiguration()

  /**
   * GET command to Github
   *
   * @param string $get
   * @return mixed
   */
  protected static function gitGet($get, $params=array()) {
    if (strpos($get, 'https://api.github.com') === 0)
      $command = $get;
    else
      $command = "https://api.github.com$get?callback=return";
    if (!empty($params)) {
      $command .= '&'.http_build_query($params);
    }
    $ch = curl_init($command);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    $matches = array();
    preg_match('/{(.*)}/', $result, $matches);
    return json_decode($matches[0], true);
  } // gitGet()

  /**
   * Remove a leading and/or trailing character from the give string
   *
   * @param string $string
   * @param string $char
   * @return string
   */
  protected static function removeLeadingAndTrailingChar($string, $char) {
    if (!empty($string) && $string[0] == $char)
      $string = substr($string, 1);
    if (strrpos($string, $char) == strlen($string)-1)
      $string = substr($string, 0, strlen($string)-1);
    return $string;
  } // removeLeadingAndTrailingChar()

  /**
   * Read a GitHub directory recursivly and get all files into an array
   *
   * @param string $cmd
   * @param string $path
   * @param array reference $entries
   * @return boolean
   */
  protected static function readDirectory($cmd, $path, &$entries) {
    $result = self::gitGet($cmd.$path);
    if (!isset($result['meta']['status']))
      // got no status information from GitHub
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Got no status information from GitHub, perhaps an connection error.'));
    if ($result['meta']['status'] <> 200)
      // gitHub status is not ok!
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf('Github error: %s - command: %s', $result['data']['message'], $cmd)));
    foreach ($result['data'] as $entry) {
      if ($entry['type'] == 'dir') {
        // read the directory content
        self::readDirectory($cmd, '/'.$entry['path'], $entries);
      }
      else {
        // we accept only files with .md extension
        if (false === stristr($entry['name'], '.md', true)) continue;
        // read the MD file to the database
        self::readMDfile($cmd, $entry['path'], $entry['sha']);
      }
    }
    return true;
  } // readDirectory()

  /**
   * Get the content from the GitHub MD file into the database
   *
   * @param string $cmd
   * @param string $path
   * @param string $sha
   * @return boolean
   */
  protected static function readMDfile($cmd, $path, $sha) {
    global $database;

    $update = false;
    // first we check if this entry already exists
    if (isset(self::$repository_pages[$path])) {
      $update = true;
      // delete the entry from the array for the existing pages
      if (self::$repository_pages[$path] == $sha) {
        // nothing to do - the entry exists and SHA is identical
        unset(self::$repository_pages[$path]);
        return true;
      }
      unset(self::$repository_pages[$path]);
    }

    $content = self::gitGet($cmd.'/'.$path);

    if (!isset($content['meta']['status']))
      // got no status information from GitHub
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Got no status information from GitHub, perhaps an connection error.'));
    if ($content['meta']['status'] <> 200)
      // gitHub status is not ok!
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf('Github error: %s - command: %s', $content['data']['message'], $cmd.'/'.$path)));
    if (!isset($content['data']['content']))
      // missing the content
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Got no content from GitHub for: '.$cmd.'/'.$path));
    // the content is base 64 encoded ...
    $text = base64_decode($content['data']['content']);
    // change from Markdown to HTML
    $html = Markdown($text);

    $file = array(
        'name' => self::$repository_name,
        'root' => self::$repository_root,
        'file_name' => $content['data']['name'],
        'file_path' => $content['data']['path'],
        'file_sha' => $content['data']['sha'],
        'directory' => substr($content['data']['path'], 0, strrpos($content['data']['path'], '/')),
        'title' => self::sanitizeText(stristr($content['data']['name'], '.md', true)),
        'description' => '',
        'keywords' => '',
        'position' => '9999',
        'content_md' => self::sanitizeVariable($text),
        'content_html' => self::sanitizeVariable($html)
        );

    // catch possible commands in the form <!--COMMAND::VALUE-->
    $commands = array('title','description','keywords','position');
    foreach ($commands as $command) {
      if (preg_match(sprintf('/<!--%s::(.*?)-->/', $command), $text, $matches)) {
        if (isset($matches[1])) {
          // set the value of the command to the file array
          $file[$command] = self::sanitizeText($matches[1]);
        }
      }
    }

    if ($update) {
      // update the already existing record
      $SQL = "UPDATE `".self::$config['table_prefix']."mod_gitmd2cms_contents` SET ";
      $start = true;
      // build the query string
      foreach ($file as $key => $value) {
        if ($start)
          $start = false;
        else
          $SQL .= ',';
        $SQL .= sprintf("`%s`='%s'", $key, $value);
      }
      $SQL .= " WHERE `file_path`='$path'";
    }
    else {
      // add a new record
      $start = true;
      $keys = '';
      $values = '';
      foreach ($file as $key => $value) {
        if ($start)
          $start = false;
        else {
          $keys .= ',';
          $values .= ',';
        }
        $keys .= sprintf("`%s`", $key);
        $values .= sprintf("'%s'", $value);
      }
      $SQL = "INSERT INTO `".self::$config['table_prefix']."mod_gitmd2cms_contents` ($keys) VALUES ($values)";
    }
    $database->query($SQL);
    if ($database->is_error())
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
    return true;
  } // readMDfile()

  /**
   * Create the HTML WYSIWYG pages from the GitHub data stored in the table
   *
   * @param array $config
   * @return boolean
   */
  protected function createPages($config) {
    global $database;

    // init the class to add pages
    $addPage = new AddPage();

    $SQL = "SELECT * FROM `".self::$config['table_prefix']."mod_gitmd2cms_contents` WHERE `name`='{$config['repository']['name']}' AND `root`='{$config['repository']['root']}'";
    if (null == ($query = $database->query($SQL)))
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));

    while (false !== ($md_file = $query->fetchRow(MYSQL_ASSOC))) {
      // loop through all records and create pages if needed
      $directory = substr($md_file['directory'], strlen($config['repository']['root']));
      $directory = self::removeLeadingAndTrailingChar($directory, '/');
      $parent_link = '/'.$config['cms']['root'];
      if (!empty($directory))
        $parent_link .= '/'.$directory;
      // get the parent page_id
      $SQL = "SELECT `page_id` FROM `".self::$config['table_prefix']."pages` WHERE `link`='$parent_link'";
      $parent_id = $database->get_one($SQL, MYSQL_ASSOC);
      if ($database->is_error())
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      if ($parent_id < 1) {
        // parent does not exists!
        continue;
      }
      $name = stristr($md_file['file_name'], '.md', true);
      $page_id = $addPage->Add($name, $md_file['title'], $parent_id, self::unsanitizeText($md_file['description']),
          self::unsanitizeText($md_file['keywords']), self::unsanitizeText($md_file['content_md']),
          self::unsanitizeText($md_file['content_html']), $md_file['id'], $md_file['position']);
echo "parent: $parent_id -> $page_id<br>";
    }
    return true;
  } // createPages()

  /**
   * Action handler for the class Access
   */
  public function action() {
    global $database;

    // get the configuration
    if (!$this->getConfiguration())
      exit($this->getError());
    // init the function to delete pages
    $deletePage = new DeletePage();
    // init the class to change the page positions
    $pagePositions = new PagePositions();

    // loop through the content settings and get the informations from GitHub
    foreach (self::$config['content'] as $content_group) {
      // owner and name must be set!
      if (!isset($content_group['repository']['owner']) || !isset($content_group['repository']['name']))
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Missing "owner" and/or "name" informations for the repository!'));
      // get the repository root
      self::$repository_root = (isset($content_group['repository']['root']) && !empty($content_group['repository']['root'])) ? $content_group['repository']['root'] : '';
      self::$repository_root = self::removeLeadingAndTrailingChar(self::$repository_root, '/');
      // get the repository name
      self::$repository_name = $content_group['repository']['name'];

      // get the already existing pages into an array
      self::$repository_pages = array();
      $SQL = "SELECT `file_path`, `file_sha` FROM `".self::$config['table_prefix']."mod_gitmd2cms_contents` WHERE `name`='{$content_group['repository']['name']}' AND `root`='{$content_group['repository']['root']}'";
      if (null == ($query = $database->query($SQL)))
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      while (false !== ($page = $query->fetchRow(MYSQL_ASSOC)))
        self::$repository_pages[$page['file_path']] = $page['file_sha'];

      // read the files and directories from the first level
      $cmd = '/repos/'.$content_group['repository']['owner'].'/'.$content_group['repository']['name'].'/contents';
      $result = array();
      $this->readDirectory($cmd, self::$repository_root, $result);
      // check if there are pages to delete...
      if (count(self::$repository_pages) > 0) {
        foreach (self::$repository_pages as $link => $sha) {
          $deletePage->Delete($sha);
        }
      }
      // create pages in the CMS
      $this->createPages($content_group);
      // set the page positions
      $pagePositions->Process($content_group);
    }

    exit('ok');
  } // action()

} // class Access