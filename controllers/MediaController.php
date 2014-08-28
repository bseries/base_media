<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013-2014 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace cms_media\controllers;

use cms_media\models\Media;
use cms_social\models\Vimeo;
use lithium\core\Libraries;
use temporary\Manager as Temporary;
use lithium\analysis\Logger;
use li3_flash_message\extensions\storage\FlashMessage;
use jsend\Response as JSendResponse;
use Exception;
use cms_core\extensions\net\http\InternalServerErrorException;
use lithium\g11n\Message;

class MediaController extends \cms_core\controllers\BaseController {

	protected $_model = '\cms_media\models\Media';

	use \cms_core\controllers\AdminEditTrait;

	public function admin_api_view() {
		$item = Media::find('first', ['conditions' => ['id' => $this->request->id]]);
		$file = $this->_export($item);

		$response = new JSendResponse('success', compact('file'));

		$this->render(array(
			'type' => 'json',
			'data' => $response->to('array')
		));
	}

	public function admin_api_index() {
		$media = Media::find('all', [
			'order' => ['created' => 'DESC']
		]);

		$files = [];
		foreach ($media as $item) {
			$files[] = $this->_export($item);
		}
		$response = new JSendResponse('success', compact('files'));

		$this->render(array(
			'type' => 'json',
			'data' => $response->to('array')
		));
	}

	// Retrieve information of transfer without actually downloading the entity.
	public function admin_api_transfer_meta() {
		try {
			list($source, $title) = $this->_handleTransferRequest();
		} catch (Exception $e) {
			$response = new JSendResponse('error', $e->getMessage());

			return $this->render(array(
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			));
		}

		$item = Media::create([
			'url' => $source,
			'title' => $title
		]);
		$file = [
			'size' => $item->size(),
			'title' => $item->title
		];
		$response = new JSendResponse('success', compact('file'));

		$this->render(array(
			'type' => 'json',
			'data' => $response->to('array')
		));
	}

	// Same as api_transfer but without storing the result permanently plus
	// running additional heuristic checks to determine if media can be
	// processed.
	public function admin_api_transfer_preflight() {
		$file = [];
		$this->render(array('type' => 'json', 'data' => compact('file')));
	}

	public function admin_api_transfer() {
		try {
			list($source, $title) = $this->_handleTransferRequest();
		} catch (Exception $e) {
			$response = new JSendResponse('error', $e->getMessage());

			return $this->render(array(
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			));
		}

		$file = Media::create([
			'url' => $source,
			'title' => $title
		]);
		if ($file->can('download')) {
			$file->url = $file->download();
		}
		if ($file->can('transfer')) {
			$file->url = $file->transfer();
		}

		try {
			$file->save();
			$file->makeVersions();
		} catch (Exception $e) {
			$response = new JSendResponse('error', $e->getMessage());

			return $this->render(array(
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			));
		}

		$file = $this->_export($file);
		$response = new JSendResponse('success', compact('file'));

		$this->render(array(
			'type' => 'json',
			'data' => $response->to('array')
		));
	}

	protected function _export($item) {
		$result = $item->data();

		$scheme = $this->request->is('ssl') ? 'https' : 'http';

		if ($versions = $item->versions()) {
			foreach ($versions as $name => $version) {
				try {
					$result['versions'][$name] = [
						'url' => $version->url($scheme)
					];
				} catch (Exception $e) {
					Logger::notice("Failed to export media version {$version->id}.");
				}
			}
		}
		return $result;
	}

	// @fixme Use Transfer handlers.
	protected function _handleTransferRequest() {
		Logger::write('debug', 'Handling transfer request.');
		extract(Message::aliases());

		if (!empty($this->request->data['url'])) {
			$source = $this->request->data['url'];
			$title = basename($source);
		} elseif (!empty($this->request->data['vimeo_id'])) {
			$source = 'vimeo://' . $this->request->data['vimeo_id'];
			if (!$item = Vimeo::first($this->request->data['vimeo_id'])) {
				throw new Exception($t('Could not find vimeo video.'));
			}
			$title = $item->title;
		} elseif (!empty($this->request->data['form']['tmp_name'])) {
			$source = 'file://' . $this->request->data['form']['tmp_name'];
			$title = $this->request->data['form']['name'];
		} else {
			if (!is_resource($this->request->data)) {
				throw new Exception('Received invalid data.');
			}
			$temporary = 'file://' . Temporary::file(['context' => 'upload']);

			file_put_contents($temporary, $this->request->data);

			$source = $temporary;
			$title = $this->request->query['title'];
		}
		return [$source, $title];
	}

	public function admin_index() {
		// Handle transfer via URL or form uplaod.
		if ($this->request->data) {
			list($source, $title) = $this->_handleTransferRequest();

			$file = Media::create([
				'url' => $source,
				'title' => $title,
				// deliberately not passing extension as a hint as we want to
				// rely on detecting the MIME type by contents of the file
				// only.
			]);

			if ($file->can('download')) {
				$file->url = $file->download();
			}
			if ($file->can('transfer')) {
				$file->url = $file->transfer();
			}

			$file->save();
			$file->makeVersions();

			return $this->redirect(['action' => 'index', 'library' => 'cms_media']);
		}
		$data = Media::find('all', ['order' => ['modified' => 'DESC']]);
		return compact('data');
	}

	public function admin_delete() {
		$item = Media::find($this->request->id);

		$item->delete();
		$item->deleteVersions();

		FlashMessage::write('Successfully deleted.', ['level' => 'success']);

		$this->redirect(['action' => 'index', 'library' => 'cms_media']);
	}

	public function admin_regenerate_versions() {
		set_time_limit(60 * 5);
		Media::regenerateVersions();

		$this->redirect(['action' => 'index', 'library' => 'cms_media']);
	}
}

?>