<?php
namespace Payroll\Repository;

use Application\Repository\HrisRepository;
use Payroll\Model\SSPayValueModified;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Select;

class SSPayValueModifiedRepo extends HrisRepository {

    public function __construct(AdapterInterface $adapter, $tableName = null) {
        if ($tableName === null) {
            $tableName = SSPayValueModified::TABLE_NAME;
        }
        parent::__construct($adapter, $tableName);
    }

    public function filter($monthId, $companyId = null, $groupId = null) {
        $csv = $this->fetchCSVSSRules();
        $employeeCondition = "";
        $boundedParameter = [];
        if ($companyId != null) {
            $employeeCondition = " AND E.COMPANY_ID = :companyId";
            $boundedParameter['companyId'] = $companyId;
        }

        if ($groupId != null) {
            $employeeCondition = " AND E.GROUP_ID = :groupId";
            $boundedParameter['groupId'] = $groupId;
        }
        $boundedParameter['monthId'] = $monthId;
        $sql = "SELECT E.EMPLOYEE_ID,
                  E.FULL_NAME,
                  C.COMPANY_ID,
                  C.COMPANY_NAME,
                  SSG.GROUP_ID,
                  SSG.GROUP_NAME,
                  PV.*
                FROM HRIS_EMPLOYEES E
                LEFT JOIN HRIS_COMPANY C ON (E.COMPANY_ID=C.COMPANY_ID)
                LEFT JOIN HRIS_SALARY_SHEET_GROUP SSG ON (E.GROUP_ID=SSG.GROUP_ID)
                LEFT JOIN
                  (SELECT *
                  FROM
                    (SELECT MONTH_ID, PAY_ID, EMPLOYEE_ID AS E_ID,VAL FROM HRIS_SS_PAY_VALUE_MODIFIED WHERE MONTH_ID =:monthId
                    ) PIVOT (MAX(VAL) FOR PAY_ID IN ({$csv}))
                  ) PV
                ON (E.EMPLOYEE_ID=PV.E_ID)
                WHERE E.STATUS   ='E' {$employeeCondition} ORDER BY C.COMPANY_NAME,SSG.GROUP_NAME,E.FULL_NAME";
        return $this->rawQuery($sql, $boundedParameter);
    }

    private function fetchCSVSSRules(): string {
        $sql = "SELECT PAY_ID
                FROM HRIS_PAY_SETUP
                WHERE INCLUDE_IN_SALARY='Y'
                AND PAY_TYPE_FLAG     IN ('A','D')
                AND STATUS ='E'
                ORDER BY PRIORITY_INDEX";
        $statement = $this->adapter->query($sql);
        $rawList = $statement->execute();

        $dbArray = "";
        foreach ($rawList as $key => $row) {
            if ($key == sizeof($rawList)) {
                $dbArray .= "{$row['PAY_ID']} AS H_{$row['PAY_ID']}";
            } else {
                $dbArray .= "{$row['PAY_ID']} AS H_{$row['PAY_ID']},";
            }
        }
        return $dbArray;
    }

    public function bulkEdit($data) {
        foreach ($data as $value) {
            $this->createOrUpdate($value['MONTH_ID'], $value['EMPLOYEE_ID'], $value['PAY_ID'], $value['VAL']);
        }
    }

    private function createOrUpdate($m, $e, $p, $v) {
        $boundedParameter = [];
        $boundedParameter['m'] = $m;
        $boundedParameter['e'] = $e;
        $boundedParameter['p'] = $p;
        $boundedParameter['v'] = $v;
        $sql = "DECLARE
                  V_MONTH_ID HRIS_SS_PAY_VALUE_MODIFIED.MONTH_ID%TYPE      :=:m;
                  V_EMPLOYEE_ID HRIS_SS_PAY_VALUE_MODIFIED.EMPLOYEE_ID%TYPE:=:e;
                  V_PAY_ID HRIS_SS_PAY_VALUE_MODIFIED.PAY_ID%TYPE          :=:p;
                  V_VAL HRIS_SS_PAY_VALUE_MODIFIED.VAL%TYPE                :=:v;
                  V_ROW_COUNT NUMBER;
                BEGIN
                  SELECT COUNT(*)
                  INTO V_ROW_COUNT
                  FROM HRIS_SS_PAY_VALUE_MODIFIED
                  WHERE MONTH_ID  =V_MONTH_ID
                  AND EMPLOYEE_ID = V_EMPLOYEE_ID
                  AND PAY_ID      = V_PAY_ID;
                  IF (V_ROW_COUNT >0 ) THEN
                    UPDATE HRIS_SS_PAY_VALUE_MODIFIED
                    SET VAL         =V_VAL
                    WHERE MONTH_ID  =V_MONTH_ID
                    AND EMPLOYEE_ID = V_EMPLOYEE_ID
                    AND PAY_ID      = V_PAY_ID;
                  ELSE
                    INSERT
                    INTO HRIS_SS_PAY_VALUE_MODIFIED
                      (
                        MONTH_ID,
                        EMPLOYEE_ID,
                        PAY_ID,
                        VAL
                      )
                      VALUES
                      (
                        V_MONTH_ID,
                        V_EMPLOYEE_ID,
                        V_PAY_ID,
                        V_VAL
                      );
                  END IF;
                END;";
        $this->executeStatement($sql, $boundedParameter);
    }

    public function fetch($q) {
        $iterator = $this->tableGateway->select(function(Select $select) use($q) {
            $select->where([
                SSPayValueModified::MONTH_ID => $q['MONTH_ID'],
                SSPayValueModified::PAY_ID => $q['PAY_ID'],
                SSPayValueModified::EMPLOYEE_ID => $q['EMPLOYEE_ID'],
                SSPayValueModified::SALARY_TYPE_ID => $q['SALARY_TYPE_ID']
            ]);
        });
        $data = iterator_to_array($iterator);
        if (count($data) == 1) {
            return $data[0]['VAL'];
        }
        return null;
    }

    public function setModifiedPayValue($data, $monthId, $salaryTypeId) {
        $payId = $data['payId'];
        $employeeId = $data['employeeId'];
        $sql = "";
        if($data['value'] == null || $data['value'] == ''){
          $sql = "DELETE FROM HRIS_SS_PAY_VALUE_MODIFIED
                  WHERE PAY_ID       = $payId
                  AND EMPLOYEE_ID    = $employeeId
                  AND MONTH_ID = $monthId
                  AND SALARY_TYPE_ID = $salaryTypeId";
        }
        else{
          $value = $data['value'];
          $sql = "
                DECLARE
                  V_PAY_ID HRIS_SS_PAY_VALUE_MODIFIED.PAY_ID%TYPE := $payId;
                  V_EMPLOYEE_ID HRIS_SS_PAY_VALUE_MODIFIED.EMPLOYEE_ID%TYPE := $employeeId;
                  V_PAY_VALUE HRIS_SS_PAY_VALUE_MODIFIED.VAL%TYPE := $value;
                  V_MONTH_ID HRIS_SS_PAY_VALUE_MODIFIED.MONTH_ID%TYPE := $monthId;
                  V_SALARY_TYPE_ID HRIS_SS_PAY_VALUE_MODIFIED.SALARY_TYPE_ID%TYPE := $salaryTypeId;
                  V_OLD_FLAT_VALUE HRIS_SS_PAY_VALUE_MODIFIED.VAL%TYPE;
                BEGIN
                  SELECT VAL
                  INTO V_OLD_FLAT_VALUE
                  FROM HRIS_SS_PAY_VALUE_MODIFIED
                  WHERE PAY_ID       = V_PAY_ID
                  AND EMPLOYEE_ID    = V_EMPLOYEE_ID
                  AND MONTH_ID = V_MONTH_ID
                  AND SALARY_TYPE_ID = V_SALARY_TYPE_ID;
                  
                  UPDATE HRIS_SS_PAY_VALUE_MODIFIED
                  SET VAL      = V_PAY_VALUE
                  WHERE PAY_ID       = V_PAY_ID
                  AND EMPLOYEE_ID    = V_EMPLOYEE_ID
                  AND MONTH_ID = V_MONTH_ID
                  AND SALARY_TYPE_ID = V_SALARY_TYPE_ID;
                  
                EXCEPTION
                WHEN NO_DATA_FOUND THEN
                  INSERT
                  INTO HRIS_SS_PAY_VALUE_MODIFIED
                    (
                      PAY_ID,
                      EMPLOYEE_ID,
                      MONTH_ID,
                      SALARY_TYPE_ID,
                      VAL
                    )
                    VALUES
                    (
                      V_PAY_ID,
                      V_EMPLOYEE_ID,
                      V_MONTH_ID,
                      V_SALARY_TYPE_ID,
                      V_PAY_VALUE
                    );
                END;";
        } 
        $statement = $this->adapter->query($sql);
        return $statement->execute();
    }

    public function getColumns($payHeadId){
      $payHeadIds = ':F_' . implode(',:F_', $payHeadId);
      $boundedParameter = [];
      for($i = 0; $i < count($payHeadId); $i++){
        $boundedParameter['F_'.$payHeadId[$i]] = $payHeadId[$i];
      }
      //$payHeadId = implode(',', $payHeadId);
      $sql = "select pay_id, pay_edesc, 'H_'||pay_id as title from hris_pay_setup where pay_id in ($payHeadIds)
      order by pay_id";
      $statement = $this->adapter->query($sql);
      return $statement->execute($boundedParameter);
    }

    public function modernFilter($monthId, $companyId = null, $groupId = null, $payId, $employeeId, $salaryTypeId) {
        $csv = $payId;
        $employeeCondition = "";
        $boundedParameter = [];
        if ($companyId != null && $companyId != -1) {
            $employeeCondition .= " AND E.COMPANY_ID = :companyId";
            $boundedParameter['companyId'] = $companyId;
        }

        if ($groupId != null && $groupId != -1) {
            $employeeCondition .= " AND E.GROUP_ID = :groupId";
            $boundedParameter['groupId'] = $groupId;
        }

        if ($employeeId != null && $employeeId != -1) {
          //$employeeId = implode(',', $employeeId);
          $employeeIds = ':F_' . implode(',:F_', $employeeId);
          for($i = 0; $i < count($employeeId); $i++){
            $boundedParameter['F_'.$employeeId[$i]] = $employeeId[$i];
          }
          $employeeCondition .= " AND E.EMPLOYEE_ID IN ($employeeIds)";
        }
        $boundedParameter['monthId'] = $monthId;
        $boundedParameter['salaryTypeId'] = $salaryTypeId;
        $sql = "SELECT E.EMPLOYEE_ID,
                  E.FULL_NAME,
                  C.COMPANY_ID,
                  C.COMPANY_NAME,
                  SSG.GROUP_ID,
                  SSG.GROUP_NAME,
                  PV.*
                FROM HRIS_EMPLOYEES E
                LEFT JOIN HRIS_COMPANY C ON (E.COMPANY_ID=C.COMPANY_ID)
                LEFT JOIN HRIS_SALARY_SHEET_GROUP SSG ON (E.GROUP_ID=SSG.GROUP_ID)
                LEFT JOIN
                  (SELECT *
                  FROM
                    (SELECT MONTH_ID, PAY_ID, EMPLOYEE_ID AS E_ID,VAL FROM HRIS_SS_PAY_VALUE_MODIFIED WHERE MONTH_ID =:monthId
                    and SALARY_TYPE_ID =:salaryTypeId
                    order by pay_id ) PIVOT (MAX(VAL) FOR PAY_ID IN ({$csv}))
                  ) PV
                ON (E.EMPLOYEE_ID=PV.E_ID)
                WHERE E.STATUS   ='E' {$employeeCondition} ORDER BY C.COMPANY_NAME,SSG.GROUP_NAME,E.FULL_NAME";
                
        return $this->rawQuery($sql, $boundedParameter);
    }
}
