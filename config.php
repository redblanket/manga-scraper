<?php

return [
    // source base url
    'base_url' => 'http://mangapanda.com',

    // default download path, can be override from command
    'download_path' => '/Volumes/Storage HD/Comics',

    // CSS selector filter for TOC page
    'table_of_content_filter' => '#listing tr td:first-child',

    // CSS selector filter for single image page
    'image_page_filter' => 'img#img:first-child',

    // How long to pause between chapter
    'page_sleep' => 10,

    // How long to pause between images
    'image_sleep' => 3,

    // Maximum number of retry. Experimental. Might not working as expected.
    'max_retry' => 5,
];
