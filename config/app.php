<?php

return array (
  'name' => 'Conduit',
  'version' => 'unreleased',
  'env' => 'development',
  'providers' => array (
    0 => 'App\\Providers\\AppServiceProvider',
    1 => 'Illuminate\\Database\\DatabaseServiceProvider',
    2 => 'JordanPartridge\\GitHubZero\\GitHubZeroServiceProvider',
  ),
);
