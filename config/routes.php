<?php

use lithium\net\http\Router;

Router::connect('/files/preflight', 'Files::preflight');
Router::connect('/files/reserve', 'Files::reserve');
Router::connect('/files/transfer', 'Files::transfer');
Router::connect('/files/import', 'Files::import');

Router::connect('/files', array('controller' => 'files', 'action' => 'index', 'library' => 'cms_media'));

Router::connect('/files/{:action}/{:id:[0-9a-f]{24}}', array(
	'controller' => 'files', 'library' => 'cms_media'
));
Router::connect('/files/{:action}/{:args}', array(
	'controller' => 'files', 'library' => 'cms_media'
));

?>