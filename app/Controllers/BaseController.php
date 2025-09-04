<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */

class BaseController extends Controller
{
 	/**
	 * Instance of the main Request object.
	 *
	 * @var IncomingRequest|CLIRequest
	 */
	protected $request;

	/**
	 * An array of helpers to be loaded automatically upon
	 * class instantiation. These helpers will be available
	 * to all other controllers that extend BaseController.
	 *
	 * @var array
	 */
	protected $helpers = [
            'sudo',
            'job',
            'p',
            'q',
            'metrics',
        ];

	/**
	 * Constructor.
	 *
	 * @param RequestInterface  $request
	 * @param ResponseInterface $response
	 * @param LoggerInterface   $logger
	 */
	public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger){
        $this->handleSession($request,$response);
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);
        //--------------------------------------------------------------------
        // Preload any models, libraries, etc, here.
        //--------------------------------------------------------------------

        if( session()->get('user_id')==null ){
            $result=$this->signInBySid();
            if( $result=='ok' ){
                return true;
            }
            $this->guestUserInit();
        }
        $this->detectChameleonMode();
    }

    private function detectChameleonMode(){
        if( session()->get('chameleonMode')=='on' ){
            header('x-chameleon: on');
            return;
        }
        $blackListCountries=getenv('chameleon.countryCodeBlacklist');
        if( $blackListCountries &&  session()->get('chameleonMode')==null ){
            $ctx = stream_context_create(['http'=>['timeout' => 1]]);
            try{
                $json=file_get_contents("http://ip-api.com/json/{$_SERVER['REMOTE_ADDR']}?fields=countryCode,city,regionName", false, $ctx);
                $resp=json_decode($json);
                if( isset($resp->countryCode) && str_contains($blackListCountries,$resp->countryCode) ){
                    session()->set('chameleonMode','on');
                    header('x-chameleon: on');
                    pl('LIMITED COUNTRY ACCESS',$resp);
                    return;
                } else {
                    session()->set('chameleonMode','off');
                }
            } catch( \Exception $e){}
        }
        $blackListAppVersion=getenv('chameleon.appVersion');
        if( isset($_SERVER['HTTP_X_VER']) && $_SERVER['HTTP_X_VER']==$blackListAppVersion ){
            session()->set('chameleonMode','on');
            header('x-chameleon: on');
        }
    }

    private function handleSession($request,$response){
        $session_id=$request->getHeaderLine('x-sid');
        if( $session_id && strlen($session_id)>10 ){
            //session_id must be valid string not 'null'
            session_id($session_id);
        }
        session();
        $response->setHeader('x-sid',session_id());
    }
    
    private function guestUserInit(){
        $PermissionModel=model('PermissionModel');
        $PermissionModel->listFillSession();
        session()->set('user_id',-1);            
    }

    private function signInBySid(){
        $session_id=session_id();
        $token_hash=hash('sha256',$session_id);
        $UserModel=model('UserModel');
        return $UserModel->signInByToken($token_hash, 'user');
    }
}
