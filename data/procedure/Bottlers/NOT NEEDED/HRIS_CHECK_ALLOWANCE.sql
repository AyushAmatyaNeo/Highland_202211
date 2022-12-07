CREATE OR REPLACE PROCEDURE HRIS_CHECK_ALLOWANCE(
    P_EMPLOYEE_ID HRIS_ATTENDANCE.EMPLOYEE_ID%TYPE,
    P_ATTENDANCE_DT HRIS_ATTENDANCE.ATTENDANCE_DT%TYPE
   )
AS
V_IN_TIME TIMESTAMP;
V_OUT_TIME TIMESTAMP;
V_SHIFT_ALLOWANCE_FLAG CHAR(1 BYTE);
V_NIGHT_SHIFT_FLAG CHAR(1 BYTE);
V_OVERALL_STATUS CHAR(2 BYTE);
V_SHIFT_START_TIME TIMESTAMP;
V_SHIFT_END_TIME TIMESTAMP;
V_SHIFT_DIFF_IN_MIN NUMBER; 
V_OT_MINUTES NUMBER;
BEGIN

SELECT 
AD.IN_TIME,
AD.OUT_TIME,
AD.SHIFT_ALLOWANCE_FLAG,
AD.NIGHT_SHIFT_FLAG,
AD.OVERALL_STATUS,
AD.OT_MINUTES,
TO_DATE(TO_CHAR(AD.IN_TIME,'DD-MON-YYYY')
    ||' '||TO_CHAR(S.START_TIME,'HH:MI AM'),'DD-MON-YYYY HH:MI AM'),
    TO_DATE(TO_CHAR(AD.OUT_TIME,'DD-MON-YYYY')
    ||' '||TO_CHAR(S.END_TIME,'HH:MI AM'),'DD-MON-YYYY HH:MI AM')
INTO
V_IN_TIME,
V_OUT_TIME,
V_SHIFT_ALLOWANCE_FLAG,
V_NIGHT_SHIFT_FLAG,
V_OVERALL_STATUS,
V_OT_MINUTES,
V_SHIFT_START_TIME,
V_SHIFT_END_TIME
FROM HRIS_ATTENDANCE_DETAIL AD
LEFT JOIN HRIS_SHIFTS S ON (S.SHIFT_ID=AD.SHIFT_ID)
WHERE AD.EMPLOYEE_ID=P_EMPLOYEE_ID AND AD.ATTENDANCE_DT=P_ATTENDANCE_DT;

IF(V_IN_TIME IS NULL OR V_OUT_TIME IS NULL ) THEN
RETURN;
END IF;

select 
    CASE
    WHEN V_IN_TIME>V_SHIFT_START_TIME THEN V_IN_TIME
    WHEN V_SHIFT_START_TIME>V_IN_TIME THEN V_SHIFT_START_TIME
    END,
    CASE
    WHEN V_OUT_TIME>V_SHIFT_END_TIME THEN V_SHIFT_END_TIME
    WHEN V_SHIFT_END_TIME>V_OUT_TIME THEN V_OUT_TIME
    END
    INTO 
     V_SHIFT_START_TIME,
    V_SHIFT_END_TIME
    FROM DUAL;

SELECT SUM(ABS(EXTRACT( HOUR FROM SHIFT_DIFF ))*60 + ABS(EXTRACT( MINUTE FROM SHIFT_DIFF )))
          INTO V_SHIFT_DIFF_IN_MIN
          FROM
            (SELECT
             V_SHIFT_END_TIME -V_SHIFT_START_TIME AS SHIFT_DIFF
            FROM DUAL
            );
            
            
            
        
            
            
         --    UPDATE HRIS_ATTENDANCE_DETAIL
     --   SET 
       --   SHIFT_TOTAL_HOUR =V_SHIFT_DIFF_IN_MIN,
        --  FOOD_ALLOWANCE    =V_FOOD_ALLOWANCE,
        --  SHIFT_ALLOWANCE    =V_SHIFT_ALLOWANCE,
        --  NIGHT_SHIFT_ALLOWANCE    =V_NIGHT_SHIFT_ALLOWANCE,
        --  HOLIDAY_COUNT =V_HOLIDAY_COUNT
       -- WHERE ATTENDANCE_DT = P_ATTENDANCE_DT
       -- AND EMPLOYEE_ID     = P_EMPLOYEE_ID;




END;
