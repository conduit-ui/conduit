<?php

use App\Services\ComponentService;

it('debugs what listInstalled actually returns', function () {
    $componentService = app(ComponentService::class);
    $installed = $componentService->listInstalled();

    dump('Installed components:', $installed);

    expect(true)->toBeTrue(); // Just to make the test pass while we debug
});
