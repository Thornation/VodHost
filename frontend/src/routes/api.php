<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->post('/api/upload', function (Request $request, Response $response, array $args) {
    $loggedIn = \App\Frontend\UserSessionHandler::isLoggedIn($request);
    $username = \App\Frontend\UserSessionHandler::getUsername($request);
    if (!$loggedIn) {
        return $response->withStatus(403);
    }

    $uploadHandler = new \App\Frontend\UploadHandler(
        $this->get('upload_directory'),
        $this->get('temp_directory'),
        $this->logger,
        $this->em
    );

    return $uploadHandler->handleChunk($request, $response);
});

/** Return a json response containing all recent broadcasts to the client.
 *
 */
// FIXME: This needs to return recentvideos, currently returns all videos
$app->get('/api/fetch/recentvideos', function (Request $request, Response $response, array $args) {
    $bmapper = new \App\Frontend\BroadcastMapper($this->em);

    $broadcasts = $bmapper->getBroadcasts();
    $message = json_encode($broadcasts);

    return $response->withJson($message, 200);
});
