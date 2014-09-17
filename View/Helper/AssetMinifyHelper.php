<?php

App::uses('AppHelper', 'View/Helper');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('MinifyUtils', 'AssetMinify.Lib');

/**
 * AssetCompress Helper.
 *
 * Handle inclusion assets using the AssetCompress features for concatenating and
 * compressing asset files.
 *
 * @package asset_compress.helpers
 */
class AssetMinifyHelper extends AppHelper {

	public $helpers = array('Html');
	private $_scripts = array();
	private $_styles = array();
	private $_urlJs = null;
	private $_urlCss = null;
	private $cssMinifiedDir = null;
	private $cssCanMinify = true;
	private $jsMinifiedDir = null;
	private $jsCanMinify = true;
	private $View = null;

	/**
	 * Constructor -
	 * @return void
	 */
	public function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);

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

		// Plugins ?
		$this->View = $View;
	}

	/*
	 * ajoute les scripts à la liste, dans l'ordre
	 */

	public function addScript($scripts, $block = 0) {
		if (is_array($scripts)) {
			foreach ($scripts as $k => $v) {
				if (is_array($v)) {

				} else {
					$this->_scripts[$block][md5($v)] = array('file' => $v);
				}
			}
		} else {
			$this->_scripts[$block][md5($scripts)] = array('file' => $scripts);
		}
	}

	/*
	 * ajoute les CSS à la liste, dans l'ordre
	 */

	public function addCss($styleSheets) {
		if (is_array($styleSheets)) {
			foreach ($styleSheets as $k => $v) {
				if (is_array($v)) {

				} else {
					$this->_styles[md5($v)] = array('file' => $v);
				}
			}
		} else {
			$this->_styles[md5($styleSheets)] = array('file' => $styleSheets);
		}
	}

	/*
	 * Ecrit le tag Html
	 */

	public function script($block = 0) {
		if ($this->cssCanMinify && (!isset($this->_urlJs[$block]) || empty($this->_urlJs[$block])))
			$this->_buildJsConfig($block);

		if (empty($this->_urlJs[$block]))
			return false;

		return $this->Html->script($this->_urlJs[$block]);
	}

	/*
	 * Ecrit le tag Html
	 */

	public function css() {
		if ($this->cssCanMinify && empty($this->_urlCss))
			$this->_buildCssConfig();

		// Pas d'urlCss ? on écrit des tags normaux pour chaque fichier
		if (empty($this->_urlCss)) {
			$urls = array();
			foreach ($this->_styles as $url) {
				$urls[] = $this->Html->css($url);
			}
			return implode('', $urls);
		} else {
			return $this->Html->css($this->_urlCss);
		}
	}

	private function _buildCssConfig() {
		// collecte des fichiers
		$paths = array();

		foreach ($this->_styles as $md5 => $data) {
			$url = $this->assetUrl($data['file'], array('pathPrefix' => Configure::read('App.cssBaseUrl'), 'ext' => '.css'));
			$url = preg_replace('`^' . $this->request->webroot . '`', '/', $url);
			$file = MinifyUtils::getPath($url);

			if (!file_exists(APP . $file))
				continue;

			$paths[] = array('url' => $url, 'md5' => md5_file(APP . $file), 'file' => preg_replace('`(\\\\|\/)+`', '/', $file));
		}

		$md5 = md5(implode(';', Hash::combine($paths, '{n}.url', array('%s => %s', '{n}.url', '{n}.md5'))));
		$cssIdentity = Configure::read('debug') > 0 ? md5(implode(';', Hash::extract($this->_styles, '{s}.file'))) : $md5;

		// Ecriture du fichier de config
		$iniFile = new File($this->cssMinifiedDir->path . DS . 'config_' . $cssIdentity . '.ini', true, 0600);

		if ($iniFile->read() === '') {
			$iniContent = array('; <?php exit; ?>');
			foreach ($paths as $data) {
				$iniContent[] = '[' . md5($data['url']) . ']';
				$iniContent[] = 'url=' . $data['url'];
				$iniContent[] = 'file=' . $data['file'];
				$iniContent[] = 'md5=' . $data['md5'];
				$iniContent[] = '';
			}
			$iniFile->write(implode(PHP_EOL, $iniContent));
		}

		$this->_urlCss = Router::url(array('plugin' => 'AssetMinify', 'controller' => 'minify', 'action' => 'css', 'id' => $cssIdentity, '?' => Configure::read('debug') > 0 ? $md5 : null), true);
	}

	private function _buildJsConfig($block = 0) {
		// collecte des fichiers
		$paths = array();
		foreach ($this->_scripts[$block] as $md5 => $data) {
			$url = $this->assetUrl($data['file'], array('pathPrefix' => Configure::read('App.jsBaseUrl'), 'ext' => '.js'));
			$url = preg_replace('`^' . $this->request->webroot . '`', '/', $url);
			$file = MinifyUtils::getPath($url);
			if (!file_exists(APP . $file))
				continue;
			$paths[] = array('url' => $url, 'md5' => md5_file(APP . $file), 'file' => preg_replace('`(\\\\|\/)+`', '/', $file));
		}

		$md5 = md5(implode(';', Hash::combine($paths, '{n}.url', array('%s => %s', '{n}.url', '{n}.md5'))));
		$jsIdentity = Configure::read('debug') > 0 ? md5(implode(';', Hash::extract($this->_scripts[$block], '{s}.file'))) : $md5;

		// Ecriture du fichier de config
		$iniFile = new File($this->jsMinifiedDir->path . DS . 'config_' . $jsIdentity . '.ini', true, 0600);

		if ($iniFile->read() === '') {
			$iniContent = array('; <?php exit; ?>');
			foreach ($paths as $data) {
				$iniContent[] = '[' . md5($data['url']) . ']';
				$iniContent[] = 'url=' . $data['url'];
				$iniContent[] = 'file=' . $data['file'];
				$iniContent[] = 'md5=' . $data['md5'];
				$iniContent[] = '';
			}
			$iniFile->write(implode(PHP_EOL, $iniContent));
		}

		$this->_urlJs[$block] = Router::url(array('plugin' => 'AssetMinify', 'controller' => 'minify', 'action' => 'js', 'id' => $jsIdentity, '?' => Configure::read('debug') > 0 ? $md5 : null), true);
	}

	/*
	 * quality :
	 * - cat : no minify, just cat
	 * - quick : remove comment
	 * - full : all in one line
	 */

	private function minifyJs($file, $quality = 'cat', $mtime = 0) {
		if (!file_exists($file))
			return false;

		if ($mtime == 0) {
			$stat = stat($file);
			$mtime = $stat[9];
		}

		// cache existant ?
		$dirCache = new Folder(CACHE.'js', true, 0755);
		if ($dirCache->path === null)
			return false;
	}

	/*
	 * http://bin.cakephp.org/view/526016317
	 *
	 * $pathSegments = explode('.', $url);
	  $ext = array_pop($pathSegments);
	  $parts = explode('/', $url);
	  $assetFile = null;

	  if ($parts[0] === 'theme') {
	  $themeName = $parts[1];
	  unset($parts[0], $parts[1]);
	  $fileFragment = urldecode(implode(DS, $parts));
	  $path = App::themePath($themeName) . 'webroot' . DS;
	  if (file_exists($path . $fileFragment)) {
	  $assetFile = $path . $fileFragment;
	  }
	  } else {
	  $plugin = Inflector::camelize($parts[0]);
	  if (CakePlugin::loaded($plugin)) {
	  unset($parts[0]);
	  $fileFragment = urldecode(implode(DS, $parts));
	  $pluginWebroot = CakePlugin::path($plugin) . 'webroot' . DS;
	  if (file_exists($pluginWebroot . $fileFragment)) {
	  $assetFile = $pluginWebroot . $fileFragment;
	  }
	  }
	  }
	 *
	 */

	private function error($msg) {
		if (Configure::read('debug') > 0) {
			debug($msg);
		} else {
			$this->log($msg, LOG_ERR);
		}
	}

}
