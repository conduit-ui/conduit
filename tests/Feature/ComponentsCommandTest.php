<?php

it('lists components', function () {
    $this->artisan('list:components')->assertExitCode(0);
});
