<?php


// https://developers.facebook.com/docs/concepts/requests/


require_once __DIR__.'/../vendor/autoload.php';
require_once 'MySql.php';

define( 'SQL_SERVER',	'localhost');
define( 'SQL_BDD',		'tablette-store' );
define( 'SQL_USER',		'root');
define( 'SQL_PWD',		'mammout');

MySql::Init(SQL_SERVER, SQL_USER, SQL_PWD, SQL_BDD);

define('FBGA', 't_facebookgame_fbga');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array( 'twig.path' => __DIR__.'/../web/assets/' ));
$app->register(new Silex\Provider\SessionServiceProvider());


// Facebook SDK
require_once 'AppInfo.php';
if (substr(AppInfo::getUrl(), 0, 8) != 'https://' && $_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
    header('Location: https://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
require_once 'utils.php';
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


function testRegistered($app) {
    $facebook = $app['facebook'];
    // Allready suscribed
    $select_fbga = new MySql(
        'SELECT id, email FROM '.FBGA.' '.
        'WHERE user_id=\''.$facebook->getUser().'\' LIMIT 1;'
    );
    if(false !== $fbgta = $select_fbga->Fetch()) {
        return true;
    }
    return false;
}


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
        return new RedirectResponse('/login');
    }
    
    // Delete FB Requests
    $request_ids = explode(',', $request->query->get('request_ids'));
    foreach ($request_ids as $request_id) {
        $full_request_id = $request_id . '_' . $user_id;    
        try {
            $delete_success = $facebook->api("/$full_request_id",'DELETE');
        } catch (FacebookApiException $e) {
            
        }
    }
};


$registerCheck = function (Request $request, Silex\Application $app) {
    if(testRegistered($app)) {
        return new RedirectResponse('/');
    }
};

/**
 * Login
 */
$app->get('/login', function () use ($app) {

    $facebook = $app['facebook'];
    $user_id = $facebook->getUser();
    if ($user_id) {
        return new RedirectResponse('/');
    }
    return $app['twig']->render('login.html');

})
->bind('login');


/**
 * Index (GET)
 */
$app->match('/', function () use ($app) {
    
    $facebook = $app['facebook'];
    
    try {
        $basic = $facebook->api('/me');
    } catch (FacebookApiException $e) {
        if (!$facebook->getUser()) {
            return new RedirectResponse('/login');
        }
    }
    
    $dates_array = array(
        '2013-08-12' => 'Lundi 12 août',
        '2013-08-19' => 'Lundi 19 août',
        '2013-08-26' => 'Lundi 26 août',
        '2013-09-02' => 'Lundi 2 septembre',
        '2013-09-09' => 'Lundi 9 septembre',
        '2013-09-16' => 'Lundi 16 septembre'
    );
    
    $dates = array();
    $winners = array();
    
    foreach($dates_array as $date => $label) {
        $select_fbga = new MySql(
            'SELECT id, name FROM '.FBGA.' WHERE windate=\''.$date.'\' LIMIT 1;'
        );
        if(false !== $fbga = $select_fbga->Fetch()) {
            $dates[] = array(
                'label' => $label,
                'done' => true,
            );
            $winners[] = $fbga->name;
        }else{
            $dates[] = array(
                'label' => $label,
                'done' => false,
            );
        }
    }
    
    return $app['twig']->render('step1.html', array(
        'username' => he(idx($basic, 'name')),
        'done' => testRegistered($app),
        'dates' => $dates,
        'winners' => $winners
    ));
    
})
->method('GET|POST')
->before($loginCheck)
->bind('home');


/**
 * Facebook Friendlist Form Display
 */
$app->get('/participer', function () use ($app) {
    
    return $app['twig']->render('step2.html', array());
    
})
->before($loginCheck)
->before($registerCheck)
->bind('participate');


/**
 * Register post id
 */
$app->post('/register_post', function (Request $request) use ($app) {

    $facebook = $app['facebook'];
    try {
        // Fetch the viewer's basic information
        $basic = $facebook->api('/me');
    } catch (FacebookApiException $e) {
        return new Response('Unauthorized', 401);
    }
    
    $user_id = $facebook->getUser();
    $post_id = $request->request->get('post_id');
    if($post_id !== null) {
        
        if(testRegistered($app)) {
            return new Response('OK', 200);
        }
        
        $email = idx($basic, 'email');
        $name = idx($basic, 'name');
        
        $insert = new MySql(
            'INSERT INTO '.FBGA.'(user_id, email, name, post_id) VALUES (\''.
                MySql::EscapeStr($user_id).'\',\''.
                MySql::EscapeStr($email).'\',\''.
                MySql::EscapeStr($name).'\',\''.
                MySql::EscapeStr($post_id).'\''.
            ');'
        );
        if($insert->Success()) {
            return new Response('OK', 200);
        }
    }
    
    return new Response('Not found', 404);
})
->before($loginCheck)
->bind('register_post');


/**
 * Final step
 */
$app->get('/confirmation', function () use ($app) {
    
    return $app['twig']->render('step3.html', array());
    
})
->before($loginCheck)
->bind('confirmation');


return $app;