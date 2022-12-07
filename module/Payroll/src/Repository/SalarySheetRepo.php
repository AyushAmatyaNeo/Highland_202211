<?php

namespace Payroll\Repository;

use Application\Helper\Helper;
use Application\Model\Model;
use Application\Model\Months;
use Application\Repository\HrisRepository;
use Payroll\Model\SalarySheet;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;

class SalarySheetRepo extends HrisRepository {

    public function __construct(AdapterInterface $adapter, $tableName = null) {
        parent::__construct($adapter, SalarySheet::TABLE_NAME);
    }

    public function add(Model $model) {
        return $this->tableGateway->insert($model->getArrayCopyForDB());
    }

    public function delete($id) {
        return $this->tableGateway->delete([SalarySheet::MONTH_ID => $id]);
    }

    public function fetchAll() {
        return $this->tableGateway->select(function (Select $select) {
                    $select->columns(Helper::convertColumnDateFormat($this->adapter, new SalarySheet(), [
                                'startDate',
                                'endDate',
                            ]), false);
                });
    }

    public function fetchById($id) {
        return $this->tableGateway->select([SalarySheet::SHEET_NO => $id]);
    }

    public function fetchByIds(array $ids) {
        return $this->tableGateway->select($ids);
    }

    public function fetchOneBy(array $ids) {
        return $this->tableGateway->select($ids)->current();
    }

    public function joinWithMonth($monthId = null, $employeeJoinDate = null) {
        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->columns([]);
        $select->from(['S' => SalarySheet::TABLE_NAME])
                ->join(['M' => Months::TABLE_NAME], 'S.' . SalarySheet::MONTH_ID . '=M.' . Months::MONTH_ID);
        if ($monthId != null) {
            $select->where([Months::MONTH_ID => $monthId]);
        }
        $select->where(["M." . Months::STATUS . " = " . "'E'"]);
        $select->where(["S." . SalarySheet::STATUS . " = " . "'E'"]);

        if ($employeeJoinDate != null) {
            $select->where(["'" . $employeeJoinDate . "'" . " <= " . "M." . Months::TO_DATE]);
        }

        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        return $result;
    }

    public function generateSalShReport($sheetNo) {
        $boundedParameter = [];
        $boundedParameter['sheetNo'] = $sheetNo;
        $this->executeStatement("BEGIN
                            HRIS_GEN_SAL_SH_REPORT(:sheetNo);
                        END;", $boundedParameter);
    }

    public function updateLoanPaymentFlag($employeeId, $sheetNo) {
        $boundedParameter = [];
        $boundedParameter['sheetNo'] = $sheetNo;
        $boundedParameter['employeeId'] = $employeeId;
        $this->executeStatement("BEGIN
                            hris_loan_payment_flag_change(:employeeId,:sheetNo);
                        END;", $boundedParameter);
    }

    public function fetchAllSalaryType() {
        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->columns(['*']);
        $select->from('HRIS_SALARY_TYPE');
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        return $result;
    }
    
    public function fetchEmployeeByGroup($monthId,$group,$salaryTypeId) {
        $boundedParameter = [];
        
        $boundedParameter['salaryTypeId'] = $salaryTypeId;
        $boundedParameter['monthId'] = $monthId;
        $sql = "SELECT 
            E.employee_id,E.employee_code,E.full_name,'Y' AS CHECKED_FLAG
            FROM HRIS_EMPLOYEES E
            JOIN (SELECT * FROM HRIS_MONTH_CODE WHERE MONTH_ID=:monthId) MC on (1=1)
            WHERE 
            E.STATUS='E' AND 
employee_id not in (SELECT employee_id FROM HRIS_SALARY_SHEET_EMP_DETAIL SED
JOIN HRIS_SALARY_SHEET SS ON (SS.SHEET_NO=SED.SHEET_NO) 
where SS.month_id=:monthId AND SS.SALARY_TYPE_ID=:salaryTypeId)  
AND  E.JOIN_DATE<MC.TO_DATE 
AND GROUP_ID IN ({$group})";

if($salaryTypeId>1){
            $sql.=" and E.employee_id in (
            select distinct employee_id from HRIS_SS_PAY_VALUE_MODIFIED 
            where 
            month_id=:monthId
            and SALARY_TYPE_ID=:salaryTypeId
            )";
        }
        
        $data = $this->rawQuery($sql, $boundedParameter);
        return $data;
    }
    
    public function fetchGeneratedSheetByGroup($monthId,$group,$salaryTypeId){
        $boundedParameter = [];
        
        $boundedParameter['salaryTypeId'] = $salaryTypeId;
        $boundedParameter['monthId'] = $monthId;
        $sql="select 
                ss.sheet_no,ssg.Group_Name,Mc.Month_Edesc,St.Salary_Type_Name,
                ss.approved, ss.locked 
                from HRIS_SALARY_SHEET ss 
                join hris_salary_sheet_group ssg on (ssg.group_id=ss.group_id)
                join Hris_Month_Code mc on (mc.month_id=ss.month_id)
                join HRIS_SALARY_TYPE st on (St.Salary_Type_Id=Ss.Salary_Type_Id)
                where ss.Month_Id=:monthId and ss.salary_type_id=:salaryTypeId  and ss.Group_Id in ($group)";
        
        $data = $this->rawQuery($sql, $boundedParameter);
        return $data;
    }
    
    public function insertPayrollEmp($empList,$monthId,$salaryTypeId) {
        $deleteSql = "delete from HRIS_PAYROLL_EMP_LIST";
        $this->executeStatement($deleteSql);
        $boundedParameter = [];
        $boundedParameter['salaryTypeId'] = $salaryTypeId;
        $boundedParameter['monthId'] = $monthId;
        foreach ($empList as $employeeId) {
            $boundedParameter['employeeId'] = $employeeId;
//            $tempSql = "INSERT INTO HRIS_PAYROLL_EMP_LIST VALUES ({$employeeId})";
            $tempSql = "INSERT INTO HRIS_PAYROLL_EMP_LIST 
                    select * from (select :employeeId as employee_id from dual) 
where employee_id not in (select employee_id from Hris_Salary_Sheet_Emp_Detail ssed
join HRIS_SALARY_SHEET ss on (ss.SHEET_NO=ssed.SHEET_NO)
where ss.month_id=:monthId and ss.SALARY_TYPE_ID=:salaryTypeId)";
            $this->executeStatement($tempSql, $boundedParameter);
        }
        $toGenerateGroupSql="select  distinct group_id 
                from 
                 hris_employees where employee_id in
                 (select employee_id from HRIS_PAYROLL_EMP_LIST)";
        $groupData = $this->rawQuery($toGenerateGroupSql);
        return $groupData;
    }
    
    public function deleteSheetBySheetNo($sheetNo){
        $sql="
        BEGIN            
        delete from HRIS_TAX_SHEET where sheet_no=:sheetNo;
        delete from HRIS_SALARY_SHEET_DETAIL where sheet_no=:sheetNo;
        delete from HRIS_SALARY_SHEET_EMP_DETAIL where sheet_no=:sheetNo;
        delete from HRIS_SALARY_SHEET where sheet_no=:sheetNo;
        END;
";
        $boundedParameter = [];
        $boundedParameter['sheetNo'] = $sheetNo;
        $this->executeStatement($sql, $boundedParameter);
        return true;
    }

    public function deleteSheetByempId($employeeId, $monthId, $sheetNo){
        $sql="
        BEGIN            
        delete from HRIS_TAX_SHEET where sheet_no=:sheetNo and employee_id=:employeeId ;
        delete from HRIS_SALARY_SHEET_DETAIL where sheet_no=:sheetNo and employee_id=:employeeId;
        delete from HRIS_SALARY_SHEET_EMP_DETAIL where sheet_no=:sheetNo and employee_id=:employeeId;
        delete from HRIS_SALARY_SHEET where sheet_no=:sheetNo and month_no=:monthId;
        END;
";
        $boundedParameter = [];
        $boundedParameter['sheetNo'] = $sheetNo;
        $boundedParameter['employeeId'] = $employeeId;
        $boundedParameter['monthId'] = $monthId;
        $this->executeStatement($sql, $boundedParameter);
        return true;
    }

    

    public function bulkApproveLock($sheetNo, $col, $val){
        $sql = "UPDATE HRIS_SALARY_SHEET set $col = :val where sheet_no=:sheetNo";
        $boundedParameter = [];
        $boundedParameter['sheetNo'] = $sheetNo;
        //$boundedParameter['col'] = $col;
        $boundedParameter['val'] = $val;
        $this->executeStatement($sql, $boundedParameter);
        return true;
    }

    public function checkApproveLock($sheetNo){
        $sql = "SELECT SHEET_NO, APPROVED, LOCKED FROM HRIS_SALARY_SHEET WHERE SHEET_NO = :sheetNo";
        $boundedParameter = [];
        $boundedParameter['sheetNo'] = $sheetNo;
        return $this->rawQuery($sql, $boundedParameter);
        //return Helper::extractDbData($data);
    }
    
    public function fetchSheetWiseEmployeeList($sheetNo){
        $sql = "select SS.SHEET_NO,SS.MONTH_ID,SS.SALARY_TYPE_ID,SSED.EMPLOYEE_ID from HRIS_SALARY_SHEET SS
JOIN HRIS_SALARY_SHEET_EMP_DETAIL SSED ON (SS.SHEET_NO=SSED.SHEET_NO)
where SS.SHEET_NO=:sheetNo";
        $boundedParameter = [];
        $boundedParameter['sheetNo'] = $sheetNo;
        return $this->rawQuery($sql, $boundedParameter);
        //return Helper::extractDbData($data);
    }
}
