<?php

namespace RedBlanket\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Component\Filesystem\Filesystem;

class Scraper extends Command
{
    /**
     * @var string Base path where we will store the files
     */
    protected $base;

    /**
     * @var string The folder name for currently fetched comic
     */
    protected $folder;

    /**
     * @var string Current comic URL
     */
    protected $url;

    /**
     * @var string Current path to save the file
     */
    protected $currentPath;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var array List of config values
     */
    protected $config;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var string The chapter to start fetching
     */
    protected $start_at;

    /**
     * @var string The chapter to stop fetching
     */
    protected $end_at;

    /**
     * @var int Number of current retries
     */
    protected $retry = 0;

    /**
     * @var array Store chapter links
     */
    protected $chapterLinks;

    /**
     * @var \Symfony\Component\DomCrawler\Crawler
     */
    protected $crawler;

    /**
     * Configure the app
     */
    public function configure()
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
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);

        try {
            $this->getChapterLinks();
            $this->getImages();

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
    private function init(InputInterface $input, OutputInterface $output)
    {
        $this->client    = new Client;
        $this->fs        = new Filesystem;
        $this->output    = $output;
        $this->input     = $input;
        $this->config    = $this->getConfig();

        if ($this->config['download_path'] == '/path/to/your/storage/disk') {
            $this->output->writeln('<error>Please set your download_path in config.php file!</error>');
            die;
        }

        // Get starting chapter
        $this->start_at   = $this->input->getOption('start') ? $this->input->getOption('start') : 0;

        // Get ending chapter
        $this->end_at     = $this->input->getOption('end') ? $this->input->getOption('end') : 999;

        $this->url        = rtrim($this->input->getArgument('url'), '/');
        $path             = $this->input->getOption('path') ? $this->input->getOption('path') : $this->config['download_path'];
        $path             = rtrim($path, '/');

        // Get comic name
        $parts        = explode('/', $this->url);
        $this->folder = $this->input->getOption('folder') ? $this->input->getOption('folder') : $parts[count($parts) - 1];
        $this->folder = str_replace('_', '-', $this->folder);

        // Set the base folder path
        $this->base = $path . '/' . $this->folder;

        // Create the folder
        $this->fs->mkdir($this->base);

        $sources = include_once ROOT_PATH . '/sources.php';

        foreach ($sources as $key => $config) {
            if (strstr($this->url, $key)) {
                $this->config = array_merge($this->config, $config, ['type' => $key]);
                continue;
            }
        }
    }

    /**
     * Get config values
     *
     * @return mixed
     */
    private function getConfig()
    {
        if (file_exists(ROOT_PATH . '/config.local.php')) {
            return include_once ROOT_PATH . '/config.local.php';
        }
        return include_once ROOT_PATH . '/config.php';
    }

    /**
     * Fetch the contents
     *
     * @param $url
     *
     * @return DomCrawler
     */
    private function fetchContent($url)
    {
        if ($this->retry > $this->config['max_retry']) {
            $this->output->writeln('<error>Maximum retry reached! Try again later.');
            die;
        }

        try {
            $body = $this->client->get($url)->getBody()->getContents();

            $crawler = new DomCrawler($body);

            $this->retry = 0;

            return $crawler;
        }
        catch (BadResponseException $e) {
            $this->retry++;
        }
    }

    /**
     * Download the image file
     *
     * @param $url
     * @param $location
     * @param $image
     */
    private function download($url, $location, $image)
    {
        $content = null;

        // Retries if the fetching fails. Untested, and might not reliable.
        if ($this->retry > $this->config['max_retry']) {
            $this->output->writeln('<error>Maximum retry reached! Try again later.');
            die;
        }

        try {
            $content = file_get_contents($url);

            // Write the file to disk
            $this->fs->dumpFile($location, $content);

            $this->output->writeln('Image: <info>' . $image . '</info>');
        }
        catch (\Exception $e) {
            // Retry
            $this->download($url, $location);

            $this->retry++;
        }

        if ((int) $this->config['image_sleep'] > 0) {
            sleep((int) $this->config['image_sleep']);
        }
    }

    /**
     * Skip file download
     *
     * @param $image
     */
    private function skipFile($image)
    {
        $this->output->writeln('Image: <info>' . $image . '</info> <error>SKIPPED!</error>');
    }

    /**
     * @param $link
     *
     * @return mixed
     */
    private function getChapterNum($link)
    {
        if ($this->config['type'] == 'mangafox') {
            // get chapter number
            $parts      = explode('/', $link);
            $chapterNum = $parts[count($parts) - 2];
            return (int) str_replace('c', '', $chapterNum);
        }
        else {
            // get chapter number
            $parts      = explode('/', $link);
            return $parts[count($parts) - 1];
        }
    }

    /**
     * Set current download path
     *
     * @param $chapter
     */
    private function setCurrentPath($chapter)
    {
        $this->currentPath = $this->base . '/' . str_pad($chapter, 3, 0, STR_PAD_LEFT);
    }

    /**
     * Get images
     */
    private function getImages()
    {
        switch ($this->config['type']) {

            case 'mangafox':
                $this->getMangaFox();
                break;

            case 'mangapanda':
            case 'mangareader':
            default:
                $this->getMangaPanda();
                break;
        }
    }

    /**
     * Get images from mangapanda.com or mangareader.net
     */
    private function getMangaPanda()
    {
        $this->showChapterTitle($this->crawler);

        foreach ($this->chapterLinks as $link) {
            $chapterNum = $this->getChapterNum($link);

            if ($chapterNum >= $this->start_at AND $chapterNum <= $this->end_at) {

                $this->output->writeln('<comment>Chapter ' . $chapterNum . '</comment>');

                // Set folder location
                $this->setCurrentPath($chapterNum);

                // Create chapter folder
                $this->fs->mkdir($this->currentPath);

                $chapterLink = $this->config['base_url'] . $link;
                $chapterPage = $this->fetchContent($chapterLink);

                // Navigate page
                $chapterPage->filter($this->config['pages_list_filter'])->children()->each(function ($child) use ($chapterLink, $chapterNum) {

                    $imgURL = $this->config['base_url'] . $child->attr('value');

                    if ($imgURL == $chapterLink) {
                        $imgURL .= '/1';
                    }

                    $imx = $this->fetchContent($imgURL);
                    $src = $imx->filter($this->config['image_page_filter'])->attr('src');

                    $parts   = explode('/', $src);
                    $imgName = $parts[count($parts) - 1];

                    // Check if the image downloaded
                    if (! file_exists($this->currentPath . '/' . $imgName)) {
                        $this->download($src, $this->currentPath . '/' . $imgName, $imgName);
                    }
                    else {
                        $this->skipFile($imgName);
                    }
                });

                $this->showPageSleep();
            } //
        }
    }

    /**
     * Get images from mangafox.me
     */
    private function getMangaFox()
    {
        $this->chapterLinks = array_reverse($this->chapterLinks);

        $this->showChapterTitle($this->crawler);

        foreach ($this->chapterLinks as $link) {

            $chapterNum = $this->getChapterNum($link);

            if ($chapterNum >= $this->start_at AND $chapterNum <= $this->end_at) {

                $this->output->writeln('<comment>Chapter ' . $chapterNum . '</comment>');

                // Set folder location
                $this->setCurrentPath($chapterNum);

                // Create chapter folder
                $this->fs->mkdir($this->currentPath);

                // Get the content
                $chapterPage = $this->fetchContent($link);

                // Navigate page
                $chapterPage->filter($this->config['pages_list_filter'])->children()->each(function ($child) use ($link, $chapterNum) {

                    $urlParts = explode('/', $link);

                    unset($urlParts[count($urlParts) - 1]);

                    if ($child->attr('value') > 0) {

                        $imgURL = implode('/', $urlParts) . '/' . $child->attr('value') . '.html';

                        $imx = $this->fetchContent($imgURL);
                        $src = $imx->filter($this->config['image_page_filter']);

                        // Make sure it's a valid resource
                        if ($src) {

                            $parts   = explode('/', $src->attr('src'));
                            $imgName = $parts[count($parts) - 1];

                            // Check if the image downloaded
                            if (! file_exists($this->currentPath . '/' . $imgName)) {
                                $this->download($src->attr('src'), $this->currentPath . '/' . $imgName, $imgName);
                            }
                            else {
                                $this->skipFile($imgName);
                            }
                        }
                    }
                });

                $this->showPageSleep();
            } //
        }
    }

    /**
     * Get comic title
     *
     * @param $content
     *
     * @return string
     */
    private function getTitle($content)
    {
        $title = $content->filter($this->config['title_filter'])->first()->text();
        return ucwords(strtolower(str_replace(' Manga', '', $title)));
    }

    /**
     * Show chapter title
     *
     * @param $content
     */
    private function showChapterTitle($content)
    {
        $this->output->writeln('');
        $this->output->writeln('<question> ' . $this->getTitle($content) . ' </question>');
        $this->output->writeln('');
    }

    /**
     * Show page sleep info
     */
    private function showPageSleep()
    {
        if ((int) $this->config['page_sleep'] > 0) {
            sleep((int) $this->config['page_sleep']);
            $this->output->writeln('<fg=cyan>Please wait ...</>');
        }
    }

    /**
     * @return DomCrawler
     */
    private function getChapterLinks()
    {
        // Get chapters link from main comic page
        $this->crawler = $this->fetchContent($this->url);

        $this->crawler->filter($this->config['table_of_content_filter'])->each(function ($node) {

            // Get the chapter link
            $this->chapterLinks[] = $node->filter($this->config['table_of_content_links_filter'])->first()->attr('href');
        });
    }
}
