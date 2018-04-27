<?php
if(!isset($argv[1])) {
  die("Usage: php convert.php {folder}\n");
}

$path = $argv[1];

if(!file_exists($path)) {
  die("Folder not found\n");
}

$files = glob($path.'/data/js/tweets/*.js');

if(count($files) == 0) {
  die("No tweet data was found in the archive folder\n");
}

if(!file_exists('.shorturls.json'))
  $shorturls = [];
else
  $shorturls = json_decode(file_get_contents('.shorturls.json'), true);

$out = fopen('tweets.csv', 'w');

// Tell Excel this is UTF-8 data so emoji show up right
fputs($out, $bom = (chr(0xEF).chr(0xBB).chr(0xBF)));

$headers = [
  'Date',
  'Time',
  'Text',
  'Mentioned URL',
  'Tweet Link',
  'Photo',
  'Source',
];

fputcsv($out, $headers);

$tz = new DateTimeZone('America/Los_Angeles');

$domains = [];
$shortdomains = [
  'bit.ly', 'buff.ly', 'goo.gl', 'ed.gr', 'j.mp', 'dlvr.it', 'tcrn.ch',
];

foreach($files as $file) {
  echo "Processing $file\n";

  $filedata = file_get_contents($file);

  // Remove the first line
  $filedata = strip_first_line($filedata);

  $tweets = json_decode($filedata, true);

  foreach($tweets as $tweet) {
    $data = [];

    $date = new DateTime($tweet['created_at']);
    $date->setTimeZone($tz);
    $data[] = $date->format('Y-m-d');
    $data[] = $date->format('H:i:s');

    $mentioned_url = false;

    $text = $tweet['text'];

    // Expand t.co links in tweets
    if(isset($tweet['entities']['urls'])) {
      foreach($tweet['entities']['urls'] as $tco) {

        $domain = parse_url($tco['expanded_url'], PHP_URL_HOST);
        if(!in_array($domain, $domains))
          $domains[] = $domain;

        if(in_array($domain, $shortdomains)) {
          if(array_key_exists($tco['expanded_url'], $shorturls)) {
            $tco['expanded_url'] = $shorturls[$tco['expanded_url']];
          } else {
            // Un-shorten the link and store in the cache
            echo "Unshortening ".$tco['expanded_url']."\n";
            $full = unshorten($tco['expanded_url']);
            if($full) {
              $full = strip_tracking_params($full);
              echo "  -> ".$full."\n";
              $shorturls[$tco['expanded_url']] = $full;
              $tco['expanded_url'] = $full;
            }
          }
        }

        if(!$mentioned_url)
          $mentioned_url = $tco['expanded_url'];

        $text = str_replace($tco['url'], $tco['expanded_url'], $text);
      }
    }

    // Remove photo URLs
    $photo = '';
    if(isset($tweet['entities']['media'])) {
      foreach($tweet['entities']['media'] as $tco) {
        $text = str_replace($tco['url'], '', $text);
        $photo = $tco['media_url_https'];
      }
    }

    $data[] = replace_entities($text);

    echo $text . "\n";

    if($mentioned_url) {
      $data[] = $mentioned_url;
    } else {
      $data[] = '';
    }

    $data[] = 'https://twitter.com/'.$tweet['user']['screen_name'].'/status/'.$tweet['id_str'];

    $data[] = $photo;

    $data[] = strip_tags($tweet['source']);

    fputcsv_rn($out, $data);
  }

}

fclose($out);

// Write all the shorturl mappings to the cache
file_put_contents('.shorturls.json', json_encode($shorturls, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));

#print_r($domains);


function strip_first_line($str) {
  return substr($str, strpos($str, "\n") + 1);
}

function fputcsv_rn($fp, $array) {
  $eol = "\r\n";
  fputcsv($fp, $array);
  if("\n" != $eol && 0 === fseek($fp, -1, SEEK_CUR)) {
    fwrite($fp, $eol);
  }
}

function unshorten($url) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_exec($ch);
  $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  if($final)
    return $final;
  else
    return false;
}

function strip_tracking_params($url) {
  $parts = parse_url($url);

  if(!array_key_exists('query', $parts))
    return $url;

  parse_str($parts['query'], $params);
  $new_params = [];
  foreach($params as $key=>$val) {
    if(substr($key, 0, 4) != 'utm_')
      $new_params[$key] = $val;
  }
  $parts['query'] = http_build_query($new_params);
  return build_url($parts);
}

function build_url($parsed_url) {
  $scheme   = !empty($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
  $host     = !empty($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = !empty($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = !empty($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = !empty($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = !empty($parsed_url['path']) ? $parsed_url['path'] : '';
  $query    = !empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = !empty($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
}

function replace_entities($text) {
  return str_replace([
    '&gt;', '&lt;',
  ], [
    '>',    '<',
  ], $text);
}
