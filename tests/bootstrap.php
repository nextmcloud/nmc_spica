<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

// in case you need to split or extend tests
//\OC::$composerAutoloader->addPsr4('OCA\\NMCSpica\\Tests\\', dirname(__FILE__) . '/unit/', true);
\OC_App::loadApp('nmc_spica');
OC_Hook::clear();
