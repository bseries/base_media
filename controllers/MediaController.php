<?php
/**
 * Copyright 2013 David Persson. All rights reserved.
 * Copyright 2016 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
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
use base_reference\models\References;
use li3_flash_message\extensions\storage\FlashMessage;
use lithium\analysis\Logger;
use lithium\core\Libraries;
use lithium\g11n\Message;
use temporary\Manager as Temporary;

class MediaController extends \base_core\controllers\BaseController {

	protected $_model = '\base_media\models\Media';

	use \base_core\controllers\AdminIndexTrait;
	use \base_core\controllers\AdminEditTrait;
	use \base_core\controllers\UsersTrait;

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
		// FC: source column
		if (Media::hasField('source')) {
			$query['conditions']['source'] = 'admin';
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
		// FC: source column
		if (Media::hasField('source')) {
			$query['conditions']['source'] = 'admin';
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
				'urlUpload' => true,
				'animatedImages' => true,
				// FIXME Check via Process config, if there's a configuration
				//       for document processing. Process currently does not
				//       allow for config checking.
				'pdfs' => PROJECT_HAS_GHOSTSCRIPT
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
			$message  = "Exception while initially handling media transfer request for meta:\n";
			$message .= "message: " . $e->getMessage() . "\n";
			$message .= "trace:\n" . $e->getTraceAsString();
			Logger::write('notice', $message);

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
		Media::pdo()->beginTransaction();

		$failResponse = function() {
			// Mask error for user.
			$response = new JSendResponse('error', 'Error while saving transfer.');

			return $this->render([
				'status' => 500,
				'type' => 'json',
				'data' => $response->to('array')
			]);
		};

		try {
			list($source, $title) = $this->_handleTransferRequest();
		} catch (Exception $e) {
			$message  = "Exception while initially handling media transfer request:\n";
			$message .= "message: " . $e->getMessage() . "\n";
			$message .= "trace:\n" . $e->getTraceAsString();
			Logger::write('notice', $message);

			Media::pdo()->rollback();
			return $failResponse();
		}

		$file = Media::create([
			'owner_id' => Gate::user(true, 'id'),
			'url' => $source,
			'title' => $title,
			'source' => 'admin'
		]);
		if ($file->can('download')) {
			$file->url = $file->download();
		}
		if ($file->can('transfer')) {
			$file->url = $file->transfer();
		}

		try {
			if (!$file->save()) {
				$message  = "Failed saving file entity for media transfer request:\n";
				$message .= "entity: " . var_export($file->data(), true) . "\n";
				Logger::write('notice', $message);

				$file->deleteUrl();
				Media::pdo()->rollback();
				return $failResponse();
			}
			if (!$file->makeVersions()) {
				$message  = "Failed making versions for media transfer request:\n";
				$message .= "entity: " . var_export($file->data(), true) . "\n";
				Logger::write('notice', $message);

				$file->deleteUrl();
				Media::pdo()->rollback();
				return $failResponse();
			}
		} catch (Exception $e) {
			$message  = "Exception while processing media transfer request:\n";
			$message .= "entity: " . var_export($file->data(), true) . "\n";
			$message .= "message: " . $e->getMessage() . "\n";
			$message .= "trace:\n" . $e->getTraceAsString();
			Logger::write('notice', $message);

			$file->deleteUrl();
			Media::pdo()->rollback();
			return $failResponse();
		}

		Media::pdo()->commit();
		$response = new JSendResponse('success', [
			'file' => $this->_export($file)
		]);

		$this->render([
			'type' => 'json',
			'data' => $response->to('array')
		]);
	}

	protected function _export($item) {
		$result = ['id' => (integer) $item->id] + $item->data() + [
			'depend' => $item->depend('count')
		];

		if ($versions = $item->versions()) {
			foreach ($versions as $name => $version) {
				try {
					$result['versions'][$name] = [
						'url' => $version->url($this->request)
					];
				} catch (Exception $e) {
					Logger::notice("Failed to export media version {$version->id}.");
				}
			}
		}
		return $result;
	}

	protected function _handleTransferRequest() {
		Logger::write('debug', 'Handling media transfer request.');
		extract(Message::aliases());

		if (!empty($this->request->data['url'])) {
			// Will throw an exception itself if URL is no supported.
			$item = RemoteMedia::createFromUrl($this->request->data['url'], $this->request);

			// Need internal URL format so that registered schemes match.
			$source = $item->url(['internal' => true]);
			$title = $item->title;
			$preview = $item->thumbnailUrl;

		} elseif (!empty($this->request->data['form']['tmp_name'])) {
			$source = 'file://' . $this->request->data['form']['tmp_name'];
			$title = $this->request->data['form']['name'];
			$preview = null;
		} else {
			if (!$stream = fopen('php://input', 'r')) {
				$message = 'Failed to open media transfer stream for reading.';
				throw new Exception($message);
			}
			$temporary = 'file://' . Temporary::file(['context' => 'upload']);

			if (!file_put_contents($temporary, $stream)) {
				$message = "Failed to write media transfer stream to temporary file `{$temporary}`.";
				throw new Exception($message);
			}
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
		extract(Message::aliases());
		set_time_limit(60 * 5);

		Media::pdo()->beginTransaction();

		$item = Media::find('first', [
			'conditions' => [
				'id' => $this->request->id
			]
		]);
		if ($item->deleteVersions() && $item->makeVersions()) {
			Media::touchTimestamp($item->id, 'modified');
			Media::pdo()->commit();

			FlashMessage::write($t('Successfully regenerated versions.', ['scope' => 'base_media']), [
				'level' => 'success'
			]);
		} else {
			Media::pdo()->rollback();
			FlashMessage::write($t('Failed to regenerate versions.', ['scope' => 'base_media']), [
				'level' => 'error'
			]);
		}

		$this->redirect(['action' => 'index', 'library' => 'base_media']);
	}

	public function admin_clean() {
		extract(Message::aliases());

		if (!Gate::checkRight('clean')) {
			FlashMessage::write($t('Missing rights for this action.', ['scope' => 'base_media']), [
				'level' => 'error'
			]);
			return $this->redirect($this->request->referer());
		}

		Media::pdo()->beginTransaction();

		if (Media::clean()) {
			Media::pdo()->commit();
			FlashMessage::write($t('Successfully cleaned media.', ['scope' => 'base_media']), [
				'level' => 'success'
			]);
		} else {
			Media::pdo()->rollback();
			FlashMessage::write($t('Failed to clean media.', ['scope' => 'base_media']), [
				'level' => 'error'
			]);
		}
		return $this->redirect($this->request->referer());
	}

	protected function _selects($item = null) {
		if (Libraries::get('base_reference')) {
			$references = References::find('list', [
				'order' => ['name' => 'ASC']
			]);
		} else {
			$references = [];
		}
		return compact('references');
	}
}

?>