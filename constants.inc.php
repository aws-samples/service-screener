<?php

define('ROOT_DIR', __DIR__);
define('SERVICE_DIR', __DIR__.'/services');
define('TEMPLATE_DIR', __DIR__.'/templates');

define('HTML_FOLDER', 'adminlte/html');
define('HTML_DIR', __DIR__.'/'.HTML_FOLDER);
define('FORK_DIR', __DIR__.'/__fork');
define('API_JSON', FORK_DIR.'/api.json');

define('GENERAL_CONF_PATH', SERVICE_DIR . '/general.reporter.json');

define('CLI_TRUE_KEYWORD_ARRAY', ['yes', 'y', 'true', '1', 1]);

define('SESSUID_FILENAME', 'sess-uuid');