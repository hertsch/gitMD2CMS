<?php

/**
 * gitMD2CMS
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/gitMD2CMS
 * @copyright 2012 phpManufaktur by Ralf Hertsch
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

/*
namespace gitMD2CMS;

use gitMD2CMS\GitHub\Access;


require_once '../../config.php';
require_once 'GitHub/Access.php';

$access = new Access();
$access->action();
*/


require_once 'oauth.php';

$oauth = new githubAuthorize();
//$oauth->checkAuthentication();
$oauth->gitAuthendticate();