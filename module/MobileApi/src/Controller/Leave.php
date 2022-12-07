<?php

namespace MobileApi\Controller;

use Application\Factory\ConfigInterface;
use Application\Helper\Helper;
use Exception;
use LeaveManagement\Model\LeaveApply;
use LeaveManagement\Repository\LeaveAssignRepository;
use LeaveManagement\Repository\LeaveMasterRepository;
use ManagerService\Repository\LeaveApproveRepository;
use MobileApi\Repository\LeaveRepository as NewLeaveRepository; 
use Notification\Model\NotificationEvents;
use SelfService\Model\LeaveSubstitute;
use SelfService\Repository\LeaveRequestRepository;
use SelfService\Repository\LeaveSubstituteRepository;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use SelfService\Repository\LeaveRepository;

class Leave extends AbstractActionController {

    private $adapter;
    private $config;
    private $employeeId;

    public function __construct(AdapterInterface $adapter, ConfigInterface $config) {
        $this->adapter = $adapter;
        $this->config = $config->getApplicationConfig();
    }
    //menu showing
    public function getMenusAction()
    {
        // var_dump('her'); die;
        try {

            $request = $this->getRequest();
            $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();
            $requestType = $request->getMethod();
            
            // $data = $request->getPost();
            // var_dump($data); die;
            $responseData = "";
            $dashRepo = new NewLeaveRepository($this->adapter);
            switch ($requestType) {
                case Request::METHOD_GET:
                    $responseData = $dashRepo->checkMenuPermission($this->employeeId);
                    // var_dump($responseData); die;
                    if ($responseData == false) {
                        $message = 'No access';
                    } else {
                        $message = 'Accessible';
                    }
                    break;
                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true,'data'=>$responseData,'message' => $message]);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }
    //shows the leave assigned to the passed employee id
    public function setupAction() {
        try {
            $request = $this->getRequest();
            $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();

            $requestType = $request->getMethod();
            $responseDate = "";

            switch ($requestType) {
                case Request::METHOD_POST:
                    throw new Exception("Unavailable Request.");
                    break;
                case Request::METHOD_GET:
                    // var_dump('herr'); die;
                    $responseDate = $this->employeeLeaveGet($this->employeeId);
                    break;

                case Request::METHOD_PUT:
                    throw new Exception("Unavailable Request.");
                    break;

                case Request::METHOD_DELETE:
                    throw new Exception("Unavailable Request.");
                    break;

                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => "Leave Types"]);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }
    //get detail of the assigned leave
    public function assignAction() {
        try {
            $request = $this->getRequest();
            $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();

            $requestType = $request->getMethod();
             //            $data = json_decode($request->getContent());
            $id = (int) $this->params()->fromRoute('id');
            // var_dump($id); die;
            $responseDate = "";

            switch ($requestType) {
                case Request::METHOD_POST:
                    throw new Exception("Unavailable Request.");
                    break;
                case Request::METHOD_GET:
                    if (isset($id) && $id != null && $id != 0) {
                        $responseDate = $this->assignGetById($id, $this->employeeId);
                    } else {
                        throw new Exception("Leave Id should be defined.");
                    }
                    break;

                case Request::METHOD_PUT:
                    throw new Exception("Unavailable Request.");
                    break;

                case Request::METHOD_DELETE:
                    throw new Exception("Unavailable Request.");
                    break;

                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => 'Leave Assigned']);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }

    public function remainingLeaveDaysAction()
    {
        try {
            $request = $this->getRequest();
            $employeeId = $request->getHeader('Employee-Id')->getFieldValue();

            $requestType = $request->getMethod();
            $data = json_decode($request->getContent());
            
            $responseDate = "";

            switch ($requestType) {
                case Request::METHOD_POST:
                    // var_dump($employeeId); die;
                    $newrepo = new LeaveRepository($this->adapter);
                    $leaves = iterator_to_array($newrepo->selectAll($employeeId), false);
                    $responseDate = $leaves;
                    break;
                case Request::METHOD_GET:
                    throw new Exception("Unavailable Request.");
                    break;
                case Request::METHOD_PUT:
                    throw new Exception("Unavailable Request.");
                    break;

                case Request::METHOD_DELETE:
                    throw new Exception("Unavailable Request.");
                    break;
                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => null]);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }

    public function calculateDaysAction() {
        try {
            $request = $this->getRequest();
            $employeeId = $request->getHeader('Employee-Id')->getFieldValue();

            $requestType = $request->getMethod();
            $data = json_decode($request->getContent());
            
            $responseDate = "";

            switch ($requestType) {
                case Request::METHOD_POST:
                    // var_dump($employeeId); die;
                    $newrepo = new LeaveRepository($this->adapter);
                    $leaves = iterator_to_array($newrepo->selectAll($employeeId), false);
                    $responseDate = $leaves;
                    break;
                case Request::METHOD_GET:
                    throw new Exception("Unavailable Request.");
                    break;
                case Request::METHOD_PUT:
                    throw new Exception("Unavailable Request.");
                    break;

                case Request::METHOD_DELETE:
                    throw new Exception("Unavailable Request.");
                    break;
                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => null]);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }

    public function requestAction() {
        try {
            
            $request = $this->getRequest();
            $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();
            $requestType = $request->getMethod();
            $data = $request->getPost();
            
            $id = $this->employeeId;
            $responseDate = "";
            switch ($requestType) {
                case Request::METHOD_POST:
                    $responseDate = $this->requestPost($data,$id);
                case Request::METHOD_GET:
                    if (isset($id) && $id != null && $id != 0) {
                        $responseDate = $this->requestGetById($id);
                    } else {

                        $responseDate = $this->requestGet();
                    }
                    break;

                case Request::METHOD_PUT:
                    $responseDate = $this->requestPut($id, $data);
                    break;

                case Request::METHOD_DELETE:
                    $responseDate = $this->requestDelete($id);
                    break;

                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => "Leave request"]);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }


    public function approvalAction() {
        try {       
            
            $request = $this->getRequest();
            $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();
            $requestType = $request->getMethod();
            $data = $request->getPost();
            $id = $this->params()->fromRoute('id');
            $responseDate = "";

            switch ($requestType) {
                case Request::METHOD_POST:
                    
                    $responseDate = $this->approvalPut($id, $data,$this->employeeId);
                    // var_dump($responseDate); die;
                    if ($responseDate != null) {
                        $message = $responseDate;
                    } else {
                        if ($data->ACTION == 'Approve') {
                            $message = 'Leave approved';
                        } else {
                            $message = 'Leave rejected';
                        }
                    }
                    
                    break;
                case Request::METHOD_GET:
                    $responseDate = $this->approvalGet($this->employeeId);
                    $message = 'Leave list';
                    break;

                case Request::METHOD_PUT:
                    throw new Exception("Unavailable Request.");
                    // $responseDate = $this->approvalPut($id, $data);
                    break;
                case Request::METHOD_DELETE:
                    throw new Exception("Unavailable Request.");
                    break;

                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => $message]);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }

    public function substituteApprovalAction() {
        try {
            $request = $this->getRequest();
            $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();

            $requestType = $request->getMethod();
            $data = $request->getPost();
            // var_dump($request); die;
            $id = $this->params()->fromRoute('id');

            $responseDate = "";

            switch ($requestType) {
                case Request::METHOD_POST:
                    $responseDate = $this->substituteApprovalPut($id, $data);
                    break;
                case Request::METHOD_GET:
                    if (isset($id) && $id != null && $id != 0) {
                        $responseDate = $this->substitueApprovalGetById($id);
                    } else {
                        $responseDate = $this->substituteApprovalGet();
                    }
                    break;

                case Request::METHOD_PUT:
                  

                case Request::METHOD_DELETE:
                    throw new Exception("Unavailable Request.");
                    break;

                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => 'status changed']);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }

    private function requestGet() {
        // var_dump('her'); die;

        $request = new LeaveRequestRepository($this->adapter);
        $result = $request->fetchByEmpId($this->employeeId);

        return Helper::extractDbData($result);
    }

    private function requestGetById($id) {
        // var_dump('herrid'); die;
        $request = new LeaveRequestRepository($this->adapter);
        $result = $request->selectAll($id);
        return Helper::extractDbData($result);
    }

    private function requestPost($data,$id) {
		// echo '<pre>'; print_r($id); die;
		
       $start = date_create($data->from_date) ;
       $end = date_create($data->to_date);
	   $num = date_diff($end,$start);
       $num = $num->format("%a");
        $num = $num+1 ;
		// echo '<pre>'; print_r($num); die;
        $leaveRequest = new LeaveApply();
        
        $leaveRequest->exchangeArrayFromDB((array) $data);
		
        $leaveRequest->id = (int) Helper::getMaxId($this->adapter, LeaveApply::TABLE_NAME, LeaveApply::ID) + 1;
        //echo '<pre>'; print_r($leaveRequest); die;
		$leaveRequest->startDate = Helper::getExpressionDateNew($data->from_date);
		// echo '<pre>'; print_r($num);die;
        $leaveRequest->employeeId = $id;
        $leaveRequest->leaveId = $data->leave_id;
        $leaveRequest->noOfDays = (int) $num;
        $leaveRequest->remarks = $data->remarks;
        $leaveRequest->endDate = Helper::getExpressionDateNew($data->to_date);
        $leaveRequest->requestedDt = Helper::getcurrentExpressionDate();
        $leaveRequest->status = "RQ";
	$leaveRequest->halfDay = $data->halfday;
		
        // ($num); die;
    //   echo '<pre>'; print_r($leaveRequest); die;
        $requestRepo = new LeaveRequestRepository($this->adapter);
        $requestRepo->add($leaveRequest);

    }

    private function requestPut($id, $data) {
        
    }

    private function requestDelete($id) {
        
    }

    private function leaveGet() {
        $leaveRepo = new LeaveMasterRepository($this->adapter);
        $result = $leaveRepo->fetchAll();
        return Helper::extractDbData($result);
    }

    private function leaveGetById($id) {
        $leaveRepo = new LeaveMasterRepository($this->adapter);
        $result = $leaveRepo->fetchById($id);
        return [$result];
    }

    private function assignGetById($leaveId, $employeeId) {
        $assignRepo = new LeaveAssignRepository($this->adapter);
        $leave = $this->leaveGetById($leaveId);
        $fiscalYearMonthNo = null;
        if($leave[0]["IS_MONTHLY"] == 'Y'){
            $fiscalYearMonthNo = date('m');
        }
        $result = $assignRepo->filterByLeaveEmployeeId($leaveId, $employeeId, $fiscalYearMonthNo);
        return $result;
    }

    private function calculateDays($data) {

        // var_dump('sds'); die;
        $request = new LeaveRequestRepository($this->adapter);
        $sdncsj = $request->fetchAvailableDays(Helper::getExpressionDate($data->START_DATE)->getExpression(), Helper::getExpressionDate($data->END_DATE)->getExpression(), $data->EMPLOYEE_ID,$data->HALF_DAY,$data->LEAVE_ID);

        return $sdncsj;
    }
    
    private function approvalGet($id) {
        
        $approvalRepository = new NewLeaveRepository($this->adapter);
        $list = $approvalRepository->getApproval($id);
        return $list;
    }

    private function approvalGetById($id) {
        $approvalRepository = new NewLeaveRepository($this->adapter);
        $list = $approvalRepository->getApproval($this->employeeId, $id);
        return $list;
    }

    /*
     * {
     * "ROLE":2,3,4,
     * "EMPLOYEE_ID":1,
     * "REMARKS":"",
     * "ACTION":"Approve","Reject"
     * }
     */

    private function approvalPut($id, $data,$eid) {
        // echo '<pre>'; print_r('$checkIfAdmin'); die;
        $newLeaveRepo = $approvalRepository = new NewLeaveRepository($this->adapter);
        $role = $newLeaveRepo->getRoleIdApproval($id,$eid);

        $leaveApply = new LeaveApply();
        $notificatinEvent = null;
        $checkIfAdmin = $newLeaveRepo->checkIfAdmin($eid);
        if ($checkIfAdmin[0]['ROLE_ID'] == 1) {
            $role[0]['ROLE'] = 4;
        }

        if (count($role) == 0) {
            $message = "You don't have access";
            return $message;
        } else {
            switch ($role[0]['ROLE']) {
                case 2:
                    $leaveApply->recommendedDt = Helper::getcurrentExpressionDate();
                    $leaveApply->status = $data->ACTION == "Approve" ? "RC" : "R";
                    $leaveApply->recommendedBy = $eid;
                    $leaveApply->recommendedRemarks = $data->REMARKS;
                    $notificatinEvent = ( $data->ACTION == "Approve") ? NotificationEvents::LEAVE_RECOMMEND_ACCEPTED : NotificationEvents::LEAVE_RECOMMEND_REJECTED;
                    break;
                case 3:
                    $leaveApply->approvedDt = Helper::getcurrentExpressionDate();
                    $leaveApply->status = $data->ACTION == "Approve" ? "AP" : "R";
                    $leaveApply->approvedBy = $eid;
                    $leaveApply->approvedRemarks = $data->REMARKS;
                    $notificatinEvent = ( $data->ACTION == "Approve") ? NotificationEvents::LEAVE_APPROVE_ACCEPTED : NotificationEvents::LEAVE_APPROVE_REJECTED;
                    break;
                case 4:
                    $leaveApply->recommendedDt = Helper::getcurrentExpressionDate();
                    $leaveApply->recommendedBy = $eid;
                    $leaveApply->approvedDt = Helper::getcurrentExpressionDate();
                    $leaveApply->status = $data->ACTION == "Approve" ? "AP" : "R";
                    $leaveApply->approvedBy = $eid;
                    $leaveApply->approvedRemarks = $data->REMARKS;
                    $notificatinEvent = ( $data->ACTION == "Approve") ? NotificationEvents::LEAVE_APPROVE_ACCEPTED : NotificationEvents::LEAVE_APPROVE_REJECTED;
                    break;
            }
            $approveRepo = new LeaveApproveRepository($this->adapter);
            $approveRepo->edit($leaveApply, $id);
            //        HeadNotification::pushNotification($notificatinEvent, $leaveApply, $this->adapter, $this);
            $message = null;
            return $message;
        }
        
        
    }

    private function substitueApprovalGetById($id) {
        $leaveRepo = new LeaveRepository($this->adapter);
        return [$leaveRepo->getSubstituteApprovalById($id)];
    }

    private function substituteApprovalGet() {
        $leaveRepo = new LeaveRepository($this->adapter);
        return $leaveRepo->getSubstituteApprovalByEmpId($this->employeeId);
    }

    /*
     * {"REMARKS":1000376,"ACTION":"Approve"}
     */

    private function substituteApprovalPut($id, $data) {
        $leaveSubstitute = new LeaveSubstitute();
        $leaveSubstitute->approvedDate = Helper::getcurrentExpressionDate();
        $leaveSubstitute->remarks = $data->REMARKS;
        $leaveSubstitute->approvedFlag = $data->ACTION == "Approve" ? "Y" : "N";
        $notificatinEvent = ( $data->ACTION == "Approve") ? NotificationEvents::LEAVE_SUBSTITUTE_ACCEPTED : NotificationEvents::LEAVE_SUBSTITUTE_REJECTED;

        $approveRepo = new LeaveRepository($this->adapter);
        $approveRepo->updateSubstituteApproval($leaveSubstitute, $id);
//        HeadNotification::pushNotification($notificatinEvent, $leaveApply, $this->adapter, $this);
        return null;
    }

    private function employeeLeaveGet($employeeId) {
        $leaveRepo = new NewLeaveRepository($this->adapter);
        return $leaveRepo->getEmployeeLeave($employeeId);
    }
    
    
    public function validateDaysAction() {
        try {
            $request = $this->getRequest();
            $this->employeeId = $request->getHeader('Employee-Id')->getFieldValue();

            $requestType = $request->getMethod();
            $data = json_decode($request->getContent());
            $responseDate = "";

            switch ($requestType) {
                case Request::METHOD_POST:
                    $responseDate = $this->validateDays($data);
                    break;
                case Request::METHOD_GET:
                    throw new Exception("Unavailable Request.");
                    break;
                case Request::METHOD_PUT:
                    throw new Exception("Unavailable Request.");
                    break;

                case Request::METHOD_DELETE:
                    throw new Exception("Unavailable Request.");
                    break;

                default:
                    throw new Exception('the request  is unknown');
            }
            return new JsonModel(['success' => true, 'data' => $responseDate, 'message' => null]);
        } catch (Exception $e) {
            return new JsonModel(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        }
    }
    
    
     private function validateDays($data) {
        $request = new LeaveRequestRepository($this->adapter);
        return $request->validateLeaveRequest(Helper::getExpressionDate($data->START_DATE)->getExpression(), Helper::getExpressionDate($data->END_DATE)->getExpression(), $data->EMPLOYEE_ID);
    }

}
