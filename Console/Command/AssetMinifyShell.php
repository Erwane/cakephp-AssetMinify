<?php

App::uses('Router', 'Routing');
App::uses('Shell', 'Console');
App::uses('MinifyUtils', 'AssetMinify.Lib');

App::uses('Folder', 'Utility');
config('routes');

/**
 * Asset Compress Shell
 *
 * Assists in clearing and creating the build files this plugin makes.
 *
 * @package AssetCompress
 */
class AssetMinifyShell extends AppShell {

	/**
	 * Create the configuration object used in other classes.
	 *
	 */
	public function startup() {
		parent::startup();
	}

	public function buildAll() {
		$this->buildJs();
		$this->buildCss();
	}

	public function buildJs() {
		App::import('Vendor', 'AssetMinify.JSMinPlus');

		// Ouverture des fichiers de config
	}

	public function buildCss() {
		App::import('Vendor', 'AssetMinify.JSMinPlus');

		// Ouverture des fichiers de config
		$dir = new Folder(Configure::read('App.www_root') . 'css' . DS . 'minified');
		if ($dir->path !== null) {
			foreach ($dir->find('config_.*.ini') as $file) {
				preg_match('`^config_(.*)\.ini$`', $file, $grep);
				$file = new File($dir->pwd() . DS . $file);
				$ini = parse_ini_file($file->path, true);

				$fileFull = new File($dir->path . DS . 'full_' . $grep[1] . '.css', true, 0644);
				$fileGz = new File($dir->path . DS . 'gz_' . $grep[1] . '.css', true, 0644);
				$contentFull = '';
				foreach ($ini as $data) {
					// On a pas de version minifié
					if (!$fileMin = $dir->find('file_' . md5($data['url'] . $data['md5']) . '.css')) {
						$fileMin = new File($dir->path . DS . 'file_' . md5($data['url'] . $data['md5']) . '.css', true, 0644);
						$this->out("Compression de " . $data['file'] . ' ... ', 0);
						$fileMin->write(MinifyUtils::compressCss(MinifyUtils::cssAbsoluteUrl($data['url'], file_get_contents($data['file']))));
						$this->out('OK');
					} else {
						$fileMin = new File($dir->path . DS . 'file_' . md5($data['url'] . $data['md5']) . '.css');
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

}
