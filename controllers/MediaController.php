<?php
/**
 * Base Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 */

namespace base_media\controllers;

use Exception;
use lithium\core\Libraries;
use lithium\analysis\Logger;
use lithium\g11n\Message;
use AD\jsend\Response as JSendResponse;
use temporary\Manager as Temporary;
use li3_flash_message\extensions\storage\FlashMessage;

use base_media\models\Media;
use base_media\models\RemoteMedia;
use base_core\extensions\net\http\InternalServerErrorException;

class MediaController extends \base_core\controllers\BaseController {

	protected $_model = '\base_media\models\Media';

	use \base_core\controllers\AdminIndexTrait;
	use \base_core\controllers\AdminEditTrait;

	public function admin_api_view() {
		$item = Media::find('first', ['conditions' => ['id' => $this->request->id]]);

		if (!$item) {
			// Bail out.
		}
		$file = $this->_export($item);

		$response = new JSendResponse('success', compact('file'));

		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	public function admin_api_index() {
		$page = $this->request->page ?: 1;
		$perPage = 20;

		$media = Media::find('all', [
			'page' => $page,
			'limit' => $perPage,
			'order' => ['created' => 'DESC']
		]);

		$files = [];
		foreach ($media as $item) {
			$files[] = $this->_export($item);
		}
		$response = new JSendResponse('success', compact('files') + [
			'meta' => [
				'total' => Media::find('count')
			]
		]);

		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	public function admin_api_search() {
		$page = $this->request->page ?: 1;
		$perPage = 20;
		$q = $this->request->q ?: null;

		list($media, $meta) = Media::search($q, [
			'page' => $page,
			'limit' => $perPage,
			'order' => ['created' => 'DESC']
		]);

		$files = [];
		foreach ($media as $item) {
			$files[] = $this->_export($item);
		}
		$response = new JSendResponse('success', compact('files') + [
			'meta' => [
				'total' => $meta['total']
			]
		]);

		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	public function admin_api_capabilities() {
		$response = new JSendResponse('success', [
			'transfer' => [
				'urlUpload' => true
			]
		]);
		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	// Retrieve information of transfer without actually downloading the entity.
	public function admin_api_transfer_meta() {
		try {
			list($source, $title, $preview) = $this->_handleTransferRequest();
		} catch (Exception $e) {
			$response = new JSendResponse('error', $e->getMessage());

			return $this->render([
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			]);
		}

		$item = Media::create([
			'url' => $source,
			'title' => $title
		]);
		$file = [
			'size' => $item->size(),
			'title' => $item->title,
			'preview' => $preview
		];
		$response = new JSendResponse('success', compact('file'));

		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	// Same as api_transfer but without storing the result permanently plus
	// running additional heuristic checks to determine if media can be
	// processed.
	public function admin_api_transfer_preflight() {
		$file = [];
		$this->render(['type' => 'json', 'data' => compact('file')]);
	}

	public function admin_api_transfer() {
		try {
			list($source, $title) = $this->_handleTransferRequest();
		} catch (Exception $e) {
			$response = new JSendResponse('error', $e->getMessage());

			return $this->render([
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			]);
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

			return $this->render([
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			]);
		}

		$file = $this->_export($file);
		$response = new JSendResponse('success', compact('file'));

		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	protected function _export($item) {
		$result = ['id' => (integer) $item->id] + $item->data() + [
			'depend' => $item->depend('count')
		];

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

	protected function _handleTransferRequest() {
		Logger::write('debug', 'Handling transfer request.');
		extract(Message::aliases());

		if (!empty($this->request->data['url'])) {
			// Will throw an exception itself if URL is no supported.
			$item = RemoteMedia::createFromUrl($this->request->data['url']);

			// Need internal URL format so that registered schemes match.
			$source = $item->url(['internal' => true]);
			$title = $item->title;
			$preview = $item->thumbnailUrl;

		} elseif (!empty($this->request->data['form']['tmp_name'])) {
			$source = 'file://' . $this->request->data['form']['tmp_name'];
			$title = $this->request->data['form']['name'];
			$preview = null;
		} else {
			$stream = fopen('php://input', 'r');
			$temporary = 'file://' . Temporary::file(['context' => 'upload']);

			file_put_contents($temporary, $stream);
			fclose($stream);

			$source = $temporary;
			$title = $this->request->title;
			$preview = null;
		}
		return [$source, $title, $preview];
	}

	public function admin_delete() {
		extract(Message::aliases());
		$item = Media::find($this->request->id);

		$item->delete();
		$item->deleteVersions();

		FlashMessage::write($t('Successfully deleted.', ['scope' => 'base_media']), [
			'level' => 'success'
		]);

		$this->redirect(['action' => 'index', 'library' => 'base_media']);
	}

	public function admin_regenerate_versions() {
		set_time_limit(60 * 5);

		$item = Media::find('first', [
			'conditions' => [
				'id' => $this->request->id
			]
		]);
		$item->deleteVersions();
		$item->makeVersions();

		$this->redirect(['action' => 'index', 'library' => 'base_media']);
	}
}

?>