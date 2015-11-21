<?php
/**
 * Created by PhpStorm.
 * User: syah
 * Date: 14/11/2015
 * Time: 12:05 AM
 */

namespace RedBlanket\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Component\Filesystem\Filesystem;

abstract class BaseCommand extends Command
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
     * @var string Store comic name
     */
    protected $title;

    /**
     * Initialize
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
        $this->client    = new Client;
        $this->fs        = new Filesystem;
        $this->output    = $output;
        $this->input     = $input;
        $this->config    = $this->getConfig();
    }

    /**
     * Get config values
     *
     * @return mixed
     */
    protected function getConfig()
    {
        if (! file_exists(ROOT_PATH . '/config.local.php')) {

            $path = $this->askDownloadPath();

            $stub = file_get_contents(ROOT_PATH . '/src/RedBlanket/Stubs/config.txt');
            $stub = str_replace('{DOWNLOAD_PATH}', $path, $stub);

            $this->fs->dumpFile(ROOT_PATH . '/config.local.php', $stub);

            $this->output->writeln('Default download location is set to <info>' . $path . '</info>. You can change the value in <comment>' . ROOT_PATH . '/config.local.php' . '</comment> file.');
            $this->output->writeln('');
        }

        return include_once ROOT_PATH . '/config.local.php';
    }

    /**
     * Fetch the contents
     *
     * @param $url
     *
     * @return DomCrawler
     */
    protected function fetchContent($url)
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
    protected function download($url, $location, $image)
    {
        $content = null;

        // Retries if the fetching fails. Untested, and might not reliable.
        if ($this->retry > $this->config['max_retry']) {
            $this->output->writeln('<error>Maximum retry reached! Try again later.');
            die;
        }

        try {
            $this->client->request('GET', $url, ['sink' => $location]);
        }
        catch (\Exception $e) {
            // Retry
            $this->download($url, $location);

            $this->retry++;
        }
        $this->output->writeln('Image: <info>' . $image . '</info>');

        if ((int) $this->config['image_sleep'] > 0) {
            sleep((int) $this->config['image_sleep']);
        }
    }

    /**
     * Skip file download
     *
     * @param $image
     */
    protected function skipFile($image)
    {
        $this->output->writeln('Image: <info>' . $image . '</info> <error>SKIPPED!</error>');
    }

    /**
     * @param $link
     *
     * @return mixed
     */
    protected function getChapterNum($link)
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
    protected function setCurrentPath($chapter)
    {
        $this->currentPath = $this->base . '/' . str_pad($chapter, 3, 0, STR_PAD_LEFT);
    }

    /**
     * Get images
     *
     * @param $links array
     */
    protected function getImages($links)
    {
        foreach ($links as $link) {

            $chapterNum = $this->getChapterNum($link);

            if ($chapterNum >= $this->start_at AND $chapterNum <= $this->end_at) {

                $this->output->writeln($this->title . ': <comment>Chapter ' . $chapterNum . '</comment>');

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

                    $value = $this->getImageLink($child->attr('value'));

                    if ($value > 0) {

                        $imgURL = $this->getImageURL($child->attr('value'), $link);
                        $img    = $this->fetchContent($imgURL);
                        $src    = $img->filter($this->config['image_page_filter'])->attr('src');

                        // Make sure it's a valid resource
                        if ($src) {

                            // Get the image name
                            $parts = explode('/', $src);
                            $imgName = $parts[count($parts) - 1];

                            // compare hash
                            $remoteFile = md5_file($src);

                            // Check if the image downloaded
                            if (! file_exists($this->currentPath . '/' . $imgName)) {
                                $this->download($src, $this->currentPath . '/' . $imgName, $imgName);
                                $localFile  = md5_file($this->currentPath . '/' . $imgName);
                            }
                            else {
                                $localFile  = md5_file($this->currentPath . '/' . $imgName);

                                if ($remoteFile == $localFile) {
                                    $this->skipFile($imgName);
                                }
                                else {
                                    $this->download($src, $this->currentPath . '/' . $imgName, $imgName);
                                }
                            }

                            $this->meta['files'][$chapterNum]['url'] = $link;
                            $this->meta['files'][$chapterNum]['images'][] = [
                                'name'      => $imgName,
                                'url'       => $src,
                                'completed' => ($localFile == $remoteFile) ? 1 : 0
                            ];

                            $this->writeMeta();
                        }
                    }
                });

                // Set latest fetched chapter
                $this->setLatestChapter($chapterNum);

                $this->writeMeta();

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
    protected function getTitle($content)
    {
        $pattern = [
            '/Manga/i',
            '/Manhwa/i',
            '/Manhua/i'
        ];

        $title = $content->filter($this->config['title_filter'])->first()->text();
        $title = rtrim(ucwords(strtolower(preg_replace($pattern, '', $title))), ' ');
        $this->title = $title;

        return $title;
    }

    /**
     * Show chapter title
     */
    protected function showChapterTitle()
    {
        $this->output->writeln('');
        $this->output->writeln('<question> ' . $this->getTitle($this->crawler) . ' </question>');
        $this->output->writeln('');
    }

    /**
     * Show page sleep info
     */
    protected function showPageSleep()
    {
        if ((int) $this->config['page_sleep'] > 0) {
            sleep((int) $this->config['page_sleep']);
            $this->output->writeln('<fg=cyan>Please wait ...</>');
        }
    }

    /**
     * @return DomCrawler
     */
    protected function getChapterLinks()
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

        if (! empty($this->input->getOption('only')) OR ! empty($this->input->getOption('except'))) {

            $exclude = ! empty($this->input->getOption('except')) ? true : false;
            $array   = ! empty($this->input->getOption('except')) ? $this->input->getOption('except') : $this->input->getOption('only');

            $this->filterChapterLinks($array, $exclude);
        }
    }

    /**
     * Write metadata to meta.json file for later use
     */
    protected function writeMeta()
    {
        $this->fs->dumpFile($this->base . '/meta.json', json_encode($this->meta));
    }

    /**
     * @param $chapterNum
     */
    protected function setLatestChapter($chapterNum)
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
    protected function getChapterLink($link)
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
    protected function getImageURL($value, $link)
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
    protected function getImageLink($value)
    {
        if (strstr($value, '/')) {
            $parts = explode('/', $value);
            return $parts[count($parts) - 1];
        }
        return $value;
    }

    /**
     * Ask default download path on first run
     *
     * @return string
     */
    protected function askDownloadPath()
    {
        $helper = $this->getHelper('question');

        $this->output->writeln("\nNo configuration file found. Please enter full path to store the files.\n");
        $question = new Question('<question>Path</question>: ');

        $path =  $helper->ask($this->input, $this->output, $question);

        if (! is_dir($path)) {
            $this->output->writeln('<error>Invalid path! Please try again.</error>');

            $this->askDownloadPath();
        }

        return rtrim($path, '/');
    }

    /**
     * Validate start and end chapter
     */
    protected function validateStartEnd()
    {
        if (isset($this->meta['latest']) AND isset($this->meta['first'])) {

            if (! empty($this->input->getOption('only')) OR ! empty($this->input->getOption('except'))) {

                if (! empty($this->input->getOption('except'))) {
                    $arr = explode(',', trim($this->input->getOption('except')));

                    foreach ($arr as $chapter) {
                        if ($chapter < $this->meta['first'] OR $chapter > $this->meta['latest']) {
                            $this->output->writeln('<error>Chapter ' . $chapter . ' is not available!</error>');
                            die;
                        }
                    }
                }
                elseif (! empty($this->input->getOption('only'))) {

                    $arr = explode(',', trim($this->input->getOption('only')));

                    foreach ($arr as $chapter) {
                        if ($chapter < $this->meta['first'] OR $chapter > $this->meta['latest']) {
                            $this->output->writeln('<error>Chapter ' . $chapter . ' is not available!</error>');
                            die;
                        }
                    }
                }
            }
            else {
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
            }
        }


    }

    /**
     * Load meta file
     */
    protected function loadMetadata()
    {
        if (! file_exists($this->base . '/meta.json')) {
            $this->fs->touch($this->base . '/meta.json');
            $this->fs->dumpFile($this->base . '/meta.json', []);
        }

        $meta = file_get_contents($this->base . '/meta.json');
        $this->meta = json_decode($meta, true);
    }

    /**
     * Include or exclude chapter to be fetched
     *
     * @param string $chapters  List of chapters separated by comma
     * @param bool   $exclude   Whether to include or exclude the chapter
     */
    protected function filterChapterLinks($chapters, $exclude = false)
    {
        $chapters = explode(',', trim($chapters));

        $links = [];

        foreach ($this->chapterLinks as $link) {
            $num = $this->getChapterNum($link);

            if ($exclude) {
                if (! in_array($num, $chapters)) {
                    $links[] = $link;
                }
            }
            else {
                if (in_array($num, $chapters)) {
                    $links[] = $link;
                }
            }
        }

        $this->chapterLinks = $links;
    }

}