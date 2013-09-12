<?php
/**
 * Bureau Media
 *
 * Copyright (c) 2013 Atelier Disko - All rights reserved
 *
 * This software is proprietary and confidential. Redistributions
 * not permitted. No warranty, explicit or implicit provided.
 */

namespace cms_media\controllers;

use cms_media\models\Media;
use lithium\core\Libraries;

class FilesController extends \lithium\action\Controller {

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

	public function preflight() {
	}

	public function reserve() {
		// Return status, long-polling
	}

	public function transfer() {
		// Translate received data into a to-be-saved item.
		$transfer = new Transfer();
		$transfer->source = $this->request->data;
		$transfer->target = '/tmp';

		$transfer->run();

		Media::create($transfer->result);
	}

	public function import() {

	}

	public function Xtransfer() {


		CakeLog::write('debug', 'Transfer handler initiated.');

		$this->Gate->requireMagicPower();
		// @fixme Sets content-type to 'json'?
		$this->RequestHandler->setContent('json');
		$this->RequestHandler->respondAs('json');

		if (!$this->RequestHandler->isPost()) {
			return $this->cakeError('error405', ['api' => true]);
		}

		if ($this->RequestHandler->requestedWith('application/octet-stream')) { // sendAsBinary
			CakeLog::write('debug', 'Receiving/received file as binary stream.');

			if (!$source = fopen('php://input', 'rb')) {
				return $this->cakeError('error500', ['api'  => true]);
			}
			$file = Temporary::file(['context' => 'npiece']);

			$target = fopen($file, 'wb');
			stream_copy_to_stream($source, $target);
			fclose($source);
			fclose($target);
		} else { // multipart
			CakeLog::write('debug', 'Received file via multipart/form:');
			CakeLog::write('debug', var_export($this->params['form'], true));
			$file = $this->params['form']['file']; // non-cake structure
		}
		// Note that $file is reused latern when saving a sample.

		CakeLog::write('debug', "Transferring from file `{$file}`.");

		// This relies on that we're being able to *always* retrieve a size.
		$meta = $this->MediaFile->transferMeta($file);

		$quota = $this->Quota->checkFiles($this->Gate->user(), ['buffer' => 1]);
		$quota = $quota && $this->Quota->checkSpace($this->Gate->user(), ['buffer' => $meta['size']]);

		$errorName = null;

		if (!$quota) {
			$errorName = 'quota';
		} else {
			/* Transaction begin. */
			ignore_user_abort(true);

			$this->MediaFile->create();
			$this->data['MediaFile'] = [
				'file' => $file,
				'user_id' => $this->Gate->user('id')
			];
			if ($this->MediaFile->save($this->data)) {
				$id = $this->MediaFile->getLastInsertID();
				CakeLog::write('debug', "Transfer handled, file bound to `MediaFile@{$id}`.");

				if (connection_aborted()) {
					CakeLog::write('debug', "Request aborted, cleaning up `MediaFile@{$id}`.");
					$this->MediaFile->delete($id); // We want to delete the uploaded file, too.
					exit(); // User doesn't need any output.
				}
			} else {
				$invalidFields = $this->MediaFile->invalidFields();
				$errorName = $invalidFields ? Inflector::underscore($invalidFields['file']) : 'unknown';
			}

			ignore_user_abort(false);
			/* Transaction end. */
		}
		if ($errorName) {
			$message  = "Transfer failed; reason is `{$errorName}`; meta was: \n";
			$message .= var_export($meta, true);
			CakeLog::write('debug', $message);

			if (file_exists($file)) {
				copy($file, $sample = ROOT . '/files/' . basename($file));
				CakeLog::write('debug', "Saved file involved in failed transfer to `{$sample}`.");
			}

			return $this->cakeError('transfer', ['api' => true, 'type' => $errorName]);
		}
		$this->autoRender = false;
	}
}

?>