<?php
namespace AttendanceManagement\Repository;

use Application\Helper\EntityHelper;
use Application\Helper\Helper;
use Application\Model\Model;
use Application\Repository\RepositoryInterface;
use AttendanceManagement\Model\Attendance;
use AttendanceManagement\Model\AttendanceDetail;
use Setup\Model\HrEmployees;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;

class ExcelUploadAttdRepository implements RepositoryInterface {

    private $tableGateway;
    private $adapter;

    public function __construct(AdapterInterface $adapter) {
        $this->tableGateway = new TableGateway(AttendanceDetail::TABLE_NAME, $adapter);
        $this->adapter = $adapter;
    }

    public function add(Model $model) {
        return;
    }
    public function edit(Model $model, $id) {
        return;
    }
    public function fetchAll() {
        return;
    }
    public function fetchById($id) {
        return;
    }
    public function delete($id) {
        return;
    }
    // public function insertAttendance($data){
    //     $sql = "INSERT INTO HRIS_ATTENDANCE(EMPLOYEE_ID, ATTENDANCE_DT, ATTENDANCE_FROM, ATTENDANCE_TIME, REMARKS, THUMB_ID, CHECKED)
    //     VALUES ( {$data['employeeId']}, to_date('{$data['attendanceDt']}', 'YYYY-MM-DD'), 'SYSTEM', to_timestamp('{$data['attendanceTime']}', 'YYYY-MM_DD HH:MI AM'), 'EXCEL UPLOAD BY HR', (SELECT ID_THUMB_ID FROM HRIS_EMPLOYEES WHERE EMPLOYEE_ID = {$data['employeeId']}), 'N')";
    //     $statement = $this->adapter->query($sql);
    //     $statement->execute();  

    //     $sql = "BEGIN HRIS_REATTENDANCE (to_date('{$data['attendanceDt']}', 'YYYY-MM-DD'), {$data['employeeId']}, to_date('{$data['attendanceDt']}', 'YYYY-MM-DD'));END;";
    //     $statement = $this->adapter->query($sql);
    //     $statement->execute(); 
    // }

    public function insertAttendance($data){
        $sql = "INSERT INTO HRIS_ATTENDANCE(EMPLOYEE_ID, ATTENDANCE_DT, ATTENDANCE_FROM, ATTENDANCE_TIME, REMARKS, THUMB_ID, CHECKED)
        VALUES ( {$data['employeeId']}, AD_DATE('{$data['attendanceDt']}'), 'SYSTEM', to_timestamp(AD_DATE('{$data['attendanceDt']}') || ('{$data['attendanceTime']}'),'DD-MM-YY HH:MI AM'), 'EXCEL UPLOAD BY HR', (SELECT ID_THUMB_ID FROM HRIS_EMPLOYEES WHERE EMPLOYEE_ID = {$data['employeeId']}), 'N')";
        $statement = $this->adapter->query($sql);
        // echo '<pre>';print_r($statement);die;
        $statement->execute();
        
        $sql = "BEGIN HRIS_REATTENDANCE (AD_DATE('{$data['attendanceDt']}'), {$data['employeeId']}, AD_DATE('{$data['attendanceDt']}'));END;";
        $statement = $this->adapter->query($sql);
        $statement->execute(); 
    }
}
