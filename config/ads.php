<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ad Management
|--------------------------------------------------------------------------
|
| Toggle for the AdManagementService integrations (Google Ads, Meta,
| YouTube). When disabled, the service's "create campaign" methods
| throw FeatureNotImplementedException with a clear message rather than
| pretending to dispatch and silently dropping the request.
|
| Production deployments that have wired the SDK + credentials flip
| this to true via ADS_ENABLED=true.
|
*/

return [
    'enabled' => filter_var(env('ADS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
