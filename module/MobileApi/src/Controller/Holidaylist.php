<?php

namespace MobileApi\Controller;

use Exception;
use MobileApi\Repository\HolidaylistRepository;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;



class Holidaylist extends AbstractActionController {

    private $adapter;
    private $employeeId;

    public function __construct(AdapterInterface $adapter) {
        $this->adapter = $adapter;
    }
    public function indexAction(){
           echo 'Wel Come ';
     die();

    }       

    public function statusAction() {
        try{
            $request = $this->getRequest();
                $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();
                $requestType = $request->getMethod();
                $responseDate = "";
                switch ($requestType) {
                    case Request::METHOD_GET:
                    
                        $responseDate = $this->getStatus($this->employeeId);
                        break;
                    default:
                        throw new Exception('the request  is unknown');
                }
                return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => "Holiday List"]);
            } catch (Exception $e) {
                return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
            }

        
    }

    private function getStatus($employeeId) {
        $StatusRepo = new HolidaylistRepository($this->adapter);
        // $getdate=$StatusRepo-> getMonthDate();
         
        return $StatusRepo->fetchEmployeeHolidayList($employeeId );
    }

}
