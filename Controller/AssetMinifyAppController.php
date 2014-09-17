<?php

class AssetMinifyAppController extends AppController {

	protected function _send($type, $mtime, $ttl, $etag, $content, $size = 0, $compress = false) {

		switch ($type) {
			case 'css-test':
				if ($compress) {
					header('Content-encoding: gzip');
				}
				if ($size > 0) {
					header('Content-Length: ' . $size);
				}
				$date = new DateTime(date('Y-m-d H:i:s', $mtime));
				$date->setTimeZone(new DateTimeZone('UTC'));
				header('Last-Modified: ' . $date->format('D, j M Y H:i:s') . ' GMT');

				header('Content-Type: text/css; charset=UTF-8');
				header('Cache-Control: public');
				header('Pragma: public');
				header('Vary:	Accept-Encoding');
				$date = new DateTime(date('Y-m-d H:i:s', time() + $ttl));
				$date->setTimeZone(new DateTimeZone('UTC'));
				header('Expires: ' . $date->format('D, j M Y H:i:s') . ' GMT');
				echo $content;
				exit;
				break;

			default :
				if ($compress) {
					$this->response->vary('Accept-Encoding');
					$this->response->header('Content-Encoding: gzip');
				}

				$this->response->type($type);
				$this->response->length(false); // apache s'en occupe
				$this->response->modified($mtime);
				$this->response->expires(time() + $ttl);
				$this->response->etag($etag);
				$this->response->body($content);

				$this->response->send();
				break;
		}

		exit;
	}

}
