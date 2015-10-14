<?php
/**
 * Base Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved.
 *
 * Licensed under the AD General Software License v1.
 *
 * This software is proprietary and confidential. Redistribution
 * not permitted. Unless required by applicable law or agreed to
 * in writing, software distributed on an "AS IS" BASIS, WITHOUT-
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *
 * You should have received a copy of the AD General Software
 * License. If not, see http://atelierdisko.de/licenses.
 */

namespace base_media\controllers;

use AD\jsend\Response as JSendResponse;
use Exception;
use base_core\extensions\cms\Settings;
use base_core\extensions\net\http\InternalServerErrorException;
use base_core\extensions\net\http\NotFoundException;
use base_core\security\Gate;
use base_media\models\Media;
use base_media\models\RemoteMedia;
use li3_flash_message\extensions\storage\FlashMessage;
use lithium\analysis\Logger;
use lithium\core\Libraries;
use lithium\g11n\Message;
use temporary\Manager as Temporary;

class MediaController extends \base_core\controllers\BaseController {

	protected $_model = '\base_media\models\Media';

	use \base_core\controllers\AdminIndexTrait;
	use \base_core\controllers\AdminEditTrait;

	public function admin_api_view() {
		$query = [
			'conditions' => [
				'id' => $this->request->id
			]
		];
		if (Settings::read('security.checkOwner') && !Gate::checkRight('users')) {
			$query['conditions']['owner_id'] = Gate::user(true, 'id');
		}
		$item = Media::find('first', $query);

		if (!$item) {
			throw new NotFoundException();
		}
		$file = $this->_export($item);

		$response = new JSendResponse('success', compact('file'));

		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	// Handles pages index as well as batch view when
	// an array of indexes is POSTed.
	public function admin_api_index() {
		if ($this->request->is('post')) {
			$query = [
				'conditions' => [
					'id' => $this->request->data['ids']
				],
				'order' => ['id' => 'ASC']
			];
		} else {
			$page = $this->request->page ?: 1;
			$perPage = 20;

			$query = [
				'page' => $page,
				'limit' => $perPage,
				'order' => ['created' => 'DESC']
			];
		}
		if (Settings::read('security.checkOwner') && !Gate::checkRight('users')) {
			$query['conditions']['owner_id'] = Gate::user(true, 'id');
		}
		$media = Media::find('all', $query);

		$files = [];
		foreach ($media as $item) {
			$files[$item->id] = $this->_export($item);
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

		$query = [
			'page' => $page,
			'limit' => $perPage,
			'order' => ['created' => 'DESC']
		];
		if (Settings::read('security.checkOwner') && !Gate::checkRight('users')) {
			$query['conditions']['owner_id'] = Gate::user(true, 'id');
		}
		list($media, $meta) = Media::search($q, $query);


		$files = [];
		foreach ($media as $item) {
			$files[$item->id] = $this->_export($item);
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
			$message  = "Exception while handling transfer:\n";
			$message .= "exception: " . ((string) $e);
			Logger::debug($message);

			$response = new JSendResponse('error', 'Error while handling transfer.');

			return $this->render([
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			]);
		}

		$file = Media::create([
			'owner_id' => Gate::user(true, 'id'),
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
			$message  = "Exception while saving transfer:\n";
			$message .= "with media entity: " . var_export($file->data(), true) . "\n";
			$message .= "exception: " . ((string) $e);
			Logger::debug($message);

			$response = new JSendResponse('error', 'Error while saving transfer.');

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

		$query = [
			'conditions' => [
				'id' => $this->request->id
			]
		];
		if (Settings::read('security.checkOwner') && !Gate::checkRight('users')) {
			$query['conditions']['owner_id'] = Gate::user(true, 'id');
		}
		if (!$item = Media::find('first', $query)) {
			throw new NotFoundException();
		}

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