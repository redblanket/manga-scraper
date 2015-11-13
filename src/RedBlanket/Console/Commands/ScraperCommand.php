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

        try {
            $this->getChapterLinks();
            $this->validateStartEnd();
            $this->showChapterTitle();
            $this->getImages();
            $this->writeMeta();

            $this->output->writeln('');
            $this->output->writeln('All done. Enjoy!');
        }
        catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }



}
