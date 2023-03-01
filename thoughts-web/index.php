<?php
// These are just here for development purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("memory_limit", 512 * 1024 * 1024); // Set memory limit to 512mb so PHP cURL files successfully download
error_reporting(E_ALL);

require_once("api.php");
$config = new config();
$api = new api();

// Get Settings and Thoughts
$data = Files::read("app/thoughts.json");
if (!is_array($data)) $data = []; // Create data array if there are no msgs

// Get possible queries
$q = $_REQUEST['q'] ?? null; // allow a specific ID to be fetched
$limit = $_REQUEST['limit'] ?? $config->web['searchLimit']; // amount of search results to return
$shuffle = $_REQUEST['shuffle'] ?? $config->web['shuffle']; // shuffle search results
$showID = $_REQUEST['showID'] ?? $config->web['showID']; // show unique ID before each thought
$platform = $_REQUEST['platform'] ?? 'web'; // anything besides "web" will be plain text mode
$quotes = $_REQUEST['quotes'] ?? tools::quotes($config->web['quotes']); // no quotes by default
$breaks = $_REQUEST['breaks'] ?? $config->api['breaks']; // prefer <br /> over /n/r (web will overwrite this)
$js = $_REQUEST['js'] ?? $config->web['js']; // web javascript features enabled by default
$apiRequest = $_REQUEST['api'] ?? null; // API version from requester

$total = count($data); // total thoughts
if ($platform == "discord") {
  $quotes = "`"; // force Discord thoughts to be in a quote box
  $limit = ($limit > 5) ? 5 : $limit; // Discord limit can't go past 5 for now. until there's a word count
}

if ($platform == "web") {
  echo "<title>Thoughts</title>

  <style>:root { --bg-color: {$config->web['backgroundColor']}; --accent-color: {$config->web['accentColor']}; --font-color: {$config->web['fontColor']}; --accent-radius: {$config->web['accentRadius']}; }</style>

  <link rel='stylesheet' href='app/thoughts.css'>

  <body>
  
  <div id='content'>"; // create the content div for web for javascript search
}

// ?q=list creates an entire list of thoughts and then quits
if ($q == "list" && $platform != "discord") {
  foreach ($data as $id => $val) {
    $thisID = ($showID == 1) ? "#{$id}: " : null;
    echo "<p class='thought'>{$thisID}{$val['msg']}</p>";
  }

// ?q=list for a non-web platform just shows a link to the list page
} else if ($q == "list" && $platform == "discord") {
  echo "Full list of thoughts can be found at {$config->url}?q=list";

// If $q is numeric or empty, fetch a random or desired ID
} else if ($q == null || is_numeric($q)) {

  //if ($platform != "web" && $apiRequest == "") die("API version required");

  if ($total != 0) {
    $rand = ($q == null) ? rand(1, $total) : $q;
    if ($rand > $total) {
      echo "I need to think more to get to #".$rand;
    } else {
      $thisID = ($showID == 1) ? "#".$rand.": " : null;
      echo $thisID."{$quotes}".$data[$rand]['msg']."{$quotes} -".$data[$rand]['author'];
    }
  } else {
    echo "There are no thoughts...";
  }

// If $q is a string, search each thought to see if that word is in it
} else if ($q != null && !is_numeric($q)) {

  $matches = [];

  foreach ($data as $id => $val) {
    if (preg_match("/{$q}/i", $val['msg'])) {
      $matches[$id] = $val['msg'];
    }
  }


  if (count($matches) == 0) {
    echo "No thoughts found related to `$q`";
  } else {
    if ($shuffle == 1) $matches = shuffle_assoc($matches);
    $results = 0;
    foreach($matches as $ids => $vals) {
      if ($results > $limit-1) break; // stop after the $limit
      if ($results > 0) echo ($platform == "web" || $breaks == 1) ? "<br />" : "\n\r"; // different line breaks per platform
      $thisID = ($showID == 1) ? "#".$ids.": " : null;
      echo "{$thisID}{$quotes}{$vals}{$quotes}";
      $results++;
    }
  }

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