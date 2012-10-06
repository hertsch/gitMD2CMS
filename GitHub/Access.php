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

// need Page function
require_once 'Pages/Delete.php';

// need the libMarkdown
require_once WB_PATH.'/modules/lib_markdown/standard/markdown.php';

class Access {

  // error message
  private static $error = '';
  // the name of the configuration file
  protected static $config_file = 'config.json';
  // array for the configuration settings
  protected static $config = null;

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
   * Get the configuration from the config.json file
   *
   * @return boolean
   */
  protected function getConfiguration() {
    if (!file_exists(WB_PATH.'/modules/gitMD2CMS/config.json')) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Missing the configuration file /modules/gitMD2CMS/config.json!'));
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
   * @param integer $level
   * @param string $path
   * @param array reference $entries
   * @return boolean
   */
  protected static function readDirectory($cmd, $level, $path, &$entries) {
    $result = self::gitGet($cmd.$path);
    if (!isset($result['meta']['status']))
      // got no status information from GitHub
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Got no status information from GitHub, perhaps an connection error.'));
    if ($result['meta']['status'] <> 200)
      // gitHub status is not ok!
      self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf('Github error: %s - command: %s', $result['data']['message'], $cmd)));
    foreach ($result['data'] as $entry) {
      if ($entry['type'] == 'dir') {
        self::readDirectory($cmd, $level+1, '/'.$entry['path'], $entries);
      }
      else {
        // we accept only files with .md extension
        if (false === ($title = stristr($entry['name'], '.md', true))) continue;
        $ord = 9999;
        if (false !== strpos($title, '__')) {
          list($ord, $title) = explode('__', $title, 2);
        }
        $directory = substr($entry['path'], 0, strrpos($entry['path'], '/'));
        $entries[] = array(
            'type' => $entry['type'],
            'file_name' => $entry['name'],
            'file_path' => $entry['path'],
            'file_sha' => $entry['sha'],
            'file_size' => $entry['size'],
            'directory' => $directory,
            'title' => $title,
            'order' => $ord,
            'level' => $level
            );
      }
    }
    return true;
  } // readDirectory()

  /**
   * Action handler for the class Access
   */
  public function action() {
    global $database;

    // get the configuration
    if (!$this->getConfiguration())
      exit($this->getError());

    // loop through the content settings and get the informations from GitHub
    foreach (self::$config['content'] as $content_group) {
      // owner and name must be set!
      if (!isset($content_group['repository']['owner']) || !isset($content_group['repository']['name']))
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Missing "owner" and/or "name" informations for the repository!'));
      // get the repository root
      $repository_root = (isset($content_group['repository']['root']) && !empty($content_group['repository']['root'])) ? $content_group['repository']['root'] : '';
      $repository_root = self::removeLeadingAndTrailingChar($repository_root, '/');
      // set the page level to zero (root)
      $level = 0;
      // read the files and directories from the first level
      $cmd = '/repos/'.$content_group['repository']['owner'].'/'.$content_group['repository']['name'].'/contents';
      $result = array();
      $this->readDirectory($cmd, $level, $repository_root, $result);

      $existing_pages = array();
      $SQL = "SELECT `file_path`, `file_sha` FROM `".self::$config['table_prefix']."mod_gitmd2cms_contents` WHERE `name`='{$content_group['repository']['name']}' AND `root`='{$content_group['repository']['root']}'";
      if (null == ($query = $database->query($SQL)))
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      while (false !== ($page = $query->fetchRow(MYSQL_ASSOC)))
        $existing_pages[$page['file_path']] = $page['file_sha'];

      // loop through the array and get the contents
      foreach ($result as $file) {
        $update = false;
        // first we check if this entry already exists
        if (isset($existing_pages[$file['file_path']]) && ($existing_pages[$file['file_path']] == $file['file_sha'])) {
          // delete the entry from the array for the existing pages
          unset($existing_pages[$file['file_path']]);
          continue;
        }

        $content = $this->gitGet($cmd.'/'.$file['file_path']);

        if (!isset($content['meta']['status']))
          // got no status information from GitHub
          self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Got no status information from GitHub, perhaps an connection error.'));
        if ($content['meta']['status'] <> 200)
          // gitHub status is not ok!
          self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, sprintf('Github error: %s - command: %s', $content['data']['message'], $cmd.'/'.$file['file_path'])));
        if (!isset($content['data']['content']))
          // missing the content
          self::setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Got no content from GitHub for: '.$cmd.'/'.$file['file_path']));
        // the content is base 64 encoded ...
        $text = base64_decode($content['data']['content']);
        // change from Markdown to HTML
        $text = Markdown($text);

        if ($update) {
          // update the existing entry
          $SQL = "UPDATE `".self::$config['table_prefix']."mod_gitmd2cms_contents` SET `file_sha`='{$file['file_sha']}', `file_size`='{$file['file_size']}', `content`='$text' WHERE `file_path`='{$file['file_path']}'";
          $database->query($SQL);
          if ($database->is_error())
            $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        }
        else {
          // add new entry in database
          $SQL = "INSERT INTO `".self::$config['table_prefix']."mod_gitmd2cms_contents` (`name`,`root`,`file_name`,`file_path`,`file_sha`,`file_size`,`directory`,`title`,`order`,`level`,`content`) ".
              "VALUES ('{$content_group['repository']['name']}','{$content_group['repository']['root']}','{$file['file_name']}','{$file['file_path']}','{$file['file_sha']}','{$file['file_size']}','{$file['directory']}','{$file['title']}','{$file['order']}','{$file['level']}','$text')";
          $database->query($SQL);
          if ($database->is_error())
            $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        }
      }
    }
    // check if there are pages to delete...
    if (count($existing_pages) > 0) {
      $deletePage = new DeletePage();
      foreach ($existing_pages as $link => $sha) {
        $deletePage->Delete('/test/test2');
      }
    }
    echo "<pre>";
    print_r($existing_pages);
    echo "</pre>";

    exit('ok');
  } // action()

} // class Access