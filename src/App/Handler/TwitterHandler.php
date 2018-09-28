<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Guzzle\Http\Exception\ClientErrorResponseException;

class TwitterHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            $account = $request->getAttribute('account');
            $params = ['screen_name' => $account, 'count' => 1];
            $return = $this->handleReturn($this->getReturn('statuses/user_timeline.json', $params));
        } catch (\Exception $e) {
            $return = $this->getErrorMessage($e->getCode(), $account);
        }
        return new JsonResponse($return);
    }

    public function handleReturn($return) {
        if(!is_array($return) || !isset($return[0]))
            throw new \Exception('Client error', 404);

        $location = !empty($return[0]['user']['location']) ? $this->getLocation(str_replace('ÜT: ', '', $return[0]['user']['location'])) : 'Location not found';
        $link = !empty($return[0]['user']['location']) ? 'https://maps.google.com/?q='.$location : false;


        return [
            'name' => $return[0]['user']['name'],
            'location' => $location,
            'image' => $return[0]['user']['profile_image_url'],
            'link' => $link,
        ];
    }

    public function getLocation($location) {
        $result = explode(",", $location);
        if(isset($result[1])) {
            $lat = trim($result[0]); $long = trim($result[1]);
            if ((is_numeric($lat)) and (is_numeric($long)))
                $params = ['lat' => $lat, 'long' => $long];
        }

        $params = isset($params) ? $params : ['query' => str_replace([',','-','.'],' ',$location)];
        $location = $this->getReturn('geo/search.json', $params);

        $location = $location['result']['places'][0];
        $location = $location['full_name'].' - '.$location['country'];
        
        return $location;
    }

    public function getReturn($url, $params) 
    {
        try {
            $stack = HandlerStack::create();

            $stack->push(new Oauth1([
                'consumer_key'    => 'YLNPTdWcmSwM2NoiBgHs290h6',
                'consumer_secret' => 'U6xgQFuBJY9TH1o74YZCLOICaobRMB091sJbqivZsnyF4GonNV',
                'token'           => '1045414562479124481-Zo3olzY8iVkieZCNujNdeSDELXzr7S',
                'token_secret'    => 'YiKM03ltd61jlE9x2kMrPwLDhois5Ycg4nXCv4m9lWGzn'
            ]));

            $client = new Client([
                'base_uri' => 'https://api.twitter.com/1.1/',
                'handler' => $stack,
                'auth' => 'oauth',
            ]);

            $response = $client->get($url,['query' => $params]);

            return json_decode($response->getBody()->getContents(),true);  
        } catch (ClientErrorResponseException $exception) {
            return $exception->getResponse()->getBody(true);
        }
    }

    public function getErrorMessage($code, $account) {
        $return['successful'] = false;
        if($code == '404') {
            $return['error'] = 'Error '.$code.' <br> User "'.$account.'" not found!';
        } else if($code == '401') {
            $return['error'] = 'Error '.$code.' <br> User "'.$account.'" not authorized!'; 
        } else if($code == '429') {
            $return['error'] = 'Error '.$code.' <br> Too many requests!'; 
        }else if($code == '500') {
            $return['error'] = 'Error '.$code.' <br> Internal server error!'; 
        } else {
            $return['error'] = $e->getMessage();
        }
        return $return;
    }
}
