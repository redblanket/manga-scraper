# Manga Scraper

Get images from [MangaPanda](http://mangapanda.com), [MangaReader](http://mangareader.net) or [MangaFox](http://mangafox.me) and save it for offline reading.

## Usage

Get the table of content page URL, for example:

```http://mangapanda.com/naruto```

Now run the command:

```./scraper run http://mangapanda.com/naruto```

### Configuration

There are some basic configuration for the app that you need to check out.

```base_url```

This is the base URL to the comic/manga website. Refer to supported website below.

```download_path```

Default location where you want to store your files. The value can be overridden using option ```--path```.

```table_of_content_filter```

The CSS selector to the table of content page. This is the element where we need to fetch all the chapter links.

```image_page_filter```

CSS selector for single image page. This is where the full image is shown, and we need the filter to download the image.

```page_sleep```

Delay between chapters. 

```image_sleep```

Delay between images. 


### Options

```--path="/full/path/to/your/own/folder"```

Set where to store the files using the option ```--path```. 


```--folder="name of your comic folder"```

Set the folder name manually using this option. If it's not set, the app will be using the URI to the comic page.

```--start=num```

Set the starting chapter to be fetched. For example, if you need to start from chapter 3, you can set ```--start=3``` on the command.

```--end=num```

Same with ```--start``` option, except this will be the last chapter to be fetched.

## Supported Website

* [MangaPanda](http://mangapanda.com)
* [MangaReader](http://mangareader.net)
* [MangaFox](http://mangafox.me)

## License

MIT License

## Copyright

All comic/manga is copyrighted to their respective author. Please buy the comic/manga if it's available in your country.