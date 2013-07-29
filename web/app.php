<?php

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array( 'twig.path' => __DIR__.'/../assets/', ));
$app->register(new Silex\Provider\SessionServiceProvider());

// Facebook SDK
require_once('AppInfo.php');
if (substr(AppInfo::getUrl(), 0, 8) != 'https://' && $_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
    header('Location: https://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
require_once('utils.php');
$app['facebook'] = $app->share(function($app) {
    if($_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
        Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER] = false;
    }
    return new Facebook(array(
        'appId'  => AppInfo::appID(),
        'secret' => AppInfo::appSecret(),
        'sharedSession' => true,
        'trustForwarded' => true,
    ));
});

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $facebook = $app['facebook'];
    $app_info = $facebook->api('/'. AppInfo::appID());
    $app_name = idx($app_info, 'name', '');
    $twig->addGlobal('og', array(
        'name' => he($app_name),
        'url' => AppInfo::getUrl(),
        'host' => $_SERVER["HTTP_HOST"],
        'image' => AppInfo::getUrl('/logo.png'),
        'id' => AppInfo::appID()
    ));
    return $twig;
}));


define('NB_FRIENDS_PER_PAGE', 5);
define('REQUIRED_NB_FRIENDS', 1);



/**
 * Check Facebook login
 */
$loginCheck = function (Request $request, Silex\Application $app) {
    $facebook = $app['facebook'];
    $user_id = $facebook->getUser();
    if (!$user_id) {
        return new RedirectResponse('/login');
    }
    try {
        $basic = $facebook->api('/me');
    } catch (FacebookApiException $e) {
        //if (!$facebook->getUser()) {
            return new RedirectResponse('/login');
        //}
    }
};

/**
 * Login
 */
$app->get('/login', function () use ($app) {

    $facebook = $app['facebook'];
    $user_id = $facebook->getUser();
    if ($user_id) {
        //ChromePhp::log('User is logged');
        return new RedirectResponse('/');
    }
    //ChromePhp::log('User is not logged');
    return $app['twig']->render('login.html');

})
->bind('login');


/**
 * Index (GET)
 */
$app->match('/', function () use ($app) {
    
    $facebook = $app['facebook'];
    
    try {
        // Fetch the viewer's basic information
        $basic = $facebook->api('/me');
    } catch (FacebookApiException $e) {
        // If the call fails we check if we still have a user. The user will be
        // cleared if the error is because of an invalid accesstoken
        if (!$facebook->getUser()) {
            //header('Location: '. AppInfo::getUrl($_SERVER['REQUEST_URI']));
            //exit();
            return new RedirectResponse('/login');
        }
    }
    
    return $app['twig']->render('step1.html', array(
        'username' => he(idx($basic, 'name'))
    ));
    
})
->method('GET|POST')
->before($loginCheck)
->bind('home');


/**
 * Facebook Friendlist Form Display
 */
$app->get('/participer', function () use ($app) {
    
    $facebook = $app['facebook'];
    $rez = $facebook->api(array(
        'method' => 'fql.query',
        'query' =>  'SELECT friend_count FROM user WHERE uid = me()'
    ));
    $friend_count = $rez[0]['friend_count'];
    
    return $app['twig']->render('step2.html', array(
        'nb_friends' => $friend_count,
        'nb_per_page' => NB_FRIENDS_PER_PAGE,
        'required_nb_friends' => REQUIRED_NB_FRIENDS 
    ));
    
})
//->before($loginCheck)
->bind('participate');


/**
 * Ajax query to fetch matching friends
 */
$app->post('/get-friends/{pagenum}', function ($pagenum, Request $request) use ($app) {

    $uids = $request->request->get('uids');
    
    $not_uids_clause = '';
    if(is_array($uids) && count($uids)>0) {
        foreach($uids as $uid) {
            $not_uids_clause .= 'AND uid!='.$uid.' ';
        }
    }
    
    $friends_list = array();
    $limit = NB_FRIENDS_PER_PAGE*$pagenum;

    $facebook = $app['facebook'];
    
    $fql = 
        'SELECT uid, name, username, pic_square FROM user WHERE uid IN('.
            'SELECT uid2 FROM friend WHERE uid1 = me()'.
        ') AND is_app_user != 1 '.$not_uids_clause.
        'LIMIT '.$limit.','.NB_FRIENDS_PER_PAGE;
    
    //ChromePhp::log($fql);
    
    $friends_list = $facebook->api(array(
        'method' => 'fql.query',
        'query' => $fql
    ));
        
    return new Response(json_encode($friends_list), 200);

})
//->before($loginCheck)
->value('pagenum', 0)
->bind('get-friends');


/**
 * Facebook Friendlist Form Submit
 */
$app->post('/validation', function (Request $request) use ($app) {

    $friends_uids = $request->request->get('friends-uid');
    
    // User has selected more than 4 friends
    if(is_array($friends_uids) && count($friends_uids)>REQUIRED_NB_FRIENDS-1) {
        
        /*$url="https://graph.facebook.com/".$friends['data'][$i]['id']."/feed";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS,"access_token=".$session['access_token']."&message=".$message);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);*/
        
        // If all went good
        if(true) {
            return new RedirectResponse('/confirmation');
        }
    }
    
    
    
    return new RedirectResponse('/participer');

})
//->before($loginCheck)
->bind('validation');


$app->get('/test', function() use ($app) {
    
    $facebook = $app['facebook'];
    
    
    $param = array(
        'template' => 'Test message',
        'href' => 'http://google.com',
        'access_token' => $facebook->getAccessToken(),
    );
    
    
    $facebook->api("/100002583870281/notifications", "POST", $param);
    
    
    /*$facebook->api('/apprequests', 'post', array(
        'ids' => '100002583870281',
        'message' => 'Test message'
    ));*/
    
    // edauv : 100002583870281
    /*$apprequest_url ="https://graph.facebook.com/" .
        $facebook->getUser() .
        "/apprequests?".$facebook->getAccessToken() .
        "&message=".urlencode('Ca te dis un FonePad ? Tente ta chance avec le jeu concours Tablette Store !').
        "&data=temp&"  .
         "&method=post";*/
    
    //$result = file_get_contents($apprequest_url);*/
    
    
    
    
    /*$token_url = "https://graph.facebook.com/oauth/access_token?" .
        "client_id=" . AppInfo::appID() .
        "&client_secret=" . AppInfo::appSecret() .
        "&grant_type=client_credentials";
    
    $app_access_token = file_get_contents($token_url);
    
    $user_id = '100002583870281';
    
    $apprequest_url ="https://graph.facebook.com/" .
        $user_id .
        "/apprequests?message='Test message'" .
        "&data='Test data'&"  .
        $app_access_token . "&method=post";
    
    $result = file_get_contents($apprequest_url);
    //echo("App Request sent?".$result);
    */
    
    
    
    return new Response("App Request sent?");
    
});



/**
 * Final step
 */
$app->get('/confirmation', function () use ($app) {
    
    return $app['twig']->render('step3.html', array());
    
})
//->before($loginCheck)
->bind('confirmation');


$app->run();

