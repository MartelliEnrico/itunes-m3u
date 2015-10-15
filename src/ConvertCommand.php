<?php

namespace MartelliEnrico\ItunesToM3u;

use CFPropertyList\CFPropertyList;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ConvertCommand extends Command {
	protected $filesystem;

	protected function configure() {
		$this
			->setName('itunes-m3u')
			->setDescription('Convert an iTunes playlist to use m3u format')
			->addArgument(
				'playlist-id',
				InputArgument::REQUIRED,
				'Id of the iTunes playlist')
			->addArgument(
				'destination',
				InputArgument::REQUIRED,
				'Playlist export directory')
			->addOption(
				'force',
				'f',
				InputOption::VALUE_NONE,
				'Force directory creation');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$playlistId = $input->getArgument('playlist-id');
		$destination = $input->getArgument('destination');
		$force = $input->getOption('force');

		$plist = $this->parsePlistFile();

		$playlist = $this->findPlaylist($plist['Playlists'], $playlistId);
		$trackIds = $this->getTrackIds($playlist);

		$tracks = $this->getTracksInformation($plist['Tracks'], $trackIds);

		$this->makeM3uFile($destination, $tracks, $force, $input, $output);

		$output->write('Playlist created!');
	}

	protected function parsePlistFile() {
		$file = $this->replaceHomeDirectory('~/Music/iTunes/iTunes Music Library.xml');
		$plist = new CFPropertyList($file);

		return $plist->toArray();
	}

	protected function findPlaylist(array $playlists, $playlistId) {
		foreach ($playlists as $playlist) {
			if ($playlist['Playlist ID'] == $playlistId) {
				return $playlist;
			}
		}

		throw new Exception("Can't find playlist by Id: " . $playlistId, 1);
	}

	protected function getTrackIds($playlist) {
		$trackIds = [];

		foreach ($playlist['Playlist Items'] as $item) {
			$trackIds[] = $item['Track ID'];
		}

		return $trackIds;
	}

	protected function getTracksInformation($tracks, array $tracksIds) {
		$songs = [];

		foreach ($tracks as $track) {
			if (in_array($track['Track ID'], $tracksIds)) {
				$songs[] = $track;
			}
		}

		if (count($songs) != count($tracksIds)) {
			throw new Exception("Couldn't find all the songs", 2);
		}

		return $songs;
	}

	protected function makeM3uFile($destination, array $tracks, $force = false, InputInterface $input, OutputInterface $output) {
		$dir = $this->replaceHomeDirectory($destination);

		if ($force || !is_dir($dir) || $this->askToOverridePlaylist($dir, $input, $output)) {
			$this->filesystem()->deleteDir($dir);
			$this->filesystem()->createDir($dir);
		} else {
			exit(0);
		}

		$content = '#EXTM3U' . PHP_EOL;

		foreach ($tracks as $track) {
			if ($track['Track Type'] != 'File') {
				continue;
			}

			$src = preg_replace('/file:\/\//', '', $track['Location']);
			var_dump($src);
			$artist = isset($track['Artist']) ? $track['Artist'] . ' - ' : '';
			$title = $track['Name'];
			$ext = pathinfo($src, PATHINFO_EXTENSION);
			$duration = round($track['Total Time'] / 1000);
			$filename = explode('Music/iTunes/iTunes%20Media/Music/', $src)[1];

			if ($this->filesystem()->copy($src, $dir . DIRECTORY_SEPARATOR . $filename)) {
				$content .= "#EXTINF:$duration, $artist$title" . PHP_EOL;
				$content .= $filename . PHP_EOL;
			}
		}

		$this->filesystem()->write($dir . DIRECTORY_SEPARATOR . 'playlist.m3u', $content);
	}

	protected function askToOverridePlaylist($dir, InputInterface $input, OutputInterface $output) {
		$helper = $this->getHelper('question');
		$question = new ConfirmationQuestion("Do you want to override this playlist?", false);

		return $helper->ask($input, $output, $question);
	}

	protected function filesystem() {
		if ($this->filesystem === null) {
			$this->filesystem = new Filesystem(new Local('/'));
		}

		return $this->filesystem;
	}

	protected function replaceHomeDirectory($path) {
		return preg_replace('/\~/', getenv('HOME'), $path, 1);
	}
}
