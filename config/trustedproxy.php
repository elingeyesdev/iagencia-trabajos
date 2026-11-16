<?php

/*
|--------------------------------------------------------------------------
| Proxies de confianza
|--------------------------------------------------------------------------
|
| Leído por Illuminate\Http\Middleware\TrustProxies. En Render el contenedor solo recibe tráfico
| a través de su proxy (que termina TLS), cuyas IP no son fijas: allí se define TRUSTED_PROXIES=*
| (confía únicamente en el par que conecta directamente, REMOTE_ADDR). En Laragon se deja sin definir:
| no se confía en ningún proxy y las cabeceras X-Forwarded-* se ignoran.
| También admite una lista de IP o rangos CIDR separados por comas.
| Las cabeceras aceptadas se limitan en bootstrap/app.php (X-Forwarded-For y X-Forwarded-Proto).
|
*/

return [
    'proxies' => env('TRUSTED_PROXIES'),
];
