<?php

/**
 * Description de buildController
 *
 * @author Erwane Breton
 */
App::uses('File', 'Utility');
App::uses('Folder', 'Utility');
App::uses('MinifyUtils', 'AssetMinify.Lib');
App::import('Vendor', 'AssetMinify.JSMinPlus');

class minifyController extends AssetMinifyAppController {

  private $cssMinifiedDir = null;
  private $cssCanMinify = true;
  private $jsMinifiedDir = null;
  private $jsCanMinify = true;

  public function __construct($request = null, $response = null) {

    // Vérification que les dossiers "minified" soient en 0777
    // JS
    $this->jsMinifiedDir = new Folder(CACHE.'js', true, 0755);
    if ($this->jsMinifiedDir == false || !is_writable($this->jsMinifiedDir->path)) {
      $this->jsCanMinify = false;
      $this->error("Unable to create JS minified Dir or Js Minified dir is not writable. Check perms");
    }

    // Css
    $this->cssMinifiedDir = new Folder(CACHE.'css', true, 0755);
    if ($this->cssMinifiedDir == false || !is_writable($this->cssMinifiedDir->path)) {
      $this->cssCanMinify = false;
      $this->error("Unable to create Css minified Dir or Css Minified dir is not writable. Check perms");
    }

    parent::__construct($request, $response);
  }

  /*
   * Return the right CSS. If no prebuilded file exists, return
   * a fast compressed CSS
   */

  public function css() {
    $this->autoRender = false;
    $cacheFull = CACHE . 'css' . DS . 'full_' . $this->request->params['id'] . '.css';
    if (Configure::read('debug') == 0 && file_exists($cacheFull)) {
      $stat = stat($cacheFull);
      // Envoi le résultat CSS
      $this->_send('css', $stat[9], 86400 * 30, md5('full_' . $this->request->params['id']), file_get_contents($cacheFull));
    } else {
      // On a pas de fichier de cache rapide, on concatène tout et on l'écrit
      $iniFile = new File(CACHE . 'css' . DS . 'config_' . $this->request->params['id'] . '.ini');
      $parse = parse_ini_file($iniFile->path, true);

      $concat = '';
      foreach ($parse as $data) {
        if (!file_exists(APP . $data['file']))
          continue;

        // Concaténation rapide de tous les fichiers
        $concat .= MinifyUtils::cssAbsoluteUrl($data['url'], file_get_contents(APP . $data['file'])) . PHP_EOL;
      }
      // Envoi le résultat CSS
      $this->_send('css', time(), 300, md5('concat_' . $this->request->params['id']), $concat);
    }
  }

  /*
   * Return the right JS. If no prebuilded file exists, return
   * a fast compressed JS
   */

  public function js() {
    $this->autoRender = false;

    $cacheFull = CACHE . 'js' . DS . 'full_' . $this->request->params['id'] . '.js';

    // On a une version en cache ?
    if (Configure::read('debug') == 0 && file_exists($cacheFull)) {
      // Envoi le résultat JS
      $stat = stat($cacheFull);
      $this->_send('js', $stat[9], 86400 * 30, md5('full_' . $this->request->params['id']), file_get_contents($cacheFull));
    } else {
      // On a pas de fichier de cache rapide, on concatène tout et on l'écrit
      $iniFile = new File(CACHE . 'js' . DS . 'config_' . $this->request->params['id'] . '.ini');
      $parse = parse_ini_file($iniFile->path, true);

      $concat = '';
      foreach ($parse as $data) {
        if (!file_exists(APP . $data['file'])) {
          continue;
        }
        // Concaténation rapide de tous les fichiers
        $concat .= file_get_contents(APP . $data['file']) . ';' . PHP_EOL . PHP_EOL;
      }
      $this->_send('js', time(), 300, md5('concat_' . $this->request->params['id']), $concat);
    }
  }

  /*
   * Build Js
   */

  private function _buildJs() {
    if ($this->jsMinifiedDir->path !== null) {
      foreach ($this->jsMinifiedDir->find('config_.*.ini') as $file) {
        preg_match('`^config_(.*)\.ini$`', $file, $grep);
        $file = new File($this->jsMinifiedDir->pwd() . DS . $file);
        $ini = parse_ini_file($file->path, true);

        $fileFull = new File($this->jsMinifiedDir->path . DS . 'full_' . $grep[1] . '.js', true, 0644);
        $contentFull = '';
        foreach ($ini as $data) {
          // On a pas de version minifié
          if (!$fileMin = $this->jsMinifiedDir->find('file_' . md5($data['url'] . $data['md5']) . '.js')) {
            $fileMin = new File($this->jsMinifiedDir->path . DS . 'file_' . md5($data['url'] . $data['md5']) . '.js', true, 0644);
            $fileMin->write(JSMinPlus::minify(file_get_contents(APP . $data['file'])));
          } else {
            $fileMin = new File($this->jsMinifiedDir->path . DS . 'file_' . md5($data['url'] . $data['md5']) . '.js');
          }
          $contentFull .= $fileMin->read() . ';' . PHP_EOL;
        }
        // version full
        $fileFull->write($contentFull);
        $fileFull->close();
      }
    }
  }

  /*
   * Build CSS
   */

  private function _buildCss() {
    //
    if ($this->cssMinifiedDir->path !== null) {
      foreach ($this->cssMinifiedDir->find('config_.*.ini') as $file) {
        preg_match('`^config_(.*)\.ini$`', $file, $grep);
        $file = new File($this->cssMinifiedDir->pwd() . DS . $file);
        $ini = parse_ini_file($file->path, true);

        $fileFull = new File($this->cssMinifiedDir->path . DS . 'full_' . $grep[1] . '.css', true, 0644);
        $fileGz = new File($this->cssMinifiedDir->path . DS . 'gz_' . $grep[1] . '.css', true, 0644);
        $contentFull = '';
        foreach ($ini as $data) {
          // On a pas de version minifié
          if (!$fileMin = $this->cssMinifiedDir->find('file_' . md5($data['url'] . $data['md5']) . '.css')) {
            $fileMin = new File($this->cssMinifiedDir->path . DS . 'file_' . md5($data['url'] . $data['md5']) . '.css', true, 0644);
            $fileMin->write(MinifyUtils::compressCss(MinifyUtils::cssAbsoluteUrl($data['url'], file_get_contents(APP . $data['file']))));
          } else {
            $fileMin = new File($this->cssMinifiedDir->path . DS . 'file_' . md5($data['url'] . $data['md5']) . '.css');
          }
          $contentFull .= $fileMin->read() . PHP_EOL;
        }
        // version full
        $fileFull->write($contentFull);
        $fileFull->close();

        // compression
        $fileGz->write(gzencode($contentFull, 6));
      }
    }
  }

  public function build() {
    $this->autoRender = false;
    switch ($this->request->type) {
      case 'js' :
        $this->_buildJs();
        break;
      case 'css' :
        $this->_buildCss();
        break;
      case 'all':
        $this->_buildCss();
        $this->_buildJs();
        break;
    }
  }

}