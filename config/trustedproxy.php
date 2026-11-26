<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| Which peers may supply the forwarded headers used to resolve the client IP.
| This is security-relevant: the login-attempt limiter is keyed on the resolved
| client IP, so a peer that is trusted when it should not be can let a client
| forge that IP and reset its own failed-attempt counter.
|
| Read per request by Illuminate\Http\Middleware\TrustProxies (it is in the
| default global middleware stack), so it can come from the environment without
| touching bootstrap/app.php.
|
| '*' (the default) is Laravel's "trust the CALLING IP only" mode. The immediate
| peer is trusted and the client IP is taken from the RIGHT-MOST
| X-Forwarded-For entry — the address the edge proxy itself observed. That is
| correct for a single edge proxy (e.g. Railway) and ignores any addresses a
| client prepends. It cannot protect a container that is ALSO reachable
| directly, because the peer would then be the client.
|
| Set TRUSTED_PROXIES to the proxy IPs/CIDRs that actually sit in front of the
| application (comma separated) when they are fixed. A directly exposed
| deployment should point it at those edge addresses only, or use an empty value
| to trust no forwarding header at all (the socket peer is then used).
|
| Do not guess platform addresses: if the proxy addresses are not fixed, keep
| the default and make sure the container is only reachable through that proxy.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES', '*'),

];
