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

-- Request the ship data for the 300i with german locale
-- Docs: https://docs.star-citizen.wiki/star_citizen_api.html#raumschiffe
-- Output: https://api.star-citizen.wiki/api/v2/vehicles/300i?locale=de_DE
local ship_300i = api.get( 'StarCitizenWikiAPI', 'v2/vehicles/300i', {
    locale = 'de_DE',
} )
local json = mw.text.jsonDecode( ship_300i )


-- Request data for the 300i with both german and english locale
local ship_300i_multi_locale = api.get( 'StarCitizenWikiAPI', 'v2/vehicles/300i', {
    locale = { 'de_DE', 'en_EN' }
} )

-- Request data for the 300i with english locale and included components
local ship_300i_with_components = api.get( 'StarCitizenWikiAPI', 'v2/vehicles/300i', {
    locale = 'en_EN',
    include = { 'components' }
} )

-- Request data for the Greycat Industrial ROC
-- Docs: https://docs.star-citizen.wiki/v2
-- Output: https://api.star-citizen.wiki/api/v2/vehicles/ROC
local roc = api.get( 'StarCitizenWikiAPI', 'v2/vehicles/ROC' )
```
