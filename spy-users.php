<?php

// if first argument does not contain target URL, ask for it and read it from stdin
if (empty($argv[1])) {
    echo "Enter target URL: ";
    $target = trim(fgets(STDIN));
} else {
    $target = $argv[1];
}

// if target URL does not contain http:// or https://, add https://
if (strpos($target, 'http://') === false && strpos($target, 'https://') === false) {
    $target = 'https://' . $target;
}

// if target URL does not end with /, add it
if (substr($target, -1) != '/') {
    $target .= '/';
}

// prepare context for file_get_contents so it does not verify SSL certificate
$opts = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
    ),
);

// get context
$context = stream_context_create($opts);

$frontpage_html = file_get_contents($target, context: $context);

// if no HTML found, exit
if (empty($frontpage_html)) {
    echo "No HTML found\n";
    exit;
}

/*
// extract wp-json API URL from the $frontpage_html, eg. <link rel="https://api.w.org/" href="https://example.com/wp-json/" />
// extract the href part using regular expressions
preg_match('/<link rel="https:\/\/api.w.org\/" href="(.*)" \/>/', $frontpage_html, $matches);

// if no API URL found, exit
if (empty($matches[1])) {
    echo "No API URL found\n";
    exit;
} else {
    echo "API URL found: " . $matches[1] . "\n";
}
*/

// read JSON data from the API endpoint /wp/v2/users
/*
$api_url = $matches[1] . 'wp/v2/users';
*/

$api_url = $target . 'wp-json/wp/v2/users';
$api_json = file_get_contents($api_url, context: $context);

// if no JSON data found, exit
if (empty($api_json)) {
    echo "No JSON data found\n";
    exit;
}

// decode JSON data
$api_data = json_decode($api_json, true);

// from each entry read fileds: id, name, slug, url, link and description
// and print them to stdout using fputcsv()
$fp = fopen('php://stdout', 'w');
fputcsv($fp, array('id', 'name', 'slug', 'url', 'link', 'description'));
foreach ($api_data as $user) {
    fputcsv($fp, array($user['id'], $user['name'], $user['slug'], $user['url'], $user['link'], $user['description']));
}