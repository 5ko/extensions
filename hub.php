<?php if (!defined('PmWiki')) { extServe(); exit();}
/**
  ExtensionHub: Configuration panel for PmWiki recipes
  Written by (c) Petko Yotov 2023-2024

  This text is written for PmWiki; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 3
  of the License, or (at your option) any later version.
  See pmwiki.php for full details and lack of warranty.
*/

$RecipeInfo['ExtensionHub']['Version'] = '2024-02-19b';
SDVA($FmtPV, [
  '$xHubVersion'  => '$GLOBALS["RecipeInfo"]["ExtensionHub"]["Version"]',
  '$ExtPubDirUrl' => 'extFarmPubDirUrl()',
]);

SDVA($HandleActions, ['hub'=>'HandleHub']);
SDVA($HandleAuth,    ['hub' => 'admin']);
SDVA($ActionTitleFmt,['hub' => '| $[Extensions]']);

SDVA($PostConfig, [
  'extGetIncluded' => 0.5,
  'extOutputResources' => 300.5,
]);

SDVA($xHub, [
  'ExtDir' => dirname(__FILE__),
  'DataDir' => "$WorkDir/.extensions",
  'DataPageName' => "Extensions.Config",
  'Resources'=>[ [], [] ], # header, footer
  'ResourcesSeen'=>[],
  'ResourcesSent'=>0,
]);

if(!isset($xHub['Template']))
  $xHub['Template'] = <<<'TEMPLATE'
markup:(:messages:)
(:Summary: Cookbook:ExtensionHub Template used when listing extensions and editing configurations:)
(:if20240205 enabled EnableExtSaved:)
>>frame note<<
$[Configuration saved.] $[Return to] [[{*$FullName}?action=hub| $[All extensions] ]] | [[{*$FullName}|+]]
>><<
(:if20240205 enabled EnableExtDeleted:)
>>frame note<<
$[Configuration deleted.] $[Return to] [[{*$FullName}|+]]
>><<

(:if20240205end:)
(:if20240205 enabled EnableExtConfig:)
!! {*$xName} configuration
(:if202402051 exists Site.{*$xName}Form:)
(:include Site.{*$xName}Form##form:)
(:else202402051:)
>>frame<<
Cookbook:{*$xName}
>><<
(:if202402051end:)

(:input form "{*$PageUrl}?action=hub&x={*$xName}&i={*$xIndex}" method=post:)
(:input default request=1:)
(:input default xNameList *:)
(:input hidden n {*$FullName}:)
(:input hidden action hub:)
(:input pmtoken:)
(:input hidden x:)
(:input hidden i:)
(:input checkbox xEnabled 1 "$[Enable configuration]":)

(:if202402052 enabled EnableExtPgCust:)
(:input datalist xNameList *:)
(:input datalist xNameList {*$Group}.*:)
(:input datalist xNameList {*$FullName}:)
(:input datalist xNameList *.{*$Name}:)
$[Applies to pages:]\\
(:input text xNamePatterns placeholder=* list=xNameList required=required:) \\
''$[Glob patterns like @@Group1.*,Group2.*,-*.HomePage@@]''

(:else202402052:)
(:input hidden xName *:)
(:if202402052end:)

(:if202402051 exists Site.{*$xName}Form:)
(:include Site.{*$xName}Form#form#formend:)

''Leave fields empty to reset to default values.''

(:if202402051end:)
(:input submit xPost "$[Save]":) &nbsp; [[{*$FullName}?action=hub| $[Cancel] ]]

(:input submit xReset "$[Delete configuration]" data-pmconfirm="$[Confirm deletion?]":)

(:input end:)


(:include Site.{*$xName}Form#formend:)

(:else20240205:)
>>recipeinfo frame<<
$[Summary]: Configuration panel for PmWiki extensions \\
$[Version]: {$xHubVersion}\\
$[Maintainer]: [[https://www.pmwiki.org/petko|Petko]]\\
$[Cookbook]: [[(Cookbook:)ExtensionHub]]
>><<

$[Here you can enable and configure your PmWiki extensions.]

(:extlist:)

$[See Cookbook:Extensions to find new extensions.]

(:if20240205end:)
TEMPLATE;

SDVA($xHub['injectFmt'], [
  'css' => "<link rel='stylesheet' href='%s' %s />\n",
  'js' => "<script src='%s' %s></script>\n",
]);

SDVA($MarkupDirectiveFunctions, ['extlist'=>'FmtExtList']);


$extInc = extInit();
foreach($extInc as $path=>$priority) {
  include_once($path);
}

function extFarmPubDirUrl() {
  global $FarmPubDirUrl, $PubDirUrl;
  $pub = $FarmPubDirUrl ?? $PubDirUrl;
  return preg_replace('#/[^/]*$#', '/extensions', $pub, 1);
}

function extCaller() {
  global $xHub;
  $dir = $xHub['ExtDir'];
  $trace = debug_backtrace();
  for($i=1; $i<count($trace); $i++) {
    $path = $trace[$i]['file'];
    $xname = basename($path, '.php');
    
    if(!preg_match("!^(phar://)?      # ? compressed
      $dir/                           # /extensions path
      ($xname(-\\w[-\\w.]*)?\\.zip/)? # ? compressed name
      $xname(-\\w[-\\w.]*)?/          # directory
      $xname\\.php$                   # script
      !x", $path, $m)) continue;
    return $xname;
  }
  return false;
}

function extAddWikiLibDir($path = 'wikilib.d') {
  global $WikiLibDirs;
  
  $xname = extCaller();
  if(!$xname) return; # abort?
  
  $active = extGetConfig('active');
  
  $c = @$active[$xname];
  if(!$c) return;
  $dir = @$c['=dir'];
  if(!$me) return;
  
  $path = "$dir/$path";
  $WikiLibDirs[$path] = $path;
}

function extAddResource($files, $attrs=[], $footer=0) {
  global $xHub;
  
  $i = $footer? 1:0;
  
  $xname = extCaller();
  if(!$xname) $xname = 'unknown';
  
  if(!isset($xHub['Resources'][$i][$xname])) $xHub['Resources'][$i][$xname] = [];
  $xHub['Resources'][$i][$xname][] = [$files, $attrs];
  
  if($xHub['ResourcesSent']) { # injected by page markup rather than globally
    extOutputResources();
  }
}

function extOutputResources() {
  global $xHub, $HTMLHeaderFmt, $HTMLFooterFmt;
  $fmts = [ &$HTMLHeaderFmt, &$HTMLFooterFmt ];
    
  for($f=0; $f<2; $f++) {
    $fmt = &$fmts[$f];
    $css = $js = $raw = [];
    
    foreach($xHub['Resources'][$f] as $xname=>$a) {
      # a $HTMLHeaderFmt[$xname] defined in config.php 
      # overrides the one suggested by the recipe.
      if(isset($fmt[$xname])) continue;
      
      $out = '';
      foreach($a as $x) {
        $out .= extFormatResource($xname, $x[0], $x[1]);
      }
      if(!isset($fmt["ext~$xname"])) $fmt["ext~$xname"] = '';
      $fmt["ext~$xname"] .= $out;
      unset($xHub['Resources'][$f][$xname]);
    }
  }
  $xHub['ResourcesSent']++;
}

function extFormatResource($xname, $a, $htmlattr) {
  global $xHub;
  
  $active = extGetConfig('active');  
  $out = '';
  if(is_array($a)) foreach($a as $v) 
    $out .= extFormatResource($xname, $v, $htmlattr);
  
  if(is_string($a)) {
    if(strpos($a, '<')!==false) return $out . $a;
    if(preg_match('/\\s/', $a)) {
      $x = preg_split('!\\s+!', $a, -1, PREG_SPLIT_NO_EMPTY);
      return $out . extFormatResource($xname, $x, $htmlattr);
    }
    
    $attr = '';
    if(@$htmlattr[$a]) foreach($htmlattr[$a] as $k=>$v) {
      if(is_string($v)) $v = PHSC($v);
      elseif(is_array($v)||is_object($v)) $v = pm_json_encode($v, true);
      if(is_int($k)) $attr .= " $v";
      else $attr .= " $k=\"$v\"";
    }
    
    if(strpos($a, ':') === false && $a[0] !== '/') {
      $conf = $active[$xname];
      $url = $conf['=url'];
      $a = "$url/$a";
      if(isset($xHub['ResourcesSeen'][$a])) return $out;
    }
    
    if(preg_match('/\\.(css|js)(?:[?#].+)?$/i', $a, $m)) {
      $fmt = $xHub['injectFmt'][strtolower($m[1])];
      $out .= sprintf($fmt, $a, $attr);
      $xHub['ResourcesSeen'][$a] = 1;
    }
  }
  return $out;
}

function extScanDir() {
  global $xHub;
  $xHub['ExtPaths'] = $list = [];
  $dir = $xHub['ExtDir'];
  
  if(!is_dir($dir)) return;
  $dirlist = scandir($dir);
 
  # Extension downloaded from repository may have its tag as suffix
  $suffix = '/-(master|main|latest)$/';
 
  foreach($dirlist as $fname) {
    if($fname[0] == '.') continue;
    if(strpos($fname, ',') !== false) continue;
        
    # Uncompressed extensions override compressed ones
    if(substr($fname, -4) === '.zip') {
      $base = substr($fname, 0, -4);
      $xname = preg_replace($suffix, '', $base);
      SDVA($list, [$xname=>"phar://$dir/$fname/$base/$xname.php"]);
    }
    elseif(is_dir("$dir/$fname")) {
      $xname = preg_replace($suffix, '', $fname);
      $list[$xname] = "$dir/$fname/$xname.php";
    }
  }
  $xHub['ExtPaths'] = $list;
}

function extGetConfig($args = ['mode'=> 'extension']) { 
  # Can be called:
  # - initially to populate the full static array
  # - to populate extGetIncluded
  # - to get extension configuration - full or merged
  # - to get configuration for ?action=hub (list, form, or save)
  
  global $xHub;
  
  static $Extensions = [], $Active = [], $page = [];
  
  if(is_string($args)) $args = ['mode'=> $args];
  
  if(!$Extensions) {
    $page = $xHub["ConfigStore"]->read($xHub["DataPageName"], READPAGE_CURRENT);
    if(!$page) $page = [];
    $keys = preg_grep('/^x\\./', array_keys($page));
    
    $list = $xHub['ExtPaths'];
  
    $x = [];
    
    foreach($keys as $key) {
      $value = $page[$key];
      $parts = explode('.', substr($key, 2), 4);
      @list($xname, $index, $xkey) = $parts;
      $xpath = @$list[$xname];
      if(!$xpath) continue;
      
      if(count($parts)==1) {
        list($priority, $actions) = explode(' ', $value, 2);
        $serve = (strncmp($xpath, 'phar://', 7)===0) ? '/' . basename(__FILE__): '';
        $extdir = basename(dirname($xpath)); // may have -tag suffix
        
        $x[$xname] = [
          'xPriority'=>intval($priority), 
          'xAction'=>$actions, 
          '=path' => $xpath,
          '=dir' => dirname($xpath),
          '=url' => "{\$ExtPubDirUrl}$serve/$extdir",
          '=conf' => [],
        ];
        continue;
      }
      
      if(count($parts)==2) {
        list($enabled, $patterns) = explode(' ', $value, 2);
        $x[$xname]['=conf'][$index] = [ 
          'xEnabled' => intval($enabled),
          'xNamePatterns'=> $patterns,
        ];
        continue;
      }
      # else count($parts)==3
      
      if(substr($xkey, -1) == '~') {
        $xkey =  substr($xkey, 0, -1);
        $value = unserialize($value);
      }
      $x[$xname]['=conf'][$index][$xkey] = $value;
    }

    uasort($x, 'extSort');
    $Extensions = $x;
  }
  
  $mode = $args['mode'];
  
  if($mode == 'all') return $Extensions;
  if($mode == 'page') return $page;
  
  if($mode == 'extension') {
    $xname = extCaller();
    if(isset($Active[$xname])) return $Active[$xname];
    return [ '=conf'=>[], ];
  }
  
  if($mode == 'full') {
    $xname = $args['xname'];
    if(isset($Extensions[$xname])) return $Extensions[$xname];
    return [ '=conf'=>[], ];
  }
  
  if($mode == 'put') {
    $Active = array_merge($Active, $args['new']);
  }
  
  if($mode == 'active') {
    return $Active;
  }
}

function extSort($a, $b) {
  return $a['xPriority'] <=> $b['xPriority'];
}

function extGetIncluded($pagename = '') { # ''=initial
  global $PostConfig, $xHub, $action;
  
  $x = extGetConfig('all');
  
  $merged = [];
  
  $list = [];
  
  foreach($x as $xname=>$conf) {
    if(@$conf['xAction'] && !MatchNames($action, $conf['xAction'], false)) continue;
    
    if($pagename==='' && $conf['xPriority']<=100) { # initial
      $my = @$conf['=conf'][0];
      if(!isset($my) || !$my['xEnabled']!=1)
      $list[ $conf['=path'] ] = $conf['xPriority'];
      $merged[$xname] = @array_merge($x[$xname], (array)$conf['=conf'][0]);
      
    }
    elseif($pagename !== '' && $conf['xPriority']>100) {
      foreach($conf['=conf'] as $a) {
        if (!$a['xEnabled']) continue;
        $pat = $a['xNamePatterns'];
        if($pat === '*' || $pat === '*.*' || MatchNames($pagename, $pat)) {
          $priority = $conf['xPriority']<=200
            ? 1+$conf['xPriority']/100
            : 51+$conf['xPriority']/200;
          
          $PostConfig[ $conf['=path'] ] = $priority;
          $merged[$xname] = array_merge($x[$xname], $a);
        }
      }
    }
  }
  extGetConfig(['mode'=>'put', 'new'=>$merged]);
  asort($list);
  asort($PostConfig); # required
  return $list;
}

function extInit() {
  global $xHub, $action;
  static $done = 0; if($done++) return [];
  
  $xHub["ConfigStore"] = new PageStore("{$xHub['DataDir']}/{\$FullName}");
  
  extScanDir();
  
  if($action == 'recipecheck') {
    FmtExtList('', '', ['onlyRecipeInfo'=>1]);
  }
  return extGetIncluded('');
}

function extSaveConfig($pagename, $xname, $index) {
  global $xHub, $EnableExtSaved, $MessagesFmt;
  if(!@$_POST['xPost'] && !@$_POST['xReset'] || !$xname) return;
  if(! pmtoken(1)) {
    $MessagesFmt[] = XL('Token invalid or missing');
    return;
  }
  
  $keys = preg_grep('/^(n|action|pmtoken|i|x|x[A-Z]\\w*)$/', array_keys($_POST), PREG_GREP_INVERT);
  
  $priority = intval(@$_POST['xPriority']);
  if(!$priority) $priority = 150;
  
  $xaction = $_POST['xAction'] ?? '*';
  
  $enabled = intval(@$_POST['xEnabled']);
  $_POST['xEnabled'] = $enabled;
  
  $patterns = strval($_POST['xNamePatterns']);
  if($patterns === '') $patterns = '*';
  
  $page = $old = extGetConfig('page');
  
  if(@$_POST['xReset']) {
    $kpat = "/^x\\.$xname\\.$index($|\\.)/";
    $deletedkeys = preg_grep($kpat, array_keys($page));
    
    foreach($deletedkeys as $key) {
      unset($page[$key]);
    }
  }
  else {
    $prefix = "x.$xname";
    
    $page[$prefix] = "$priority $xaction";
    $page["$prefix.$index"] = "$enabled $patterns";
  
    foreach($keys as $k) {
      $v = $_POST[$k];
      if(is_numeric($v)) $v = floatval($v);
      elseif(is_array($v)) {
        $k .= "~";
        $v = serialize($v);
      }
      $xkey = "$prefix.$index.$k";
      
      if($v === '') unset($page[$xkey]);
      else $page[$xkey] = $v;
    }
  }
  
  if($page != $old) {
    $xHub['ConfigStore']->write($xHub['DataPageName'], $page);
    Redirect($pagename, "{\$PageUrl}?action=hub&x=$xname&i=$index&saved=1");
  }
  
  if(@$_POST['xReset']) {
    Redirect($pagename, '{*$PageUrl}?action=hub&deleted=1');
    exit;
  }
}

function HandleHub($pagename, $auth='admin') {
  global $xHub, $FmtPV, $WikiLibDirs, $PageStartFmt, $PageEndFmt,
    $EnableExtDeleted, $EnableExtSaved, $EnableExtConfig, $EnableExtPgCust, 
    $InputValues, $HTMLStylesFmt, $CurrentExtension;
    
  $HTMLStylesFmt['hub-form'] = '.wikiexthub input:checked + label { font-weight: bold;}';
    
  $page = RetrieveAuthPage($pagename, $auth, true, READPAGE_CURRENT);
  if(!$page) return Abort('?No permissions');
  
  $paths = $xHub['ExtPaths'];
  
  $index = intval(@$_REQUEST['i']);
  $xname = @$_REQUEST['x'];
  
  if(@$_REQUEST['deleted']) $EnableExtDeleted = 1;
  
  if($xname && isset($paths[$xname])) {
    extSaveConfig($pagename, $xname, $index);
    
    if(@$_REQUEST['saved']) $EnableExtSaved = 1;
    
    $EnableExtConfig = 1;
    
    $CurrentExtension = $xname;
    $FmtPV['$xName'] = '$GLOBALS["CurrentExtension"]';
    $FmtPV['$xIndex'] = $index;
    $FmtPV['$xVersion'] = 'extGetVersion($GLOBALS["CurrentExtension"])';
    
    $extconf = extGetConfig(['mode' => 'full', 'xname'=>$xname]);
    
    $currentconf = $extconf['=conf'][$index] ?? [];
    
    if(!$index && !$currentconf) $currentconf['xNamePatterns'] = '*';
    
    foreach($currentconf as $k => $v) {
      if(isset($_POST[$k])) continue;
      if (is_array($v)) {
        foreach($v as $vk=>$vv) {
          if (is_numeric($vk)) $InputValues["{$k}[]"][] = PHSC($vv, ENT_NOQUOTES);
          else $InputValues["{$k}[{$vk}]"] = PHSC($vv, ENT_NOQUOTES);
        }
      }
      else {
        $InputValues[$k] = PHSC($v, ENT_NOQUOTES);
      }
    }
    
    $priority = $extconf['xPriority'] ?? 150;
    
    $EnableExtPgCust = $priority <= 100? 0:1;
    
    $wlpath = preg_replace('!/[^/]+$!', '', $paths[$xname]) . "/wikilib.d";
    if($xname!='PmX' && file_exists($wlpath)) {
      $WikiLibDirs[] = new PageStore("$wlpath/{\$FullName}");
    }
  }
  
  SDV($xHub['ActionFmt'], [ &$PageStartFmt, '<div class="wikiexthub">', 
    $xHub['Template'], '</div>', &$PageEndFmt ]);

  PrintFmt($pagename, $xHub['ActionFmt']);
}

# Parse extension scripts to get their RecipeInfo versions
function extGetVersion($xname) {
  global $xHub;
  static $versions = [];
  if(isset($versions[$xname])) return $versions[$xname];
  $path = $xHub['ExtPaths'][$xname];
  $f = file_get_contents($path);
  if(preg_match('!\\$RecipeInfo\\[.*?\\]\\[.*?\\] *= *([\'"])(.+?)\\1!', $f, $m)) {
    $version = $m[2];
  }
  else {
    $mtime = filemtime($path);
    $version = PSFT('%F', $mtime) . '^';
  }
  $versions[$xname] = $version;
  return $version;
}

function FmtExtList($pagename, $d, $args) {
  global $xHub, $RecipeInfo;
  $paths = $xHub['ExtPaths'];
  ksort($paths);
  
  if(!count($paths)) {
    $out = "$[No extensions currently available.]";
    return $out;
  }
  
  $out  = "|| class='simpletable sortable filterable' \n";
  $out .= "||! $[Extension] ||! $[Version] ||! $[Priority] "
    . "||! $[Actions] ||! $[Configurations] ||\n";
  
  foreach($paths as $xname=>$path) {
    $version = extGetVersion($xname);
    if(@$args['onlyRecipeInfo']) {
      SDV($RecipeInfo[$xname]['Version'], $version);
      continue;
    }

    $conf = extGetConfig(['mode' => 'full', 'xname'=>$xname]);
    $compressed = (strncmp('phar://', $path, 7)===0)? '*' : '';
    
    $select = "(:input form '{*\$PageUrl}' get:)";
    $select .= "(:input hidden n $pagename:)(:input hidden action hub:)";
    $select .= "(:input hidden x $xname:)";
    $j=0;
    foreach($conf['=conf'] as $i=>$a) {
      # circle emojis: green=&#128994 white=&#9898; red=&#128308;;
      $icon = $a['xEnabled']? "&#128994;" : "&#9898;";
      
      $select .= "(:input select i $i \"$icon {$a['xNamePatterns']}\":)";
      $j = $i+1;
    }
    if(!isset($conf['xPriority']) || $conf['xPriority']>100 || !$j)
      $select .= "(:input select i $j \"&#9898; $[New configuration]\":)";
    
    $select .= "[==] (:input submit '' \"$[Edit]\":)";
    $select .= "(:input end:)";
    
    $out .= @"||'''$xname'''$compressed || $version || {$conf['xPriority']} "
      . @"|| {$conf['xAction']} || $select ||\n";
  }
  
  return $out;
}

function extServe() {

  $ServeExts = array(
    'css' => 'text/css', 'js'=>'application/javascript', 'json'=>'application/json',
    'ttf' => 'font/ttf', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
    'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'apng' => 'image/apng', 'ico' => 'image/x-icon',
    'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'svgz' => 'image/svg+xml',
  );
  $ExtRx = "/\\.(".implode('|', array_keys($ServeExts)).")$/i";

  $pi = strval(@$_SERVER['PATH_INFO']);
  $pi = preg_replace('!\\.+/+!', '/', $pi);
  $pi = preg_replace('!^/+|[^-\\w./]+!', '', $pi);

  if(! $pi) {
    extReject("Bad Request");
  }

  if(!preg_match($ExtRx, $pi, $m)) {
    extReject("Unsupported file type");
  }
  $contenttype = $ServeExts[strtolower($m[1])];

  list($basename, $path) = explode('/', $pi, 2);
  $fname = "$basename.zip";

  if(!file_exists($fname)) {
    extReject("File not found", 404);
  }

  # Let the browser cache the compressed resource for 1 hour.
  # (Most actual visits are 2-3 minutes.)
  header("Cache-Control: max-age=3600, must-revalidate");

  $filelastmod = gmdate('D, d M Y H:i:s \G\M\T', filemtime($fname));
  if (@$_SERVER['HTTP_IF_MODIFIED_SINCE'] == $filelastmod) extReject('', 304); 
  header("Last-Modified: $filelastmod");

  $xpath = "phar://$fname/$basename/$path";
  if(!file_exists($xpath)) {
    extReject("File not found", 404);
  }
  header("Content-type: $contenttype");

  $filesize = filesize($xpath);
  header("Content-Length: $filesize");

  @readfile($xpath);

  exit;
}

function extReject($msg, $code = 400) {
  http_response_code($code);
  if($msg) echo $msg;
  exit;
}
