<?php

namespace MartelliEnrico\ItunesToM3u;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

class MyApplication extends Application {
	protected function getCommandName(InputInterface $input) {
		return 'itunes-m3u';
	}

	protected function getDefaultCommands() {
		$defaultCommands = parent::getDefaultCommands();
		$defaultCommands[] = new ConvertCommand;

		return $defaultCommands;
	}

	public function getDefinition() {
		$inputDefinition = parent::getDefinition();
		$inputDefinition->setArguments();

		return $inputDefinition;
	}
}