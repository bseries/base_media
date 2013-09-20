<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace cms_media\controllers;

use cms_media\models\Media;
use lithium\core\Libraries;
use temporary\Manager as Temporary;
use lithium\analysis\Logger;

class FilesController extends \lithium\action\Controller {

	public function transfer() {
		// $this->response->headers('Access-Control-Allow-Origin', '*');

		if (!$source = fopen('php://input', 'rb')) {
			throw new InternalServerError();
		}
		$temporary = 'file://' . Temporary::file(['context' => 'upload']);

		file_put_contents($temporary, $source);
		fclose($source);

		$file = Media::create([
			'url' => $temporary,
			'title' => $this->request->query['title']
		]);

		if (parse_url($file->url, PHP_URL_SCHEME) != 'file') {
			$file->url = $file->download();
		}
		$file->url = $file->transfer();

		$file->save();
		$file->makeVersions();

		$file = $this->_export($file);
		$this->render(array('type' => 'json' /* $this->request->accepts()*/, 'data' => compact('file')));
	}

	public function view() {
		$item = Media::find('first', ['conditions' => ['id' => $this->request->id]]);
		$file = $this->_export($item);

		$this->render(array('type' => $this->request->accepts(), 'data' => compact('file')));
	}

	public function index() {
		$media = Media::find('all');

		foreach ($media as $item) {
			$files[] = $this->_export($item);
		}
		$this->render(array('type' => $this->request->accepts(), 'data' => compact('files')));
	}

	protected function _export($item) {
		$result = $item->data();
		$result['url'] = $item->version('fix1')->url('http');

		return $result;
	}

	public function admin_index() {
		// Handle transfer via URL or form uplaod.
		if ($this->request->data) {
			if ($this->request->data['transfer']['url']) {
				$source = $this->request->data['transfer']['url'];
				$title = basename($source);
			} else {
				$source = 'file://' . $this->request->data['transfer']['form']['tmp_name'];
				$title = $this->request->data['transfer']['form']['name'];
			}
			$file = Media::create([
				'url' => $source,
				'title' => $title,
				// deliberately not passing extension as a hint as we want to
				// rely on detecting the MIME type by contents of the file
				// only.
			]);

			if (parse_url($file->url, PHP_URL_SCHEME) != 'file') {
				$file->url = $file->download();
			}
			$file->url = $file->transfer();

			$file->save();
			$file->makeVersions();

			return $this->redirect(['action' => 'index', 'library' => 'cms_media']);
		}
		$data = Media::all();
		return compact('data');
	}

	public function admin_delete() {
		$item = Media::find($this->request->id);

		$item->delete();
		$item->deleteVersions();

		$this->redirect(['action' => 'index', 'library' => 'cms_media']);
	}

	public function admin_edit() {
		$item = Media::find($this->request->id);

		if (!$item) {
			$this->redirect(['action' => 'index', 'library' => 'cms_media']);
		}
		if (($this->request->data) && $item->save($this->request->data)) {
			$this->redirect(['action' => 'index', 'library' => 'cms_media']);
		}
		$this->_render['template'] = 'admin_form';

		return compact('item');
	}

	public function admin_regenerate_versions() {
		$data = Media::all();

		foreach ($data as $item) {
			$item->deleteVersions();
			$item->makeVersions();
		}
		$this->redirect(['action' => 'index', 'library' => 'cms_media']);
	}

	public function preflight() {
	}

	public function reserve() {
		// Return status, long-polling
	}

	public function import() {

	}
}

?>