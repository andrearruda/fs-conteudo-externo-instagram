<?php
// Routes

$app->get('/feed/{username}[/{amount}]', 'App\Action\InstagramAction:feed')->setName('instagram.posts');