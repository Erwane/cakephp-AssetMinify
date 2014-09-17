<?php

App::uses('View', 'View');

class MinifyView extends View {

  public function render($view = null, $layout = null) {
    $content = parent::render($view, $layout);
    if ($this->response->type() != 'text/html') {
      return $content;
    }

    $find = array(
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
      // remove ws after html tag start
      'ws_starttag' => '`\<[\s]+([^>]+/?\>)`U',
      // remove ws before html tag end
      'ws_endtag' => '`(\<[^>]+)[\s]+(/?\>)`U',
      // fix CDATA (need to put back the LFs)
      'fix_cdata' => '`(//\s*\<\!\[CDATA\[)`',
      // strip LFs
      'ws_eol' => '`[\r\n]+`',
      //'ws_eol' => '`[\r\n]+`',
      // replace remaining multiple spaces to single ones
      'ws_multi' => '`\s\s[\s]+`',
      // strip js / css spaces around : & =
      'ws_eq' => '`\s*(:|=)\s*(\{|\'|\"|\[0-9]+)`',
    );

    $replace = array(
      'html_comments' => "self::_html_comment('$0')",
      'js_comments' => '',
      'slashed_comments' => '',
      'ws_start' => '',
      'ws_var' => "$1var ",
      'ws_end' => '',
      'ws_starttag' => '<$1',
      'ws_endtag' => '$1$2',
      'fix_cdata' => "$1\n",
      'ws_eol' => "\n",
      'ws_multi' => " ",
      'ws_eq' => "$1$2"
    );

    if (Configure::read('debug') == 1) {
      if (!isset($find_no_ws)) {
        $find_with_ws = $find;
        $replace_with_ws = $replace;
        unset($find_with_ws['ws_start'], $find_with_ws['ws_var'], $find_with_ws['ws_end'], $find_with_ws['ws_multi'], $find_with_ws['ws_eol'], $find_with_ws['ws_eq']);
        unset($replace_with_ws['ws_start'], $replace_with_ws['ws_var'], $replace_with_ws['ws_end'], $replace_with_ws['ws_multi'], $replace_with_ws['ws_eol'], $replace_with_ws['ws_eq']);
      }
      return preg_replace($find_with_ws, $replace_with_ws, $content);
    } else if (Configure::read('debug') == 2) {
      return $content;
    }

    return preg_replace($find, $replace, $content);
  }

  /**
   * string _html_comment(string $comment)
   */
  private static function _html_comment($comment) {
    return (0 === strpos($comment, '<!--[if') || false !== strpos($comment, '<![endif]-->')) ? str_replace('\\"', '"', $comment) : '';
  }

}