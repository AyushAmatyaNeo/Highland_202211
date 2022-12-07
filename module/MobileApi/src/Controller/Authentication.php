<?php

namespace MobileApi\Controller;

use Application\Factory\ConfigInterface;
use AttendanceManagement\Model\Attendance;
use AttendanceManagement\Repository\AttendanceRepository;
use MobileApi\Repository\AuthRepository;
use Zend\Authentication\Adapter\DbTable\CredentialTreatmentAdapter;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Renderer\RendererInterface;

class Authentication extends AbstractActionController {

    private $adapter;
    private $config;
    private $employeeId;

    public function __construct(AdapterInterface $adapter, ConfigInterface $config) {
        $this->adapter = $adapter;
        $this->config = $config->getApplicationConfig();
    }

    public function indexAction() {
		//return 'test';
        $request = $this->getRequest();
        // $data = json_decode($request->getContent());
        $data = $request->getPost();
        // var_dump($username); die();
        $temp = new CredentialTreatmentAdapter($this->adapter, 'HRIS_USERS', 'USER_NAME', 'FN_DECRYPT_PASSWORD(PASSWORD)');
        $temp->setIdentity($data->username)->setCredential($data->password);
        $result = $temp->authenticate();
        
        $response = ['success' => false, 'data' => "", 'message' => null];

        if ($result->isValid()) {
            $response['success'] = true;
            $resultRow = $temp->getResultRowObject();

            $authRepo = new AuthRepository($this->adapter);
            $url =  $_SERVER['PHP_SELF'];
            $stringReplace = trim(str_replace("/index.php"," ",$url));
            // echo '<pre>'; print_r($stringReplace); die;

            $userProfile = $authRepo->getUserProfile($resultRow->EMPLOYEE_ID);
            $userProfile['PROFILE_PICTURE_PATH'] =  'http://' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $stringReplace .'/uploads/' . (isset($userProfile['FILE_PATH']) ? $userProfile['FILE_PATH'] : $this->config['default-profile-picture']);
            $userProfile['USER_ID'] = $resultRow->USER_ID;
            $userProfile['ROLE_ID'] = $resultRow->ROLE_ID;

            $response['data'] = $userProfile;
            $this->employeeId=$resultRow->EMPLOYEE_ID;
            // condition check 
            
            if ($data->condition=="Y"){
            $this->attendanceInsert($data);
            }
        }else {
		 $data = (object)[];
            $response['data'] = $data;
        }

        foreach ($result->getMessages() as $message) {
            $response['message'] = $response['message'] . $message;
        }

        return new JsonModel($response);
    }
}
