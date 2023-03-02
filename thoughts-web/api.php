<?php
// Clear the current session config in case something has changed
if (isset($_SESSION['api']['url'])) {
    $_SESSION['api'] = array();
    $_SESSION['web'] = array();
}
session_start();

// These are just here for development purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load api and config classes
$config = new Config();
$api = new api();

// Process the request immediately if just the api.php is being loaded
if (!isset($web)) $api->process();


class Config {

    public $defaults;
    public $versions; // Web, API, and Bot versions to prevent incompatibility
    public $api;
    public $web;

    function __construct() {

        $this->versions = array(
            'web' => 1.0, // Website version
            'bot' => 1.0, // Bot version last time website was updated
            'api' => 1.0 // API version
        );

        // List of config settings. Array includes the follwoing:
        // [0] Default value
        // [1] Requirements
        // [2] Description
        // [3] Comment to place before variable in config file (put ## before it to add an extra blank line)
        
        $this->defaults = array(
            'api' => array(
                'url' => array('auto', [], 'Change if auto detection fails','#API Settings'),
                'tags' => array(array('thought', 'music', 'spam'), [], 'Tags that thoughts can be categorized into'),
                'tagDefault' => array('thought', ['alphanum'], 'Default tag for new posts. Must be an existing tag'),
                'searchLimit' => array(500, ['number'], 'Search results max limit. Cannot be changed by API request'),
                'searchResults' => array(3, ['number'], 'Default amount of results per search'),
                'create' => array(1, ['binary'], 'Allow new post creation to non-mods via API'),
                'createFlood' => array('10s', ['alphanum'], 'Time between when a user can post again (format: 5s, 3m, 5d, 7w, etc)'),
                'shuffle' => array(1, ['binary'], 'Shuffle search results'),
                'showID' => array(0, ['binary'], 'Show thought ID before each result'),
                'quotes' => array('none', [], 'Quotes around each thought. options: \'none\', \'single\', \'double\', or custom'),
                'breaks' => array(0, ['binary'], 'Use <br /> instead of \n\r in API calls'),
                'platform' => array('api', ['alpha'], 'Can be set to anything where the request came from (example: discord)')),
            "web" => array(
                'shuffle' => array(1, ['binary'], 'Shuffle search results', '#Website Settings'),
                'showID' => array(0, ['binary'], 'Show thought ID before each result'),
                'quotes' => array('none', [], 'Quotes around each thought. options: \'none\', \'single\', \'double\', or custom'),
                'backgroundColor' => array('#212121', [], 'Background color'),
                'fontColor' => array('#e9e5e5', [], 'Font color'),
                'accentColor' => array('#393939', [], 'Box accent color'),
                'accentRadius' => array("10px", [], 'Border radius of boxes'),
                'info' => array(1, ['binary'], 'Enable API info box'),
                'infoVisible' => array(1, ['binary'], 'Show API info box by default'),
                'create' => array(1, ['binary'], 'Enable thought creation box'),
                'createVisible' => array(1, ['binary'], 'Show create box by default'),
                'createFlood' => array('10s', ['alphanum'], 'Time between when a user can post again (format: 5s, 3m, 5d, 7w, etc)'),
                'search' => array(1, ['binary'], 'Enable search'),
                'searchLimit' => array(500, ['number'], 'Search results max limit'),
                'searchResults' => array(50, ['number'], 'Default amount of results per search'),
                'searchVisible' => array(1, ['binary'], 'Show search box by default'),
                'github' => array(1, ['binary'], 'Show GitHub link in footer'),
                'js' => array(1, ['binary'], 'Use JavaScript for search and other features'),
                'jquery' => array('local', [], 'options: local, google, jquery, microsoft, cdnjs, jsdelivr, Custom URL', 'jQuery.js location. Change from "local" to direct URL or built in CDN options'))
            );
        

            $this->load(); // Load the config file and variables
    }

    public function load() {

        // Check if there is already session config data
        if (isset($_SESSION['api']['url'])) {
            // Loop through each session config var for the object vars
            foreach($_SESSION as $key => $val) {
                $this->$key = $val;
            }
    
        } else {
            require(__DIR__."/app/config.php");

            // Make sure owner has set valid tokens in config.php )
            $checkToken = $this->token($set['token'], 'valid');
            
            if ($checkToken !== true) {
                if ($checkToken == 'Token blank') // Extend the token blank message
                    $checkToken = 'Token blank. Update $set[\'api\'][\'public_tokens\'] in config.php';
                die($checkToken);
            }

            // Throw each var into a session
            // Note: is it necessary to always load the discord-web api key or only when needed? maybe only when logged in too.
            foreach($set as $key => $var) {
                $_SESSION[$key] = $var;
                $this->$key = $var;
            }
            //unset($_SESSION['api']['public_tokens'], $_SESSION['api']['private_tokens']); // Don't include tokens in session

            // Detect URL if auto enabled
            if ($this->api['url'] == 'auto') {
                $this->api['url'] = tools::detectURL($this->api['url']);
                $_SESSION['api']['url'] = $this->api['url'];
            }

            // Convert floodTime to seconds
            $this->api['createFlood'] = tools::floodTime();
            $_SESSION['api']['floodTime'] = $this->api['createFlood'];
        }
        
    }

    // Check to make sure setting matches required criteria
    function check($key1, $key2, $val) {

        // Get the requirements of key
        $req = $this->defaults[$key1][$key2][1];

        // Automatically pass if there are no requirements
        if (count($req) == 0) return true;

        foreach ($req as $r) {

            // Whole number requirement
            if ($r == "number" && !ctype_digit($val)) return "Value must be a whole number";

            // Binary requirement
            if ($r == "binary" && $val != 1 && $val != 0) return "Value must be a 1 or 0";

            // Alphabet only (no numbers)
            if ($r == "alpha" && !ctype_alpha($val)) return "Value must only contain letters of the alphabet";

            // Character limit (ex: char>5, char<=5, char=5, char!=5)
            // Integer limit (ex: int>5, int<=5, int=5, int!=5)
            if (substr($r, 0, 4) == "char" || substr($r, 0, 3) == "int") {
                
                if (substr($r, 0, 4) == "char") {
                    $x = [substr($r, 4, 1), substr($r, 5, 1)]; // Get the 5th and 6th characters
                    $restAmt = 5;
                    $checkVal = strlen($val);
                    $ending = "characters";
                } else if (substr($r, 0, 3) == "int") {
                    $x = [substr($r, 3, 1), substr($r, 4, 1)]; // Get the 4th and 5th characters
                    $restAmt = 4;
                    $checkVal = $val;
                    $ending = null;
                }
                
                // If the last extracted character isn't an = sign, then it's either >, <, or =, otherwise it's <=, >=, or !=
                if ($x[1] != "=") {
                    $oper = $x[0];
                    $rest = substr($r, $restAmt); // Number requested
                } else {
                    $oper = $x[0].$x[1];
                    $rest = substr($r, $restAmt+1); 
                }
     
                // Make the comparison
                if ($oper == "<") {
                    if ($checkVal >= $rest) return "Value must be less than $rest $ending";
                } else if ($oper == ">") {
                    if ($checkVal <= $rest) return "Value must be greater than $rest $ending";
                } else if ($oper == "<=") {
                    if ($checkVal > $rest) return "Value must be less than or equal to $rest $ending";
                } else if ($oper == ">=") {
                    if ($checkVal < $rest) return "Value must be greater than or equal to $rest $ending";
                } else if ($oper == "=") {
                    if ($checkVal != $rest) return "Value must be equal to $rest $ending";
                } else if ($oper == "!=") {
                    if ($checkVal == $rest) return "Value must be not be equal to $rest $ending";
                } else {
                    return "Invalid operator";
                }

            }

        }

        return true;
    }

    // Convert array to a string such as ['one', 'two']
    function arrayToString($arr) {

        if (!is_array($arr)) {
            return $arr;
        } else {
            $result = "[";
            foreach($arr as $str) {
                $result .= "'{$str}', ";
            }
            $result = substr($result, 0, -2); // remove comma from end
            $result .= "]";
        }
        return $result;

    }

    // Fetch or change a setting
    function set($key1, $key2, $newVal=null) {

        //if ($key2 == "public_tokens" || $key2 == "private_tokens") echo "You can't change tokens via the API";

        // Only show a config variable, don't overwrite
        if ($newVal == null) {
            // If var still doesn't exist kill the script
            return $_SESSION[$key1][$key2] ?? null;
        } else {

            // Make sure tagDefault is an existing tag
            if ($key2 == 'tagDefault' && $this->isTag($newVal) == false) die("Tag doesn't exist");

            // Make sure createFlood has at least a 's' at the end (for seconds)
            if ($key2 == 'createFlood' && ctype_digit($newVal)) $newVal .= "s";
            
            // As far as I know, to do this we have to rewrite the config.php file each time.
            // This will loop through the defaults to get the keys and descriptions to remake the file
            // The user's current config settings will be saved but the requested key will be overwritten
            require("app/config.php"); // old config settings will be in $set

            // Make sure this var is an actual setting
            if (!isset($set[$key1][$key2]) || !isset($_SESSION[$key1][$key2])) return "ERROR: `{$key1} {$key2}` is not a valid setting";
            
            // Check to make sure new value meets requirements
            $checkReq = $this->check($key1, $key2, $newVal);
            if ($checkReq !== true) die($checkReq); // Kill script and show error if fails
            
            $result = "<?php\n#  API Setup\n# Make unique and secure tokens. Must be at least 8 characters in length with no spaces.\n# You may create multiple tokens with different permissions\n# Permissions: admin, config, create, delete, list, read, search, tags\n# 'admin' permission has full access to all commands. Only give these tokens to people you trust that will help you manage the API and website\n# Do not delete the 'default' token. This is used for when your API is accessed with no other request.\n# All other tokens will inherit 'default' token's permissions\n# The default token permissions should generally not be changed unless you want to prevent public post creation.\n# When config is complete, give an admin token to your Discord Bot with: [p]thoughtset setup api yourAdminToken\n\n# Tokens\n";

            // Loop through current $set['token']s and reprint them all out with their permissions
            foreach($set['token'] as $tVal => $tPerms) {
                $result .= "\$set['token']['{$tVal}'] = ".$this->arrayToString($set['token'][$tVal]).";\n";
            }

            // Print out the admins and mods
            $result .= "\n# Admins and Mods\n";
            $result .= "\$set['admin'] = ".$this->arrayToString($set['admin'])."; # Discord IDs of those that can do anything\n";
            $result .= "\$set['mod'] = ".$this->arrayToString($set['mod'])."; # Discord IDs of those that can mod posts\n";

            foreach ($this->defaults as $oldCategory => $oldCatVal) {

                foreach($this->defaults[$oldCategory] as $oldKey => $oldVal) {
                
                    // Show the prefix comment if there is one
                    if (isset($this->defaults[$oldCategory][$oldKey][3])) {
                        $comment = $this->defaults[$oldCategory][$oldKey][3];
                        if (substr($comment, 0, 1) == "#") {
                            $result .= "\n# ".substr($comment, 1)."\n";
                        } else if ($comment == '') {
                            $result .= "\n";
                        } else {
                            $result .= "# {$comment}\n";
                        }
                    }
                    // $finalVal is the $newVal if this is the changed setting
                    $finalVal = ($oldCategory == $key1 && $oldKey == $key2) ? str_replace("HASHTAG", "#", $this->arrayToString($newVal)) : $this->arrayToString($set[$oldCategory][$oldKey]); 
                        
                    // Don't includes quotes around the val if it's meant to be a number
                    $reqs = $this->defaults[$oldCategory][$oldKey][1];
                    $quote = (substr($finalVal, 0, 1) == '[' || in_array("number", $reqs) || in_array("binary", $reqs)) ? null : "'";
                    
                    $result .= "\$set['$oldCategory']['$oldKey'] = {$quote}{$finalVal}{$quote}; # {$this->defaults[$oldCategory][$oldKey][2]}\n";

                }
            }
            
            Files::write("app/config.php", $result);
            return "OK";
        }

    }

    // Check if a token is valid or make sure API request token is valid and has proper permissions
    function token($token, $permission='search') { // $keyType can be all, valid, public, or private
        
        // If token isn't supplied as an array, put it in one
        if (!is_array($token)) {
            $tokens[$token] = array($permission);
        } else {
            $tokens = $token;
        }

        // Just make sure the tokens the admin set are valid
        if ($permission == "valid") {

            // Loop through the array and check if each token is valid. Make sure there's a default token
            $hasDefault = false;
            foreach($tokens as $key => $val) {
                if ($key == 'default') $hasDefault = true;
                if ($key == '') return 'Token blank';
                if (strlen($key) < 8 && $key != 'default') return "Token `{$key}` too short";
                if (strpos($key, ' ') !== false) return "Token `$key` cannot have spaces";
                if (count($val) == 0) return "Token `$key` doesn't have any permissions";
                if ($key == "changeThisAdminToken" || $key == "changeThisCustomToken") return "Set tokens in config.php";
            }
            if ($hasDefault === false) return 'No default token set';

        } else {
            
            // Loop through array and check if each token has proper requested permissions
            foreach($tokens as $key => $permissions) {
                if (!isset($_SESSION['token'][$key])) return "Invalid token";
                $perms = $_SESSION['token'][$key];
                // Check if this token has 'admin' or requested permission (or is in default permissions)
                if (!in_array($permission, $perms) && !in_array('admin', $perms) && !in_array($permission, $_SESSION['token']['default'])) return "Token doesn't have `{$permission}` permissions";
            }
        }

        return true;
    }

    // Check if userID is a Mod (use $adminOnly==1 to only check if user is admin.)
    function isMod($id, $adminOnly=null) {
        $admins = $_SESSION['admin'];
        if ($adminOnly == 1) {
            if (!in_array($id, $admins)) return false;
        } else {
            $mods = $_SESSION['mod'];
            if (!in_array($id, $admins) && !in_array($id, $mods)) return false;
        }
     
        return true; // if no errors
    }

    // Shortcut for isMod($id, 1)
    function isAdmin($id) {
        return $this->isMod($id, 1);
    }

    // Check if a tag is valid
    function isTag($tag) {
        $tags = $_SESSION['api']['tags'];
        return (in_array($tag, $tags)) ? true:false;
    }

}


class api extends config {

    public $req = []; // all $_REQUESTs will be stored
    public $allowedFunctions; // All allowed functions by the API

    function __construct() {
        
        Config::__construct();

        $this->allowedFunctions = ['config', 'create', 'delete', 'list', 'search', 'tags', 'info', 'dev'];

        // Get all request variables and put them in an array
        foreach($_REQUEST as $key => $val) {
            $this->req[$key] = $val;
        }

        if (!isset($this->req['token']) || empty($this->req['token'])) $this->req['token'] = 'default'; // default token if none set

    }

    // Process what has been requested and send to proper function
    function process($source='') {

        // Require API version (?version=) if source isn't web browser
        if ($source != 'web') {
            if (!isset($this->req['version']) || $this->req['version'] == '') {
                die("Please supply your API version in request");
            } else {
                if ($this->req['version'] < $this->versions['api']) {
                    die("API Version {$this->req['version']} is not supported. Latest: {$this->versions['api']}");
                }
            }
        }

        // Check if there was a 'q' (query) request
        $func = $this->req['q'] ?? null; // no query = search for random thought
        if (empty($func)) $func = 'search';

        // Make sure this is a valid function
        if (!in_array($func, $this->allowedFunctions)) die("Invalid function");

        // Run requested function
        $this->$func();

    }

    // Change a setting in config.php
    function config() {

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'config');
        if ($checkToken !== true) die($checkToken);

        $key1 = $this->req['key1'] ?? null;
        $key2 = $this->req['key2'] ?? null;
        $val = $this->req['val'] ?? null;

        // All fields required
        if (empty($key1) || empty($key2) || $val == null || empty($this->req['token']))
            die("Missing key1, key2, val, or token");
    
        // Can't change token from API
        if ($key1 == "token")
            die("You can't change the web tokens from the API");

        $val = str_replace("HASHTAG", "#", $val); // convert HASHTAG to #
        
        $changeSetting = $this->set($key1, $key2, $val);
        
        if ($changeSetting == "OK") {
            echo "Changed `{$key1} {$key2}` to `{$val}`";
        } else {
            echo $changeSetting;
        }
        die();
    }

    function create() {

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'create');
        if ($checkToken !== true) die($checkToken);

        $author = $this->req['author'] ?? null;
        $authorID = $this->req['authorID'] ?? null;

        if ($this->api['create'] == 0 && $this->isMod($authorID) == false) die("Post creation is currently disabled");

        $data = Files::read("app/thoughts.json");
        if (!is_array($data)) $data = []; // Create data array if there are no msgs
        $total = count($data); // total thoughts

        // Check if thoughts.json is empty
        if ($total > 0) {
            $lastID = key(array_slice($data, -1, 1, true)); // Get the last key's ID
            $nextID = intval($lastID) + 1; // increase it by 1
        } else {
            $total = 0;
            $nextID = "1";
        }
        $cMsg = $this->req['msg'] ?? null;
        $cTag = $this->req['tag'] ?? null;
        $cBase = $this->req['base64'] ?? null; // created msg and author may be in base64
        $platform = $this->req['platform'] ?? "api";

        if ($author == null || $authorID == null || $cMsg == null || $cTag == null) {
            echo ("SOMETHING IS MISSING | Author: ".$author." | ID: ".$authorID." | Tag: ".$cTag." | Msg: ".$cMsg);
            die();
        }

        // Only certain tags are allowed
        if ($this->isTag(strtolower($cTag)) == false) {
            echo "Invalid tag";
            die();
        }

        // Decode Base64 if requested
        if ($cBase == 1) {
            $cMsg = base64_decode($cMsg);
            $author = base64_decode($author);
        }

        // Make sure the author isn't flooding (if they're not a mod)
        $lastPost = 0;
        if ($this->isMod($authorID) == false) {
            // Loop through the thoughts and find the author's latest post
            foreach ($data as $id => $val) {
                if ($val['authorID'] == $authorID) {
                    $lastPost = $val['timestamp'];
                }
            }
        }

        if ($lastPost != 0) {
            $floodFinal = $lastPost - (time() - intval($_SESSION['api']['createFlood'])); // Subtract last post time from flood check to see seconds left
            if ($floodFinal > 0) {
            echo "`Slow down!` You can post again in ".tools::floodTime($floodFinal, 1)."."; // stop if flood triggered
            die();
            }
        }

        // All is well, post results
        echo "`".ucfirst($cTag)." posted!` {$_SESSION['api']['url']}?s={$nextID}";

        // Add this to the thoughts.json
        $data[$nextID] = array("msg" => $cMsg, "tag" => $cTag, "author" => str_replace("HASHTAG", "#", $author), "authorID" => $authorID, "timestamp" => time(), "source" => $platform);
        Files::write("app/thoughts.json", json_encode($data, JSON_PRETTY_PRINT));
        die();

    }

    // Delete thought
    function delete() {

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'delete');
        if ($checkToken !== true) die($checkToken);
     
        $id = $this->req['id'] ?? null; // ID of post to be deleted
        $deleter = $this->req['deleter'] ?? null; // Name of who is requesting delete
        $deleterID = $this->req['deleterID'] ?? null; // ID of who is requesting delete
        $reason = $this->req['reason'] ?? null; // Reason this post was deleted
        $wipe = $this->req['wipe'] ?? 0; // Wipe=1 will remove the original text from the .json file instead of just marking it as deleted
        
        if ($id == null || $deleter == null || $deleterID == null || $reason == null)
            die("Missing id, deleter, deleterID, or reason");

        // Load thoughts data
        $data = Files::read("app/thoughts.json");
        if (!is_array($data)) $data = []; // Create data array if there are no msgs
        $total = count($data); // total thoughts
        if ($total == 0) return "There are no thoughts to delete";

        // Get data about requested ID
        if (isset($data[$id])) {

            $alreadyDeleted = $data[$id]['deleted'] ?? 0;
            if ($alreadyDeleted == 1) die("#{$id} already deleted");

            // Make sure deleter owns the post
            $posterID = $data[$id]['authorID'];

            if ($deleterID != $posterID && $this->isMod($deleterID) != true) die("You are not the author of this post");
            
            $data[$id]['deleted'] = 1;
            $data[$id]['deleter'] = str_replace("HASHTAG", "#", $deleter);
            $data[$id]['deleterID'] = $deleterID;

            // Make sure deleteReason doesn't start with 'wipe'
            if (substr($reason, 0, 4) == 'wipe') {
                $wipe = 1;
                $reason = trim(substr($reason, 4)); // remove 'wipe' from front of reason
            }

            $data[$id]['deleteReason'] = $reason;
            if ($wipe == 1) $data[$id]['msg'] = "[WIPED]";
            Files::write("app/thoughts.json", json_encode($data, JSON_PRETTY_PRINT));
            $delMethod = ($wipe != 1) ? "deleted" : "wiped";
            echo "Post #{$id} {$delMethod}";

        } else {
            echo "ID DOESNT EXIST";
        }

    }
    
    // List all posts
    function list() {
        $this->req['s'] = 'list'; // change search result to 'list' (this needs to be fixed)
        $this->search();
    }

    function search() {

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'read');
        if ($checkToken !== true) die($checkToken);

        // Get Settings and Thoughts
        $data = Files::read("app/thoughts.json");
        if (!is_array($data)) $data = []; // Create data array if there are no msgs

        // Get possible queries
        $s = $this->req['s'] ?? null; // specific ID or query to be searched
        $limit = $this->req['limit'] ?? $_SESSION['api']['searchLimit']; // amount of search results to return
        $shuffle = $this->req['shuffle'] ?? $_SESSION['api']['shuffle']; // shuffle search results
        $showID = $this->req['showID'] ?? $_SESSION['api']['showID']; // show unique ID before each thought
        $platform = $this->req['platform'] ?? 'web'; // anything besides "web" will be plain text mode
        $quotes = $this->req['quotes'] ?? tools::quotes($_SESSION['api']['quotes']); // no quotes by default
        $breaks = $this->req['breaks'] ?? $_SESSION['api']['breaks']; // prefer <br /> over /n/r (web will overwrite this)
        $apiRequest = $this->req['api'] ?? null; // API version from requester
        $reason = $this->req['reason'] ?? 1; // Show reason for post deletion
        $reasonby = $this->req['reasonby'] ?? 1; // Show who deleted post

        $total = count($data); // total thoughts
        if ($platform == "discord") {
            $quotes = "`"; // force Discord thoughts to be in a quote box
            $limit = ($limit > 5) ? 5 : $limit; // Discord limit can't go past 5 for now. until there's a word count
        }

        // ?q=list creates an entire list of thoughts and then quits
        if ($s == "list" && $platform != "discord") {
            foreach ($data as $id => $val) {
                if (isset($val['deleted'])) continue;
                $thisID = ($showID == 1) ? "#{$id}: " : null;
                echo "<p class='thought'>{$thisID}{$val['msg']}</p>";
            }
        
        // ?q=list for a non-web platform just shows a link to the list page
        } else if ($s == "list" && $platform == "discord") {
            echo "Full list of thoughts can be found at {$_SESSION['api']['url']}?q=list";

        // ?q=info to display Thoughts info and versions
        } else if ($s == "info") {
            $this->info();
        
        // If $s is numeric or empty, fetch a random or desired ID
        } else if ($s == null || is_numeric($s)) {
                
            if ($total == 0) die("There are no thoughts...");

            if ($s > $total) die("I need to think more to get to #".$s);

            // If user didn't submit a search query:
            // Generate a random number within the count of the data array
            // If that ID happens to be deleted, up $rand by 1 and keep trying the next post up until one isn't deleted
            // If the $rand gets higher than $total then output an error or maybe start back at 1?

            $rand = rand(1, $total);
            while ($s == null) {
                $isDeleted = isset($data[$rand]['deleted']) ?? 0;
                if ($isDeleted != 1){
                    $s = $rand;
                    break;
                } else {
                    $rand++;
                }
                if ($rand > $total) die("Something went wrong... Try again.");
            }

            // Check if the search is deleted. If it is, show that the post is deleted.
            $isDeleted = isset($data[$s]['deleted']) ?? 0;
            if ($isDeleted == 0) { // Check if post has been deleted (show that it has if the post was directly requested)
                $thisID = ($showID == 1) ? "#".$s.": " : null;
                echo $thisID."{$quotes}".$data[$s]['msg']."{$quotes} -".$data[$s]['author'];
            } else {
                echo "`Post deleted.`";
                if ($reasonby == 1) echo " Deleted by: ".str_replace("HASHTAG", "#", $data[$s]['deleter']).".";
                if ($reason == 1) echo " Reason: {$data[$s]['deleteReason']}.";
            }
        
        // If $s is a string, search each thought to see if that word is in it
        } else if ($s != null && !is_numeric($s)) {
        
            $matches = [];
        
            foreach ($data as $id => $val) {
                if (preg_match("/{$s}/i", $val['msg'])) {
                    if (isset($val['deleted'])) continue; // don't include if message is deleted
                    $matches[$id] = $val['msg'];
                }
            }
        
            if (count($matches) == 0) {
                echo "No thoughts found related to `$s`";
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
        

    }

    // List and manage Tags
    function tags() {

        $s = $this->req['s'] ?? 'list';
        $tag = $this->req['tag'] ?? null; // Tag user is requesting
        $tagRename = $this->req['rename'] ?? null; // User may be requesting to edit tag's name
        $tags = $this->api['tags'];

        // List all tags
        if ($s == 'list') {

            // This function only requires 'read' permissions
            $checkToken = $this->token($this->req['token'], 'read');
            if ($checkToken !== true) die($checkToken);

            $taglist = '';
            foreach ($tags as $tag) {
                $taglist .= $tag.", ";
            }
            die (substr($taglist, 0, -2));
        }

        // Anything past this point requires 'tags' permissions
        $checkToken = $this->token($this->req['token'], 'tags');
        if ($checkToken !== true) die($checkToken);

        // Add or remove tag
        if ($s == 'add' || $s == 'remove') {

            $authorID = $this->req['authorID'] ?? null;
            if ($authorID == null) die("Missing authorID");
            if ($this->isAdmin($authorID) == false) die("Only admin can edit tags");
            if ($tag == null) die("Missing tag"); // Tag required

            if ($s == 'add') {
                if (!in_array($tag, $this->api['tags'])) {
                    array_push($this->api['tags'], $tag);
                    $this->set('api', 'tags', $this->api['tags']);
                    echo "Tag `$tag` added";
                } else {
                    echo "Tag `$tag` already exists";
                }
            } else {
                //print_r($this->api['tags']);
                if (in_array($tag, $this->api['tags'])) {
                    $tagID = array_search($tag, $this->api['tags']);
                    unset($this->api['tags'][$tagID]);
                    $this->set('api', 'tags', $this->api['tags']);
                    echo "Tag `$tag` removed";
                } else {
                    echo "Tag `$tag` doesn't exist";
                }
            }   
        }

        // Edit tag
        if ($s == 'edit') {

            // Make sure tag exists
            if (!is_array($tags)) die("Tag doesn't exist");

            $data = Files::read("app/thoughts.json");
            if (!is_array($data)) $data = []; // Create data array if there are no posts

            // Rename tag
            if ($tagRename != null) {
                $tagID = array_search($tag, $this->api['tags']);
                unset($this->api['tags'][$tagID]);
                array_push($this->api['tags'], $tagRename);

                // Loop through current posts and change their current tag
                foreach($data as $key => $val) {
                    if ($val['tag'] == $tag) $data[$key]['tag'] = $tagRename;
                }
                $this->set('api', 'tags', $this->api['tags']);
                Files::write("app/thoughts.json", json_encode($data, JSON_PRETTY_PRINT));
                echo "Tag `$tag` renamed to `$tagRename`";
            }

        }

    }

    // Display info about Thoughts and each version
    function info() {

        // This function only requires 'read' permissions
        $checkToken = $this->token($this->req['token'], 'read');
        if ($checkToken !== true) die($checkToken);
        $botVersion = $this->req['botversion'] ?? null;
        echo "Thoughts by Catalyst\nAPI Version: {$this->versions['api']}\nWeb Version: {$this->versions['web']}";
        if ($botVersion != null)
            echo "\nBot Version: {$botVersion}";
        echo "\nSource: <https://github.com/cata-lystic/cata-cogs>";
     }

    // This function is only used for me to test out code.
    function dev() {
       echo 'Nothing in dev() right now...';
    }

}


// View class to get HTML for search, api box, create box, etc
class view {

    public function search($args=[]) {

        if ($_SESSION['web']['search'] != 1) return false;

        $s = $args['s'] ?? ''; // Search query
        $limit = $args['limit'] ?? '';
        $quotes = $args['quotes'] ?? '';
        $showID = $args['showID'] ?? '';
        $shuffle = $args['shuffle'] ?? '';
        $shuffleChecked = ($shuffle == 1) ? "checked" : null;
        $showIDChecked = ($showID == 1) ? "check" : null;
        $submitVisible = ($_SESSION['web']['js'] != 1) ? "<input id='searchSubmit' type='submit' value='Search' />":null; // Submit button only needs to be shown if javascript is disabled
        $searchVisible = ($_SESSION['web']['searchVisible'] == 1) ? "block":"none";
        return "
        <div id='search' style='display: {$searchVisible}'>
        <form id='searchForm' method='get' action='index.php'>
            <p><input type='text' id='searchbox' name='q' placeholder='Search...' value='{$s}' /><p>
            <p><label>Limit: <input type='number' id='searchLimit' name='limit' value='{$limit}' size='4' /></label> <label>Quotes: <input type='text' id='searchQuotes' name='quotes' value='{$quotes}' size='3'></label></p>
            <p><label><input type='checkbox' id='searchShuffle' name='shuffle' value='1' {$shuffleChecked} /> Shuffle</label> <label><input type='checkbox' id='searchShowID' name='showID' {$showIDChecked} /> Show ID</label></p> {$submitVisible}
            <input type='hidden' id='searchJS' name='js' value='0' />
            <input type='hidden' id='showAPI' name='showAPI' value='1' />
            <input type='hidden' id='showSearch' name='showSearch' value='1' />
        </form>
        </div>";
    }
    
    // Create Box
    public function create() {
        
        if ($_SESSION['web']['create'] != 1) return false;

        $createVisible = ($_SESSION['web']['createVisible'] == 1) ? "block":"none";
        $createForm = "
        <div id='create' style='display: {$createVisible}'>
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
        $visible = ($_SESSION['web']['infoVisible'] == 1) ? "block":"none"; // Default visibility
        $d = $_SESSION['api']['url'];
        echo "
        <div id='info' style='display: {$visible}'>
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
            &amp;quotes=&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Mark to put around each thought (single, double, none)<br />
            &breaks=0&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Return &lt;br /&gt; instead of \\n\\r</p>
        </div>";
    }
    
    public static function footer() {
        $ret = array(null,null,null,null); // Return variables

        if ($_SESSION['web']['create'] == 1)
            $ret[0] = "<a href='#' data-toggle='#create' class='divToggle'>Create</a>&nbsp;&nbsp;&nbsp;";

        if ($_SESSION['web']['search'] == 1)
            $ret[1] = "<a href='#' data-toggle='#search' class='divToggle'>Search</a>&nbsp;&nbsp;&nbsp;";

        if ($_SESSION['web']['info'] == 1)
            $ret[2] = "<a href='#' data-toggle='#info' class='divToggle'>API</a>&nbsp;&nbsp;&nbsp;";

        if ($_SESSION['web']['github'] == 1)
            $ret[3] = "<a href='https://github.com/cata-lystic/redbot-cogs/tree/main/thoughts' target='_blank'>GitHub</a>";

        return "
        <div id='footer'>
            <div id='footerContent'>
                {$ret[0]}{$ret[1]}{$ret[2]}{$ret[3]}
            </div>
        </div>";
    }

    // Detect which Javascript and jQuery file to use (or not use)
    public function js() {

        if ($_SESSION['web']['js'] != 1 || isset($_REQUEST['js']) && $_REQUEST['js'] == 0) return false;
        $jq = strtolower($_SESSION['web']['jquery']);
        $jqVer = "3.6.3";

        // Supported CDNs
        $cdns = array(
        "local" => "app/jquery-{$jqVer}.min.js",
        "google" => "https://ajax.googleapis.com/ajax/libs/jquery/{$jqVer}/jquery.min.js",
        "jquery" => "https://code.jquery.com/jquery-{$jqVer}.min.js",
        "microsoft" => "https://ajax.aspnetcdn.com/ajax/jQuery/jquery-{$jqVer}.min.js",
        "cdnjs" => "https://cdnjs.cloudflare.com/ajax/libs/jquery/{$jqVer}/jquery.min.js",
        "jsdelivr" => "https://cdn.jsdelivr.net/npm/jquery@{$jqVer}/dist/jquery.min.js");

        // If $jq is in the CDNs array, use it. If not, use what URL the user hopeufully supplied
        $jquery = (isset($cdns[$jq])) ? $cdns[$jq] : $jq;

        return "
        <script src='{$jquery}'></script>
        <script src='app/thoughts.js'></script>";
    }

  }

// Misc functions
class tools {

    // Detect URL or use custom
    public static function detectURL($url=null) {

        if ($url == null) $url = 'auto';

        // Try to autodetect the URL. It can overwritten it in settings.
        if ($url == '' || $url == 'auto') {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://'; 
            $url = $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];

            // Remove the main possible files that could be accessed. Remove them and you have the domain.
            if (substr($url, -8) == '/api.php') $url = substr($url, 0, -8);
            if (substr($url, -10) == '/index.php') $url = substr($url, 0, -10);
            $url = $protocol.$url;
        }
        return $url;
    }

    // Convert floodTime to seconds
    // $method = 0 will convert format like "2w" to 2 weeks in seconds
    // $method = 1 will 1 will convert 160 seconds into "2 min 40 sec" 
    public static function floodTime($floodTime=null, $method=0) {

        // Use config floodTime if none given
        $ft = ($floodTime == null) ? $_SESSION['api']['createFlood'] : $floodTime;

        if ($method == 0) {

            // get the last char
            $number = substr($ft, 0, -1);
            $letter = strtolower(substr($ft, -1));
            if ($letter == "s") {
                $time = $number;
            } else if ($letter == "m") { // Minutes
                $time = ($number * 60);
            } else if ($letter == "h") { // Hours
                $time = ($number * 60 * 60);
            } else if ($letter == "d") { // Days
                $time = ($number * 60 * 60 * 24);
            } else if ($letter == "w") { // Weeks
                $time = ($number * 60 * 60 * 24 * 7);
            } else {
                $time = null;
            }

        } else if ($method == 1) {
            $time = round($ft);
            $time = @sprintf('%02d:%02d:%02d:%02d:%02d', ($ft / 604800),($ft / 86400),($ft/ 3600 % 24),($ft/ 60 % 60), $ft% 60);
            $t = explode(":", $time); // Put times into an array
            $time = "";
            $time .= ($t[0] > 0) ? $t[0]." ".tools::plural($t[0], 'Weeks').", ":null;
            $time .= ($t[1] > 0) ? $t[1]." ".tools::plural($t[1], 'Days').", ":null;
            $time .= ($t[2] > 0) ? $t[2]." ".tools::plural($t[2], 'Hours').", ":null;
            $time .= ($t[3] > 0) ? $t[3]." ".tools::plural($t[3], 'Minutes').", ":null;
            $time .= ($t[4] > 0) ? $t[4]." ".tools::plural($t[4], 'Seconds').", ":null;
        }

        return $time;
    }

    // Process quotes (convert single or double to ' or "")
    public static function quotes($str) {
        if ($str == "none") {
            $quote = "";
        } else if ($str == "single") {
            $quote = "'";
        } else if ($str == "double") {
            $quote = '"';
        } else {
            $quote = $str;
        }
        return $quote;
    }

    // Plural. Check if word (like Days) needs an 's' at the end.
    // Will be updated for "y/ies" words if it comes to that
    public static function plural($amt, $word) {
        if ($amt == 1) {
            return substr($word, 0, -1);
        } else {
            return $word;
        }
    }

}


class Files {

    // Read a .txt file and return it as a JSON object
    public static function read($file, $plain=0) { // $plain=1 will return plain text instead of JSON

        // Open text file and return data about build
        $dir = __DIR__;
        $fh = fopen($dir."/".$file, 'r');
        $currentTxtContents = fread($fh, filesize($dir."/".$file));
        fclose($fh);
        return ($plain == 0) ? json_decode($currentTxtContents, true) : $currentTxtContents;

    }

    public static function write($fileName = null, $fileContents = null) {

        if ($fileName == null) {
        return false;
        }

        $fileName = __DIR__."/".$fileName;

        $myFileLink2 = fopen($fileName, 'w+') or die("Can't open file.");
        fwrite($myFileLink2, $fileContents);
        fclose($myFileLink2);
    }
}

// Shuffle associated array
function shuffle_assoc($arr) {
    $keys = array_keys($arr);

    shuffle($keys);

    foreach($keys as $key) {
        $new[$key] = $arr[$key];
    }

    $arr = $new;
    return $arr;
}
