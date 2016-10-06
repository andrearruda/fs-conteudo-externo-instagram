<?php
// Routes

$app->get('/', function(){
//    return $this->response->withRedirect($this->router->pathFor('instagram.login'));
});


$app->group('/instagram', function (){
    $this->get('/feed/{username}[/{amount}]', 'App\Action\InstagramAction:feed')->setName('instagram.posts');
});