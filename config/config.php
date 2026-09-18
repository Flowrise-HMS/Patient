<?php

return [
    'name' => 'Patient',
    'permissions' => [
        'print_hospital_card' => 'Print Hospital Card',
        'discharge_patient' => 'Discharge Patient',
        'view_patient_balance' => 'View Patient Balance',
        'import_patients' => 'Import Patients',
        'merge_patients' => 'Merge Patients',
    ],
    'merge' => [
        /*
         * Demographic attributes copied from the duplicate onto the surviving
         * profile when the survivor's value is blank. Names, MRN and branch are
         * never touched.
         */
        'fillable_fields' => [
            'title', 'middle_name', 'date_of_birth', 'is_date_of_birth_estimated', 'gender',
            'blood_type', 'marital_status', 'education_level', 'occupation', 'nationality',
            'phone', 'email', 'preferred_language', 'photo', 'user_id', 'address', 'contact',
            'old_hospital_number',
        ],

        /*
         * Tables with a `patient_id` column that the generic repoint must leave alone.
         */
        'skip_tables' => [],
    ],
];
