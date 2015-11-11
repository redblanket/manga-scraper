<?php

namespace RedBlanket\Console\Commands;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;

class Scraper extends Command
{
    protected $client;
    protected $config;
    protected $fs;
    protected $manga;
    protected $output;

    public function configure()
    {
        $this
            ->setName('run')
            ->setDescription('MangaPanda image scrapper')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'A URL to scrape data from'
            )
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Full path where to store the files'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $url  = $input->getArgument('url');
        $path = $input->getArgument('path');
        $path = rtrim($path, '/');

        $this->config = include_once(ROOT_PATH . '/config.php');
        $this->fs     = new Filesystem;
        $this->output = $output;

        // Get manga name
        $parts = explode('/', $url);
        $mangaName = $parts[count($parts) - 1];

        // Set the base folder path
        $this->manga = $path . '/' . $mangaName;

        // Create the folder
        $this->fs->mkdir($this->manga);

        $this->output->writeln('Manga: <question>' . $mangaName . '</question>');

        try {

            $this->client = new Client;

            $content = $this->client->get($url)->getBody()->getContents();

            $crawler = new Crawler($content);

            $crawler->filter('#listing tr td:first-child')->each( function($node) {

                $link = $node->filter('a')->first()->attr('href');

                // get chapter number
                $parts = explode('/', $link);
                $chapterNum = $parts[count($parts) - 1];

                // Set folder location
                $location = $this->manga . '/' . str_pad($chapterNum, 3, 0, STR_PAD_LEFT);

                // Create chapter folder
                $this->fs->mkdir($location);

                $chapterLink = 'http://mangapanda.com' . $link;

                $this->output->writeln('Page:  <comment>' . $chapterLink . '</comment>');

                $chContent = $this->client->get($chapterLink)->getBody()->getContents();

                $ch = new Crawler($chContent);

                $countImg = 1;

                // Navigate page
                $ch->filter('#pageMenu')->children()->each( function($child) use ($countImg, $location, $chapterLink) {

                    $imgURL = 'http://mangapanda.com' . $child->attr('value');

                    if ($imgURL == $chapterLink) {
                        $imgURL .= '/1';
                    }

                    $imgContent = $this->client->get($imgURL)->getBody()->getContents();

                    $imx = new Crawler($imgContent);

                    $src = $imx->filter('img#img:first-child')->attr('src');

                    $parts = explode('/', $src);

                    $imgName = $parts[count($parts) - 1];

                    if (! file_exists($location . '/' . $imgName)) {

                        $this->download($src, $location . '/' . $imgName);

                        $this->output->writeln('Image: <info>' . $imgURL . '</info>');

                        if ((int) $this->config['image_sleep'] > 0) {
                            sleep((int) $this->config['image_sleep']);
                        }
                    }
                    else {
                        $this->output->writeln('Image: <info>' . $imgURL . '</info> <error>SKIPPED!</error>');
                    }
                });

                if ((int) $this->config['page_sleep'] > 0) {
                    sleep((int) $this->config['page_sleep']);
                    $this->output->writeln('<fg=cyan>Plase wait ...</>');
                }
            });

        }
        catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }
    }

    private function download($url, $location)
    {
        switch ($this->config['downloader']) {
            default:
            case 'normal':
                $this->downloadNormal($url, $location);
                break;

            case 'curl':
                $this->downloadCurl($url, $location);
                break;
        }
    }

    private function downloadNormal($url, $location)
    {
        file_put_contents($location, file_get_contents($url));
    }

    private function downloadCurl($url, $location)
    {
        $ch = curl_init($url);
        $fp = fopen($location, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }
}