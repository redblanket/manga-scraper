<?php

namespace RedBlanket\Console\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScraperCommand extends BaseCommand
{
    /**
     * Configure the app
     */
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Manga Scraper')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'A URL to scrape data from. Basically it\'s the page with the list of chapter links.'
            )
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'Full path where to store the files'
            )
            ->addOption(
                'folder',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the folder name for the comic. If not set, we will use the default name base on the comic URL.'
            )
            ->addOption(
                'start',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the first chapter to be fetched',
                1
            )
            ->addOption(
                'end',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the last chapter to be fetched',
                999
            );
    }

    /**
     * Run the command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);
        $this->loadMetadata();

        try {
            $this->getChapterLinks();
            $this->validateStartEnd();
            $this->showChapterTitle();
            $this->getImages($this->chapterLinks);
            $this->writeMeta();

            $this->output->writeln('');
            $this->output->writeln('All done. Enjoy!');
        }
        catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * Initialize
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        parent::init($input, $output);

        // Get starting chapter
        $this->start_at   = $this->input->getOption('start') ? $this->input->getOption('start') : 0;

        // Get ending chapter
        $this->end_at     = $this->input->getOption('end') ? $this->input->getOption('end') : 999;

        // Save current URL to meta array
        $this->meta['url'] = rtrim($this->input->getArgument('url'), '/');

        // Get the download path, if available, or use default value from config.local.php
        $path         = $this->input->getOption('path') ? $this->input->getOption('path') : $this->config['download_path'];
        $path         = rtrim($path, '/');

        // Get comic name
        $parts        = explode('/', $this->meta['url']);
        $this->folder = $this->input->getOption('folder') ? $this->input->getOption('folder') : $parts[count($parts) - 1];
        $this->folder = str_replace('_', '-', $this->folder);

        // Set the base folder path
        $this->base   = $path . '/' . $this->folder;

        // Create the folder
        $this->fs->mkdir($this->base);

        $sources = include_once ROOT_PATH . '/src/RedBlanket/Config/sources.php';

        foreach ($sources as $key => $config) {
            if (strstr($this->meta['url'], $key)) {
                $this->config = array_merge($this->config, $config, ['type' => $key]);
                continue;
            }
        }

        // Get the TOC page
        $this->crawler = $this->fetchContent($this->meta['url']);
    }

}
