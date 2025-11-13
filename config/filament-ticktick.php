<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TickTick Access Token
    |--------------------------------------------------------------------------
    |
    | Your TickTick API access token. You can obtain this through the OAuth2
    | flow as described in the README. This package uses the buzkall/laravel-ticktick
    | package which requires OAuth2 authentication.
    |
    | To get your access token, follow the OAuth flow described at:
    | https://developer.ticktick.com/
    |
    */

    'access_token' => env('TICKTICK_ACCESS_TOKEN'),
];
