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

$api_url = $target . 'wp-json/';
$api_json = file_get_contents($api_url);

// if no JSON data found, exit
if (empty($api_json)) {
    echo "No JSON data found\n";
    exit;
}

// decode JSON data
$api_data = json_decode($api_json, true);
$namespaces = $api_data['namespaces'];

$namepsace_to_plugin_name = [
    'ssp' => 'Seriously Simple Podcasting',
    'wordfence' => 'Wordfence',
    'yoast' => 'Yoast SEO',
    'divi' => 'Divi',
    'uag' => 'Ultimate Addons for Gutenberg (UAG, now Spectra)',
    'spectra' => 'Spectra - WordPress Gutenberg Blocks',
    'elementor' => 'Elementor',
    'elementor-pro' => 'Elementor Pro',
    'wp' => 'WordPress REST API',
    'otter' => 'Otter Blocks',
    'wp-site-health' => 'Wordpress Site Health',
    'wp-block-editor' => 'Wordpress Block Editor (Gutenberg)',
    'oembed' => 'Wordpress oEmbed Provider API',
    'litespeed' => 'LiteSpeed Cache',
    'simplystatic' => 'Simply Static',
    'google-site-kit' => 'Google Site Kit',
    'filebird' => 'FileBird - WordPress Media Library Folders & File Manager',
    'SBR' => 'Simple Basic Registration',
    'wp-super-cache' => 'WP Super Cache',
    'contact-form-7' => 'Contact Form 7',
    'duplicator' => 'Duplicator',
    'redirection' => 'Redirection',
];

fputcsv(STDOUT, ['Type', 'Name', 'Namespace', 'Version / Count', 'Schema']);
foreach ($namespaces as $namespace) {
    // split namespace into parts, eg. oembed/1.0 -> oembed, 1.0
    $namespace_parts = explode('/', $namespace);
    $namespace_name = $namespace_parts[0];
    $namespace_version = $namespace_parts[1];

    // get plugin name from $namepsace_to_plugin_name if exists, otherwise Unknown
    if (!isset($namepsace_to_plugin_name[$namespace_name])) {
        $plugin_name = 'Unknown';
    } else {
        $plugin_name = $namepsace_to_plugin_name[$namespace_name];
    }

    fputcsv(STDOUT, ['Plugin', $plugin_name, $namespace_name, $namespace_version, $api_url . $namespace]);
}

$api_json = file_get_contents($api_url . 'wp/v2/types');
$api_data = json_decode($api_json, true);

$types = $api_data;

$type_to_type_name = [
    'post' => 'Post',
    'page' => 'Page',
    'attachment' => 'Attachment',
    'nav_menu_item' => 'Menu Item',
    'wp_block' => 'Block',
    'wp_template' => 'Template',
    'wp_template_part' => 'Template Part',
    'wp_navigation' => 'Navigation',
    'spectra-popup' => 'Spectra Popup',
];

$types_for_logged_users = [
    'attachment',
    'wp_template',
    'wp_template_part',
    'nav_menu_item',
];

//fputcsv(STDOUT, ['Type', 'Name', 'Namespace', 'Version', 'Schema']);

foreach ($types as $type) {
    $type_name = $type['name'];
    $type_slug = $type['slug'];
    $rest_namespace = $type['rest_namespace'];
    $rest_base = $type['rest_base'];
    $rest_url = "$api_url$rest_namespace/$rest_base";

    // get type name from $type_to_type_name if exists, otherwise Unknown
    if (!isset($type_to_type_name[$type_slug])) {
        $type_name_2 = 'Unknown';
    } else {
        $type_name_2 = $type_to_type_name[$type_slug];
    }

    // if type is $types_for_logged_users
    if (!in_array($type_slug, $types_for_logged_users)) {
        // get JSON contents from $rest_url
        $rest_json = file_get_contents($rest_url);

        // decode JSON data
        $rest_data = json_decode($rest_json, true);

        // if JSON data found, check if it is an array and count the number of elements
        if (!empty($rest_data)) {
            if (is_array($rest_data)) {
                $rest_count = count($rest_data);

                // check if $http_response_header contains header 'X-WP-Total' and use it as count
                foreach ($http_response_header as $header) {
                    $header_lowercase = strtolower($header);
                    $pattern_lowercase = strtolower('X-WP-Total:');
                    if (str_starts_with($header_lowercase, $pattern_lowercase)) {
                        $rest_count = str_replace("$pattern_lowercase ", '', $header_lowercase);
                    }
                }
            } else {
                $rest_count = 'not an array';
            }
        } else {
            $rest_count = 'no data';
        }
    } else {
        $rest_count = 'requires login';
    }

    fputcsv(STDOUT, ['Type', "$type_name ($type_name_2)", $type_slug, $rest_count, $rest_url]);
}
