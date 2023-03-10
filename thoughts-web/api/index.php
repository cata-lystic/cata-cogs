<?php
session_start();
session_destroy();

// These are just here for development purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load API (which extends Config class)
$api = new api();

// Process the request immediately if just the api is being loaded
if (!isset($webVersion)) $api->process();

class Db { 

    public $db;

    public function __construct() {
 
        $this->db = new PDO("sqlite:database.db");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
     
    }

    // Load user info by User ID (unique row in database)
    public function checkUserByID($id) {

        if ($id == 'all') {
            $checkUser = $this->db->prepare("SELECT id, username, discordID, admin, mod, totalPosts FROM users");
            $checkUser->execute();
        } else {
            $checkUser = $this->db->prepare("SELECT id, username, discordID, admin, mod, totalPosts FROM users WHERE id = ? LIMIT 1");
            $checkUser->execute([$id]);

        }
        $userInfo = $checkUser->fetchAll(PDO::FETCH_ASSOC); // load user info into object
        return $userInfo;
        
    }

    // Count how many current users there are
    public function userCount() {
        return $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    // Load user info by Discord ID
    public function checkUserByDiscord($discordID) {

        // Check if user exists
        $checkUser = $this->db->prepare("SELECT * FROM users WHERE discordID = ? LIMIT 1");
        $checkUser->execute([$discordID]);
        $userInfo = $checkUser->fetch(PDO::FETCH_ASSOC); // load user info into object
        return $userInfo;
    }

    // :username, :passhash, :discordid, :timecreated, :ipcreated, :iplast, :regsource, :token
    public function newUser($dat) {

        // Check if user exists
        $userInfo = $this->checkUserByDiscord($dat[':discordid']);

        // Create user if they don't exist
        if ($userInfo == false) {

            $prepare = $this->db->prepare("INSERT INTO 'users' ('username', 'passhash', 'discordID', 'timeCreated', 'ipCreated', 'ipLast', 'regSource', 'admin', 'mod', 'token', 'tokenTemp')
            VALUES (:username, :passhash, :discordid, :timecreated, :ipcreated, :iplast, :regsource, '0', '0', :token, '');");

            $prepare->execute($dat);
            $newUserID = $this->db->lastInsertId();
            return $newUserID; // return ID of new user

        } else {
            return $userInfo['id']; // return ID of user
        }
    }

    // Load post by ID
    public function getPostByID($id='all') {

        if ($id == 'all') {
            $check = $this->db->prepare("SELECT * FROM posts");
            $check->execute();
        } else if ($id == 'rand') {
            $check = $this->db->prepare("SELECT * FROM posts ORDER BY RANDOM() LIMIT 1");
            $check->execute();
        } else {
            $check = $this->db->prepare("SELECT * FROM posts WHERE id = ? LIMIT 1");
            $check->execute([$id]);
        }

        $post = $check->fetchAll(PDO::FETCH_ASSOC); // load user info into array
        return $post;
    }

    // Load post by Discord ID
    public function getPostByDiscord($id) {
        $check = $this->db->prepare("SELECT * FROM posts WHERE discordID = ?");
        $check->execute([$id]);
        $post = $check->fetchAll(PDO::FETCH_ASSOC); // load user info into array
        return $post;
    }

    // Load post by Username
    public function getPostByUsername($user) {
        $check = $this->db->prepare("SELECT * FROM posts WHERE username = ?");
        $check->execute([$user]);
        $post = $check->fetchAll(PDO::FETCH_ASSOC); // load user info into array
        return $post;
    }

    // Load posts by Search
    public function getPostBySearch($msg, $tag='all') {
        if ($tag == 'all') {
            $check = $this->db->prepare("SELECT * FROM posts WHERE msg LIKE ?");
            $check->execute(["%{$msg}%"]);
        } else {
            $check = $this->db->prepare("SELECT * FROM posts WHERE msg LIKE ? AND tag = ?");
            $check->execute(["%{$msg}%", $tag]);
        }
        $post = $check->fetchAll(PDO::FETCH_ASSOC); // load user info into array
        return $post;
    }

    // :msg, :tag, :userid, :username, :timecreated, :platform, :ip
    public function newPost($dat) {

        $prepare = $this->db->prepare("INSERT INTO 'posts' ('msg', 'tag', 'userID', 'username', 'discordID', 'timeCreated', 'timeDeleted', 'source', 'ip', 'deleted', 'deleter', 'deleterID', 'deleterDiscordID', 'deleteReason')
        VALUES (:msg, :tag, :userid, :username, :discordid, :timecreated, '0', :platform, :ip, '0', '', '', '', '');");

        if ($prepare->execute($dat)) {
            $newPostID = $this->db->lastInsertId(); // new post id

            // Set timeLastPost on user to prevent creation flooding
            $prepareUpdate = $this->db->prepare("UPDATE 'users' SET timeLastPost = ?, totalPosts = totalPosts+1 WHERE discordID = ? LIMIT 1");
            $prepareUpdate->execute([time(), $dat[':discordid']]);

            return $newPostID; // return new post id

        } else {
            return print_r($prepare->errorInfo());
        }

    }

    // Delete Post
    public function deletePost($postID, $deleter, $deleterID, $reason, $wipe) {

        try {
            $delete = $this->db->prepare("UPDATE posts SET deleted = '1', deleter = ?, deleterID = ?, deleterDiscordID = ?, timeDeleted = ?, deleteReason = ? WHERE id = ? LIMIT 1");
            $delete->execute([$deleter, $deleterID, $deleterID, time(), $reason, $postID]);
            return true;
        } catch( PDOException $e) {
            return $e;
        }

    }

    // Tag renamed, update all posts in database
    public function postUpdateTag($oldTag, $newTag) {

        try {
            $delete = $this->db->prepare("UPDATE posts SET tag = ? WHERE tag = ?");
            $delete->execute([$newTag, $oldTag]);
            return true;
        } catch( PDOException $e) {
            return $e;
        }

    }


}

class Config {

    public $defaults;
    public $versions; 
    public $api;
    public $web;

    function __construct() {

        // Web, API, and Bot versions to prevent incompatibility
        $this->versions = array(
            'api' => '1.0', // Current API version
            'apiMin' => '1.0', // Minimum supported API version
            'web' => '1.0', // Website version last time API was updated
            'webMin' => '1.0', // Minimum supported Website version
            'bot' => '1.0', // Bot version last time API was updated
            'botMin' => '1.0'
        );

        // List of config settings. Array includes the following:
        // [0] Default value
        // [1] Requirements
        // [2] Description
        // [3] Comment to place before variable in config file (put ## before it to add an extra blank line)
        $this->defaults = array(
            'api' => array(
                'url' => array('auto', [], 'Change if auto detection fails','#API Settings'),
                'enable' => array(1, ['binary'], 'Do not disable unless you only want Admin Tokens to access API/Website'),
                'tags' => array(array('thought', 'music', 'spam'), [], 'Tags that thoughts can be categorized into'),
                'tagDefault' => array('thought', ['alphanum'], 'Default tag for new posts. Must be an existing tag'),
                'searchLimit' => array(500, ['number'], 'Search results max limit. Cannot be changed by API request'),
                'searchResults' => array(3, ['number'], 'Default amount of results per search'),
                'create' => array(1, ['binary'], 'Allow new post creation to non-mods via API'),
                'createFlood' => array('10s', ['alphanum'], 'Time between when a user can post again (format: 5s, 3m, 5d, 7w, etc)'),
                'shuffle' => array(1, ['binary'], 'Shuffle search results'),
                'breaks' => array(0, ['binary'], 'Use <br /> instead of \n\r in API calls'),
                'platform' => array('api', ['alpha'], 'Can be set to anything where the request came from (example: discord)'),
                'ipLog' => array(1, ['binary'], 'Log IP address of post creator'),
                'ipHash' => array(1, ['binary'], 'Hash IP addresses'),
                'cli' => array(1, ['binary'], 'Enable CLI (Command Line Interface) usage of API .php file without an Admin Token')),
            "web" => array(
                'enable' => array(1, ['binary'], 'Enable website', '#Website Settings'),
                'shuffle' => array(1, ['binary'], 'Shuffle search results'),
                'showUser' => array(0, ['binary'], 'Show post author\'s username before each result'),
                'showID' => array(0, ['binary'], 'Show post ID before each result'),
                'wrap' => array('none', [], 'Wrap (quotes) around each thought. options: \'none\', \'single\', \'double\', or custom'),
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
                'versionVisible' => array(1, ['binary'], 'Show Thoughts Web version in footer'),
                'js' => array(1, ['binary'], 'Use JavaScript for search and other features'),
                'jquery' => array('local', [], 'options: local, google, jquery, microsoft, cdnjs, jsdelivr, Custom URL', 'jQuery.js location. Change from "local" to direct URL or built in CDN options')),
            "theme" => array(
                'backgroundColor' => array('#212121', [], 'Background color', '#Theme Settings'),
                'font' => array('Arial', [], 'Font family'),
                'fontColor' => array('#e9e5e5', [], 'Font color'),
                'fontSize' => array('1em', [], 'Font size'),
                'postBg' => array('#393939', [], 'Post background color'),
                'postBorder' => array('none', [], 'Post border'),
                'postFontColor' => array('#e9e5e5', [], 'Post font color'),
                'postMargin' => array("10px", [], 'Post margin'),
                'postPadding' => array("10px", [], 'Post padding'),
                'postRadius' => array("10px", [], 'Post border radius'),
                'postWidth' => array('50%', [], 'Post width'),
                'accentColor' => array('#393939', [], 'Box accent color'),
                'accentRadius' => array("10px", [], 'Border radius of boxes'),
                'urlColor' => array("#e9e5e5", [], 'URL color'))
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
            $_SESSION['versions'] = $this->versions;
    
        } else {
            require("config.php");

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
            if ($key2 == 'createFlood' && ctype_digit($newVal)) $newVal .= 's';

            // Make sure some things at least have 'px' at the end if only a number was supplied
            $sizeChanges = array('fontSize', 'postWidth', 'accentRadius', 'postRadius', 'postMargin', 'postPadding');
            if (in_array($key2, $sizeChanges) && ctype_digit($newVal)) $newVal .= 'px';

            // Make sure color changes at least have '#' at the beginning if only 6 chars was supplied
            $colorChanges = array('fontColor', 'backgroundColor', 'postBg', 'postFontColor', 'accentColor', 'urlColor');
            if (in_array($key2, $colorChanges) && strlen($newVal) == 6) $newVal = '#'.$newVal;

            // Make user use the --confirm flag if trying to disable CLI from the CLI
            // An Admin Token will be required to re-enable it (checked in api->cli()) from CLI (can still be changed via config.php, API, or Discord Bot)
            if ($key2 == 'cli' && $newVal == 0 && tools::isCLI() && !isset($this->req['confirm']))
                die(PHP_EOL."Are you sure you want to disable CLI access from the CLI?".PHP_EOL."You will not be able to re-enable via CLI without an Admin Token.".PHP_EOL."To re-enable without Admin Token: change \$set['api']['cli'] to 1 in config.php or use API/Discord Bot.".PHP_EOL.PHP_EOL."If you are sure, add the --confirm flag to your request".PHP_EOL.PHP_EOL);
            
            // As far as I know, to do this we have to rewrite the config.php file each time.
            // This will loop through the defaults to get the keys and descriptions to remake the file
            // The user's current config settings will be saved but the requested key will be overwritten
            require("config.php"); // old config settings will be in $set

            // Make sure this var is an actual setting
            if (!isset($set[$key1][$key2]) || !isset($_SESSION[$key1][$key2])) return "`{$key1} {$key2}` is not a valid setting";
            
            // Check to make sure new value meets requirements
            $checkReq = $this->check($key1, $key2, $newVal);
            if ($checkReq !== true) die($checkReq); // Kill script and show error if fails
            
            $result = "<?php\n#  API Setup\n# Make unique and secure tokens. Must be at least 8 characters in length with no spaces.\n# You may create multiple tokens with different permissions\n# Permissions: admin, config, create, delete, list, read, search, tags, user\n# 'admin' permission has full access to all commands. Only give these tokens to people you trust that will help you manage the API and website\n# Do not delete the 'default' token. This is used for when your API is accessed with no other request.\n# All other tokens will inherit 'default' token's permissions\n# The default token permissions should generally not be changed unless you want to prevent public post creation.\n# When config is complete, give an admin token to your Discord Bot with: [p]thoughtset setup api yourAdminToken\n\n# Tokens\n";

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
                        
                    // Don't include quotes wrap around the val if it's meant to be a number
                    $reqs = $this->defaults[$oldCategory][$oldKey][1];
                    $wrap = (substr($finalVal, 0, 1) == '[' || in_array("number", $reqs) || in_array("binary", $reqs)) ? null : "'";
                    
                    $result .= "\$set['$oldCategory']['$oldKey'] = {$wrap}{$finalVal}{$wrap}; # {$this->defaults[$oldCategory][$oldKey][2]}\n";

                }
            }
            
            Files::write("config.php", $result);
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


class api extends Config {

    public $req = []; // all $_REQUESTs will be stored
    public $allowedFunctions; // All allowed functions by the API
    public $source; // web or api, usually
    public $db; // SQL database

    function __construct() {
        
        parent::__construct(); // Load Config construct to get settings from config.php

        $this->allowedFunctions = ['config', 'create', 'delete', 'list', 'search', 'tags', 'info', 'user', 'dev', 'new'];

        // If script is ran via CLI, overwrite $_REQUESTs from the params
        if (tools::isCLI()) $this->cli();

        // JSON output by default, but API can output raw text if output is 'text' or 'txt'
        if (!isset($_REQUEST['output']) || ($_REQUEST['output'] != 'txt' && $_REQUEST['output'] != 'text')) $_REQUEST['output'] = 'json'; 

        // Get all request variables and put them in an array
        foreach($_REQUEST as $key => $val) {
            $this->req[$key] = $val;
        }

        if (!isset($this->req['token']) || empty($this->req['token'])) $this->req['token'] = 'default'; // default token if none set
        
        // Check if API is enabled (Admin Tokens can still access)
        if ($this->api['enable'] != 1 && $this->token($this->req['token'], 'admin') !== true) die("API is disabled.");

        $this->db = new Db();

    }

    // Process what has been requested and send to proper function
    function process($source='', $apiVersion=null) {

        $this->source = ($source != '') ? $source : 'api'; // API is default source

        // force TXT output for web
        if ($this->source == 'web') {
            $this->req['output'] = 'txt';

            // Check if website is enabled (Admin Tokens can still access)
            if ($this->web['enable'] !== 1 && $this->token($this->req['token'], 'admin') !== true) die("Website is disabled.");
        }

        // Check if there was a 'f' (function) request
        $func = $this->req['f'] ?? null; // no query = search for random thought

        if (empty($func)) $func = 'search';

        // Make sure this is a valid function
        if (!in_array($func, $this->allowedFunctions)) die("Invalid function");

        // If $apiversion is set, place this as the user's api version
        if ($apiVersion != null) $this->req['version'] = $apiVersion;

        // Run requested function
        $this->$func();

    }

    // Request Parameters: 'key' => [0] default value, [1] required (binary), [2] type (string, number, etc)
    function processParams($params, $showOptional=1) {
        // Loop through $params and create variables for each one. Check requirements
        $missing = ''; // keep track of missing required parameters
        $optional  = ''; // keep track of optional parameters
        $processed = [];
        foreach($params as $key => $val) {
            if (isset($this->req[$key])) {
                $processed[$key] = $this->req[$key]; // use user provided value
            } else {
                $processed[$key] = $val[0]; // use default value if req doesn't exist
                if ($val[1] == 1) $missing .= $key.', '; // add to missing if this was a required param
            }
            if ($val[1] == 0) $optional .= $key.', '; // if optional, add to optional params to send along with missing
        }

        if ($missing != '') {
            $opt = ($showOptional == 1 && $optional != '') ? "| Optional: ".rtrim($optional, ', ') : null;  // only show optional if 
            $mis = rtrim($missing, ', ');
            echo rtrim("Missing parameters: {$mis} {$opt}");
            die();
        } else {
            return $processed; // return array of processed values
        }
    }

    // Change a setting in config.php
    function config() {

        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails
        if ($checkAPI !== true) $output['meta']['error'] = $checkAPI;

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'config');
        if ($checkToken !== true) $output['meta']['error'] = $checkToken;

        // Continue if no errors
        if (!isset($output['meta']['error'])) {

            // Check if they're just trying to view the list (key1=list can also trigger it)
            if (isset($_REQUEST['list']) || (isset($_REQUEST['key1']) && $_REQUEST['key1'] == 'list')) {

                $getConfigSettings = require(__DIR__."/config.php"); // Load config file
                unset($set['token'], $set['admin'], $set['mod']); // don't show tokens, admins, or mods

                // Show a header if TXT output requested
                if ($this->req['output'] != 'json') $output['meta']['success'] = "Current config.php settings:".PHP_EOL; // header

                // Loop through each of the parent settings
                foreach ($set as $setParent => $setVal) {

                    // Loop through each of the child settings
                    foreach ($set[$setParent] as $key => $val) {
                        // JSON output
                        if ($this->req['output'] == 'json') {
                            $output['config'][$setParent][$key] = $val;
                        // TXT output
                        } else {
                            $output['meta']['success'] .= "{$setParent} {$key}: ".$this->arrayToString($val).PHP_EOL;
                        }
                    }

                }

            // Try to change a setting
            } else {
                
                // Request Parameters: 'key' => [0] default value, [1] required (binary), [2] type (string, number, etc)
                $params = array(
                    'key1' => [null, 1, 'string'],
                    'key2' => [null, 1, 'string'],
                    'val' => [null, 1, 'string'],
                    'list' => [null, 0, 'binary']
                );

                $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params
            
                // Can't change token from API
                if ($p['key1'] == 'token')
                    die("You can't change the web tokens from the API");

                $val = str_replace("HASHTAG", "#", $p['val']); // convert HASHTAG to #
                
                $changeSetting = $this->set($p['key1'], $p['key2'], $p['val']);
                
                if ($changeSetting == "OK") {
                    $output['meta']['success'] = "Changed `{$p['key1']} {$p['key2']}` to `{$p['val']}`";
                } else {
                    $output['meta']['error'] = $changeSetting;
                }

            }
        }

        // Output JSON
        if ($this->req['output'] == 'json') {
            echo json_encode($output);
        // Output TXT
        } else {
            if (isset($output['meta']['success'])) echo $output['meta']['success'];
            if (isset($output['meta']['error'])) echo $output['meta']['error'];
        }

    }


    function create() {

        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails

        // Make sure 'api create' is enabled
        if ($this->api['create'] == 0 && $this->isMod($this->req['userID']) == false) die("Post creation is currently disabled");

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'create');
        if ($checkToken !== true) die($checkToken);

        // Request Parameters: 'key' => [0] default value, [1] required (binary), [2] type (string, number, etc)
        $params = array(
            'user' => [null, 1, 'string'],
            'userID' => [null, 1, 'string'],
            'msg' => [null, 1, 'string'],
            'tag' => [$this->api['tagDefault'], 0, 'string'],
            'base64' => [0, 0, 'binary'],
            'platform' => ['api', 0, 'string']
        );

        $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params

        // Process IP address if necessary
        if ($this->api['ipLog'] == 1) {

            // Check if script is being ran from command line
            if (tools::isCLI())
                $ip = "CLI";
            else
                $ip = ($this->api['ipHash'] == 1) ? md5($_SERVER['REMOTE_ADDR']) : $_SERVER['REMOTE_ADDR']; // Check ipHash setting

        } else {
            $ip = null; 
        }

        // Only certain tags are allowed
        if ($this->isTag(strtolower($p['tag'])) == false) {
            echo "Invalid tag";
            die();
        }

        // Decode Base64 if requested
        if ($p['base64'] == 1) {
            $msg = base64_decode($p['msg']);
            $user = base64_decode($p['user']);
        }

        // Attempt to get user info from database
        $userInfo = $this->db->checkUserByDiscord(($p['userID']));

        // If user found, make sure they're not flooding (if they're not a mod)
        if ($userInfo != false && $this->isMod($p['userID']) == false) {
            $lastPost = $userInfo['timeLastPost'];

            if ($lastPost != 0) {
                $floodFinal = $lastPost - (time() - intval($_SESSION['api']['createFlood'])); // Subtract last post time from flood check to see seconds left
                if ($floodFinal > 0) {
                echo "`Slow down!` You can post again in ".tools::floodTime($floodFinal, 1)."."; // stop if flood triggered
                die();
                }
            }

        }

        // All is well, post results
        if ($userInfo == false) {
            $ui = [
            ':username' => str_replace("HASHTAG", "#", $p['user']),
            ':passhash' => '',
            ':discordid' => $p['userID'],
            ':timecreated' => time(),
            ':ipcreated' => $ip,
            ':iplast' => $ip,
            ':regsource' => $p['platform'],
            ':token' => $this->req['token']];
            
            // Add user to database if they don't already exist (returns new or old userID regardless)
            $newUserID = $this->db->newUser($ui);
        }

        // Add post to database. Return new post ID
        $postData = [
        ':msg' => $p['msg'],
        ':tag' => $p['tag'],
        ':userid' => $p['userID'],
        ':username' => str_replace("HASHTAG", "#", $p['user']),
        ':discordid' => $p['userID'],
        ':timecreated' => time(),
        ':platform' => $p['platform'],
        ':ip' => $ip];
    
        $newPostID = $this->db->newPost($postData);

        echo "`".ucfirst($p['tag'])." posted!` {$_SESSION['api']['url']}?s={$newPostID}";

    }

    // Delete thought
    function delete() {

        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'delete');
        if ($checkToken !== true) die($checkToken);

        // Request Parameters: 'key' => [0] default value, [1] required (binary), [2] type (string, number, etc)
        $params = array(
            'id' => [null, 1, 'number'], // ID of post to be deleted
            'deleter' => [null, 1, 'string'], // Name of who is requesting delete
            'deleterID' => [null, 1, 'string'], // ID of who is requesting delete
            'reason' => [null, 1, 'string'], // Reason this post was deleted
            'wipe' => [0, 0, 'binary'] // Wipe=1 will remove the original text from the msg instead of just marking it as deleted
        );

        $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params

        // Get info about this post
        $post = $this->db->getPostByID($p['id']);

        // Get data about requested ID
        if ($post != false) {

            if ($post['deleted'] != 1) {

                // Make sure post author is who is trying to delete it (or they're a mod)
                if ($post['discordID'] != $p['deleterID'] && !$this->isMod($p['deleterID'])) {
                    $output['meta']['error'] = "You are not the author of this post";
                }

                // Delete the post
                $deletePost = $this->db->deletePost($p['id'], $p['deleter'], $p['deleterID'], $p['reason'], $p['wipe']);
                $delMethod = ($p['wipe'] != 1) ? "deleted" : "wiped"; // wipe doesn't currently work, may be deprecated

                if ($deletePost === true) 
                    $output['meta']['success'] = "Post #{$p['id']} {$delMethod}";
                else
                    $output['meta']['error'] = $deletePost;
                
            } else {
                $output['meta']['error'] = "Post #{$p['id']} already deleted";
            }

        } else {
            $output['meta']['error'] = "Post #{$p['id']} doesn't exist.";
        }

        // Output JSON
        if ($this->req['output'] == 'json') {
            echo json_encode($output);
        // Output TXT
        } else {
            if (isset($output['meta']['success'])) echo $output['meta']['success'];
            if (isset($output['meta']['error'])) echo $output['meta']['error'];
        }

    }
    
    // List all posts
    function list() {
        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails
        $this->req['s'] = 'list'; // change search result to 'list' (this needs to be fixed)
        $this->search();
    }

    function search() {

        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'read');
        if ($checkToken !== true) die($checkToken);

        // Request Parameters: 'key' => [0] default value, [1] required (binary), [2] type (string, number, etc)
        $params = array(
            's' => [null, 0, 'string'],
            'tag' => ['all', 0, 'string'], // Tag search results must be in
            'limit' => [$_SESSION['api']['searchLimit'], 0, 'number'],
            'shuffle' => [$_SESSION['api']['shuffle'], 0, 'string'], 
            'platform' => ['web', 0, 'string'],
            'reason' => [1, 0, 'string'], // show reason for post deletion
            'reasonby' => [1, 0, 'binary'], // show who deleted post
            'showID' => [$_SESSION['web']['showID'], 0, 'binary'], // Web only. Show post ID
            'showUser' => [$_SESSION['web']['showUser'], 0, 'binary'], // Web only. Show post User
            'wrap' => [tools::wrap($_SESSION['web']['wrap']), 0, 'string'],  // Web only. Show wrap (quotes) around msg
            'breaks' => [$_SESSION['api']['breaks'], 0, 'binary'] // Show <br /> instead of \\n\\r
        );

        $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params
        $s = $p['s']; // shortcut for search query

        $output = ['results' => []];
        if ($p['platform'] == "discord") {
            $p['wrap'] = "`"; // force Discord thoughts to be in a quote box
            $p['limit'] = ($p['limit'] > 5) ? 5 : $p['limit']; // Discord limit can't go past 5 for now. until there's a word count
        }

        // First check to make sure they're not just asking for a short request

        // ?s=list for Discord just shows a link to the list page
        if ($s == "list" && $p['platform'] == "discord") {

            $output['meta']['success'] = "Full list of posts can be found at {$_SESSION['api']['url']}?s=list";

        // If list is not for Discord, put all non-deleted posts in output results
        } else if ($s == "list" && $p['platform'] != "discord") {

            $fetch = $this->db->getPostByID('all');
            
            if ($fetch != false) {
                foreach($fetch as $key => $val) {
                    $output['results'][$val['id']] = $val; // Add to results (this will be only result)
                }
            } else {
                $output['meta']['error'] = "Post #{$s} does not exist";
            }

        // If $s is empty, fetch a random ID
        } else if ($s == null) {

            $fetch = $this->db->getPostByID('rand');

            if ($fetch != false)
                $output['results'][$fetch['id']] = $fetch; // Add to results (this will be only result)
            else
                $output['meta']['error'] = "Post #{$s} does not exist";

        // If $s is a number, fetch a specific ID
        } else if (is_numeric($s)) {
            
            $fetch = $this->db->getPostByID($s);

            // Make sure ID exists
            if ($fetch != false)
                $output['results'][$fetch['id']] = $fetch; // Add to results (this will be only result)
            else
                $output['meta']['error'] = "Post #{$s} does not exist";
        
        // If $s is a string, search each post to see if that word is in it
        } else if ($s != null) {
        
            $fetch = $this->db->getPostBySearch($s, $p['tag']);

            // Make sure posts were returned
            if ($fetch != false) {
                foreach($fetch as $key => $val) {
                    $output['results'][$val['id']] = $val; // Add to results (this will be only result)
                }
            } else {
                $output['meta']['error'] = "No posts found related to `$s` in tag `{$p['tag']}`";
            }
        
        }

        // Process output results if there were no errors
        if (!isset($output['meta']['error'])) {

            if ($p['shuffle'] == 1) $output['results'] = shuffle_assoc($output['results']); // Shuffle results if necessary
            $results = 0; // keep count of shown results to not go past limit

            // Make output['meta']['success'] a string if TXT is requested
            if ($this->req['output'] != 'json') $output['meta']['success'] = ''; 
            
            foreach($output['results'] as $ids => $vals) {
                if (isset($vals['deleted']) && $vals['deleted'] == 1) $vals['msg'] = '[deleted]'; // don't show deleted messages
                if ($p['wrap'] != null) $vals['msg'] = $p['wrap'].$vals['msg'].$p['wrap'];
                // JSON output
                if ($this->req['output'] == 'json') {
                    $output['results'][$ids] = $vals; // only changed if message is deleted
                // TXT output
                } else {
                    $thisID = ($p['showID'] == 1) ? "#".$ids.": " : null;
                    $thisUser = ($p['showUser'] == 1) ? " -{$vals['username']}":null;
                    //$output['meta']['success'] .= ($results > 0 && ($p['platform'] == "web" || $p['breaks'] == 1)) ? "<br />" : PHP_EOL; // different line breaks per platform
                    if ($this->source == 'web' || $this->req['platform'] == 'web') {
                        $html1 = "<p class='post'>";
                        $html2 = "</p>";
                    } else {
                        $html1 = '';
                        $html2 = '';
                    }
                    $output['meta']['success'] .= "{$html1}{$thisID}{$p['wrap']}{$vals['msg']}{$p['wrap']}{$thisUser}{$html2}";
                    // Show Deleted By and Reason if requested
                    if ($p['reasonby'] === 1 && $vals['deleter'] != '') $output['meta']['success'] .= " Deleted by: ".str_replace("HASHTAG", "#", $vals['deleter']).".";
                    if ($p['reason'] === 1 && $vals['deleteReason'] != '') $output['meta']['success'] .= " Reason: {$vals['deleteReason']}.";

                }
                $results++;
            }
            $output['meta']['total'] = $results;
            $output['meta']['searchQuery'] = $p['s'];
            $output['meta']['shuffle'] = $p['shuffle'];
            $output['meta']['tag'] = $p['tag'];

        }

        // Output JSON
        if ($this->req['output'] == 'json') {
            echo json_encode($output);
        // Output TXT
        } else {
            if (isset($output['meta']['success'])) echo $output['meta']['success'];
            if (isset($output['meta']['error'])) echo $output['meta']['error'];
        }
        

    }

    // List and manage Tags
    function tags() {

        // Check API version
        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails
        
        // Load all current tags
        $tags = $this->api['tags'];

        // Request Parameters: 'key' => [0] default value, [1] required (binary), [2] type (string, number, etc)
        $params = array(
            'action' => ['list', 0, 'string'], // If no action requested, show list of tags and meta info
            'tag' => [null, 0, 'string'], // Tag user is requesting
            'rename' => [null, 0, 'string'], // User may be requesting to edit tag's name
            'userID' => [null, 0, 'string'] // UserID of who is add/remove/editing a tag
        );

        $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params
        $ac = $p['action']; // shortcut for action request
        $t = $p['tag']; // shortcut for supplied tag

        // Make sure this is a valid action
        $actions = ['list', 'add', 'remove', 'edit', 'total', 'default'];
        if (!in_array($ac, $actions)) {

            $output['meta']['error'] = "Invalid action. (allowed: list, add, remove, edit, total, default)";

        // List all tags and meta info (default action)
        } else if ($ac == 'list') {

            // This function only requires 'read' permissions
            $checkToken = $this->token($this->req['token'], 'read');
            if ($checkToken !== true) {
                $output['meta']['error'] = $checkToken;
            } else {
                // JSON output
                if ($this->req['output'] == 'json') {
                    // Add all current tags to an output[tags] array
                    foreach ($tags as $tag) { $output['tags'][] = ['name' => $tag]; }
                    $output['meta']['total'] = count($output['tags']); // add total to metadata output
                    $output['meta']['default'] = $this->api['tagDefault']; // add default tag to meta
                // TXT output
                } else {
                    $taglist = ''; // returning a string
                    // Make a long string with each tag ending in a comma and sapce
                    foreach ($tags as $tag) { $taglist .= $tag.", "; }
                    // Output taglist with last comma removed
                    $output['meta']['success'] = substr($taglist, 0, -2);
                }

            }

        // Add, remove, or edit tag
        } else if ($ac == 'add' || $ac == 'remove' || $ac == 'edit') {

            // These functions requires 'tags' permissions
            $checkToken = $this->token($this->req['token'], 'tags');
            if ($checkToken !== true) $output['meta']['error'] = $checkToken;

            // Make sure required fields are filled
            if ($p['userID'] == null) $output['meta']['error'] = "Missing userID"; // userID required to edit
            if ($this->isAdmin($p['userID']) == false) $output['meta']['error'] = "Only admin can edit tags"; // Only Admin can edit
            if ($t == null) $output['meta']['error'] = "Missing tag"; // Tag required

            // Only continue if everything required is set
            if (!isset($output['meta']['error'])) {
                // Add tag
                if ($ac == 'add') {
                    // Add new tag if it doesn't already exist
                    if (!in_array($t, $this->api['tags'])) {
                        array_push($this->api['tags'], $t); // add new tag to list of current tags
                        $this->set('api', 'tags', $this->api['tags']); // set tags in config file with the new list
                        $output['meta']['success'] = "Tag `{$t}` added";
                    } else {
                        $output['meta']['error'] = "Tag `{$t}` already exists";
                    }
                // Remove tag
                } else if ($ac == 'remove') {
                    // Remove tag if it exists
                    if (in_array($t, $this->api['tags'])) { // check if this is in the tags array
                        $tagID = array_search($t, $this->api['tags']); // get the tag's array ID
                        unset($this->api['tags'][$tagID]); // remove tag from current tags
                        $this->set('api', 'tags', $this->api['tags']); // set tags in config file with the new list
                        $output['meta']['success'] = "Tag `{$t}` removed";
                    } else {
                        $output['meta']['error'] = "Tag `{$t}` doesn't exist";
                    }
                
                // Edit tag
                } else if ($ac == 'edit') {

                    // Make sure tag being renamed exists
                    if (!is_array($tags) || !in_array($t, $tags)) $output['meta']['error'] = "Tag `{$t}` doesn't exist";

                    // Make sure a new tag name was given
                    if (empty($p['rename'])) $output['meta']['error'] = "'rename' flag required";

                    // Rename tag
                    if (!isset($output['meta']['error'])) {
                        $tagID = array_search($t, $this->api['tags']); // get tag's array ID
                        unset($this->api['tags'][$tagID]); // remove tag from current tags
                        array_push($this->api['tags'], $p['rename']); // add new/renamed tag to current tags

                        // Change tag for all posts in database
                        $changeTags = $this->db->postUpdateTag($p['tag'], $p['rename']);
    
                        $this->set('api', 'tags', $this->api['tags']); // change config.php file with new list of tags

                        $output['meta']['success'] = "Tag `{$t}` renamed to `{$p['rename']}`";
                    }
                }
            
            }

        // Show total tags number
        } else if ($ac == 'total') {
            $output['meta']['success'] = count($tags);

        // Show default tag
        }  else if ($ac == 'default') {
            $output['meta']['success'] = $this->api['tagDefault'];
        }

        // Output JSON
        if ($this->req['output'] == 'json') {
            echo json_encode($output);
        // Output TXT
        } else {
            if (isset($output['meta']['success'])) echo $output['meta']['success'];
            if (isset($output['meta']['error'])) echo $output['meta']['error'];
        }

    }

    // Load info about a user/users
    function user() {

        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails
        
        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'user');
        if ($checkToken !== true) die($checkToken);

        // Request Parameters: 'key' => [0] default value, [1] required (binary), [2] type (string, number, etc)
        $params = array(
            'id' => [null, 0, 'number'], // ID of the user in database
            'discordID' => [null, 0, 'number'], // Discord ID of user
            'user' => [null, 0, 'string'], // username of user
            'list' => [null, 0, 'string'], // ID of who is requesting delete
            'count' => [null, 0, 'string']
        );

        $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params

        if (isset($this->req['list'])) { // Show List of users

            $fetch = $this->db->checkUserByID('all'); // fetch all users

            if ($fetch != false) {
                // JSON output
                if ($this->req['output'] == 'json') {
                    foreach($fetch as $key => $val) {
                        $output['results'][] = $val;
                    }
                // TXT output
                } else {
                    $output['meta']['success'] = "<h1>All Users</h1>";
                    foreach($fetch as $key=> $val) {
                        $output['meta']['success'] .= "<p>{$val['username']} ({$val['discordID']})</p>";
                    }
                }
            }

            $output['meta']['total'] = count($fetch);

        } else if (isset($this->req['count'])) { // Count how many unique users there are

            $output['meta']['success'] = $this->db->userCount();

        } else { // Show all posts by user or userID

            if ($p['discordID'] != null) {
                $userPosts = $this->db->getPostByDiscord($p['discordID']);
            } else {
                $userPosts = $this->db->getPostByUsername($p['user']);
            }
            
            if ($userPosts != false) {
                // JSON output
                if ($this->req['output'] == 'json') {
                    foreach($userPosts as $key => $val) {
                        $output['results'][] = $val;
                    }
                // TXT output
                } else {
                    $postsBy = ($p['user'] != '') ? $p['user'] : $p['discordID'];
                    $output['meta']['success'] = "<h1>Posts by $postsBy</h1>";
                    foreach($userPosts as $key=> $val) {
                        $output['meta']['success'] .= "<p>{$val['msg']} -{$val['username']}</p>";
                    }
                }
            }

            $output['meta']['total'] = count($userPosts);

        }

        // Output JSON
        if ($this->req['output'] == 'json') {
            echo json_encode($output);
        // Output TXT
        } else {
            if (isset($output['meta']['success'])) echo $output['meta']['success'];
            if (isset($output['meta']['error'])) echo $output['meta']['error'];
        }

    }

    // Setup the CLI params into $_REQUESTs
    function cli() {

        // Make sure CLI is enabled
        
        $cliShortOptions = "f:hs:a:i:m:r:t:vb:w:l"; // q=query: h=help:: s=search:: u=user:: i=userID:: m=msg:: r=searchResults:: t=token:: v=version::
        
        $cliLongOptions = ['function:', 'help', 'search:', 'user:', 'userID:', 'userid:', 'shuffle:', 'searchResults:', 'searchresults:', 'token:', 'version', 'man', 'key1:', 'key2:', 'val:', 'apiversion:', 'botversion:', 'break:', 'showID:', 'showid:', 'showUser:', 'showuser:', 'wrap:', 'id:', 'list', 'confirm'];
        
        $options = getopt($cliShortOptions, $cliLongOptions);
        
        //$firstArg = $argv[1] ?? null; // First arg in case they want to skip using -q (coming later)
        // ^ maybe have list of API functions (create, search, etc) and if a non-optional/non-required flag of that is found, set f=whatever. would have to only do it for the first one found... work in progress

        // Convert all CLI to the proper $_REQUEST they would match
        $optionToVar = [
            'function' => 'f',
            'search' => 's',
            'u' => 'user',
            'i' => 'userID',
            'm' => 'msg',
            'r' => 'searchResults',
            't' => 'token',
            'b' => 'break',
            'w' => 'wrap',
            'l' => 'list',
            'userid' => 'userID',
            'searchresults' => 'searchResults',
            'showuser' => 'showUser',
            'showid' => 'showID',
            'apiversion' => 'version' // this needs to be changed to apiversion as the main API flag
        ];
        foreach ($optionToVar as $key => $val) {
            if (isset($options[$key])) {
                $options[$val] = $options[$key]; // val is the new key
                unset($options[$key]); // remove the old one
            }
        }
        
        // End script if CLI is disabled and an Admin Token wasn't provided
        $tok = $options['token'] ?? 'default';
    
        if ($this->api['cli'] != 1 && $this->token($tok, 'admin') !== true) die('CLI is disabled');

        // Just show the API version
        if (isset($options['v']) || isset($options['version'])) {
            $config = new Config();
            echo PHP_EOL."Thoughts API. Version {$config->versions['api']}".PHP_EOL.PHP_EOL;
            die();
        }

        // Just show the help
        if (isset($options['h']) || isset($options['help']) || isset($options['man'])) {
            $config = new Config();
            $n = PHP_EOL;
            echo "
            Thoughts {$config->versions['api']}
            Required
            -f --function    str  Main API function you want to run (search, create, info, etc)
            Optional
            -t --token       str  API Token. Will use 'default' if none given
            --apiversion     num  API version you'd like to make this call with
            --botversion     num  Bot version you'd like to make this call with

            Search Parameters
            -s --search      str  Search query (put multiple words in quotes)
            --id             num  Fetch direct post ID
            -l --list             List all posts (ignores -s and --id)
            --shuffle        bin  Shuffle search results
            --searchResults  num  Max number of search results to return
            --showID         bin  Show ID # of each post
            -b --break       bin  Show <br /> instead of \\n
            -w --wrap        bin  Wrap (quotes) to use around each result

            Create Parameters
            -u --user        str  Post author's username
            -i --userID      str  Post author's full ID (usually Discord ID)
            -m --msg         str  Post message contents
            --tag            str  Post's tag (will default to config setting)

            Config Parameters
            --key1           str  Config setting category to change
            --key2           str  Config setting to change
            --val            str  New config setting value
            -l --list             List current config settings

            Info:
            -h --help             Show this help menu
            -v --version          Show API version
            ";
            die();
        }

        // Loop through each options (if set) and turn them into the $_REQUESTS
        foreach ($options as $key => $val) {
            $_REQUEST[$key] = $val;
        }
    }

    // Display info about Thoughts and each version
    function info() {

        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails

        $testing = $_REQUEST['testing'] ?? 'none';

        if ($testing != 'none') {
            $checkAPI = $this->version(1.1);
            echo 'only version 1.1 and up can see this';
        }

        // This function only requires 'read' permissions
        $checkToken = $this->token($this->req['token'], 'read');
        if ($checkToken !== true) die($checkToken);

        $break = $this->req['break'] ?? 0; // User can also request breaks via API instead of platform=web

        $info = "Thoughts by Catalyst\nAPI Version: {$this->versions['api']}";
        $botVersion = $this->req['versionbot'] ?? null;
        if ($botVersion != null) {
            $botAPIoutdated = ($this->req['version'] < $this->versions['api']) ? "(Outdated)":null;
            $info .= "\nBot Version: {$botVersion} - Supports API {$this->req['version']} {$botAPIoutdated}";
        }
        
        $extra1 = null; $extra2 = null;
        if (isset($this->req['platform']) && $this->req['platform'] == 'discord') {
            $extra1 = '<'; $extra2 = '>'; // show <> around URL for Discord so it doesn't embed link
        }

        $info .= "\nWebsite: {$extra1}{$this->api['url']}{$extra2}";
        $info .= "\nSource: {$extra1}https://github.com/cata-lystic/cata-cogs{$extra2}";
        echo ($break != 1 && $this->source != 'web') ? $info : nl2br($info); // show breaks for web
     }

    // Detect if user's API version supports current function
    function version($requiredVersion=null, $dieOnFail=1) {
        $userVer = $this->req['version'] ?? 1.0; // if no API is set, just provide 1.0
        $latestVer = $this->versions['api'];
        $checkVer = ($requiredVersion != null) ? $requiredVersion : $this->versions['api']; // check if using latest API version if none requested
        if ($userVer > $latestVer || $userVer < 1) $error = "API Version $userVer does not exist";
        if ($userVer < $checkVer) $error = "API Version $userVer does not support this feature. Requires API >= $checkVer";
        if (isset($error)) {
            if ($dieOnFail == 1) die($error); // kill script instead of return if called
            else return $error; // return error normally if not
        } else {
            return true;
        }
    }

    // This function is only used for me to test out code.
    function dev() {
       echo 'Nothing in dev() right now...';
    }

    // This function is only to test API version checks
    function new() {
        $checkAPI = $this->version(1.5);
        die($checkAPI);
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
            $host = $_SERVER['HTTP_HOST'] ?? null;
            $url = $host.$_SERVER['PHP_SELF'];
            // Remove the main possible files that could be accessed. Remove them and you have the domain.
            if (substr($url, -14) == '/api/index.php') $url = substr($url, 0, -14);
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

    // Process wrap (convert single or double to ' or "")
    public static function wrap($str) {
        if ($str == "none") {
            $wrap = "";
        } else if ($str == "single") {
            $wrap = "'";
        } else if ($str == "double") {
            $wrap = '"';
        } else {
            $wrap = $str;
        }
        return $wrap;
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

    // Detect if script is being called from CLI (Command Line Interface)
    public static function isCLI() {
        return (PHP_SAPI == "cli") ? true : false;
    }

}


// Secret Post encryption/decryption
class Secret {

    function __construct() {
    }

    function encrypt($encryptionKey, $data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $encrypted = openssl_encrypt($data, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }
    
    function decrypt($encryptionKey, $data) {
        $c = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($cipher="AES-256-GCM");
        $iv = substr($c, 0, $ivlen);
        $tag = substr($c, $ivlen, $taglen=16);
        $ciphertext_raw = substr($c, $ivlen+$taglen);
        return openssl_decrypt($ciphertext_raw, 'aes-256-gcm', $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
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

// Put a new line at the end of a PHP CLI output
if (PHP_SAPI == "cli") {
    echo PHP_EOL;
}