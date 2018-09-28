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
            $return = $this->handleReturn($this->getApiReturn('statuses/user_timeline.json', $params, true));
        } catch (\Exception $e) {
            $return = $this->getErrorMessage($e->getMessage(), $e->getCode(), $account);
        }
        return new JsonResponse($return);
    }

    public function handleReturn($return) {
        if(!is_array($return) || !isset($return[0]))
            throw new \Exception('Return not found', 404);

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
        //FIRST CHECK BING MAPS API IF THERE'S ANY INFORMATION ABOUT THE LOCATION INFORMED
        $params = ['o'=>'json', 'key'=>'ArEYzz06b5sFVh7e02K8ONBas5I-ALF-PkZvj6vW4Ymj1VGQJWDLM-U2x4TQtBuJ'];
        $return = $this->getApiReturn('http://dev.virtualearth.net/REST/v1/Locations/'.$location, $params);
        if($return['resourceSets'][0]['estimatedTotal'] == 0) {
            //IF BING MAPS DOESN'T RETURN ANY ADDRESS TRY TO CONNECT INTO TWITTER GEOLOCATION API
            
            //CONVERT THE STRIG TO LATITUDE AND LONGIGUDE SEPARETED VARIABLES
            $result = explode(",", $location);
            if(isset($result[1])) {
                $lat = trim($result[0]); $long = trim($result[1]);
                if ((is_numeric($lat)) and (is_numeric($long)))
                    $params = ['lat' => $lat, 'long' => $long];
            }
            //CONNECT INTO TWITTER API TO CHECK IF THERE'S ANY INFORMATION ABOUT THE LOCATION INFORMED
            $params = isset($params) ? $params : ['query' => str_replace([',','-','.'],' ',$location)];
            $return = $this->getApiReturn('geo/search.json', $params, true);

            $address = $return['result']['places'][0];
            $location = $address['full_name'].' - '.$address['country'];       
        } else {
            $address = $return['resourceSets'][0]['resources'][0]['address'];
            $location = $address['formattedAddress'];
        }

        
        return $location;
    }

    public function getApiReturn($url, $params, $oauth = false) 
    {
        try {

            $client = new Client();
            
            if($oauth) {
                $client = $this->getOauthClient();
            }

            $response = $client->get($url,['query' => $params]);

            return json_decode($response->getBody()->getContents(),true);  
        } catch (ClientErrorResponseException $exception) {
            return $exception->getResponse()->getBody(true);
        }
    }

    private function getOauthClient() {
        $stack = HandlerStack::create();

        $stack->push(new Oauth1([
            'consumer_key'    => 'YLNPTdWcmSwM2NoiBgHs290h6',
            'consumer_secret' => 'U6xgQFuBJY9TH1o74YZCLOICaobRMB091sJbqivZsnyF4GonNV',
            'token'           => '1045414562479124481-Zo3olzY8iVkieZCNujNdeSDELXzr7S',
            'token_secret'    => 'YiKM03ltd61jlE9x2kMrPwLDhois5Ycg4nXCv4m9lWGzn'
        ]));

       return new Client([
            'base_uri' => 'https://api.twitter.com/1.1/',
            'handler' => $stack,
            'auth' => 'oauth',
        ]);
    }

    public function getErrorMessage($message, $code, $account) {
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
            $return['error'] = $message;
        }
        return $return;
    }
}
