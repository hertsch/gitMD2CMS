<?php
/*
$curl = curl_init('http://localhost/sample.php');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_USERPWD, 'key:123456');
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_USERAGENT, 'Sample Code');

$response = curl_exec($curl);
$resultStatus = curl_getinfo($curl);

if($resultStatus['http_code'] == 200) {
  echo $response;
} else {
  echo 'Call Failed '.print_r($resultStatus);
}
*/

$curl = curl_init('https://api.github.com/authorizations');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($curl, CURLOPT_USERAGENT, 'Sample Code');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($curl);
curl_close($curl);
echo "<pre>";
print_r($result);
echo "</pre>";