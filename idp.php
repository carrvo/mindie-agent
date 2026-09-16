<?php
declare(strict_types=1);

require '/usr/src/mindie-idp/selfauth/index.php';
require '/usr/src/mindie-idp/mintoken/endpoint.php';

$issuer = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
$app_url = $issuer . getenv('MIndieAgentPath');
$agent = getenv('MIndieAgentTitle') ?? 'MIndie-Agent';

$method = get_method();
$action = get_token_action();
if ($method === 'GET') {
    if ($action === 'metadata') {
        header('Content-type: application/json');
        $meta = [
	        "issuer" => $issuer,
	        "authorization_endpoint" => "$app_url?action=authorize",
	        "token_endpoint" => "$app_url?action=authorize",
	        "introspection_endpoint" => "$app_url?action=introspect",
	        "response_types_supported" => ["code"],
	        "response_modes_supported" => ["query"],
	        "grant_types_supported" => ["authorization_code"],
	        "token_endpoint_auth_methods_supported" => ["client_secret_basic"],
	        "introspection_endpoint_auth_methods_supported" => ["client_secret_basic"],
	        "service_documentation" => "https://indieauth.spec.indieweb.org/#indieauth-server-metadata-li-11",
	        "code_challenge_methods_supported" => ["S256"],
        ];
        exit(json_encode($meta));
    }
    if ($action === 'authorize') {
        $request = filter_input_array(INPUT_GET, [
            'grant_type' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => ['regexp' => '@^authorization_code$@'],
            ],
            'code' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => ['regexp' => '@^[\x20-\x7E]+$@'],
            ],
            'client_id' => FILTER_VALIDATE_URL,
            'redirect_uri' => FILTER_VALIDATE_URL,
        ]);
        if (in_array(null, $request, true) || in_array(false, $request, true)) {
            invalidRequest('missing field for request: '.print_r($request, true));
        }

        // query SelfAuth library directly
        $configs = load_user_config($app_url);

        // Scan through the existing users then
        // Exit if there are errors in the client supplied data.
        $user_verified = user_verify($request['code'], $request['redirect_uri'], $request['client_id'], $configs);
        if ($user_verified === false) {
            invalidRequest('Verification Failed: Given Code Was Invalid');
        }

        $info = get_response($request['code'], $user_verified);
        // end SelfAuth library

        exit(json_encode($info));
    }
    // else is a PROFILE request
    // see the end of the $method if-else statements for continuation of execution
} elseif ($method === 'POST') {
    $type = get_media_type();
    $token = get_token();
    if (!is_string($action)) {
        invalidRequest('no action provided');
    }
    // check if is POST+revoke request
    if ($action === 'revoke') {
        if (is_string($token)) {
            revokeToken($token);
        }
        header('HTTP/1.1 200 OK');
        exit();
    }
    // check if is POST+introspection request
    if ($action === 'introspect') {
        $tokenInfo = retrieveToken($token);
        token_introspection($token, $tokenInfo);
    }
    // else is a POST+authorization request
    $request = get_request();


    // query SelfAuth library directly
    $configs = load_user_config($app_url);

    // Scan through the existing users then
    // Exit if there are errors in the client supplied data.
    $user_verified = user_verify($request['code'], $request['redirect_uri'], $request['client_id'], $configs);
    if ($user_verified === false) {
        invalidRequest('Verification Failed: Given Code Was Invalid');
    }

    $info = get_response($request['code'], $user_verified);
    // end SelfAuth library


    $token = storeToken($info['me'], $request['client_id'], $info['scope']);
    header('HTTP/1.1 200 OK');
    header('Content-Type: application/json;charset=UTF-8');
    exit(json_encode([
        'access_token' => $token,
        'token_type' => 'Bearer',
        'scope' => $info['scope'],
        'me' => $info['me'],
    ]));
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: GET, POST');
    exit();
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="indieauth-metadata" href="<?php echo "$app_url?action=metadata" ?>" />
  <link rel="authorization_endpoint" href="<?php echo "$app_url?action=authorize" ?>" />
  <link rel="token_endpoint" href="<?php echo "$app_url?action=authorize" ?>" />
  <title><?php echo "$agent" ?></title>
  <style>
    @media (prefers-color-scheme: dark) {
      html { background-color: #111111; color: #fbfbfb; }
      a { color: #ca8465; }
    }
    body {
      line-height: 1.6;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
      text-align: center;
      margin: 1rem auto;
      min-height: 90vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }
    img.u-photo { border-radius: 50%; }
    ul { padding: 0; list-style: none; }
  </style>
</head>

<body>
  <main class="h-card" rel="author">
    <h1>
      I'm <span class="p-name"><?php echo "$agent" ?></span>.
    </h1>

    <p class="p-note">
      ...and I am a <em>bot</em> on the Internet.
    </p>

    <ul>
      <li><a class="u-uid u-url" href="<?php echo "$app_url" ?>">My Instance</a></li>
      <li><a class="u-url" href="https://github.com/carrvo/mindie-agent">My Source Code</a></li>
    </ul>
  </main>
</body>

</html>
