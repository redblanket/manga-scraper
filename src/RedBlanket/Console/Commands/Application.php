<?php

namespace RedBlanket\Console\Commands;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    const NAME = 'Manga Scraper';
    const VERSION = '1.1.5';

    public function __construct()
    {
        parent::__construct(static::NAME, static::VERSION);
    }
}