<?php
// DIC configuration

$container = $app->getContainer();

// -----------------------------------------------------------------------------
// Service providers
// -----------------------------------------------------------------------------

// -----------------------------------------------------------------------------
// Service factories
// -----------------------------------------------------------------------------
// Facebook Config

$container['paths'] = function ($c) {
    $config = require('config.local.php');
    return $config['paths'];
};


// -----------------------------------------------------------------------------
// Action factories
// -----------------------------------------------------------------------------

$container[App\Action\InstagramAction::class] = function ($c) {
    return new App\Action\InstagramAction($c->get('paths'));
};