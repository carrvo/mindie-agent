<?php

require __DIR__ . '/agent.php';
require __DIR__ . '/vendor/autoload.php';
use Mf2;

/*
 * See https://github.com/microformats/php-mf2
 */
function mf2($resource_uri: string, $access_token: string)
{
    #$authorization_header = "Authorization: Bearer $access_token";
    $curl = initAgentCurl($resource_uri);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BEARER);
    curl_setopt($curl, CURLOPT_XOAUTH2_BEARER, $access_token);
    #curl_setopt($curl, CURLOPT_HTTPHEADER, array($authorization));
    curl_setopt($curl, CURLOPT_COOKIE, "oauth_token=$access_token");
    $body = curl_exec($curl);
    curl_close($curl);
    $error_code = curl_errno($curl);
    if (!$error_code) {
        $error = curl_error($curl);
        throw "Request to `$resource_uri` had error `$error_code $error`";
    }
    $parsed = Mf2\parse($body, $resource_uri);
    return $parsed;
}

setup();
$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '@^[!#$%&\'*+.^_`|~0-9a-z-]+$@i']]);
if ($method === 'POST') {
    $resource_uri = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);
    $login_page = filter_input(INPUT_POST, 'login', FILTER_VALIDATE_URL);
    $login_field = filter_input(INPUT_POST, 'field', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '@^[0-9a-z_-]+$@i']]);
    $idp_request = login($resource_uri, $login_page, $login_field);
    $auth = authenticate($idp_request);
    $resource = mf2($resource_uri, $auth['access_token']);
}

?><!doctype html>
<html>
    <head>
        <title></title>
        <style>
h1{text-align:center;margin-top:3%;}
body {text-align:center;}
fieldset, pre {width:400px; margin-left:auto; margin-right:auto;margin-bottom:50px; background-color:#FFC; min-height:1em;}

.form-login{ 
margin-left:auto;
width:300px;
margin-right:auto;
text-align:center;
margin-top:20px;
border:solid 1px black;
padding:20px;
}
.form-line{ margin:5px 0 0 0;}
.submit{width:100%}
.yellow{background-color:#FFC}

        </style>
    </head>
    <body>
        <form method="POST" action="">
            <h1>Resource</h1>
            <div class="form-login">
                <p class="form-line">
                    Logging in as:<br />
                    <span class="yellow"><?php echo htmlspecialchars(getAppUrl()); ?></span>
                </p>
                <div class="form-line">
                    <label for="url">URL:</label><br />
                    <input type="url" name="url" id="url" />
                </div>
                <div class="form-line">
                    <label for="login">Login:</label><br />
                    <input type="url" name="login" id="login" />
                </div>
                <div class="form-line">
                    <label for="field">Login Field:</label><br />
                    <input type="text" name="field" id="field" />
                </div>
                <div class="form-line">
                    <input class="submit" type="submit" name="submit" value="Submit" />
                </div>
            </div>
        </form>
        <?php if ($resource) ?>
        <blockquote>
            <?php echo var_dump($resource) ?>
        </blockquote>
        <?php endif ?>
    </body>
</html>
