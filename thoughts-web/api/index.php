<?php
session_start();
session_destroy();

// These are just here for development purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load api and config classes
$config = new Config();
$api = new api();

// Process the request immediately if just the api is being loaded
if (!isset($webVersion)) $api->process();

class Config {

    public $defaults;
    public $versions; // Web, API, and Bot versions to prevent incompatibility
    public $api;
    public $web;

    function __construct() {

        $this->versions = array(
            'api' => '1.0', // Current API version
            'apiMin' => '1.0', // Minimum supported API version
            'web' => '1.0', // Website version last time API was updated
            'webMin' => '1.0', // Minimum supported Website version
            'bot' => '1.0', // Bot version last time API was updated
            'botMin' => '1.0'
        );

        // List of config settings. Array includes the follwoing:
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
                'showUser' => array(0, ['binary'], 'Show post author\'s username before each result'),
                'showID' => array(0, ['binary'], 'Show post ID before each result'),
                'wrap' => array('none', [], 'Wrap (quotes) around each thought. options: \'none\', \'single\', \'double\', or custom'),
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
                'fontColor' => array('#e9e5e5', [], 'Font color'),
                'fontSize' => array('1em', [], 'Font size'),
                'postBg' => array('#393939', [], 'Post background color'),
                'postFontColor' => array('#e9e5e5', [], 'Post font color'),
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
            $sizeChanges = array('fontSize', 'postWidth', 'accentRadius', 'postRadius');
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
            if (!isset($set[$key1][$key2]) || !isset($_SESSION[$key1][$key2])) return "ERROR: `{$key1} {$key2}` is not a valid setting";
            
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
                        
                    // Don't includes wrap around the val if it's meant to be a number
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


class api extends config {

    public $req = []; // all $_REQUESTs will be stored
    public $allowedFunctions; // All allowed functions by the API
    public $source; // web or api, usually

    function __construct() {
        
        Config::__construct();

        $this->allowedFunctions = ['config', 'create', 'delete', 'list', 'search', 'tags', 'info', 'user', 'dev', 'new'];

        // If script is ran via CLI, overwrite $_REQUESTs from the params
        if (tools::isCLI()) $this->cli();

        // Get all request variables and put them in an array
        foreach($_REQUEST as $key => $val) {
            $this->req[$key] = $val;
        }

        if (!isset($this->req['token']) || empty($this->req['token'])) $this->req['token'] = 'default'; // default token if none set
        
        // Check if API is enabled (Admin Tokens can still access)
        if ($this->api['enable'] != 1 && $this->token($this->req['token'], 'admin') !== true) die("API is disabled.");

        // Check if website is enabled (Admin Tokens can still access)
        if ($this->web['enable'] != 1 && $this->token($this->req['token'], 'admin') !== true) die("Website is disabled.");

    }

    // Process what has been requested and send to proper function
    function process($source='', $apiVersion=null) {

        $this->source = ($source != '') ? $source : 'api'; // API is default source

        // Check if there was a 'f' (query) request
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

        // Make sure token is valid and has the proper permissions
        $checkToken = $this->token($this->req['token'], 'config');
        if ($checkToken !== true) die($checkToken);

        // Check if they're just trying to view the list (key1=list can also trigger it)
        if (isset($_REQUEST['list']) || (isset($_REQUEST['key1']) && $_REQUEST['key1'] == 'list')) {

            $getConfigSettings = require(__DIR__."/config.php");
            unset($set['token'], $set['admin'], $set['mod']); // don't show tokens, admins, or mods
            echo "Current config.php settings:".PHP_EOL;
            foreach ($set as $setParent => $setVal) {
                foreach ($set[$setParent] as $key => $val) {
                    echo "{$setParent} {$key}: ".$this->arrayToString($val).PHP_EOL;
                }
            }

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
                echo "Changed `{$p['key1']} {$p['key2']}` to `{$p['val']}`";
            } else {
                echo $changeSetting;
            }

        }

        die();
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

        $data = Files::read("thoughts.json");
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

        // Make sure the user isn't flooding (if they're not a mod)
        $lastPost = 0;
        if ($this->isMod($p['user']) == false) {
            // Loop through the thoughts and find the user's latest post
            foreach ($data as $id => $val) {
                if ($val['userID'] == $p['userID']) {
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
        echo "`".ucfirst($p['tag'])." posted!` {$_SESSION['api']['url']}?s={$nextID}";

        // Add this to the thoughts.json
        $data[$nextID] = array("msg" => $p['msg'], "tag" => $p['tag'], "user" => str_replace("HASHTAG", "#", $p['user']), "userID" => $p['userID'], "timestamp" => time(), "source" => $p['platform']);
        if ($ip != null) $data[$nextID]['ip'] = $ip;
        Files::write("thoughts.json", json_encode($data, JSON_PRETTY_PRINT));
        die();

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
            'wipe' => [0, 0, 'binary'] // Wipe=1 will remove the original text from the .json file instead of just marking it as deleted
        );

        $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params

        // Load thoughts data
        $data = Files::read("thoughts.json");
        if (!is_array($data)) $data = []; // Create data array if there are no msgs
        $total = count($data); // total thoughts
        if ($total == 0) return "There are no thoughts to delete";

        // Get data about requested ID
        $id = $p['id'];
        if (isset($data[$id])) {

            $alreadyDeleted = $data[$id]['deleted'] ?? 0;
            if ($alreadyDeleted == 1) die("#{$id} already deleted");

            // Make sure deleter owns the post
            $posterID = $data[$id]['userID'];

            if ($p['deleterID'] != $posterID && $this->isMod($p['deleterID']) != true) die("You are not the author of this post");
            
            $data[$id]['deleted'] = 1;
            $data[$id]['deleter'] = str_replace("HASHTAG", "#", $p['deleter']);
            $data[$id]['deleterID'] = $p['deleterID'];

            // Make sure deleteReason doesn't start with 'wipe'
            if (substr($p['reason'], 0, 4) == 'wipe') {
                $wipe = 1;
                $reason = trim(substr($p['reason'], 4)); // remove 'wipe' from front of reason
            }

            $data[$id]['deleteReason'] = $p['reason'];
            if ($p['wipe'] == 1) $data[$id]['msg'] = "[WIPED]";
            Files::write("thoughts.json", json_encode($data, JSON_PRETTY_PRINT));
            $delMethod = ($p['wipe'] != 1) ? "deleted" : "wiped";
            echo "Post #{$id} {$delMethod}";

        } else {
            echo "ID DOESNT EXIST";
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

        // Get Settings and Thoughts
        $data = Files::read("thoughts.json");
        if (!is_array($data)) $data = []; // Create data array if there are no msgs

        // Get possible queries
        $s = $this->req['s'] ?? null; // specific ID or query to be searched
        $limit = $this->req['limit'] ?? $_SESSION['api']['searchLimit']; // amount of search results to return
        $shuffle = $this->req['shuffle'] ?? $_SESSION['api']['shuffle']; // shuffle search results
        $showAuthor = $this->req['showAuthor'] ?? $_SESSION['api']['showAuthor']; // show author's username before each post
        $showID = $this->req['showID'] ?? $_SESSION['api']['showID']; // show unique ID before each post
        $platform = $this->req['platform'] ?? 'web'; // anything besides "web" will be plain text mode
        $wrap = $this->req['wrap'] ?? tools::wrap($_SESSION['api']['wrap']); // no wrap by default
        $breaks = $this->req['breaks'] ?? $_SESSION['api']['breaks']; // prefer <br /> over /n/r (web will overwrite this)
        $apiRequest = $this->req['api'] ?? null; // API version from requester
        $reason = $this->req['reason'] ?? 1; // Show reason for post deletion
        $reasonby = $this->req['reasonby'] ?? 1; // Show who deleted post

        $total = count($data); // total thoughts
        if ($platform == "discord") {
            $wrap = "`"; // force Discord thoughts to be in a quote box
            $limit = ($limit > 5) ? 5 : $limit; // Discord limit can't go past 5 for now. until there's a word count
        }

        // ?q=list creates an entire list of thoughts and then quits
        if ($s == "list" && $platform != "discord") {
            foreach ($data as $id => $val) {
                if (isset($val['deleted'])) continue;
                $thisID = ($showID == 1) ? "#{$id}: " : null;
                $thisAuthor = ($showAuthor == 1) ? " -{$val['user']}" : null;
                echo "<p class='thought'>{$thisID}{$val['msg']}{$thisAuthor}</p>";
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
                $thisAuthor = ($showAuthor == 1) ? " -{$data[$s]['user']}" : null;
                echo $thisID."{$wrap}".$data[$s]['msg']."{$wrap}{$thisAuthor}";
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
                    if ($results > 0) echo ($platform == "web" || $breaks == 1) ? "<br />" : PHP_EOL; // different line breaks per platform
                    $thisID = ($showID == 1) ? "#".$ids.": " : null;
                    echo "{$thisID}{$wrap}{$vals}{$wrap}";
                    $results++;
                }
            }
        
        }
        

    }

    // List and manage Tags
    function tags() {

        $checkAPI = $this->version(1.0); // no optional parameter so it will kill script if fails
        
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

            $userID = $this->req['userID'] ?? null;
            if ($userID == null) die("Missing userID");
            if ($this->isAdmin($userID) == false) die("Only admin can edit tags");
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

            $data = Files::read("thoughts.json");
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
                Files::write(__DIR__."thoughts.json", json_encode($data, JSON_PRETTY_PRINT));
                echo "Tag `$tag` renamed to `$tagRename`";
            }

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
            'userID' => [null, 0, 'string'], // ID of the user (this will be prioritized over usern)
            'user' => [null, 0, 'string'], // username of user
            'list' => [null, 0, 'string'] // ID of who is requesting delete
        );

        $p = $this->processParams($params); // Process params into an array. Give error (and optional params) if missing required params

        // Load thoughts data
        $data = Files::read("thoughts.json");
        if (!is_array($data)) $data = []; // Create data array if there are no msgs
        $total = count($data); // total thoughts
        if ($total == 0) return "There are no posts for this user";

        if (isset($this->req['list'])) { // Show List

            foreach($data as $key => $val) {
                if (!isset($data[$key]['deleted'])) {
                    echo "#{$key}: {$data[$key]['msg']} -{$data[$key]['user']}".PHP_EOL;
                }
            }
        
        } else { // Show only posts by user

            $searchUser = ($p['userID'] != null) ? 'userID' : 'user';
            $searchUserParse = str_replace("HASHTAG", "#", $p[$searchUser]);
            echo "<h1>Posts by $searchUserParse</h1>";
            foreach($data as $key => $val) {
                if ($data[$key][$searchUser] == $searchUserParse && !isset($data[$key]['deleted'])) {
                    echo "#{$key}: {$data[$key]['msg']}".PHP_EOL;
                }
            }

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