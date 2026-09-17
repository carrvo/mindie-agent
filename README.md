# MIndie-Agent

An extension of [MIndie](https://github.com/carrvo/mindie)
that acts as an [IndieAuth](https://indieweb.org/IndieAuth) agent,
able to log into IndieAuth by itself without user interaction.
This can be used to build IndieAuth service-to-service agents.

## Setup

1. Clone
1. Run `make debian-package-dependencies` to install dependent *build* Debian packages
1. Run `make debian-package` to build package locally
1. Run `dpkg -i package/mindie-agent_X.X.X_all.deb` to install package locally
1. Modify the configuration for your Apache HTTPd configuration (installed to `/etc/apache2/conf-available/mindie-agent-instance.php.conf`)
    ```
    AliasMatch ^/mindie-agent/idp$ /usr/src/mindie-agent/idp.php
    <LocationMatch ^/mindie-agent/idp$>
	    # Required - this is the path to your idp endpoint that identifies your agent.
	    # Your agent will be required to have this as well.
	    SetEnv MIndieAgentPath "/mindie-agent/idp"
	    # Optional - give a title for your agent's public profile. Not used for the agent component.
	    SetEnv MIndieAgentTitle MIndie-Agent
    </LocationMatch>
    ```
1. Build your agent
    ```
	# Required - this is the path to your idp endpoint that identifies your agent.
	# Your agent will be required to have this as well.
	SetEnv MIndieAgentPath "/mindie-agent/idp"
	# Optional (only agent) - customize the agent string that outgoing requests identify as.
	SetEnv MIndieAgentAgent "MIndie-Agent (https://github.com/carrvo/mindie-agent) curl/8.5.0"

    # Required - call the login with your target's information or create your own login function.
    $idp_request = login($resource_uri, $login_page, $login_field);
    # Required - authenticate the login redirect. This will need to point to your idp component.
    $auth = authenticate($idp_request);
    # Optional - request the desired resource.
    # This example utilizes microformats (https://github.com/microformats/php-mf2)
    # and expacts that it authenticated against MIndie-Client (https://github.com/carrvo/mindie-client).
    $resource = mf2($resource_uri, $auth['oauth_token']['value']);
    ```

This will setup the following endpoints on your Apache server:
- `https://example.com/mindie-agent/idp`

## IndieAuth

### IndieAuth Pieces

The parties that this agent constitutes are:
- [x] user agent (this does all of the physical actions for you and is usually your browser)
- [x] user profile (this is your personal public webpage!)
- [] client service (this is the service you are trying to login to and use, this can be a webpage or a web resource)
- [x] IdP (this is what controls your identity and what you must Authenticate with before accessing a service)

### **Modified** IndieAuth Flow (with Metadata Discovery)

1. *MIndie-Agent (agent-instance.php)* **requests** the *client service* **login** page.
1. *MIndie-Agent (agent-instance.php)* **requests** the *client service* to perform a login with **MIndie-Agent's supplied URL**.
1. The *client service* **requests** *MIndie-Agent's profile (idp.php)* to **discover** the IdP metadata endpoint.
1. The *client service* **requests** *MIndie-Agent's (idp.php)* metadata endpoint to **discover** the IdP URL.
1. The *client service* **responds** to *MIndie-Agent (agent-instance.php)* with the IdP URL.
1. *MIndie-Agent (agent.php)* **Authenticates and Authorizes itself**.
1. *MIndie-Agent (agent.php)* generates an **authorization code**.
1. *MIndie-Agent (agent.php)* **requests (including the authorization code)** the *client service* to complete the login.
1. The *client service* **requests (including the authorization code)** *MIndie-Agent (idp.php)* to validate the login.
1. *MIndie-Agent (idp.php)*, upon valid authorization code, **responds** to the *client service* with an **access token**.
1. The *client service* **responds (including a cookie with the access token)** to *MIndie-Agent (idp.php)* with a login success.
1. *MIndie-Agent (agent-instance.php)* **requests (including the cookie with the access token)** the *client service* **webpage or resource**.
1. The *client service* **requests (including the access token)** *MIndie-Agent (idp.php)* for token information (called introspection).
1. *MIndie-Agent (idp.php)*, upon valid access token, **responds** to the *client service* with an **identity token**.
1. The *client service*, upon valid Authorization, **responds** to *MIndie-Agent (agent-instance.php)* with the appropriate **webpage or resource**.

Note that for a non-browser agent (including a client-side script), it would return the **access token** directly, instead of inside a cookie; and then the agent would have to include the `Authorize: Bearer <access token>` header instead of sending the **access token** inside a cookie.

## License

Copyright 2026 by carrvo. Available under the MIT license.

### Licenses for Dependencies

- [MIndie-IdP](https://github.com/carrvo/mindie-idp) - Copyright 2024 by carrvo. Available under the MIT license.
- [MIndie-Profile](https://github.com/carrvo/mindie-profile) - CC0

Example Dependencies
- [php-mf2](https://github.com/microformats/php-mf2) - [CC0 1.0 Universal](https://creativecommons.org/publicdomain/zero/1.0/)

