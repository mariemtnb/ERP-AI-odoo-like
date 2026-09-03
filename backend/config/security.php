<?php

return [
    /*
    | Requests per minute allowed on the API, keyed per authenticated user (or
    | per IP when unauthenticated). This is the global ceiling applied to every
    | endpoint; sensitive routes (login, register, sending mail...) keep their
    | own tighter per-route throttles on top.
    */
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 300),
];
