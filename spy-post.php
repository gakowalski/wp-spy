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

$post_html = file_get_contents($target);

// if no HTML found, exit
if (empty($post_html)) {
    echo "No HTML found\n";
    exit;
}

function join_regexes($regexes) {
    // if not array, return it as is
    if (!is_array($regexes)) {
        return $regexes;
    }

    // if no regexes, return empty string
    if (empty($regexes)) {
        return '';
    }

    // if only one regex, return it
    if (count($regexes) == 1) {
        return $regexes[0];
    }

    // if more than one regex, join them with | and wrap them in ()
    $joined_regexes = [];
    foreach ($regexes as $regex) {
        // remove / at the beginning and end of regex if present
        $regex = trim($regex, '/');
        $joined_regexes[] = $regex;
    }
    // join regexes by | and add / at the beginning and end of resulting regex
    return '/' . join('|', $joined_regexes) . '/';
}

function extract_from_html() {

}

function analyze_html($html) {
    $info_to_be_extracted = [
        'Canonical link' => [
            'regex' => '/<link rel="canonical" href="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Short link' => [
            'regex' => '/<link rel="shortlink" href="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Post ID' => [
            'regex' => '/<link rel="shortlink" href=".*\?p=(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Open Graph locale' => [
            'regex' => '/<meta property="og:locale" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Open Graph type' => [
            'regex' => '/<meta property="og:type" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Title' => [
            'regex' => '/<meta property="og:title" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Open Graph description' => [
            'regex' => '/<meta property="og:description" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Open Graph URL' => [
            'regex' => '/<meta property="og:url" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Open Graph site name' => [
            'regex' => '/<meta property="og:site_name" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Open Graph image' => [
            'regex' => '/<meta property="og:image" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Article publisher' => [
            'regex' => '/<meta property="article:publisher" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Post published time' => [
            'regex' => '/<meta property="article:published_time" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Post modified time' => [
            'regex' => '/<meta property="article:modified_time" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Author' => [
            'regex' => '/<meta name="author" content="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Generator' => [
            'regex' => '/( name="generator"| content="(:QUOTED:)"){2}/',
            'matches_index' => 2,
            'multiple' => true,
        ],
        'RSS feed' => [
            'regex' => '/<link rel="alternate" type="application\/rss\+xml" title=".*" href="(.*)" \/>/',
            'matches_index' => 1,
            'multiple' => true,
        ],
        'RSD link' => [
            'regex' => '/<link rel="EditURI" type="application\/rsd\+xml" title="RSD" href="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'oEmbed JSON link' => [
            'regex' => '/<link rel="alternate" type="application\/json\+oembed" href="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'oEmbed XML link' => [
            'regex' => '/<link rel="alternate" type="text\/xml\+oembed" href="(.*)" \/>/',
            'matches_index' => 1,
        ],
        'Page cached by' => [
            // Page cached by LiteSpeed Cache 5.6 on 2023-09-21 12:07:41
            'regex' => '/Page cached by (.*) on .*/',
            'matches_index' => 1,
        ],
        'Page cached on' => [
            // Page cached by LiteSpeed Cache 5.6 on 2023-09-21 12:07:41
            'regex' => '/Page cached by .* on (.*)/',
            'matches_index' => 1,
        ],
        'WP-API Post data link' => [
            // <link rel="alternate" type="application/json" href="https://example/wp-json/wp/v2/posts/659" />
            'regex' => '/<link rel="alternate" type="application\/json" href="(:QUOTED:)" \/>/',
            'matches_index' => 1,
        ],
    ];

    $info = [];

    // for each info to be extracted check execute regex and add result to $info
    // some info can be extracted multiple times, eg. multiple <meta name="generator" content="...">
    foreach ($info_to_be_extracted as $info_name => $info_data) {
        $matches = [];
        preg_match_all(strtr($info_data['regex'], [
            ' ' => '\s*',
            '"' => '[\'|"]',
            ':QUOTED:' => '[^"\']*',
        ]), $html, $matches, PREG_UNMATCHED_AS_NULL);

        if (empty($matches[$info_data['matches_index']])) {
            $info[$info_name] = 'Not found';
        } else {
            if (isset($info_data['multiple'])) {
                $info[$info_name] = $matches[$info_data['matches_index']];
            } else {
                $info[$info_name] = $matches[$info_data['matches_index']][0];
            }
        }
    }

    return $info;
}

function analyze_rsd($html) {
    $info_to_be_extracted = [
        'WP-API' => [
            'regex' => '/<api name="WP-API" blogID="\d" preferred="(true|false)" apiLink="(.*)" \/>/',
            'matches_index' => 2,
        ],
    ];

    $info = [];

    // for each info to be extracted check execute regex and add result to $info
    // some info can be extracted multiple times, eg. multiple <meta name="generator" content="...">
    foreach ($info_to_be_extracted as $info_name => $info_data) {
        $matches = [];
        preg_match_all($info_data['regex'], $html, $matches, PREG_UNMATCHED_AS_NULL);
        if (empty($matches[$info_data['matches_index']])) {
            $info[$info_name] = 'Not found';
        } else {
            if (isset($info_data['multiple'])) {
                $info[$info_name] = $matches[$info_data['matches_index']];
            } else {
                $info[$info_name] = $matches[$info_data['matches_index']][0];
            }
        }
    }

    return $info;
}

function analyze_oembed_json($json) {
    $info_to_be_extracted = [
        'Author' => 'author_name',
        'Author URL' => 'author_url',
        'Title' => 'title',
    ];

    $info = [];

    // get info from $json
    foreach ($info_to_be_extracted as $info_name => $info_key) {
        if (empty($json[$info_key])) {
            $info[$info_name] = 'Not found';
        } else {
            $info[$info_name] = $json[$info_key];
        }
    }

    return $info;
}

function analyze_wp_api_post_data($json) {
    $info_to_be_extracted = [
        'Post ID' => 'id',
        //'Post title' => 'title.rendered',
        'Auhtor ID' => 'author',
        //'Short link' => 'guid.rendered',
        'Post published time' => 'date_gmt',
        'Post modified time' => 'modified_gmt',
    ];

    $info = [];

    // get info from $json
    foreach ($info_to_be_extracted as $info_name => $info_key) {
        if (empty($json[$info_key])) {
            $info[$info_name] = 'Not found';
        } else {
            $info[$info_name] = $json[$info_key];
        }
    }

    return $info;
}

function combine_info_arrays($info, $new_info) {
    // find keys that are in both arrays and echo if they are different
    foreach ($info as $key => $value) {
        if (isset($new_info[$key])) {
            if ($value != $new_info[$key]) {
                echo "Different $key: $value vs. $new_info[$key]\n";
            }
        }
    }

    // find keys that are in $new_info but not in $info and add them to $info
    foreach ($new_info as $key => $value) {
        if (!isset($info[$key]) || $info[$key] == 'Not found') {
            $info[$key] = $value;
        }
    }

    return $info;
}

$html_analysis_results = analyze_html($post_html);
if (empty($html_analysis_results)) {
    echo "No info found\n";
    exit;
}

// if isset 'RSD link', get contents and analyze RSD 
if (isset($html_analysis_results['RSD link'])) {
    $rsd_url = $html_analysis_results['RSD link'];
    $rsd_xml = file_get_contents($rsd_url);
    $rsd_analysis_results = analyze_rsd($rsd_xml);
    $html_analysis_results = combine_info_arrays($html_analysis_results, $rsd_analysis_results);
}

// if isset 'oEmbed JSON link', get contents and analyze oEmbed JSON
if (isset($html_analysis_results['oEmbed JSON link'])) {
    $oembed_json_url = $html_analysis_results['oEmbed JSON link'];
    $oembed_json = file_get_contents($oembed_json_url);
    // decode json
    $oembed_json = json_decode($oembed_json, true);
    $oembed_json_analysis_results = analyze_oembed_json($oembed_json);
    $html_analysis_results = combine_info_arrays($html_analysis_results, $oembed_json_analysis_results);
}

// if isset ''WP-API Post data link', get contents and analyze WP-API Post data
if (isset($html_analysis_results['WP-API Post data link'])) {
    $wp_api_post_data_url = $html_analysis_results['WP-API Post data link'];
    $wp_api_post_data_json = file_get_contents($wp_api_post_data_url);
    // decode json
    $wp_api_post_data_json = json_decode($wp_api_post_data_json, true);
    $wp_api_post_data_analysis_results = analyze_wp_api_post_data($wp_api_post_data_json);
    $html_analysis_results = combine_info_arrays($html_analysis_results, $wp_api_post_data_analysis_results);
}

// sort $html_analysis_results by key
ksort($html_analysis_results);

// print results to STDOUT using fputcsv
fputcsv(STDOUT, ['Info', 'Value']);
foreach ($html_analysis_results as $key => $value) {
    // if value is array, echo values in separate lines
    if (is_array($value)) {
        foreach ($value as $value2) {
            fputcsv(STDOUT, [$key, $value2]);
        }
    } else {
        fputcsv(STDOUT, [$key, $value]);
    }
}

// get Internet Archive timemap data
// https://web.archive.org/web/timemap/json?url=https%3A%2F%2Fexample.com%2F
$ia_timemap_url = 'https://web.archive.org/web/timemap/json?url=' . urlencode($target);
$ia_timemap_json = file_get_contents($ia_timemap_url);
$ia_timemap = json_decode($ia_timemap_json, true);  

// if no timemap found, exit
if (empty($ia_timemap)) {
    echo "No Internet Archive timemap found\n";
    exit;
} else {
    // remove first timestamp
    array_shift($ia_timemap);
}

// loop over timemap and get all timestamps, ignore first timestamp
$ia_timestamps = [];
foreach ($ia_timemap as $ia_timemap_entry) {
    $ia_timestamps[] = $ia_timemap_entry[1];
}

// for each timestamp generate URL to archived post
// echo to STDOUT using fputcsv
fputcsv(STDOUT, ['Timestamp', 'URL']);
foreach ($ia_timestamps as $ia_timestamp) {
    $ia_url = 'https://web.archive.org/web/' . $ia_timestamp . '/' . $target;
    fputcsv(STDOUT, [$ia_timestamp, $ia_url]);
}