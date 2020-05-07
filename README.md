# Plesk DDNS
A simple PHP script that can be called from routers to maintain DDNS records within Plesk.

## Features
### Core features
- Automatically detects the remote IP.
- Adds it to an `A` record for a given subdomain.
- Deletes all other records for that subdomain.

### Security and robustness
- Restricts access using a configurable key.
- Only updates records for predefined subdomains.
- Uses the [Plesk XML-RPC API](https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api.28709/) for robustness.
- Can only be called using a `POST` request.
- Returns appropriate HTTP codes based on the result.

## Installation
Firstly, install the requirements using Composer. The script uses  [plesk/api-php-lib](https://github.com/plesk/api-php-lib) to interact with the Plesk API.

```bash
composer install
```

Secondly, configure the script. Copy the contents of `credentials.example.ini` to `credentials.ini` and update them as follows.
- `ddns_key` should contain a self-defined key to restrict access to the script.
- `ddns_hosts` should contain a comma-separated list of full subdomains that can be updated.
- `plesk_host` should contain the FQDN of the Plesk server.
- `plesk_key` should contain a secret key from Plesk, [generated using the CLI](https://support.plesk.com/hc/en-us/articles/115001284353-How-to-create-an-API-access-token-and-how-to-use-it-for-API-authentication).

```ini
[ddns]
ddns_key=<your-key-here>
ddns_hosts=home.example.com,home.example.org

[plesk]
plesk_host=plesk.example.com
plesk_key=<plesk-key-here>
```

Thirdly, upload all files to a webserver. The script can then be called from a router to keep the DDNS record updated. This can be achieved through some built-in functionality or using a custom script like below.

```bash
#!/bin/sh

KEY=<your-key-here>
DOMAIN=example.com
SUBDOMAIN=home

curl --silent --fail -X POST -d "key=$KEY&domain=$DOMAIN&subdomain=$SUBDOMAIN" "https://ddns.example.com/update.php" >/dev/null 2>&1
if [ $? -eq 0 ];
then
    # Do something on success
    echo "Success"
else
    # Do something on failure
    echo "Failure"
fi

```

## Disclaimer
This is an independent open source project. All product and company names are trademarks&trade; or registered&reg; trademarks of their respective holders. Use of them does not imply any affiliation with or endorsement by them. Plesk is a trademark of Plesk International GmbH.

## License
[MIT License](https://github.com/stijneikelboom/plesk_ddns/raw/master/LICENSE) 
