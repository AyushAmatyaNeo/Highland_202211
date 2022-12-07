<?php

namespace MobileApi\Repository;

use Application\Helper\EntityHelper;
use Application\Helper\Helper;
use Application\Model\Model;
use LeaveManagement\Model\LeaveApply;
use LeaveManagement\Model\LeaveAssign;
use LeaveManagement\Model\LeaveMaster;
use SelfService\Model\LeaveSubstitute;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway;

class LeaveRepository {

    private $adapter;

    public function __construct(AdapterInterface $adapter) {
        $this->adapter = $adapter;
    }

    public function getApproval($id) {
        // var_dump($id); die;
        $boundedParameter = [];
        $boundedParameter['id'] = $id;
        $sql = "SELECT DISTINCT * FROM (
                SELECT 
                  LA.ID                  AS ID,
                  LA.EMPLOYEE_ID,
                  E.EMPLOYEE_CODE AS EMPLOYEE_CODE,
                  INITCAP(E.FULL_NAME)   AS FULL_NAME,
                  INITCAP(L.LEAVE_ENAME) AS LEAVE_ENAME,
                  INITCAP(TO_CHAR(LA.START_DATE, 'DD-MON-YYYY'))   AS START_DATE_AD,
                  BS_DATE(TO_CHAR(LA.START_DATE, 'DD-MON-YYYY'))   AS START_DATE_BS,
                  INITCAP(TO_CHAR(LA.END_DATE, 'DD-MON-YYYY'))     AS END_DATE_AD,
                  BS_DATE(TO_CHAR(LA.END_DATE, 'DD-MON-YYYY'))     AS END_DATE_BS,
                  LA.NO_OF_DAYS,
                  INITCAP(TO_CHAR(LA.REQUESTED_DT, 'DD-MON-YYYY')) AS APPLIED_DATE_AD,
                  BS_DATE(TO_CHAR(LA.REQUESTED_DT, 'DD-MON-YYYY')) AS APPLIED_DATE_BS,
                  LA.HALF_DAY AS HALF_DAY,
                  (CASE WHEN (LA.HALF_DAY IS NULL OR LA.HALF_DAY = 'N') THEN 'Full Day' WHEN (LA.HALF_DAY = 'F') THEN 'First Half' ELSE 'Second Half' END) AS HALF_DAY_DETAIL,
                  LA.GRACE_PERIOD AS GRACE_PERIOD,
                  (CASE WHEN LA.GRACE_PERIOD = 'E' THEN 'Early' WHEN LA.GRACE_PERIOD = 'L' THEN 'Late' ELSE '-' END) AS GRACE_PERIOD_DETAIL,
                   LA.REMARKS AS REMARKS,                  
                  LA.STATUS                            AS STATUS,
                  LEAVE_STATUS_DESC(LA.STATUS) AS STATUS_DETAIL,
                  LA.RECOMMENDED_BY AS RECOMMENDED_BY,
                  INITCAP(TO_CHAR(LA.RECOMMENDED_DT, 'DD-MON-YYYY')) AS RECOMMENDED_DT,
                  LA.RECOMMENDED_REMARKS AS RECOMMENDED_REMARKS,
                  LA.APPROVED_BY AS APPROVED_BY,
                  INITCAP(TO_CHAR(LA.APPROVED_DT, 'DD-MON-YYYY')) AS APPROVED_DT,
                  LA.APPROVED_REMARKS AS APPROVED_REMARKS,
                  RA.RECOMMEND_BY                                         AS RECOMMENDER,
                  RA.APPROVED_BY                                          AS APPROVER,
                  LS.APPROVED_FLAG                                        AS APPROVED_FLAG,
                  INITCAP(TO_CHAR(LS.APPROVED_DATE, 'DD-MON-YYYY'))       AS SUB_APPROVED_DATE,
                  LS.EMPLOYEE_ID                                          AS SUB_EMPLOYEE_ID,
                  REC_APP_ROLE(U.EMPLOYEE_ID,
                  CASE WHEN L.ENABLE_OVERRIDE='Y'  THEN RAO.RECOMMENDER
                  WHEN ALR.R_A_ID IS NOT NULL THEN ALR.R_A_ID ELSE RA.RECOMMEND_BY END,
                  CASE WHEN L.ENABLE_OVERRIDE='Y'  THEN RAO.APPROVER
                  WHEN ALA.R_A_ID IS NOT NULL THEN ALA.R_A_ID ELSE RA.APPROVED_BY END
                  )      AS ROLE,
                  REC_APP_ROLE_NAME(U.EMPLOYEE_ID,
                  CASE WHEN L.ENABLE_OVERRIDE='Y'  THEN RAO.RECOMMENDER
                  WHEN ALR.R_A_ID IS NOT NULL THEN ALR.R_A_ID ELSE RA.RECOMMEND_BY END,
                  CASE WHEN L.ENABLE_OVERRIDE='Y'  THEN RAO.APPROVER
                  WHEN ALA.R_A_ID IS NOT NULL THEN ALA.R_A_ID ELSE RA.APPROVED_BY END
                  ) AS YOUR_ROLE,
                  CASE WHEN ( ALR.R_A_ID IS NOT NULL OR ALA.R_A_ID  IS NOT NULL ) THEN 'SECONDARY' ELSE 'PRIMARY' END AS PRI_SEC
                FROM HRIS_EMPLOYEE_LEAVE_REQUEST LA
                LEFT JOIN HRIS_LEAVE_MASTER_SETUP L
                ON L.LEAVE_ID=LA.LEAVE_ID
                LEFT JOIN HRIS_EMPLOYEES E
                ON E.EMPLOYEE_ID=LA.EMPLOYEE_ID
                LEFT JOIN HRIS_EMPLOYEES E1
                ON E1.EMPLOYEE_ID=LA.RECOMMENDED_BY
                LEFT JOIN HRIS_EMPLOYEES E2
                ON E2.EMPLOYEE_ID=LA.APPROVED_BY
                LEFT JOIN HRIS_RECOMMENDER_APPROVER RA
                ON (E.EMPLOYEE_ID=RA.EMPLOYEE_ID and RA.status = 'E')
                LEFT JOIN HRIS_LEAVE_SUBSTITUTE LS
                ON LA.ID              = LS.LEAVE_REQUEST_ID
                LEFT JOIN HRIS_ALTERNATE_R_A ALR
                ON(ALR.R_A_FLAG='R' AND ALR.EMPLOYEE_ID=LA.EMPLOYEE_ID AND ALR.R_A_ID=:id)
                LEFT JOIN HRIS_ALTERNATE_R_A ALA
                ON(ALA.R_A_FLAG='A' AND ALA.EMPLOYEE_ID=LA.EMPLOYEE_ID AND ALA.R_A_ID=:id)
                -- CHANGES
                LEFT JOIN hris_rec_app_override RAO ON E.EMPLOYEE_ID=RAO.EMPLOYEE_ID
                LEFT JOIN HRIS_EMPLOYEES U
                ON(
                (
                (U.EMPLOYEE_ID   = RA.RECOMMEND_BY
                OR U.EMPLOYEE_ID   =RA.APPROVED_BY
                OR U.EMPLOYEE_ID   =ALR.R_A_ID
                OR U.EMPLOYEE_ID   =ALA.R_A_ID)
               AND L.ENABLE_OVERRIDE='N' )
               OR
               (
                (U.EMPLOYEE_ID   = RAO.recommender
                OR U.EMPLOYEE_ID   =RAO.approver
               ) AND L.ENABLE_OVERRIDE='Y' 
               )
               
                )
                
             -- CHANGES
                
                
                WHERE E.STATUS        ='E'
                AND E.RETIRED_FLAG    ='N'
                AND ((
                (
                (
                (RA.RECOMMEND_BY= U.EMPLOYEE_ID)
                OR(ALR.R_A_ID= U.EMPLOYEE_ID)
                AND L.ENABLE_OVERRIDE='N'
                ) OR (RAO.recommender=U.EMPLOYEE_ID AND L.ENABLE_OVERRIDE='Y' )
                )
                AND LA.STATUS IN ('RQ')) 
                OR (
                (
                (
                (RA.APPROVED_BY= U.EMPLOYEE_ID)
                OR(ALA.R_A_ID= U.EMPLOYEE_ID)
                AND L.ENABLE_OVERRIDE='N'
                )
                OR ( RAO.APPROVER=U.EMPLOYEE_ID AND L.ENABLE_OVERRIDE='N' )
                )
                AND LA.STATUS IN ('RC')) )
                AND U.EMPLOYEE_ID=:id
                AND (LS.APPROVED_FLAG =
                  CASE
                    WHEN LS.EMPLOYEE_ID IS NOT NULL
                    THEN ('Y')
                  END
                OR LS.EMPLOYEE_ID IS NULL
                OR LA.STATUS IN ('CP','CR'))
                ORDER BY LA.REQUESTED_DT DESC)";
	
        $statement = $this->adapter->query($sql);
        $result = $statement->execute($boundedParameter);
        return Helper::extractDbData($result);
    }

    public function getSubstituteApprovalByEmpId($employeeId) {
        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->columns([
            new Expression("INITCAP(TO_CHAR(LA.START_DATE, 'DD-MON-YYYY')) AS FROM_DATE_AD"),
            new Expression("BS_DATE(TO_CHAR(LA.START_DATE, 'DD-MON-YYYY')) AS FROM_DATE_BS"),
            new Expression("INITCAP(TO_CHAR(LA.END_DATE, 'DD-MON-YYYY')) AS TO_DATE_AD"),
            new Expression("BS_DATE(TO_CHAR(LA.END_DATE, 'DD-MON-YYYY')) AS TO_DATE_BS"),
            new Expression("INITCAP(TO_CHAR(LA.REQUESTED_DT, 'DD-MON-YYYY')) AS REQUESTED_DT_AD"),
            new Expression("BS_DATE(TO_CHAR(LA.REQUESTED_DT, 'DD-MON-YYYY')) AS REQUESTED_DT_BS"),
            new Expression("INITCAP(TO_CHAR(LA.APPROVED_DT, 'DD-MON-YYYY')) AS APPROVED_DT"),
            new Expression("INITCAP(TO_CHAR(LA.RECOMMENDED_DT, 'DD-MON-YYYY')) AS RECOMMENDED_DT"),
            new Expression("LA.STATUS AS STATUS"),
            new Expression("LA.RECOMMENDED_REMARKS AS RECOMMENDED_REMARKS"),
            new Expression("LA.APPROVED_REMARKS AS APPROVED_REMARKS"),
            new Expression("LA.REMARKS AS REMARKS"),
            new Expression("LA.NO_OF_DAYS AS NO_OF_DAYS"),
            new Expression("LA.ID AS ID"),
            new Expression("LA.RECOMMENDED_BY AS RECOMMENDED_BY"),
            new Expression("LA.APPROVED_BY AS APPROVED_BY")
                ], true);

        $select->from(['LA' => LeaveApply::TABLE_NAME])
                ->join(['E' => "HRIS_EMPLOYEES"], "E.EMPLOYEE_ID=LA.EMPLOYEE_ID", ["FULL_NAME" => new Expression("INITCAP(E.FULL_NAME)")])
                ->join(['L' => 'HRIS_LEAVE_MASTER_SETUP'], "L.LEAVE_ID=LA.LEAVE_ID", ['LEAVE_CODE', 'LEAVE_ENAME' => new Expression("INITCAP(L.LEAVE_ENAME)")])
                ->join(['E1' => "HRIS_EMPLOYEES"], "E1.EMPLOYEE_ID=LA.RECOMMENDED_BY", ['RECOMMENDED_BY_NAME' => new Expression("INITCAP(E1.FULL_NAME)")], "left")
                ->join(['E2' => "HRIS_EMPLOYEES"], "E2.EMPLOYEE_ID=LA.APPROVED_BY", ['APPROVED_BY_NAME' => new Expression("INITCAP(E2.FULL_NAME)")], "left")
                ->join(['RA' => "HRIS_RECOMMENDER_APPROVER"], "RA.EMPLOYEE_ID=LA.EMPLOYEE_ID", ['RECOMMENDER_ID' => 'RECOMMEND_BY', 'APPROVER_ID' => 'APPROVED_BY'], "left")
                ->join(['RECM' => "HRIS_EMPLOYEES"], "RECM.EMPLOYEE_ID=RA.RECOMMEND_BY", ['RECOMMENDER_NAME' => new Expression("INITCAP(RECM.FULL_NAME)")], "left")
                ->join(['APRV' => "HRIS_EMPLOYEES"], "APRV.EMPLOYEE_ID=RA.APPROVED_BY", ['APPROVER_NAME' => new Expression("INITCAP(APRV.FULL_NAME)")], "left")
                ->join(['LS' => "HRIS_LEAVE_SUBSTITUTE"], "LS.LEAVE_REQUEST_ID=LA.ID", ["SUB_EMPLOYEE_ID" => "EMPLOYEE_ID", "SUB_APPROVED_DATE_AD" => new Expression("INITCAP(TO_CHAR(LS.APPROVED_DATE, 'DD-MON-YYYY'))"), "SUB_APPROVED_DATE_BS" => new Expression("BS_DATE(LS.APPROVED_DATE)"), "SUB_APPROVED_FLAG" => "APPROVED_FLAG"], "left");

        $select->where([
            "L.STATUS='E'",
            "LS.EMPLOYEE_ID= {$employeeId} "
        ]);
        $select->order("LA.REQUESTED_DT DESC");
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        // var_dump($statement); die;
        return Helper::extractDbData($result);
    }

    public function getSubstituteApprovalById($id) {
        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->columns([
            new Expression("INITCAP(TO_CHAR(LA.START_DATE, 'DD-MON-YYYY')) AS FROM_DATE_AD"),
            new Expression("BS_DATE(TO_CHAR(LA.START_DATE, 'DD-MON-YYYY')) AS FROM_DATE_BS"),
            new Expression("INITCAP(TO_CHAR(LA.END_DATE, 'DD-MON-YYYY')) AS TO_DATE_AD"),
            new Expression("BS_DATE(TO_CHAR(LA.END_DATE, 'DD-MON-YYYY')) AS TO_DATE_BS"),
            new Expression("INITCAP(TO_CHAR(LA.REQUESTED_DT, 'DD-MON-YYYY')) AS REQUESTED_DT_AD"),
            new Expression("BS_DATE(TO_CHAR(LA.REQUESTED_DT, 'DD-MON-YYYY')) AS REQUESTED_DT_BS"),
            new Expression("INITCAP(TO_CHAR(LA.APPROVED_DT, 'DD-MON-YYYY')) AS APPROVED_DT"),
            new Expression("INITCAP(TO_CHAR(LA.RECOMMENDED_DT, 'DD-MON-YYYY')) AS RECOMMENDED_DT"),
            new Expression("LA.STATUS AS STATUS"),
            new Expression("LA.RECOMMENDED_REMARKS AS RECOMMENDED_REMARKS"),
            new Expression("LA.APPROVED_REMARKS AS APPROVED_REMARKS"),
            new Expression("LA.REMARKS AS REMARKS"),
            new Expression("LA.NO_OF_DAYS AS NO_OF_DAYS"),
            new Expression("LA.ID AS ID"),
            new Expression("LA.RECOMMENDED_BY AS RECOMMENDED_BY"),
            new Expression("LA.APPROVED_BY AS APPROVED_BY")
                ], true);

        $select->from(['LA' => LeaveApply::TABLE_NAME])
                ->join(['E' => "HRIS_EMPLOYEES"], "E.EMPLOYEE_ID=LA.EMPLOYEE_ID", ["FULL_NAME" => new Expression("INITCAP(E.FULL_NAME)")])
                ->join(['L' => 'HRIS_LEAVE_MASTER_SETUP'], "L.LEAVE_ID=LA.LEAVE_ID", ['LEAVE_CODE', 'LEAVE_ENAME' => new Expression("INITCAP(L.LEAVE_ENAME)")])
                ->join(['E1' => "HRIS_EMPLOYEES"], "E1.EMPLOYEE_ID=LA.RECOMMENDED_BY", ['RECOMMENDED_BY_NAME' => new Expression("INITCAP(E1.FULL_NAME)")], "left")
                ->join(['E2' => "HRIS_EMPLOYEES"], "E2.EMPLOYEE_ID=LA.APPROVED_BY", ['APPROVED_BY_NAME' => new Expression("INITCAP(E2.FULL_NAME)")], "left")
                ->join(['RA' => "HRIS_RECOMMENDER_APPROVER"], "RA.EMPLOYEE_ID=LA.EMPLOYEE_ID", ['RECOMMENDER_ID' => 'RECOMMEND_BY', 'APPROVER_ID' => 'APPROVED_BY'], "left")
                ->join(['RECM' => "HRIS_EMPLOYEES"], "RECM.EMPLOYEE_ID=RA.RECOMMEND_BY", ['RECOMMENDER_NAME' => new Expression("INITCAP(RECM.FULL_NAME)")], "left")
                ->join(['APRV' => "HRIS_EMPLOYEES"], "APRV.EMPLOYEE_ID=RA.APPROVED_BY", ['APPROVER_NAME' => new Expression("INITCAP(APRV.FULL_NAME)")], "left")
                ->join(['LS' => "HRIS_LEAVE_SUBSTITUTE"], "LS.LEAVE_REQUEST_ID=LA.ID", ["SUB_EMPLOYEE_ID" => "EMPLOYEE_ID", "SUB_APPROVED_DATE_AD" => new Expression("INITCAP(TO_CHAR(LS.APPROVED_DATE, 'DD-MON-YYYY'))"), "SUB_APPROVED_DATE_BS" => new Expression("BS_DATE(LS.APPROVED_DATE)"), "SUB_APPROVED_FLAG" => "APPROVED_FLAG"], "left");

        $select->where([
            "L.STATUS='E'",
            "LA.ID=" . $id
        ]);
        $select->order("LA.REQUESTED_DT DESC");
        $statement = $sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        return $result->current();
    }

    public function updateSubstituteApproval(Model $model, $id) {
        $gateway = new TableGateway(LeaveSubstitute::TABLE_NAME, $this->adapter);
        $gateway->update($model->getArrayCopyForDB(), [LeaveSubstitute::LEAVE_REQUEST_ID => $id]);
    }

    public function getEmployeeLeave($employeeId) {
      //  $sql = new Sql($this->adapter);
      //  $select = $sql->select();
       // $select->columns(EntityHelper::getColumnNameArrayWithOracleFns(LeaveMaster::class, [LeaveMaster::LEAVE_ENAME], NULL, NULL, NULL, NULL, 'L', false), false);
       // $select->from(['L' => LeaveMaster::TABLE_NAME]);
       // $select->join(['LA' => LeaveAssign::TABLE_NAME], "LA." . LeaveAssign::LEAVE_ID . "=" . "L." . LeaveMaster::LEAVE_ID, [], 'left');
       // $select->where(["L.STATUS= 'E'"]);
       /// $select->where(["LA.EMPLOYEE_ID" => $employeeId]);
      //  $select->order([LeaveMaster::LEAVE_ENAME => Select::ORDER_ASCENDING]);
      //  $statement = $sql->prepareStatementForSqlObject($select);
	  
	  $sql="SELECT
     distinct l.leave_id AS leave_id,
    l.leave_code AS leave_code,
    initcap(l.leave_ename) AS leave_ename,
    l.leave_lname AS leave_lname,
    l.allow_halfday AS allow_halfday,
    l.default_days AS default_days,
    l.fiscal_year AS fiscal_year,
    l.carry_forward AS carry_forward,
    l.cashable AS cashable,
    l.created_dt AS created_dt,
    l.modified_dt AS modified_dt,
    l.status AS status,
    l.remarks AS remarks,
    l.created_by AS created_by,
    l.modified_by AS modified_by,
    l.paid AS paid,
    l.max_accumulate_days AS max_accumulate_days,
    l.is_substitute AS is_substitute,
    l.allow_grace_leave AS allow_grace_leave,
    l.is_monthly AS is_monthly,
    l.is_substitute_mandatory AS is_substitute_mandatory,
    l.assign_on_employee_setup AS assign_on_employee_setup,
    l.is_prodata_basis AS is_prodata_basis,
    l.enable_substitute AS enable_substitute,
    l.company_id AS company_id,
    l.branch_id AS branch_id,
    l.department_id AS department_id,
    l.designation_id AS designation_id,
    l.position_id AS position_id,
    l.service_type_id AS service_type_id,
    l.employee_type AS employee_type,
    l.gender_id AS gender_id,
    l.employee_id AS employee_id,
    l.day_off_as_leave AS day_off_as_leave,
    l.holiday_as_leave AS holiday_as_leave,
    la.balance AS remaining
    FROM
    hris_leave_master_setup l
    LEFT JOIN hris_employee_leave_assign la ON la.leave_id = l.leave_id
    WHERE
        l.status = 'E'
    AND
        la.employee_id ={$employeeId}
    ORDER BY leave_ename ASC";
		$statement = $this->adapter->query($sql);
        $result = $statement->execute();
        return Helper::extractDbData($result);
    }

    public function getRoleIdApproval($tid,$id)
    {
        $boundedParameter = [];
        $boundedParameter['id'] = $id;
        $sql = "SELECT DISTINCT * FROM (
                SELECT 
                  REC_APP_ROLE(U.EMPLOYEE_ID,
                  CASE WHEN L.ENABLE_OVERRIDE='Y'  THEN RAO.RECOMMENDER
                  WHEN ALR.R_A_ID IS NOT NULL THEN ALR.R_A_ID ELSE RA.RECOMMEND_BY END,
                  CASE WHEN L.ENABLE_OVERRIDE='Y'  THEN RAO.APPROVER
                  WHEN ALA.R_A_ID IS NOT NULL THEN ALA.R_A_ID ELSE RA.APPROVED_BY END
                  )      AS ROLE
                FROM HRIS_EMPLOYEE_LEAVE_REQUEST LA
                LEFT JOIN HRIS_LEAVE_MASTER_SETUP L
                ON L.LEAVE_ID=LA.LEAVE_ID
                LEFT JOIN HRIS_EMPLOYEES E
                ON E.EMPLOYEE_ID=LA.EMPLOYEE_ID
                LEFT JOIN HRIS_EMPLOYEES E1
                ON E1.EMPLOYEE_ID=LA.RECOMMENDED_BY
                LEFT JOIN HRIS_EMPLOYEES E2
                ON E2.EMPLOYEE_ID=LA.APPROVED_BY
                LEFT JOIN HRIS_RECOMMENDER_APPROVER RA
                ON (E.EMPLOYEE_ID=RA.EMPLOYEE_ID and RA.status = 'E')
                LEFT JOIN HRIS_LEAVE_SUBSTITUTE LS
                ON LA.ID              = LS.LEAVE_REQUEST_ID
                LEFT JOIN HRIS_ALTERNATE_R_A ALR
                ON(ALR.R_A_FLAG='R' AND ALR.EMPLOYEE_ID=LA.EMPLOYEE_ID AND ALR.R_A_ID=:id)
                LEFT JOIN HRIS_ALTERNATE_R_A ALA
                ON(ALA.R_A_FLAG='A' AND ALA.EMPLOYEE_ID=LA.EMPLOYEE_ID AND ALA.R_A_ID=:id)
                -- CHANGES
                LEFT JOIN hris_rec_app_override RAO ON E.EMPLOYEE_ID=RAO.EMPLOYEE_ID
                LEFT JOIN HRIS_EMPLOYEES U
                ON(
                (
                (U.EMPLOYEE_ID   = RA.RECOMMEND_BY
                OR U.EMPLOYEE_ID   =RA.APPROVED_BY
                OR U.EMPLOYEE_ID   =ALR.R_A_ID
                OR U.EMPLOYEE_ID   =ALA.R_A_ID)
               AND L.ENABLE_OVERRIDE='N' )
               OR
               (
                (U.EMPLOYEE_ID   = RAO.recommender
                OR U.EMPLOYEE_ID   =RAO.approver
               ) AND L.ENABLE_OVERRIDE='Y' 
               )
               
                )
                
             -- CHANGES
                
                
                WHERE E.STATUS        ='E'
                AND E.RETIRED_FLAG    ='N'
                AND LA.ID = {$tid}
                AND ((
                (
                (
                (RA.RECOMMEND_BY= U.EMPLOYEE_ID)
                OR(ALR.R_A_ID= U.EMPLOYEE_ID)
                AND L.ENABLE_OVERRIDE='N'
                ) OR (RAO.recommender=U.EMPLOYEE_ID AND L.ENABLE_OVERRIDE='Y' )
                )
                AND LA.STATUS IN ('RQ')) 
                OR (
                (
                (
                (RA.APPROVED_BY= U.EMPLOYEE_ID)
                OR(ALA.R_A_ID= U.EMPLOYEE_ID)
                AND L.ENABLE_OVERRIDE='N'
                )
                OR ( RAO.APPROVER=U.EMPLOYEE_ID AND L.ENABLE_OVERRIDE='N' )
                )
                AND LA.STATUS IN ('RC')) )
                AND U.EMPLOYEE_ID=:id
                AND (LS.APPROVED_FLAG =
                  CASE
                    WHEN LS.EMPLOYEE_ID IS NOT NULL
                    THEN ('Y')
                  END
                OR LS.EMPLOYEE_ID IS NULL
                OR LA.STATUS IN ('CP','CR'))
                ORDER BY LA.REQUESTED_DT DESC)";
	
        $statement = $this->adapter->query($sql);
        $result = $statement->execute($boundedParameter);
        return Helper::extractDbData($result);
    }

    public function checkIfAdmin($id)
    {
      $sql = "SELECT ROLE_ID from hris_users where employee_id = {$id}";
      $statement = $this->adapter->query($sql);
      $result = $statement->execute();
      return Helper::extractDbData($result);
    }
    public function checkMenuPermission($id)
    {
      $sql = "select ROLE_ID from hris_users where employee_id = {$id}";
      $statement = $this->adapter->query($sql);

      $result = $statement->execute()->current();
      // var_dump($result['ROLE_ID']); die;
      $sql = "SELECT * FROM HRIS_ROLE_PERMISSIONS where MENU_ID = '5' and ROLE_ID = '{$result['ROLE_ID']}' and STATUS = 'E'";
      $statement = $this->adapter->query($sql);

      $result = $statement->execute()->current();
      // var_dump($result); die;
      return $result;
    }

}
