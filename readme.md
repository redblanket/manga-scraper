# Manga Scraper

Get images from [MangaPanda](http://mangapanda.com), [MangaReader](http://mangareader.net) or [MangaFox](http://mangafox.me) and save it for offline reading.

**Use it for educational purpose only!**

## Installation

Run these command to install:

```
git clone https://github.com/redblanket/manga-scraper.git
cd manga-scaper
composer install
```

## Usage

```
./scraper run <url>
```

### Basic command

```
// mangapanda
./scraper run http://mangapanda.com/naruto

// mangareader
./scraper run http://mangareader.net/naruto

// mangafox
./scraper run http://mangafox.me/manga/naruto/

```

### Set folder name

You can set the folder name for the images. By default, the folder name created based on comic/manga name from URI.

```
./scraper run http://mangapanda.com/naruto --folder=Naruto

./scraper run http://mangafox.me/manga/one_piece/ --folder="One Piece"
```

### Custom save path

Set custom save path, different from defined in your *config.php* file. 

```
./scraper run http://mangapanda.com/naruto --path=/Users/syahzul/Documents/Manga

./scraper run http://mangafox.me/manga/one_piece/ --path="C:\My Documents\Comics"
```

### Start chapter

Set the chapter to start. This is useful when your previous download stopped and you want to resume the downloads. 

```
./scraper run http://mangapanda.com/naruto --start=10
```

***Note:***
*You can combine with ```--end``` option. See below.*

### End chapter

Set the chapter to end. This is useful when you want to limit fetching process.

```
./scraper run http://mangapanda.com/naruto --end=20
```

***Note:***
*You can combine with ```--start``` option. See above.*


### Argument

<dl>
	<dt>url</dt>
	<dd>URL to the comic/manga list of chapter links.</dd>
</dl>


## Configuration

There are some basic configuration for the app that you need to check out.

<dl>
	<dt>download_path</dt>
	<dd>Default location where you want to store your files. The value can be overridden using option <code>--path</code>.</dd>

	<dt>page_sleep</dt>
	<dd>Delay between chapters.</dd>

	<dt>image_sleep</dt>
	<dd>Delay between images.</dd>
</dl>

### Options

<dl>

	<dt>--path="/full/path/to/disk"</dt>
	<dd>Set where to store the files. Specify full path to the <strong>parent</strong> folder.</code></dd>

	<dt>--folder="name of your comic folder"</dt>
	<dd>Set the folder name manually using this option. If it's not set, the folder will be created based on comic/manga name from URI.</dd>

	<dt>--start=num</dt>

	<dd>Set the starting chapter to be fetched. For example, if you need to start from chapter 3, you can set <code>--start=3</code> on the command.</dd>

	<dt>--end=num</dt>

	<dd>Same with <code>--start</code> option, except this will be the last chapter to be fetched.</dd>
	
</dl>	

## Supported Website

* [MangaPanda](http://mangapanda.com)
* [MangaReader](http://mangareader.net)
* [MangaFox](http://mangafox.me)

## Changelog

### v1.1.4
* Ask for download path on first run
* Remove config.php and relies on auto-generated config.local.php

### v1.1.3
* Download method for all website has been merged.
* Do extra checking for input option ```--start``` and ```--end``` to make sure the value is valid.
* Create metadata file to save some information about the comic/manga.

## License

Open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).

## Note

The main purpose I'm building this app is to learn building console app using [Symfony Console](http://symfony.com/doc/current/components/console/index.html) component and some other packages like [Guzzle](https://github.com/guzzle/guzzle). 

**Use it at your own risk!**

## Copyright

All comic/manga is copyrighted to their respective author. Please buy the comic/manga if it's available in your country.