<?php
/*
 * First authored by Brian Cray
  * Edited by: niklasnx
 * License: http://creativecommons.org/licenses/by/3.0/
 * Contact the author at http://briancray.com/
 */

ini_set('display_errors', 0);

// Function to handle deprecated magic quotes
function sanitize($value) {
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }
    return stripslashes($value);
}

// Sanitize $_REQUEST['longurl']
$url_to_shorten = isset($_REQUEST['longurl']) ? sanitize(trim($_REQUEST['longurl'])) : '';

if(!empty($url_to_shorten) && preg_match('|^https?://|', $url_to_shorten))
{
    require('config.php');

    // check if the client IP is allowed to shorten
    if($_SERVER['REMOTE_ADDR'] != LIMIT_TO_IP)
    {
        die('You are not allowed to shorten URLs with this service.');
    }
    
    // check if the URL is valid
    if(CHECK_URL)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_to_shorten);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($ch);
        $response_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($response_status == '404')
        {
            die('Not a valid URL');
        }
        
    }
    
    // check if the URL has already been shortened
    $stmt = $mysqli->prepare('SELECT id FROM ' . DB_TABLE. ' WHERE long_url=?');
    $stmt->bind_param('s', $url_to_shorten);
    $stmt->execute();
    $stmt->bind_result($already_shortened);
    $stmt->fetch();
    $stmt->close();
    if(!empty($already_shortened))
    {
        // URL has already been shortened
        $shortened_url = getShortenedURLFromID($already_shortened);
    }
    else
    {
        // URL not in database, insert
        $stmt = $mysqli->prepare('INSERT INTO ' . DB_TABLE . ' (long_url, created, creator) VALUES (?, ?, ?)');
        $created_time = time();
        $creator = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('sis', $url_to_shorten, $created_time, $creator);
        $stmt->execute();
        $shortened_url = getShortenedURLFromID($mysqli->insert_id);
        $stmt->close();
    }
    echo BASE_HREF . $shortened_url;
}

function getShortenedURLFromID ($integer, $base = ALLOWED_CHARS)
{
    $out = '';
    $length = strlen($base);
    while($integer > $length - 1)
    {
        $out = $base[fmod($integer, $length)] . $out;
        $integer = floor( $integer / $length );
    }
    return $base[$integer] . $out;
}
?>
