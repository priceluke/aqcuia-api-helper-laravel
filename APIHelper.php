<?php

namespace App\Helpers;

use App\Action;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\GenericProvider;
use App\Library\Environment;
use App\Library\Project;
use Illuminate\Support\Facades\Auth;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class APIHelper
{
    public $user = User::class;
    public $provider;

    public function __construct($userID)
    {
        $this->user = User::findOrFail($userID);
        $this->provider = new GenericProvider([
            'clientId' => $this->user->api_key,
            'clientSecret' => $this->user->api_secret,
            'urlAuthorize' => '',
            'urlAccessToken' => 'https://accounts.acquia.com/api/auth/oauth/token',
            'urlResourceOwnerDetails' => '',
        ]);
    }

    function post($path, $params = null)
    {
        $client = new Client();
        $this->provider->setHttpClient($client);


        $accessToken = $this->provider->getAccessToken('client_credentials');
        if (is_null($params)) {
            $header = [
                'headers' => ['Content-Type' => 'application/json'],
            ];
        } else {
            $header = [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($params)
            ];
        }
        $request = $this->provider->getAuthenticatedRequest(
            'POST',
            "https://cloud.acquia.com/" . $path,
            $accessToken,
            $header
        );

        $response = $client->send($request);

        $responseBody = json_decode($response->getBody()->getContents(), true);

        $notificationLink = $responseBody['_links']['notification']['href'];
        $action = $this->user->actions()->create(['action' => 'POST', 'request' => $path, 'response' => '', 'notification_link' => $notificationLink, 'params' => json_encode($params)]);
        $notificationLink = str_replace('https://cloud.acquia.com/', '', $notificationLink);
        return $notificationLink;

    }

    function checkNotification($path)
    {
        $body = $this->get($path, false);
        return json_decode($body, true)['status'];;
    }

    function get($path, $useCache = true)
    {
        $cache = $this->user->actions()->where([['action', '=', 'GET'], ['request', '=', $path], ['request', '=', $path]])->whereDate('created_at', Carbon::now()->subMinutes(5))->get()->last();
        if (!is_null($cache) && $useCache) {
            return $cache->response;
        }
        try {
            $accessToken = $this->provider->getAccessToken('client_credentials');

            $request = $this->provider->getAuthenticatedRequest(
                'GET',
                'https://cloud.acquia.com/' . $path,
                $accessToken
            );

            $client = new Client();
            $response = $client->send($request);

            $responseBody = $response->getBody();


        } catch (IdentityProviderException $e) {
            exit($e->getMessage());
        }
        $action = $this->user->actions()->create(['action' => 'GET', 'request' => $path, 'response' => $responseBody, 'notification_link' => '', 'params' => 'none']);

        return $responseBody;
    }
}
