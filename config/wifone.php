<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding
    |--------------------------------------------------------------------------
    |
    | Sites allowed to show Wifone in a frame, besides Wifone itself: origins
    | separated by spaces, e.g. the Life OS game on
    | "http://localhost:5173 http://localhost:4173". Framed, you sign in once
    | and stay signed in there, on a session kept apart from your normal one
    | (see EmbeddedSession), and the page tells the site around it about your
    | calls (see resources/js/embedBridge.js). Unset, nothing else may frame it.
    |
    */

    'embed' => [
        'origins' => array_values(array_filter(explode(' ', (string) env('WIFONE_EMBED_ORIGINS', '')))),
    ],

];
