<?php
$issuer = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
$current_page = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$agent = getenv('MIndieAgentTitle') ?? 'MIndie-Agent';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="indieauth-metadata" href="<?php echo "$issuer/.well-known/indieauth-agent" ?>" />
  <link rel="authorization_endpoint" href="<?php echo "$issuer/mindie-agent/index" ?>" />
  <link rel="token_endpoint" href="<?php echo "$issuer/mindie-agent/token" ?>" />
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
      <li><a class="u-uid u-url" href="<?php echo "$current_page" ?>">My Instance</a></li>
      <li><a class="u-url" href="https://github.com/carrvo/mindie-agent">My Source Code</a></li>
    </ul>
  </main>
</body>

</html>
