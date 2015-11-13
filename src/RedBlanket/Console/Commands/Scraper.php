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
     * @var integer Store comic meta to be written on meta.json
     */
    protected $meta;

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

        $this->meta['url'] = rtrim($this->input->getArgument('url'), '/');

        $path             = $this->input->getOption('path') ? $this->input->getOption('path') : $this->config['download_path'];
        $path             = rtrim($path, '/');

        // Get comic name
        $parts        = explode('/', $this->meta['url']);
        $this->folder = $this->input->getOption('folder') ? $this->input->getOption('folder') : $parts[count($parts) - 1];
        $this->folder = str_replace('_', '-', $this->folder);

        // Set the base folder path
        $this->base = $path . '/' . $this->folder;

        // Create the folder
        $this->fs->mkdir($this->base);

        $sources = include_once ROOT_PATH . '/sources.php';

        foreach ($sources as $key => $config) {
            if (strstr($this->meta['url'], $key)) {
                $this->config = array_merge($this->config, $config, ['type' => $key]);
                continue;
            }
        }

        // Get the TOC page
        $this->crawler = $this->fetchContent($this->meta['url']);
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
        // If starting chapter is lower than the first available chapter
        if ($this->start_at < $this->meta['first']) {
            $this->output->writeln('<error>Starting chapter ' . $this->start_at . ' is not available!</error>');
            die;
        }
        // If starting chapter is higher than the latest available chapter
        elseif ($this->start_at > $this->meta['latest']) {
            $this->output->writeln('<error>Chapter ' . $this->start_at . ' is not available!</error>');
            die;
        }
        // If starting chapter is higher than the ending chapter
        elseif ($this->start_at > $this->end_at) {
            $this->output->writeln('<error>Starting chapter cannot be higher value than ending chapter!</error>');
            die;
        }

        $this->showChapterTitle($this->crawler);

        foreach ($this->chapterLinks as $link) {

            $chapterNum = $this->getChapterNum($link);

            if ($chapterNum >= $this->start_at AND $chapterNum <= $this->end_at) {

                $this->output->writeln('<comment>Chapter ' . $chapterNum . '</comment>');

                // Set folder location
                $this->setCurrentPath($chapterNum);

                // Create chapter folder
                $this->fs->mkdir($this->currentPath);

                // Get proper chapter URL
                $link = $this->getChapterLink($link);

                // Get the content
                $chapterPage = $this->fetchContent($link);

                // Navigate page
                $chapterPage->filter($this->config['pages_list_filter'])->children()->each(function ($child) use ($link, $chapterNum) {

                    $value = $this->getDropdownValue($child->attr('value'));

                    if ($value > 0) {

                        $imgURL = $this->getImageURL($child->attr('value'), $link);
                        $img    = $this->fetchContent($imgURL);
                        $src    = $img->filter($this->config['image_page_filter'])->attr('src');

                        // Make sure it's a valid resource
                        if ($src) {

                            // Get the image name
                            $parts = explode('/', $src);
                            $imgName = $parts[count($parts) - 1];

                            // Check if the image downloaded
                            if (! file_exists($this->currentPath . '/' . $imgName)) {
                                $this->download($src, $this->currentPath . '/' . $imgName, $imgName);
                            }
                            else {
                                $this->skipFile($imgName);
                            }
                        }
                    }
                });

                // Set latest fetched chapter
                $this->setLatestChapter($chapterNum);

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
        // Get chapter links from main comic page
        $this->crawler->filter($this->config['table_of_content_filter'])->each(function ($node) {

            // Get the chapter link
            $this->chapterLinks[] = $node->filter($this->config['table_of_content_links_filter'])->first()->attr('href');
        });

        if ($this->config['type'] == 'mangafox') {
            $this->chapterLinks = array_reverse($this->chapterLinks);
        }

        // Get first chapter number
        $this->meta['first']  = $this->getChapterNum($this->chapterLinks[0]);

        // Get the latest chapter number
        $this->meta['latest'] = $this->getChapterNum($this->chapterLinks[count($this->chapterLinks) - 1]);
    }

    /**
     * Write metadata to meta.json file for later use
     */
    private function writeMeta()
    {
        $this->fs->touch($this->base . '/meta.json');
        $this->fs->dumpFile($this->base . '/meta.json', json_encode($this->meta));
    }

    /**
     * @param $chapterNum
     */
    private function setLatestChapter($chapterNum)
    {
        // Only set it if the current chapter is lower
        if (! isset($this->meta['current']) OR $this->meta['current'] < $chapterNum) {

            // Get latest fetched chapter
            $this->meta['current'] = $chapterNum;
        }
    }

    /**
     * Construct proper URL to chapter page
     *
     * @param $link
     * @return string
     */
    private function getChapterLink($link)
    {
        if (! strstr($link, 'http')) {
            return rtrim($this->config['base_url'], '/') . '/' . ltrim($link, '/');
        }

        return $link;
    }

    /**
     * Get image URL
     *
     * @param $value
     * @param $link
     * @return string
     */
    private function getImageURL($value, $link)
    {
        switch ($this->config['type']) {

            case 'mangafox':
                $urlParts = explode('/', $link);
                unset($urlParts[count($urlParts) - 1]);

                return implode('/', $urlParts) . '/' . $value . '.html';
                break;

            case 'mangareader':
            case 'mangapanda':
            default:
                $imgURL = $this->config['base_url'] . $value;

                if ($imgURL == $link) {
                    $imgURL .= '/1';
                }

                return $imgURL;
                break;
        }
    }

    /**
     * Get proper image link value
     *
     * @param $value
     * @return mixed
     */
    private function getDropdownValue($value)
    {
        if (strstr($value, '/')) {
            $parts = explode('/', $value);
            return $parts[count($parts) - 1];
        }
        return $value;
    }

}
