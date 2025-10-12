# Apiunto

Lua Extension for MediaWiki to access REST APIs.

## Installation
Add the following to your `LocalSettings.php`
```php
wfLoadExtension( 'Apiunto' );

$wgApiuntoSources = [
    'StarCitizenWikiAPI' => [
        'baseUrl' => 'https://api.star-citizen.wiki',
        'token' => '', // optional
        'timeout' => 5, // optional, default 5
        'cacheDuration' => 3600, // optional, default 86400
    ]
];
```

## Lua Usage
```lua
local api = mw.ext.Apiunto

-- Request data for the Greycat Industrial ROC
-- Docs: https://docs.star-citizen.wiki/v2
-- Output: https://api.star-citizen.wiki/api/v2/vehicles/ROC
local roc = api.fetch( 'StarCitizenWikiAPI', 'v2/vehicles/ROC' )
```
