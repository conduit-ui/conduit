<?php

it('lists components', function () {
    $this->artisan('components list')->assertExitCode(0);
});
