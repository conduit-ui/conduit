<?php

use App\Services\ComponentService;

describe('ComponentService', function () {

    beforeEach(function () {
        $this->componentService = app(ComponentService::class);
    });

    it('can be instantiated from the container', function () {
        expect($this->componentService)->toBeInstanceOf(ComponentService::class);
    });

    it('can list installed components as an array', function () {
        $installed = $this->componentService->listInstalled();
        expect($installed)->toBeArray();
    });

    it('can handle component installation with minimal tests', function () {
        $result = $this->componentService->install('non-existent-test-component-12345');
        expect($result)->toBeInstanceOf(\App\Services\ComponentResult::class);
        expect($result->isSuccessful())->toBeFalse();
    });
});
