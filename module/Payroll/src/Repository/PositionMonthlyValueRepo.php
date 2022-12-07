<?php

namespace Payroll\Repository;

use Application\Helper\EntityHelper;
use Application\Repository\HrisRepository;
use Payroll\Model\PositionMonthlyValue;
use Zend\Db\Adapter\AdapterInterface;

class PositionMonthlyValueRepo extends HrisRepository {

    public function __construct(AdapterInterface $adapter, $tableName = null) {
        if ($tableName == null) {
            $tableName = PositionMonthlyValue::TABLE_NAME;
        }
        parent::__construct($adapter, $tableName);
    }

    public function fetchValue($keys) {
        $boundedParameter = [];
        $boundedParameter['EMPLOYEE_ID'] = $keys['EMPLOYEE_ID'];
        $boundedParameter['MONTH_ID'] = $keys['MONTH_ID'];
        $boundedParameter['MTH_ID'] = $keys['MTH_ID'];
        $sql = "SELECT (
                  CASE
                    WHEN ASSIGN_TYPE ='E'
                    THEN MTH_VALUE
                    ELSE ASSIGNED_VALUE
                  END) AS ASSIGNED_VALUE
                FROM
                  (SELECT MVS.ASSIGN_TYPE,
                    MVD.MTH_VALUE,
                    PMV.ASSIGNED_VALUE
                  FROM HRIS_MONTHLY_VALUE_SETUP MVS
                  LEFT JOIN
                    (SELECT *
                    FROM HRIS_MONTHLY_VALUE_DETAIL
                    WHERE MONTH_ID =:MONTH_ID
                    AND EMPLOYEE_ID=:EMPLOYEE_ID
                    ) MVD
                  ON (MVS.MTH_ID=MVD.MTH_ID)
                  LEFT JOIN
                    (SELECT *
                    FROM HRIS_POSITION_MONTHLY_VALUE
                    WHERE MONTH_ID =:MONTH_ID
                    AND POSITION_ID=
                      (SELECT POSITION_ID FROM HRIS_EMPLOYEES WHERE EMPLOYEE_ID = :EMPLOYEE_ID
                      )
                    ) PMV
                  ON (MVS.MTH_ID   =PMV.MTH_ID)
                  WHERE MVS.MTH_ID =:MTH_ID
                  )";
        $resultList = $this->rawQuery($sql, $boundedParameter);
        if (sizeof($resultList) != 1) {
            return 0;
        }
        return isset($resultList[0]['ASSIGNED_VALUE']) ? $resultList[0]['ASSIGNED_VALUE'] : 0;
    }

    public function fetchById($id) {
        $sql = "SELECT PMV.ASSIGNED_VALUE
                FROM HRIS_POSITION_MONTHLY_VALUE PMV
                JOIN HRIS_EMPLOYEES E
                ON(PMV.POSITION_ID = E.POSITION_ID)
                WHERE PMV.MTH_ID   =:mthId
                AND PMV.MONTH_ID   =:monthId
                AND E.EMPLOYEE_ID  =:employeeId
                ";

        $boundedParameter = [];
        $boundedParameter['mthId'] = $id['MTH_ID'];
        $boundedParameter['monthId'] = $id['MONTH_ID'];
        $boundedParameter['employeeId'] = $id['EMPLOYEE_ID'];
        return $this->rawQuery($sql, $boundedParameter)[0];
        // $statement = $this->adapter->query($sql);
        // $rawResult = $statement->execute();
        // return $rawResult->current();
    }

    public function getPositionMonthlyValue($monthId) {
      $boundedParameter = [];
      $boundedParameter['monthId'] = $monthId;
        $sql = "
            SELECT *
            FROM
              ( SELECT MTH_ID,POSITION_ID,ASSIGNED_VALUE FROM HRIS_POSITION_MONTHLY_VALUE WHERE MONTH_ID =:monthId
              ) PIVOT ( MAX(ASSIGNED_VALUE) FOR MTH_ID IN ({$this->fetchMonthlyValueAsDbArray()}) )";
        return $this->rawQuery($sql, $boundedParameter);
    }

    private function fetchMonthlyValueAsDbArray() {
        $rawList = EntityHelper::rawQueryResult($this->adapter, "SELECT MTH_ID FROM HRIS_MONTHLY_VALUE_SETUP WHERE STATUS ='E'");
        $dbArray = "";
        foreach ($rawList as $key => $row) {
            if ($key == sizeof($rawList)) {
                $dbArray .= "{$row['MTH_ID']}";
            } else {
                $dbArray .= "{$row['MTH_ID']},";
            }
        }
        return $dbArray;
    }

    public function setPositionMonthlyValue($monthId, $positionId, $mthId, $assignedValue) {
        $boundedParameter = [];
        $boundedParameter['monthId'] = $monthId;
        $boundedParameter['mthId'] = $mthId;
        $boundedParameter['positionId'] = $positionId;
        $boundedParameter['assignedValue'] = $assignedValue;
        $sql = "
                DECLARE
                  V_MONTH_ID HRIS_POSITION_MONTHLY_VALUE.MONTH_ID%TYPE             := :monthId;
                  V_MTH_ID HRIS_POSITION_MONTHLY_VALUE.MTH_ID%TYPE                 := :mthId;
                  V_POSITION_ID HRIS_POSITION_MONTHLY_VALUE.POSITION_ID%TYPE       := :positionId;
                  V_ASSIGNED_VALUE HRIS_POSITION_MONTHLY_VALUE.ASSIGNED_VALUE%TYPE := :assignedValue;
                  V_OLD_ASSIGNED_VALUE HRIS_POSITION_MONTHLY_VALUE.ASSIGNED_VALUE%TYPE;
                BEGIN
                  SELECT ASSIGNED_VALUE
                  INTO V_OLD_ASSIGNED_VALUE
                  FROM HRIS_POSITION_MONTHLY_VALUE
                  WHERE MTH_ID    = V_MTH_ID
                  AND POSITION_ID = V_POSITION_ID
                  AND MONTH_ID    = V_MONTH_ID;
                  UPDATE HRIS_POSITION_MONTHLY_VALUE
                  SET ASSIGNED_VALUE = V_ASSIGNED_VALUE
                  WHERE MTH_ID       = V_MTH_ID
                  AND POSITION_ID    = V_POSITION_ID
                  AND MONTH_ID       = V_MONTH_ID;
                EXCEPTION
                WHEN NO_DATA_FOUND THEN
                  INSERT
                  INTO HRIS_POSITION_MONTHLY_VALUE
                    (
                      MTH_ID,
                      POSITION_ID,
                      MONTH_ID,
                      ASSIGNED_VALUE
                    )
                    VALUES
                    (
                      V_MTH_ID,
                      V_POSITION_ID,
                      V_MONTH_ID,
                      V_ASSIGNED_VALUE
                    );
                END;";
        $statement = $this->adapter->query($sql);
        return $statement->execute($boundedParameter);
    }

}
