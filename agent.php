<?php

require '/usr/src/mindie-idp/selfauth/index.php';

$issuer = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
$app_url = $issuer . preg_replace('/index.*$/', '', $_SERVER['REQUEST_URI']);
#$app_url = "$issuer/mindie-agent/index";

function getAppUrl(): string
{
    global issuer, app_url;
    return $app_url;
}

function initAgentCurl(string $url): CurlHandle|false
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 8);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, round(MINTOKEN_CURL_TIMEOUT * 1000));
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 2000);
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2);

    $agent = getenv('MIndieAgent');
    if ($agent === false) {
        $agent = 'MIndie-Agent (https://github.com/carrvo/mindie-agent) curl/8.5.0';
    }
    curl_setopt($curl, CURLOPT_USERAGENT, "$agent");
    
    return $curl;
}

/*
 * must call with `curl_setopt($curl, CURLOPT_HEADER, true);` first
 * Credit: https://stackoverflow.com/a/895858/7163041
 *
 * must call with `curl_setopt($curl, CURLOPT_COOKIELIST, array(''));`
 *   Credit: https://stackoverflow.com/questions/9714360/reading-cookie-when-using-curl-in-php-how-to#comment71856937_41309070
 * replace this function with `curl_getinfo($curl, CURLINFO_COOKIELIST);`
 *   (does not need `curl_setopt($curl, CURLOPT_HEADER, true);`)
 */
function parseCookies($body)
{
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $body, $matches);
    $cookies = array();
    foreach($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }
    return $cookies;
}

/*
 * Credit: https://stackoverflow.com/a/35207936/7163041
 */
function build_url(array $parts) {
    return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') . 
        ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') . 
        (isset($parts['user']) ? "{$parts['user']}" : '') . 
        (isset($parts['pass']) ? ":{$parts['pass']}" : '') . 
        (isset($parts['user']) ? '@' : '') . 
        (isset($parts['host']) ? "{$parts['host']}" : '') . 
        (isset($parts['port']) ? ":{$parts['port']}" : '') . 
        (isset($parts['path']) ? "{$parts['path']}" : '') . 
        (isset($parts['query']) ? "?{$parts['query']}" : '') . 
        (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
}

function login($resource_uri : string, $login_page: string, $login_field: string = 'url'): string {
    $curl = initAgentCurl($login_page);
    $body = curl_exec($curl);
    curl_close($curl);
    $error_code = curl_errno($curl);
    if (!$error_code) {
        $error = curl_error($curl);
        throw "Request to `$login_page` had error `$error_code $error`";
    }
    
    // see https://www.php.net/manual/en/class.domdocument.php
    // see https://www.php.net/manual/en/class.dom-htmldocument.php
    $dom = new DOMDocument();
    $dom->loadHTML($body);
    $form = $dom->getElementsByTagName('form')->item(0);
    $action = $form->getAttribute('action');
    $method = filter_var($form->getAttribute('method'), FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '@^[!#$%&\'*+.^_`|~0-9a-z-]+$@i']]);
    $data = [];
    $inputs = $form->getElementsByTagName('input');
    foreach ($inputs as $input) {
        $name = $input->getAttribute('name');
        $value = $input->getAttribute('value');
        if ($name) {
            $data[$name] = $value;
        }
    }
    
    if (parse_url($action, PHP_URL_SCHEME) === false) {
        # need to fix because relative
        $login = parse_url($login_page);
        $login['path'] = $action;
        $action = build_url($login);
    }
    $data[$login_field] = getAppUrl();
    
    $curl = initAgentCurl($action);
    if ($method === 'GET') {
        $curl = initAgentCurl($action . '?' . http_build_query($data));
        curl_setopt($curl, CURLOPT_GET, true);
    }
    else if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    else {
        throw "invalid login method $method";
    }
    $body = curl_exec($curl);
    curl_close($curl);
    $error_code = curl_errno($curl);
    if (!$error_code) {
        $error = curl_error($curl);
        throw "Request to `$action` had error `$error_code $error`";
    }
    $redirect = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
    return $redirect;
}

function authenticate($idp_request: string): array
{
    global issuer, app_url;
    $idp = parse_url($idp_request);
    if (strcmp($idp['scheme'], 'http' . (isset($_SERVER['HTTPS']) ? 's' : '')) !== 0) {
        throw 'Invalid IDP: The scheme is invalid.';
    }
    if (strcmp($idp['host'], $_SERVER['HTTP_HOST']) !== 0) {
        throw 'Invalid IDP: The host is invalid.';
    }
    if (strcmp($idp['path'], preg_replace('/index.*$/', '', $_SERVER['REQUEST_URI'])) !== 0) {
        throw 'Invalid IDP: The path is invalid.';
    }
    parse_str($idp['query'], $idp_input);
    
    $me = filter_var($idp_input['me'], FILTER_VALIDATE_URL);
    $config = load_user_config($app_url, $me)[0];
    $client_id = filter_var($idp_input['client_id'], FILTER_VALIDATE_URL);
    $redirect_uri = filter_var($idp_input['redirect_uri'], FILTER_VALIDATE_URL);
    $state = filter_var_regexp($idp_input['state'], '@^[\x20-\x7E]*$@');
    $response_type = filter_var_regexp($idp_input['response_type'], '@^(id|code)?$@');
    $scope = filter_var_regexp($idp_input['scope'], '@^([\x21\x23-\x5B\x5D-\x7E]+( [\x21\x23-\x5B\x5D-\x7E]+)*)?$@');
    
    verify_client_supplied_data($me, $config, $client_id, $redirect_uri, $state, $response_type, $scope);
    if ($scope === '') { // scope is left empty.
        // Treat empty parameters as if omitted.
        $scope = null;
    }
    
    $client_meta = client_info($client_id);
    
    $code = create_signed_code($config['app_key'], $config['user_url'] . $redirect_uri . $client_id, 5 * 60, $scope);

    $final_redir = $redirect_uri;
    if (strpos($redirect_uri, '?') === false) {
        $final_redir .= '?';
    } else {
        $final_redir .= '&';
    }
    $parameters = array(
        'code' => $code,
        'iss' => $issuer,
        'me' => $config['user_url']
    );
    if ($state !== null) {
        $parameters['state'] = $state;
    }
    $final_redir .= http_build_query($parameters);

    // Optional logging for successful logins.
    //
    // Enabling this on shared hosting may not be a good idea if syslog
    // isn't private and accessible. Enable with caution.
    if (function_exists('syslog') && defined('SYSLOG_SUCCESS') && SYSLOG_SUCCESS === 'I understand') {
        syslog(LOG_INFO, sprintf(
            'IndieAuth: login from %s for %s',
            $_SERVER['REMOTE_ADDR'],
            $me
        ));
    }
    
    $curl = initAgentCurl($final_redir);
    #curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_COOKIELIST, array(''));
    $body = curl_exec($curl);
    curl_close($curl);
    $error_code = curl_errno($curl);
    if (!$error_code) {
        $error = curl_error($curl);
        #$info = curl_getinfo($curl);
        throw "Request to `$final_redir` had error `$error_code $error`";
    }
    $complete_redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $cookies = curl_getinfo($curl, CURLINFO_COOKIELIST); #parseCookies($body);
    
    $result = array(
        'service' => $client_meta,
        'state' => $state,
        'redirect' => $complete_redirect,
    );
    $result = array_merge($result, $parameters);
    $result = array_merge($result, $cookies);
    return $result;
}

function setup()
{
    global issuer, app_url;
    $config = load_user_config($app_url, null);
    if ($config !== null) {
        # this is already configured!
        return;
    }
    
    define('RANDOM_BYTE_COUNT', 32);
    if (function_exists('random_bytes')) {
        $bytes = random_bytes(RANDOM_BYTE_COUNT);
        $strong_crypto = true;
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(RANDOM_BYTE_COUNT, $strong_crypto);
    } else {
        $bytes = '';
        for ($i=0; $i < RANDOM_BYTE_COUNT; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        $strong_crypto = false;
    }
    $app_key = bin2hex($bytes);
    $pass = md5($app_url . $app_key . $app_key);
    
    $pdo = connectToDatabase();
    for ($i = 0; $i < 10; $i++) {
        // We have to prepare inside the loop, https://github.com/teamtnt/tntsearch/pull/126
        $statement = $pdo->prepare('INSERT INTO logins (app_url, app_key, user_hash, user_url) VALUES (?, ?, ?, ?)');
        try {
            $statement->execute([$app_url, $app_key, $pass, $app_url]);
        } catch (PDOException $e) {
            $lastException = $e;
            if ($statement->errorInfo()[1] !== 19) {
                throw $e;
            }
            continue;
        }
        break;
    }
    if ($lastException !== null) {
        throw $e;
    }
}

?>
