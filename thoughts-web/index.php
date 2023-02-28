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
$q = $_GET['q'] ?? null; // allow a specific ID to be fetched
$limit = $_GET['limit'] ?? $config->api['searchLimit']; // amount of search results to return
$shuffle = $_GET['shuffle'] ?? $config->api['shuffle']; // shuffle search results
$showID = $_GET['showID'] ?? $config->api['showID']; // show unique ID before each thought
$platform = $_GET['platform'] ?? 'web'; // anything besides "web" will be plain text mode
$quotes = $_GET['quotes'] ?? $config->api['quotes']; // no quotes by default
$breaks = $_GET['breaks'] ?? $config->api['breaks']; // prefer <br /> over /n/r (web will overwrite this)
$js = $_GET['js'] ?? $config->web['js']; // web javascript features enabled by default
$apiRequest = $_GET['api'] ?? null; // API version from requester

$total = count($data); // total thoughts
if ($platform == "discord") {
  $quotes = "`"; // force Discord thoughts to be in a quote box
  $limit = ($limit > 5) ? 5 : $limit; // Discord limit can't go past 5 for now. until there's a word count
}

// Create Thought (then kill script)
if (isset($_GET['create'])) {

  // Check if thoughts.json is empty
  if ($total > 0) {
    $lastID = key(array_slice($data, -1, 1, true)); // Get the last key's ID
    $nextID = intval($lastID) + 1; // increase it by 1
  } else {
    $total = 0;
    $nextID = "1";
  }

  $cAuthor = $_GET['author'] ?? null;
  $cAuthorID = $_GET['authorID'] ?? null;
  $cMsg = $_GET['msg'] ?? null;
  $cTag = strtolower($_GET['tag']) ?? null;
  $cBase = $_GET['base64'] ?? null; // created msg and author may be in base64

  if ($cAuthor == null || $cAuthorID == null || $cMsg == null || $cTag == null) {
    echo ("SOMETHING IS MISSING | Author: ".$cAuthor." | ID: ".$cAuthorID." | Tag: ".$cTag." | Msg: ".$cMsg);
    die();
  }

  // Only certain tags are allowed
  $tagsAllowed = array('thought', 'music', 'spam');
  if (!in_array($cTag, $tagsAllowed)) {
    echo "WRONG TAG";
    die();
  }

  // Decode Base64 if requested
  if ($cBase == 1) {
    $cMsg = base64_decode($cMsg);
    $cAuthor = base64_decode($cAuthor);
  }

  // Make sure the author isn't flooding
  // Loop through the thoughts and find the author's latest post
  $lastPost = 0;
  foreach ($data as $id => $val) {
    if ($val['author'] == $cAuthor) {
      $lastPost = $val['timestamp'];
    }
  }

  if ($lastPost != 0) {
    $floodFinal = $lastPost - (time() - $config->floodTime); // Subtract last post time from flood check to see seconds left
    if ($floodFinal > 0) {
      echo "`Slow down!` You can post again in ".$config->floodTime($floodFinal, 1)."."; // stop if flood triggered
      die();
    }
  }

  // All is well, post results
  echo "`".ucfirst($cTag)." posted!` {$config->url}?q={$nextID}";

  // Add this to the thoughts.json
  $data[$nextID] = array("msg" => $cMsg, "tag" => $cTag, "author" => $cAuthor, "authorID" => $cAuthorID, "timestamp" => time(), "source" => $platform);
  Files::write("app/thoughts.json", json_encode($data, JSON_PRETTY_PRINT));
  die();
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
      echo $thisID."{$quotes}".$data[$rand]['msg']."{$quotes}";
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