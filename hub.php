<?php if (!defined('PmWiki')) { extServe(); exit();}
/**
  ExtensionHub: Configuration panel for PmWiki recipes
  Written by (c) Petko Yotov 2023-2025

  This text is written for PmWiki; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 3
  of the License, or (at your option) any later version.
  See pmwiki.php for full details and lack of warranty.
*/

$RecipeInfo['ExtensionHub']['Version'] = '2025-12-14';
SDVA($FmtPV, [
  '$ExtHubVersion'  => '$GLOBALS["RecipeInfo"]["ExtensionHub"]["Version"]',
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
  '=included'=>[],
  'StatusIcons'=>[
    'new' => '&#128993;',      # yellow circle
    'active' => '&#128994;',   # green circle
    'inactive' => '&#128308;', # red circle
  ],
  'EncodeFn' => 'base64_encode',
  'DecodeFn' => 'base64_decode',
  'EnablePriority' => 1,
]);

$Conditions['extension_enabled'] = 'CondExtEnabled($condparm)';

if(!isset($xHub['Pages']['{$SiteAdminGroup}.ExtensionHub']))
  $xHub['Pages']['{$SiteAdminGroup}.ExtensionHub'] = <<<'TEMPLATE'
(:messages:)
(:Summary: Cookbook:ExtensionHub Template used when listing extensions and editing configurations:)
(:if20240205 enabled EnableExtSaved:)
>>frame note<<
$[Configuration saved.] $[Return to] [[{*$FullName}|+]]
>><<
(:if20240205 enabled EnableExtDeleted:)
>>frame note<<
$[Configuration deleted.]
>><<

(:if20240205end:)
(:if20240205 enabled EnableExtConfig:)
!! {*$ExtName} configuration
(:if202402051 exists Site.{*$ExtName}Form:)
(:include Site.{*$ExtName}Form##form:)
(:else202402051:)
>>frame<<
Cookbook:{*$ExtName}
>><<
(:if202402051end:)

(:input form "{*$PageUrl}?action=hub&x={*$ExtName}&i={*$ExtIndex}" method=post:)
(:input default request=1:)
(:input hidden n {*$FullName}:)
(:input hidden action hub:)
(:input pmtoken:)
(:input hidden x:)
(:input hidden i:)
(:input checkbox xEnabled 1 "$[Enable configuration]":)

(:if202402052 enabled EnableExtPgCust:)
$[Applies to pages:]\\
(:input textarea xNamePatterns placeholder=* required=required cols=60 rows={*$NamePatternsRows}:) \\
''$[Glob patterns like @@Group1.*,Group2.*,-*.HomePage@@]''

(:else202402052:)
(:input hidden xName *:)
(:if202402052end:)

(:if202402051 exists Site.{*$ExtName}Form:)
(:include Site.{*$ExtName}Form#form#formend:)

''Leave fields empty to reset to default values.''

(:if202402051end:)
(:xmoveconfig:)(:input submit xPost "$[Save]":) &nbsp; [[{*$FullName}| $[Cancel] ]]

(:input submit xReset "$[Delete configuration]" data-pmconfirm="$[Confirm deletion?]":)

(:input end:)


(:include Site.{*$ExtName}Form#formend:)

(:else20240205:)
>>recipeinfo frame<<
$[Summary]: Configuration panel for PmWiki extensions \\
%hlt php%@@$ExtPubDirUrl@@: {$ExtPubDirUrl}\\
$[Version]: {$ExtHubVersion}\\
$[Cookbook]: [[(Cookbook:)ExtensionHub]]\\
$[Maintainer]: [[https://www.pmwiki.org/petko|Petko]]
>><<

$[Here you can enable and configure your PmWiki extensions.]

>>padding=.5em<<
(:extlist:)
>><<

$[See Cookbook:Extensions to find new extensions.]

(:if20240205end:)
TEMPLATE;

SDVA($xHub['injectFmt'], [
  'css' => "<link rel='stylesheet' href='%s' %s />\n",
  'js' => "<script src='%s' %s></script>\n",
]);

SDVA($MarkupDirectiveFunctions, ['extlist'=>'FmtExtList']);

# This allows us to ship template pages as variables rather than files.
class HubPageStore extends PageStore {
  private $pagetexts = [];
  private $pagetime = 0;
  function __construct() {
    global $xHub, $RecipeInfo, $SiteAdminGroup;
    foreach($xHub['Pages'] as $pnfmt=>$text) {
      $pn = FmtPageName($pnfmt, "$SiteAdminGroup.$SiteAdminGroup");
      $this->pagetexts[$pn] = $text;
    } 
    $date = substr($RecipeInfo['ExtensionHub']['Version'], 0, 10);
    $this->pagetime = strtotime($date) + 12*3600;
  }
  function read($pagename, $since=0) {
    if(!isset($this->pagetexts[$pagename])) return [];
    list($g, $n) = explode('.', $pagename);
    $page = [
      'text' => $this->pagetexts[$pagename],
      'time' => $this->pagetime,
      'title' => $n,
      'passwdattr' => '@lock',
      'passwdedit' => '@lock',
      'passwdread' => '@lock',
    ];
    return $page;
  }
  function write($pagename,$page) {
    Abort('?Not supported.');
  }
  function exists($pagename) {
    return isset($this->pagetexts[$pagename]);
  }
  function delete($pagename) {
    Abort('?Not supported.');
  }
  function ls($pats=NULL) {
    $list = array_keys($this->pagetexts);
    if($pats) $list = MatchPageNames($list, $pats);
    return $list;
  }
}

# Read plain text files (PmWiki source text) as pages.
# These are easier to review from version control
class HubPlainPageStore extends PageStore {
  function read($pagename, $since=0) {
    $pagefile = $this->pagefile($pagename);
    if(!$pagefile || !file_exists($pagefile)) return false;
    
    $page = $this->attr;
    $page['time'] = filemtime($pagefile);
    $page['text'] = file_get_contents($pagefile);
    return $page;
  }
  function write($pagename,$page) {
    Abort('?Not supported.');
  }
  function exists($pagename) {
    $pagefile = $this->pagefile($pagename);
    return file_exists($pagefile);
  }
  function delete($pagename) {
    Abort('?Not supported.');
  }
}


$extInc = extInit();
foreach($extInc as $path=>$priority) {
  include_once($path);
}
unset($extInc, $path, $priority);

function CondExtEnabled($condparm) {
  global $xHub, $action;
  if(!$condparm) {
    if($action == 'hub') {
      $condparm = $_REQUEST['x'];
    }
    else return false;
  }
  $enabled = array_keys($xHub['=included']);
  return (bool)MatchNames($enabled, $condparm);
}

function extInit() {
  global $xHub, $action, $WikiLibDirs, $PostConfig;
  static $done = 0; if($done++) return [];
  $WikiLibDirs[] = new HubPageStore();
  $xHub["ConfigStore"] = new PageStore("{$xHub['DataDir']}/{\$FullName}");
  extScanDir();
  if($action == 'recipecheck') {
    $PostConfig['extRecipeCheck'] = 0.4;
  }
  return extGetIncluded('');
}

function extFarmPubDirUrl() {
  global $ExtPubDirUrl, $FarmPubDirUrl, $PubDirUrl, $xHub;
  if(isset($ExtPubDirUrl)) return $ExtPubDirUrl;
  $extbasedir = basename($xHub['ExtDir']);
  $pub = $FarmPubDirUrl ?? $PubDirUrl;
  return preg_replace('#/[^/]*$#', "/$extbasedir", $pub, 1);
}

function extCaller() {
  global $xHub;
  $extbasedir = preg_quote(strtr($xHub['ExtDir'], '\\', '/'));
  $trace = debug_backtrace();
  for($i=1; $i<count($trace); $i++) {
    $path = strtr($trace[$i]['file'], '\\', '/');
    $xname = basename($path, '.php');
    $qname = preg_quote($xname, '!');
    $rx = "!^(phar://)?                 # ? compressed
      $extbasedir /                     # /extensions path
      ($qname(-\\w[-\\w.]*)?\\.zip / )? # ? compressed name
      $qname(-\\w[-\\w.]*)? /           # extension own directory
      $qname\\.php$                     # script
      !x";
    if(!preg_match($rx, $path, $m)) continue;
    return $xname;
  }
  return false;
}

function extAddWikiPlainDir($path = 'wikiplain.d') {
  global $WikiLibDirs;
  $xname = extCaller();
  if(!$xname) return; # abort?
  $active = extHubGetConfig('active');
  $c = @$active[$xname];
  if(!$c) return;
  $dir = @$c['=dir'];
  if(!$dir) return;

  $path = "$dir/$path/{\$FullName}";
  $WikiLibDirs[$path] = new HubPlainPageStore($path);
}

function extAddWikiLibDir($path = 'wikilib.d') {
  global $WikiLibDirs;
  $xname = extCaller();
  if(!$xname) return; # abort?
  $active = extHubGetConfig('active');
  $c = @$active[$xname];
  if(!$c) return;
  $dir = @$c['=dir'];
  if(!$dir) return;

  $path = "$dir/$path/\$FullName";
  $WikiLibDirs[$path] = new PageStore($path);
}

function extAddHeaderResource($files, $attrs=[]) {
  return extAddResource($files, $attrs, 0);
}

function extAddFooterResource($files, $attrs=[]) {
  return extAddResource($files, $attrs, 1);
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

  $active = extHubGetConfig('active');  
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
  $suffix = '/-(master|main|latest|v?\\d\\d\\d\\d-?\\d\\d-?\\d\\d[a-z]?|v?\\d+(\\.\\d+)*)$/';

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

# May be called from an extension, with default values to be merged
function extGetConfig($default = []) {
  $args = ['mode'=> 'extension'];
  $conf = extHubGetConfig($args);
  $x = explode(' ', '=conf =path xAction xEnabled xNamePatterns xPriority');
  foreach($x as $k) unset($conf[$k]);
  return array_merge($default, $conf);
}

function extHubGetConfig($args = ['mode'=> 'extension']) { 
  # Can be called:
  # - initially to populate the full static array
  # - to populate extGetIncluded
  # - to get extension configuration - full or merged per page
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
      
      if(preg_match('/^(enc_|passwd)/', $xkey)) {
        $cfn = $xHub['DecodeFn'];
        $value = $cfn($value);
      }

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

  $x = extHubGetConfig('all');

  $merged = $list = [];

  foreach($x as $xname=>$conf) {
    if(@$conf['xAction'] && !MatchNames($action, $conf['xAction'], false)) continue;

    if($pagename==='' && $conf['xPriority']<=100) { # initial
      $my = @$conf['=conf'][0];
      if(!isset($my) || !$my['xEnabled']!=1)
      $list[ $conf['=path'] ] = $conf['xPriority'];
      $merged[$xname] = @array_merge($x[$xname], (array)$conf['=conf'][0]);
      $xHub['=included'][$xname] = 1;
    }
    elseif($pagename !== '' && $conf['xPriority']>100) {
      foreach($conf['=conf'] as $a) {
        if (!$a['xEnabled']) continue;
        $pat = preg_replace('/[,\\s]+/', ',', $a['xNamePatterns']);
        if($pat === '*' || $pat === '*.*' || MatchNames($pagename, $pat)) {
          $priority = $conf['xPriority']<=200
            ? 1+$conf['xPriority']/100
            : 51+$conf['xPriority']/200;

          $PostConfig[ $conf['=path'] ] = $priority;
          $merged[$xname] = array_merge($x[$xname], $a);
          $xHub['=included'][$xname] = 1;
          if($xHub['EnablePriority']) break;
        }
      }
    }
  }
  extHubGetConfig(['mode'=>'put', 'new'=>$merged]);
  asort($list);
  asort($PostConfig); # required
  return $list;
}

function extSaveConfig($pagename, $xname, $index) {
  global $xHub, $EnableExtSaved, $MessagesFmt, $RecipeInfo;
  if(!@$_POST['xPost'] && !@$_POST['xReset'] || !$xname) return;
  if(! pmtoken(1)) {
    $MessagesFmt[] = XL('Token invalid or missing');
    return;
  }
  
  $kpat = '/^(n|action|pmtoken|i|x|x[A-Z]\\w*|.*[^a-zA-Z0-9_].*)$/';
  $postedkeys = preg_grep($kpat, array_keys($_POST), PREG_GREP_INVERT);
  
  $priority = intval(@$_POST['xPriority']);
  if(!$priority) $priority = 150;

  $xaction = $_POST['xAction'] ?? '*';
  
  $post = PPRAR(["/\r+/"=>''], $_POST);
  
  $enabled = intval(@$_POST['xEnabled']);
  $_POST['xEnabled'] = $enabled;

  $patterns = strval(@$post['xNamePatterns']);
  if($patterns === '') $patterns = '*';

  $page = $old = extHubGetConfig('page');
  $conf = extHubGetConfig(['mode' => 'full', 'xname'=>$xname]);
  
  $dpat = "/^x\\.$xname\\.\\d+($|\\.)/";
  $deletedkeys = preg_grep($dpat, array_keys($page));

  foreach($deletedkeys as $key) {
    unset($page[$key]);
  }
  
  $prefix = "x.$xname";
  $oldconf = $conf['=conf'];
  $newidx = $index;
  if(@$_POST['xReset']) {
    unset($conf['=conf'][$index], $oldconf[$index]);
  }
  else {
    $xver = extGetVersion($xname);
    $hver = $RecipeInfo['ExtensionHub']['Version'];
    $confconf = ['=curr'=>1, '_xversions'=> "$hver $xver",
      'xEnabled'=>$enabled, 'xNamePatterns'=>$patterns];
    foreach($postedkeys as $k) {
      $confconf[$k] = $post[$k];
    }
    $xMove = $_POST['xMove']??'';
    if($xMove==='') {
      $oldconf[$index] = $confconf;
    }
    else {
      unset($oldconf[$index]);
      
      if($xMove=='end') {
        $oldconf[] = $confconf;
      }
      else {
        $xMove = intval($xMove);
        $i=0; 
        
        $newconf = [];
        foreach($oldconf as $k=>$a) {
          if($k == $xMove) {
            $newconf[] = $confconf;
          }
          $newconf[] = $a;
        }
        $oldconf = $newconf;
      }
    }
  }
  $newconf = array_values($oldconf);
  foreach($newconf as $k=>&$v) {
    if(isset($v['=curr'])) {
      unset($v['=curr']);
      $newidx = $k;
    }
  }
  
  $prefix = "x.$xname";

  $page[$prefix] = "$priority $xaction";
  
  foreach($newconf as $i=>$a) {
    $page["$prefix.$i"] = "{$a['xEnabled']} {$a['xNamePatterns']}";
    foreach($a as $kk=>$vv) {
      if(preg_match('/^x[A-Z]/', $kk)) continue;
      list($pk, $pv) = extStringify($kk, $vv);
      if($vv==='') continue;
      $page["$prefix.$i.$pk"] = $pv;
    }
  }

  if($page !== $old) {
    $page['ExtHubVersion'] = $RecipeInfo['ExtensionHub']['Version'];
    $xHub['ConfigStore']->write($xHub['DataPageName'], $page);
  }
  
  if(@$_POST['xReset']) {
    $rfmt = '{*$PageUrl}?deleted=1';
  }
  else {
    $rfmt = "{\$PageUrl}?action=hub&x=$xname&i=$newidx&saved=1";
  }
  Redirect($pagename, $rfmt);
}

function extStringify($k, $v) {
  global $xHub;
  if(is_numeric($v)) return [$k, floatval($v)];
  if(is_array($v)) return ["$k~", serialize($v)];
  if(preg_match('/^(enc_|passwd)/', $k)) {
    $cfn = $xHub['EncodeFn'];
    return [$k, $cfn($v)];
  }
  return [$k, strval($v)];
}

function HandleHub($pagename, $auth='admin') {
  global $xHub, $FmtPV, $WikiLibDirs, $PageStartFmt, $PageEndFmt,
    $EnableExtDeleted, $EnableExtSaved, $EnableExtConfig, $EnableExtPgCust, 
    $InputValues, $HTMLStylesFmt, $CurrentExtension;

  $page = RetrieveAuthPage($pagename, $auth, true, READPAGE_CURRENT);
  if(!$page) return Abort('?No permissions');

  $HTMLStylesFmt['hub-form'] = '.wikiexthub input:checked + label { font-weight: bold;}';

  $paths = $xHub['ExtPaths'];

  $index = intval(@$_REQUEST['i']);
  $xname = @$_REQUEST['x'];

  if(@$_REQUEST['deleted']) $EnableExtDeleted = 1;

  if($xname && isset($paths[$xname])) {
    extSaveConfig($pagename, $xname, $index);

    if(@$_REQUEST['saved']) $EnableExtSaved = 1;

    $EnableExtConfig = 1;

    $CurrentExtension = $xname;
    $FmtPV['$ExtName'] = '$GLOBALS["CurrentExtension"]';
    $FmtPV['$ExtIndex'] = $index;
    $FmtPV['$ExtVersion'] = 'extGetVersion($GLOBALS["CurrentExtension"])';

    $extconf = extHubGetConfig(['mode' => 'full', 'xname'=>$xname]);
    
    $currentconf = $extconf['=conf'][$index] ?? [];

    if(!$index && !$currentconf) $currentconf['xNamePatterns'] = '*';
    
    if(count($extconf['=conf'])>1 && $xHub['EnablePriority']) {
      $kco = XL("Keep current order");
      $reorder = XL("Place the configuration:");
      $mbefore = XL("Before");
      $mend = XL("At the end");
      $select = "$reorder<br>";
      
      if(isset($extconf['=conf'][$index]))
        $select .= "(:input select xMove \"\" \"$kco\":)";
      else $InputValues['xMove'] = 'end';
      
      foreach($extconf['=conf'] as $k=>$a) {
        $last = $k;
        if($k==$index || $k == $index+1) continue;
        $label = PHSC(preg_replace('/\\s+/', ' ', $a['xNamePatterns']));
        $select .= "(:input select xMove \"$k\" \"$mbefore $label\":)";
      }
      if($last != $index)
        $select .= "(:input select xMove end \"$mend\":)";
      
      $select .= "<br><br>";
      
      Markup('xmvc', '<input-select', '/\\(:xmoveconfig:\\)/', $select);
    }
    else {
      Markup('xmvc', '<input-select', '/\\(:xmoveconfig:\\)/', '');
    }
    
    $nplines = explode("\n", trim(strval(@$currentconf['xNamePatterns'])));
    $FmtPV['$NamePatternsRows'] = min(4, count($nplines)+1);
    
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

    $wlpath = preg_replace('!/[^/]+$!', '', $paths[$xname]);
    if(file_exists("$wlpath/wikiplain.d")) {
      $WikiLibDirs[] = new HubPlainPageStore("$wlpath/wikiplain.d/{\$FullName}");
    }
    elseif(file_exists("$wlpath/wikilib.d")) {
      $WikiLibDirs[] = new PageStore("$wlpath/wikilib.d/{\$FullName}");
    }
  }
  HandleBrowse($pagename);
}

# Parse extension scripts to get their RecipeInfo versions
function extGetVersion($xname) {
  global $xHub;
  static $versions = [];
  if(isset($versions[$xname])) return $versions[$xname];
  $path = $xHub['ExtPaths'][$xname];
  if(!file_exists($path)) return '0000-00-00'; # not extension
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

# Populate extension versions for ?action=recipecheck
function extRecipeCheck(){
  FmtExtList('', '', ['onlyRecipeInfo'=>1]);
}

if(!function_exists('PPRAR')) {
  
  function PPRAR($array, $x) { # Recursive PPRA
    if(is_array($x)) {
      foreach($x as $k=>$v) $y[$k] = PPRAR($array, $v);
      return $y;
    }
    else return PPRA($array, $x);
  }
}

function FmtExtList($pagename, $d, $args) {
  global $HandleAuth, $xHub, $RecipeInfo, $HTMLStylesFmt;
  $page = RetrieveAuthPage($pagename, $HandleAuth['hub'], false, READPAGE_CURRENT);
  if(!$page) return '$[No permissions]';

  $paths = $xHub['ExtPaths'];
  ksort($paths);
  if(!count($paths)) {
    $out = "$[No extensions currently available.]";
    return $out;
  }

  $HTMLStylesFmt['hublist-form'] = 'form.hublistform select  { width: 12em; text-overflow: ellipsis; }';

  $out  = "|| class='simpletable sortable filterable' \n";
  $out .= "||! $[Extension] ||! $[Version] ||! $[Priority] "
    . "||! $[Actions] ||! $[Configurations] ||\n";
  
  $keepz = Keep('');
  
  foreach($paths as $xname=>$path) {
    $version = extGetVersion($xname);
    if(@$args['onlyRecipeInfo']) {
      SDV($RecipeInfo[$xname]['Version'], $version);
      continue;
    }

    $conf = extHubGetConfig(['mode' => 'full', 'xname'=>$xname]);
    $compressed = (strncmp('phar://', $path, 7)===0)? '*' : '';

    $select = "(:input form '{*\$PageUrl}' get class=hublistform:)";
    $select .= "(:input hidden n $pagename:)(:input hidden action hub:)";
    $select .= "(:input hidden x $xname:)";
    $j=0;
    foreach($conf['=conf'] as $i=>$a) {
      $icon = $a['xEnabled']
        ? $xHub['StatusIcons']['active']
        : $xHub['StatusIcons']['inactive'];

      $np = preg_replace('/[,\\s]+/', ',', $a['xNamePatterns']);
      $select .= "(:input select i $i \"$icon $np\":)";
      $j = $i+1;
    }
    if(!isset($conf['xPriority']) || $conf['xPriority']>100 || !$j)
      $select .= "(:input select i $j \"{$xHub['StatusIcons']['new']} $[New configuration]\":)";

    $select .= "$keepz (:input submit '' \"$[Edit]\":)";
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
  $pi = preg_replace('![/\\\\]+!', '/', $pi);
  $pi = preg_replace('!\\.+/+!', '/', $pi);
  $pi = preg_replace('!^/+|[^-\\w./]+!', '', $pi);

  if(! $pi) {
    extReject("Bad request");
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
