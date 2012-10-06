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

class Content {

  public function action($prompt=true) {
    $result = "TEST IT! - ".PAGE_ID;

    if ($prompt)
      echo $result;
    else
      return $result;
  } // action()

} // class Content

