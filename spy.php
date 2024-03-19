<?php

// if first argument does not contain target URL, ask for it and read it from stdin
if (empty($argv[1])) {
    echo "Enter target URL: ";
    $target = trim(fgets(STDIN));
} else {
    $target = $argv[1];
}

// if second argument does not contain starting ID, assume it is 1
if (empty($argv[2])) {
    $post_id_first = 1;
} else {
    $post_id_first = $argv[2];
}

// if third argument does not contain starting ID, assume it is 1
if (empty($argv[3])) {
    $post_id_last = null;
} else {
    $post_id_last = $argv[3];
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

if ($post_id_last === null) {
    $frontpage_html = file_get_contents($target, context: $context);

    // if no HTML found, exit
    if (empty($frontpage_html)) {
        echo "No HTML found\n";
        exit;
    }

    // get link to RSS feed, extract it from the $frontpage_html
    // it is in the form of <link rel="alternate" type="application/rss+xml" title="RSS" href="http://www.example.com/rss.xml" />
    // exztract the href part using regular expressions
    preg_match('/<link rel="alternate" type="application\/rss\+xml" title=".*" href="(.*)" \/>/', $frontpage_html, $matches);

    // if no RSS feed found, exit
    if (empty($matches[1])) {
        echo "No RSS feed found, trying to guess one\n";

        $rss_url = strtr("$target/feed/", [ '//' => '/']);
        echo "Trying " . $rss_url . "\n";
    } else {
        echo "RSS feed found: " . $matches[1] . "\n";

        // get RSS feed URL
        $rss_url = $matches[1];
    }



    // get RSS feed content
    $rss_xml = file_get_contents($rss_url, context: $context);

    if (empty($rss_xml)) {
        echo "No RSS XML found\n";
        exit;
    }

    // retrieve link in <guid> tag
    // it is in the form of <guid isPermaLink="false">http://www.example.com/?p=123</guid>
    // extract the URL part using regular expressions
    preg_match('/<guid isPermaLink="false">(.*)<\/guid>/', $rss_xml, $matches);

    // if no link found, exit
    if (empty($matches[1])) {
        echo "No GUID link found\n";
        exit;
    } else {
        echo "GUID link found: " . $matches[1] . "\n";
    }

    // extract post ID from the link
    preg_match('/\?p=(.*)/', $matches[1], $matches);

    // if no post ID found, exit
    if (empty($matches[1])) {
        echo "No post ID found\n";
        exit;
    } else {
        echo "Post ID found: " . $matches[1] . "\n";
    }
}

// get post ID
if ($post_id_last === null) {
    $post_id_latest = $matches[1];
} else {
    $post_id_latest = $post_id_last;
}

// get all posts from 1 do $post_id_latest, follow all redirections
// if you get a 404, ignore it
// if you get a 301, get the new URL and continue
// if you get a 200, get the URL, print it on the screen and save it to $posts array
$posts = array();
for ($i = $post_id_first; $i <= $post_id_latest; $i++) {
    $url = $target . '?p=' . $i;
    echo "$i / $post_id_latest  -> ";
    echo "Checking " . $url . " -> ";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // ignore SSL errors
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // set user agent
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:45.0) Gecko/20100101 Firefox/45.0');
    // set timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // set max redirects
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    // set cookie file
    //curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
    //curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($http_code == 0) {
        echo "Error: " . curl_error($ch) . "\n";
    }
    echo "HTTP code: " . $http_code . "\n";
    curl_close($ch);
    if ($http_code != 404) {
        // extract URL from HTML from link rel="canonical"
        preg_match('/<link rel="canonical" href="(.*)" \/>/', $response, $matches);
        $type = 'canonical';
        
        // if no canonical URL found, extract URL from HTML from meta property="og:url"
        if (empty($matches[1])) {
            preg_match('/<meta property="og:url" content="(.*)" \/>/', $response, $matches);
            $type = 'og:url';
        }

        if (!empty($matches[1])) {
            $url = $matches[1];
            echo "$type URL found: " . $url . "\n";
            $posts[] = [
                'id' => $i,
                'url' => $url,
                'type' => $type,
            ];
        }
    }
}

$found_posts_count = count($posts);

// sanitize target URL so it can be used as a filename
$target_filename = str_replace(array('http://', 'https://', '/'), array('', '', '_'), $target);

// file handle to write to file $target_filename.csv
$fh = fopen($target_filename . '.csv', 'w');

// print all posts
echo "Found $found_posts_count posts:\n";
fputcsv($fh, ['id', 'url', 'type']);
foreach ($posts as $post) {
    fputcsv($fh, $post);

    // also fputcsv to stdout
    fputcsv(STDOUT, $post);
}

fclose($fh);