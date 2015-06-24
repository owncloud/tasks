<?php

namespace OCA\Tasks\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\IRequest;


class WebController extends Controller {

	public function __construct($appName, IRequest $request){
		parent::__construct($appName, $request);
	}

	public function success($data, $message = null) {
		$response = array(
			'status' => 'success',
			'data' => $data,
			'message' => $message
		);
		return (new JSONResponse())->setData($response);
	}

	public function error($message) {
		$response = array(
			'status' => 'error',
			'data' => null,
			'message' => $message
		);
		return (new JSONResponse())->setData($response);
	}
}