(function ($, app) {
    'use strict';
    $(document).ready(function () {
        if (document.employeeId == document.selfEmployeeId) {
            app.lockField(true, [
			'employeeCode', 'firstName', 'middleName', 'lastName', 'nameNepali','companyId'
                        , 'tab1'
                        , 'tab3'
                        , 'tab4'
                    ]);
        }
    });
})(window.jQuery, window.app);