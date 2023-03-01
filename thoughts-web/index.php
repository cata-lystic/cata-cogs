<?php
// These are just here for development purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("memory_limit", 512 * 1024 * 1024); // Set memory limit to 512mb so PHP cURL files successfully download
error_reporting(E_ALL);

require_once("api.php");
$config = new config();
$api = new api();


if ($platform == "web") {
  echo "<head>
  <title>Thoughts</title>

  <style>:root { --bg-color: {$config->web['backgroundColor']}; --accent-color: {$config->web['accentColor']}; --font-color: {$config->web['fontColor']}; --accent-radius: {$config->web['accentRadius']}; }</style>

  <link rel='stylesheet' href='app/thoughts.css'>
  </head>
  <body>
  
  <div id='content'>"; // create the content div for web for javascript search
}

// Website only shows if needed
if ($platform == "web") {

  $view = new view();
  
  echo "</div>"; // end #content div for web

  echo $view->create(); // Create Box

  echo $view->infoBox(); // API Info Box

  // Search Bar for Web  
  echo $view->search(array('q'=>$q, 'limit'=>$limit, 'quotes'=>$quotes, 'shuffle'=>$shuffle, 'showID'=>$showID));

  echo $view->footer(); // Footer  

  echo $view->js(); // Javascript, if enabled

}