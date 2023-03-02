<?php
// These are just here for development purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("memory_limit", 512 * 1024 * 1024); // Set memory limit to 512mb so PHP cURL files successfully download
error_reporting(E_ALL);

$web = true; // Let API know this is the website
require_once("api.php");

// Get Settings and Thoughts
$data = Files::read("app/thoughts.json");
if (!is_array($data)) $data = []; // Create data array if there are no msgs

// Get possible queries
$q = $_REQUEST['q'] ?? 'search'; // Query
$s = $_REQUEST['s'] ?? null; // allow a specific ID to be fetched
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


echo "<head>
<title>Thoughts</title>

<style>:root { --bg-color: {$config->web['backgroundColor']}; --accent-color: {$config->web['accentColor']}; --font-color: {$config->web['fontColor']}; --accent-radius: {$config->web['accentRadius']}; }</style>

<link rel='stylesheet' href='app/thoughts.css'>
</head>
<body>

<div id='content'>"; // create the content div for web for javascript search

$api->process('web'); // fetch thought from whatever user has requested

$view = new view();

echo "</div>"; // end #content div for web

echo $view->create(); // Create Box

echo $view->infoBox(); // API Info Box

// Search Bar for Web  
echo $view->search(array('s'=>$s, 'limit'=>$limit, 'quotes'=>$quotes, 'shuffle'=>$shuffle, 'showID'=>$showID));

echo $view->footer(); // Footer  

echo $view->js(); // Javascript, if enabled
