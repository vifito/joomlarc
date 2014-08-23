#!/usr/bin/env php
<?php
// application.php

require __DIR__ . '/../vendor/autoload.php';

use Atingo\Console\Command\InstallCommand;

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new InstallCommand);

$application->run();
