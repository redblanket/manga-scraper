<?php

return [

    'mangapanda' => [

        // source base url
        'base_url' => 'http://mangapanda.com',

        // CSS selector filter for TOC page
        'table_of_content_filter' => '#listing tr td:first-child',

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

        // Pages dropdown
        'pages_list_filter' => '#top_bar select.m',

        // CSS selector filter for single image page
        'image_page_filter' => '#viewer img:first-child',

        // CSS selector to get comic title
        'title_filter' => '#title h1',
    ],
];