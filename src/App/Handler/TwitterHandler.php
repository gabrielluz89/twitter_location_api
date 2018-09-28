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

class TwitterHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            $account = $request->getAttribute('account');
            $params = ['screen_name' => $account, 'count' => 1];
            $return = $this->handleReturn($this->getReturn('statuses/user_timeline.json', $params));
        } catch (\Exception $e) {
            if(substr($e->getMessage(),0,12) == 'Client error') {
                $return = ['successful' => false, 'error' => 'User "'.$account.'" not found!'];
            } else {
                $return = ['successful' => false, 'error' => $e->getMessage()]; 
            }
        }
        return new JsonResponse($return);
    }

    public function handleReturn($return) {
        if(!isset($return[0]))
            throw new \Exception('Client error');


        $location = !empty($return[0]['user']['location']) ? str_replace('ÜT: ', '', $return[0]['user']['location']) : 'Location not found';
        $location = $this->getLocation($location);
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
        $lat = trim($result[0]);
        $long = trim($result[1]);
        if ((is_numeric($lat)) and (is_numeric($long))) {
            $params = ['lat' => $lat, 'long' => $long];
        } else {
            $params = ['query' => $location];
        }
        $location = $this->getReturn('geo/search.json', $params);
            var_dump($location);
        die;
        foreach ($location as $key => $row) {
            echo'<br>';
        }
        //37.795917
        //-122.39966
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
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
