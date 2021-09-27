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
            'p',
            'sudo',
            'q'
        ];

	/**
	 * Constructor.
	 *
	 * @param RequestInterface  $request
	 * @param ResponseInterface $response
	 * @param LoggerInterface   $logger
	 */
	public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger){
		// Do Not Edit This Line
		parent::initController($request, $response, $logger);
		//--------------------------------------------------------------------
		// Preload any models, libraries, etc, here.
		//--------------------------------------------------------------------
                
                if( session()->get('user_id')==null ){
                    $this->guestUserInit();
                }
                $this->handleCors();
	}
        
        private function handleCors(){
            foreach (getallheaders() as $name => $value) {
                if( $name==='Origin' && (str_contains($value, 'tezkel') || str_contains($value, 'localhost')) ){
                    header("Access-Control-Allow-Origin: $value");
                }
            }
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            header("Access-Control-Allow-Credentials: true");
            //header("Access-Control-Allow-Headers: Origin,X-Requested-With,Content-Type,Accept,Access-Control-Request-Method,Authorization,Cache-Control,access-controll-allow-credentials,access-controll-allow-headers,access-controll-allow-methods,access-controll-allow-origin,cross-origin-resource-policy");
            //header("Cross-Origin-Resource-Policy: cross-origin");
        }
        
        
        private function guestUserInit(){
            $PermissionModel=model('PermissionModel');
            $PermissionModel->listFillSession();
            session()->set('user_id',-1);            
        }
        
	protected function error( $error_token='unknown_error', $error_code=500, $error_description='' ){
		$this->response->setStatusCode($error_code,$error_description);
		$this->response->setBody($error_token);
		$this->response->send();
		die();
	}
}
