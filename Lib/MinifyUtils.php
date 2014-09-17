<?php

class MinifyUtils {

  public static $webroot = null;

  static public function getPath($url) {
    $parse = parse_url($url);
    $url = $parse['path'];

    $pathSegments = explode('.', $url);
    $ext = array_pop($pathSegments);

    $url = implode('.', $pathSegments);

    $parts = explode('/', $url);
    $parts = Hash::filter($parts);

    $route = Router::parse($url);

    // Thème
    if (isset($route['controller']) && $route['controller'] === 'theme') {
      $parts = array_merge(array('View', 'Themed', $route['action'], 'webroot'), $route['pass']);
    }

    // Plugin
    elseif (isset($route['plugin']) && $route['plugin'] != '') {
      foreach ($parts as $k => $v) {
        if ($v == $route['plugin']) {
          unset($parts[$k]);
          break;
        }
        unset($parts[$k]);
      }
    }

    $endPath = implode(DS, $parts);

    if (isset($route['controller']) && $route['controller'] === 'theme') {
      $basePath = APP;
    } else if (isset($route['plugin']) && $route['plugin'] != '') {
      $basePath = CakePlugin::path(Inflector::camelize($route['plugin'])) . WEBROOT_DIR . DS;
    } else {
      $basePath = APP . WEBROOT_DIR . DS;
    }

    // Remove APP_DIR path
    $basePath = str_replace(APP, '', $basePath);

    return $basePath . $endPath . '.' . $ext;
  }

  /**
   * string compress_html(string $html)
   */
  public static function compress_html($html) {
    static $find = array(
    // strip comment except ie comment
    'html_comments' => '`\<\!--.*--\>`Use',
    // strip js comments
    'js_comments' => '`/\*.*\*/`Us',
    // strip js / css comments, preserve CDATA
    // and DOCTYPE but does not removes : code; // comment
    'slashed_comments' => '`^\s*//(?!(\s*\<\!\[CDATA\[|\s*\]\]\>)).*$`m',
    // strip any space in the begining of a line
    'ws_start' => '`^[\s]+`m',
    // special case : var followed by a newline.
    // we in such case replace it with var followed by a ws followed by the next line
    'ws_var' => "`([\s]*)var[\s]*[\r\n]+`",
    // strip spaces and tabs at the end of each lines
    'ws_end' => '`[ \t]+$`m',
    // strip LFs
    'ws_eol' => '`[\r\n]+`',
    // replace remaining multiple spaces to single ones
    'ws_multi' => '`[\s]+`',
    // fix CDATA (need to put back the LFs)
    'fix_cdata' => '`(// \<\!\[CDATA\[)`',
    // strip js / css spaces around : & =
    'ws_eq' => '`\s*(:|=)\s*(\{|\'|\"|\[0-9]+)`',
    ), $find_with_ws;
    static $replace = array(
    'html_comments' => "self::_html_comment('$0')",
    'js_comments' => '',
    'slashed_comments' => '',
    'ws_start' => '',
    'ws_var' => "$1var ",
    'ws_end' => '',
    'ws_eol' => '',
    'ws_multi' => ' ',
    'fix_cdata' => "$1\n",
    'ws_eq' => "$1$2"
    ), $replace_with_ws;
    if (strpos($html, '@XPRESERVEWS@') !== false) {
      if (!isset($find_no_ws)) {
        $find_with_ws = $find;
        $replace_with_ws = $replace;
        unset($find_with_ws['ws_start'], $find_with_ws['ws_var'], $find_with_ws['ws_end'], $find_with_ws['ws_multi'], $find_with_ws['ws_eol'], $find_with_ws['ws_eq']);
        unset($replace_with_ws['ws_start'], $replace_with_ws['ws_var'], $replace_with_ws['ws_end'], $replace_with_ws['ws_multi'], $replace_with_ws['ws_eol'], $replace_with_ws['ws_eq']);
      }
      return preg_replace($find_with_ws, $replace_with_ws, $html);
    }
    return preg_replace($find, $replace, $html);
  }

  /**
   * string _html_comment(string $comment)
   */
  private static function _html_comment($comment) {
    return (0 === strpos($comment, '<!--[if') || false !== strpos($comment, '<![endif]-->')) ? str_replace('\\"', '"', $comment) : '';
  }

  static function cssAbsoluteUrl($abs, $content) {
    $images = array();

    $parse = parse_url($abs);
    $abs = $parse['path'];

    if (preg_match('`(\.\w{1,3}|/)$`', $abs))
      $abs = dirname($abs);

    if (self::$webroot === null) {
      self::$webroot = Router::url('/');
    }

    $baseUrl = self::$webroot . 'css_minified/';
    $assetPath = Configure::read('App.www_root').'css_minified';

    preg_match_all('`url\((\'|\")?(?!data:image)((\w|\.|\/)+[^\)]+[^\'\"])(\'|\")?\)`', $content, $grep);
    $pattern = array();
    $replace = array();

    foreach ($grep[2] as $k => $relative) {
      $filePath = realpath(APP . MinifyUtils::getPath($abs . DS . $relative));

      /*
       * Si il n'a pas déjà été traité, on copie ce fichier vers /webroot/css_minified
       * avec un nom MD5 qui va bien puis on remplace l'url dans la CSS par ce fichier racine
       */
      if ($filePath !== false) {
        // Ajoute le pattern de recherche pour remplacement
        $pattern[] = '`' . preg_quote($grep[0][$k]) . '`';
        $md5name = md5($filePath);
        if (!isset($images[$md5name])) {
          $fileInfo = MinifyUtils::getFileInfo($filePath, $assetPath);

          // fichier destination n'existe pas
          if (!file_exists($fileInfo['destfile'])) {
            // on le copie
            copy($fileInfo['srcfile'], $fileInfo['destfile']);
          }
          $images[$fileInfo['md5name']] = $fileInfo;
        }
        $replace[] = 'url("' . $baseUrl . $images[$md5name]['destname'] . '")';
      }
    }

    return preg_replace($pattern, $replace, $content);
  }

  static function getFileInfo($file, $dest) {
    if (!file_exists($file))
      return false;

    if (!is_dir($dest))
      mkdir($dest, 0777, true);

    $pathinfo = pathinfo($file);
    $md5File = md5_file($file);
    $fileName = $pathinfo['filename'] . '_' . $md5File . '.' . $pathinfo['extension'];

    $dest = realpath($dest);
    $destFile = $dest . DS . $fileName;

    $ary = array(
      'md5name' => md5($file),
      'md5file' => $md5File,
      'srcfile' => $file,
      'destname' => $fileName,
      'destfile' => $destFile,
    );
    return $ary;
  }

  static function compressCss($content) {
    $regex = array(
      'find' => array(
        '`/\*.*\*/`Us', // remove all coments, no css hacks
        '`[\s]+`', // all multi white spaces => single
        '`\s*(\{|\})\s*`', // remove ws surrounding { & }
        '`(\:|\;|\,)\s+`', // remove ws after : ; ,
        '`}\s+\.`', // remove ws between } & .
        '`;}`', // remove last ; in {}
        '`[^{}]+{[\s]*}`U', // remove empty ruleset
        '`([^=])#([a-f\\d]+)\2([a-f\\d]+)\3([a-f\\d]+)\4([\s;\}]+)`i', // minimize hex colors
        '`:([a-z]+)-([a-z]+)\{`i', // prevent triggering IE6 bug: http://www.crankygeek.com/ie6pebug/
      ),
      'replace' => array(
        '',
        ' ',
        '\1',
        '\1',
        '}.',
        '}',
        '',
        '\1#\2\3\4\5',
        ':\1-\2 {'),
    );
    return preg_replace($regex['find'], $regex['replace'], $content);
  }

}