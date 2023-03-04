<?php
$webVersion = '1.0'; // Website version. This also lets API know you're using the web platform
$webAPI = '1.0'; // latest version of API this web frontend uses
$apiFolder = 'api'; // Only change this if you've moved the API folder

require_once("$apiFolder/index.php");

// Get possible queries
$q = $_REQUEST['q'] ?? 'search'; // Query
$s = $_REQUEST['s'] ?? null; // allow a specific ID to be fetched
$limit = $_REQUEST['limit'] ?? $config->web['searchLimit']; // amount of search results to return
$shuffle = $_REQUEST['shuffle'] ?? $config->web['shuffle']; // shuffle search results
$showAuthor = $_REQUEST['showAuthor'] ?? $config->web['showAuthor']; // show author after each post
$showID = $_REQUEST['showID'] ?? $config->web['showID']; // show unique ID before each post
$platform = $_REQUEST['platform'] ?? 'web'; // anything besides "web" will be plain text mode
$wrap = $_REQUEST['wrap'] ?? tools::wrap($config->web['wrap']); // no wrap by default
$breaks = $_REQUEST['breaks'] ?? $config->api['breaks']; // prefer <br /> over /n/r (web will overwrite this)
$js = $_REQUEST['js'] ?? $config->web['js']; // web javascript features enabled by default
$apiRequest = $_REQUEST['api'] ?? null; // API version from requester

if ($platform == "discord") {
  $wrap = "`"; // force Discord thoughts to be in a quote box
  $limit = ($limit > 5) ? 5 : $limit; // Discord limit can't go past 5 for now. until there's a word count
}

echo "<!DOCTYPE html>
<head>
<title>Thoughts</title>

<style>:root { --bg-color: {$config->theme['backgroundColor']}; --accent-color: {$config->theme['accentColor']}; --font-color: {$config->theme['fontColor']}; --accent-radius: {$config->theme['accentRadius']}; --font-size: {$config->theme['fontSize']}; --url-color: {$config->theme['urlColor']}; }</style>

<link rel='stylesheet' href='assets/thoughts.css'>
</head>
<body>

<div id='content'>"; // create the content div for web for javascript search

$api->process('web', $webAPI); // fetch thought from requests. provide API version web uses

$view = new view($apiFolder, $webAPI, $webVersion);

echo "</div>"; // end #content div for web

echo $view->create(); // Create Box

echo $view->infoBox(); // API Info Box

// Search Bar for Web  
echo $view->search(array('s'=>$s, 'limit'=>$limit, 'wrap'=>$wrap, 'shuffle'=>$shuffle, 'showID'=>$showID));

echo $view->footer(); // Footer  

echo $view->js(); // Javascript, if enabled


// View class to get HTML for search, api box, create box, etc
class view {

  public $apiFolder;
  public $apiVersion;
  public $webVersion;

  function __construct($apiFolder, $apiVersion, $webVersion) {
    $this->apiFolder = $apiFolder;
    $this->apiVersion = $apiVersion;
    $this->webVersion = $webVersion;
  }

  public function search($args=[]) {

      if ($_SESSION['web']['search'] != 1) return false;

      $s = $args['s'] ?? ''; // Search query
      $limit = $args['limit'] ?? '';
      $wrap = $args['wrap'] ?? '';
      $showAuthor = $args['showAuthor'] ?? '';
      $showID = $args['showID'] ?? '';
      $shuffle = $args['shuffle'] ?? '';
      $shuffleChecked = ($shuffle == 1) ? "checked" : null;
      $showAuthorChecked = ($showAuthor == 1) ? "check" : null;
      $showIDChecked = ($showID == 1) ? "check" : null;
      $submitVisible = ($_SESSION['web']['js'] != 1) ? "<input id='searchSubmit' type='submit' value='Search' />":null; // Submit button only needs to be shown if javascript is disabled
      $searchVisible = ($_SESSION['web']['searchVisible'] == 1) ? null:"fade";
      return "
      <div id='search' class='fadeBox {$searchVisible}'>
      <form id='searchForm' method='get' action='index.php'>
        <input type='hidden' name='q' value='search'>
          <p><input type='text' id='searchbox' name='s' placeholder='Search...' value='{$s}' /><p>
          <p><label>Limit: <input type='number' name='limit' value='{$limit}' size='4' /></label> <label>wrap: <input type='text' name='wrap' value='{$wrap}' size='3'></label></p>
          <p><label><input type='checkbox' name='shuffle' value='1' {$shuffleChecked} /> Shuffle</label> <label><input type='checkbox' name='showAuthor' {$showAuthorChecked} /> Author</label> <label><input type='checkbox' name='showID' {$showIDChecked} /> ID</label></p> {$submitVisible}
          <input type='hidden' name='breaks' value='1' />
          <input type='hidden' id='searchJS' name='js' value='1' />
          <input type='hidden' id='showAPI' name='showAPI' value='1' />
          <input type='hidden' id='showSearch' name='showSearch' value='1' />
          <input type='hidden' name='version' value='{$this->apiVersion}' />
      </form>
      </div>";
  }
  
  // Create Box
  public function create() {
      
      if ($_SESSION['web']['create'] != 1 || $_SESSION['api']['create'] != 1) return false;

      $createVisible = ($_SESSION['web']['createVisible'] == 1) ? null:"fade";
      $createForm = "
      <div id='create' class='fadeBox {$createVisible}'>
      <form id='createForm' method='get' action='index.php'>
          <input type='hidden' name='q' value='create' />
          <h1>Create Thought</h1>
          <p><input type='text' id='createUser' name='author' placeholder='Username' value='' /><p>
          <p><input type='text' id='createUserID' name='authorID' placeholder='User ID' value='' /><p>
          <p><select id='createTag' name='tag'>";
          $tags = $_SESSION['api']['tags'];
          $createForm .= "<option value='".strtolower($_SESSION['api']['tagDefault'])."'>".ucfirst($_SESSION['api']['tagDefault'])."</option>"; // immediately show the default tag
          sort($tags); // sort tags alphabetically
          foreach ($tags as $name) {
              if ($name == $_SESSION['api']['tagDefault']) continue; // don't list the default tag
              $createForm .= "<option value='{$name}'>".ucfirst($name)."</option>";
          }
          $createForm .= "</select></p>
          <p><input type='text' id='createThought' name='msg' placeholder='Message' value='' /><p>
          <p><input type='submit' id='createSubmit' name='createSubmit' value='Submit Thought' /></p>
          <input type='hidden' id='createTrigger' name='create' value='1' />
      </form>
      </div>";
      echo $createForm;

  }

  // API Info Box
  public function infoBox() {
      if ($_SESSION['web']['info'] != 1) return false;
      $visible = ($_SESSION['web']['infoVisible'] == 1) ? null:"fade"; // Default visibility
      $d = $_SESSION['api']['url']."/".$this->apiFolder."/".$this->apiVersion."/";
      echo "
      <div id='info' class='fadeBox {$visible}'>
          <h1>API</h1>
          <p>Random Thought<br />
          <a href='{$d}'>{$d}</a></p>
          <p>Thought List<br />
          <a href='{$d}?s=list'>{$d}?s=list</a></p>
          <p>Link to Thought List<br />
          <a href='{$d}?s=list&platform=discord'>{$d}?s=list&platform=discord</a></p>
          <p>Thought by ID #<br />
          <a href='{$d}?s=25'>{$d}?s=25</a> (ID #25)</p>
          <p>Thought by word search<br />
          <a href='{$d}?s=multiple word search&limit=3'>{$d}?s=multiple words&limit=3&shuffle=1</a></p>
          <p>Other flags<br />
          &js=1&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Javascript on web page<br />
          &showID=0&nbsp;&nbsp;&nbsp;&nbsp;Show ID of thought<br />
          &amp;wrap=&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Mark to put around each thought (single, double, none)<br />
          &breaks=0&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Return &lt;br /&gt; instead of \\n\\r</p>
      </div>";
  }
  
  function footer() {
      $ret = array(null,null,null,null); // Return variables

      if ($_SESSION['web']['create'] == 1)
          $ret[0] = "<a href='#' data-toggle='create' class='divToggle'>Create</a>&nbsp;&nbsp;&nbsp;";

      if ($_SESSION['web']['search'] == 1)
          $ret[1] = "<a href='#' data-toggle='search' class='divToggle'>Search</a>&nbsp;&nbsp;&nbsp;";

      if ($_SESSION['web']['info'] == 1)
          $ret[2] = "<a href='#' data-toggle='info' class='divToggle'>API</a>&nbsp;&nbsp;&nbsp;";

      if ($_SESSION['web']['github'] == 1)
          $ret[3] = "<a href='https://github.com/cata-lystic/redbot-cogs/tree/main/thoughts' target='_blank'>GitHub</a>&nbsp;&nbsp;&nbsp;";

      if ($_SESSION['web']['versionVisible'] == 1)
          $ret[4] = "<span title='API Version: ".$this->apiVersion."'>Thoughts ".$this->webVersion."</span>";

      return "
      <div id='footer'>
          <div id='footerContent'>
              {$ret[0]}{$ret[1]}{$ret[2]}{$ret[3]}{$ret[4]}
          </div>
      </div>";
  }

  // Detect which Javascript and jQuery file to use (or not use)
  // (jQuery is not currently in use and may be removed soon)
  public function js() {

      if ($_SESSION['web']['js'] != 1 || isset($_REQUEST['js']) && $_REQUEST['js'] == 0) return false;
      $jq = strtolower($_SESSION['web']['jquery']);
      $jqVer = "3.6.3";

      // Supported CDNs
      $cdns = array(
      "local" => "assets/jquery-{$jqVer}.min.js",
      "google" => "https://ajax.googleapis.com/ajax/libs/jquery/{$jqVer}/jquery.min.js",
      "jquery" => "https://code.jquery.com/jquery-{$jqVer}.min.js",
      "microsoft" => "https://ajax.aspnetcdn.com/ajax/jQuery/jquery-{$jqVer}.min.js",
      "cdnjs" => "https://cdnjs.cloudflare.com/ajax/libs/jquery/{$jqVer}/jquery.min.js",
      "jsdelivr" => "https://cdn.jsdelivr.net/npm/jquery@{$jqVer}/dist/jquery.min.js");

      // If $jq is in the CDNs array, use it. If not, use what URL the user hopeufully supplied
      $jquery = (isset($cdns[$jq])) ? $cdns[$jq] : $jq;

      return "<script src='assets/thoughts.js'></script>";
  }

}
?>