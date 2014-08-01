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

use lithium\g11n\Message;
use cms_core\extensions\cms\Panes;

extract(Message::aliases());

Panes::register('media', [
	'title' => $t('Media'),
	'order' => 20,
	'url' => ['controller' => 'media', 'action' => 'index', 'library' => 'cms_media', 'admin' => true]
]);

?>