<?php

/**
 * WARNING:
 *
 * Do not change any values in this file, unless you know what you're doing.
 */

return [

    'mangapanda' => [

        // source base url
        'base_url' => 'http://mangapanda.com',

        // CSS selector filter for TOC page
        'table_of_content_filter' => '#listing tr td:first-child',

        // CSS selector filter for TOC chapter links
        'table_of_content_links_filter' => 'a',

        // Pages dropdown
        'pages_list_filter' => '#pageMenu',

        // CSS selector filter for single image page
        'image_page_filter' => 'img#img:first-child',

        // CSS selector to get comic title
        'title_filter' => '#mangaproperties h1',
    ],

    'mangareader' => [

        // source base url
        'base_url' => 'http://mangareader.net',

        // CSS selector filter for TOC page
        'table_of_content_filter' => '#listing tr td:first-child',

        // CSS selector filter for TOC chapter links
        'table_of_content_links_filter' => 'a',

        // Pages dropdown
        'pages_list_filter' => '#pageMenu',

        // CSS selector filter for single image page
        'image_page_filter' => 'img#img:first-child',

        // CSS selector to get comic title
        'title_filter' => '#mangaproperties h1',
    ],

    'mangafox' => [

        // source base url
        'base_url' => 'http://mangafox.me/manga',

        // CSS selector filter for TOC page
        'table_of_content_filter' => '#chapters ul.chlist li',

        // CSS selector filter for TOC chapter links
        'table_of_content_links_filter' => 'a.tips',

        // Pages dropdown
        'pages_list_filter' => '#top_bar select.m',

        // CSS selector filter for single image page
        'image_page_filter' => '#viewer img:first-child',

        // CSS selector to get comic title
        'title_filter' => '#title h1',
    ],

    'mangahere' => [

        // source base url
        'base_url' => 'http://www.mangahere.co/manga',

        // CSS selector filter for TOC page
        'table_of_content_filter' => '#main .detail_list ul li',

        // CSS selector filter for TOC chapter links
        'table_of_content_links_filter' => 'a.color_0077',

        // Pages dropdown
        'pages_list_filter' => '#top_chapter_list',

        // CSS selector filter for single image page
        'image_page_filter' => '#viewer #image',

        // CSS selector to get comic title
        'title_filter' => '.title h2',
    ],
];