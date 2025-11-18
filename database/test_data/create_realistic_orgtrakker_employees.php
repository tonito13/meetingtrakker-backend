<?php
/**
 * Script to create realistic test employees in orgtrakker_100000 database
 * This copies the employee template from the company database and creates realistic sample employees
 */

// Get the template structure from the output above
$templateStructure = json_decode('[{"id": 1762161266515, "label": "Personal Information", "fields": [{"id": 1762161266516, "type": "text", "label": "Employee ID", "options": [], "is_required": true, "customize_field_label": "Employee ID"}, {"id": 1762161266517, "type": "text", "label": "First Name", "options": [], "is_required": true, "customize_field_label": "First Name"}, {"id": 1762161266518, "type": "text", "label": "Middle Name", "options": [], "is_required": false, "customize_field_label": "Middle Name"}, {"id": 1762161266519, "type": "text", "label": "Last Name", "options": [], "is_required": true, "customize_field_label": "Last Name"}, {"id": 1762161266520, "type": "date", "label": "Date of Birth", "options": [], "is_required": true, "customize_field_label": "Date of Birth"}, {"id": 1762161266521, "type": "text", "label": "Birth Place", "options": [], "is_required": false, "customize_field_label": "Birth Place"}, {"id": 1762161266522, "type": "select", "label": "Sex", "options": ["Male", "Female", "Other"], "is_required": true, "customize_field_label": "Sex"}, {"id": 1762161266523, "type": "select", "label": "Civil Status", "options": ["Single", "Married", "Divorced", "Widowed"], "is_required": true, "customize_field_label": "Civil Status"}, {"id": 1762161266524, "type": "text", "label": "Nationality", "options": [], "is_required": true, "customize_field_label": "Nationality"}, {"id": 1762161266525, "type": "select", "label": "Blood Type", "options": ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"], "is_required": false, "customize_field_label": "Blood Type"}, {"id": 1762161266526, "type": "text", "label": "Email Address", "options": [], "is_required": true, "customize_field_label": "Email Address"}, {"id": 1762161266527, "type": "text", "label": "Contact Number", "options": [], "is_required": true, "customize_field_label": "Contact Number"}, {"id": 1762161266528, "type": "text", "label": "Street No./Lot No., Block No.", "options": [], "is_required": false, "customize_field_label": "Street No./Lot No., Block No."}, {"id": 1762161266529, "type": "text", "label": "Street Name", "options": [], "is_required": false, "customize_field_label": "Street Name"}, {"id": 1762161266530, "type": "text", "label": "Barangay", "options": [], "is_required": false, "customize_field_label": "Barangay"}, {"id": 1762161266531, "type": "text", "label": "Municipality/City", "options": [], "is_required": true, "customize_field_label": "Municipality/City"}, {"id": 1762161266532, "type": "text", "label": "Province", "options": [], "is_required": true, "customize_field_label": "Province"}, {"id": 1762161266533, "type": "text", "label": "Zip Code", "options": [], "is_required": true, "customize_field_label": "Zip Code"}], "customize_group_label": "Personal Information"}, {"id": 1762161266615, "label": "Employment Details", "fields": [{"id": 1762161266616, "type": "select", "label": "Job Role", "options": [], "is_required": true, "dynamicOptions": true, "customize_field_label": "Job Role"}, {"id": 1762161266617, "type": "select", "label": "Reports To", "options": [], "is_required": false, "dynamicOptions": true, "customize_field_label": "Reports To"}, {"id": 1762161266618, "type": "select", "label": "Employment Type", "options": ["Full-Time", "Part-Time", "Contract", "Temporary"], "is_required": true, "customize_field_label": "Employment Type"}, {"id": 1762161266619, "type": "text", "label": "Department", "options": [], "is_required": true, "customize_field_label": "Department"}, {"id": 1762161266620, "type": "date", "label": "Date of Hire", "options": [], "is_required": true, "customize_field_label": "Date of Hire"}], "customize_group_label": "Employment Details"}, {"id": 1762161266715, "label": "Payroll Details", "fields": [{"id": 1762161266716, "type": "text", "label": "Monthly Salary", "options": [], "is_required": true, "customize_field_label": "Monthly Salary"}, {"id": 1762161266717, "type": "select", "label": "Pay Frequency", "options": ["Weekly", "Bi-Weekly", "Monthly"], "is_required": true, "customize_field_label": "Pay Frequency"}, {"id": 1762161266718, "type": "select", "label": "Payment Mode", "options": ["Bank Transfer", "Cash", "Check"], "is_required": true, "customize_field_label": "Payment Mode"}, {"id": 1762161266719, "type": "text", "label": "Bank Account Number", "options": [], "is_required": true, "customize_field_label": "Bank Account Number"}, {"id": 1762161266720, "type": "select", "label": "Tax Status", "options": ["Single", "Married", "Head of Household"], "is_required": true, "customize_field_label": "Tax Status"}], "customize_group_label": "Payroll Details"}, {"id": 1762161266815, "label": "Statutory Deductions", "fields": [{"id": 1762161266816, "type": "text", "label": "SSS Number", "options": [], "is_required": true, "customize_field_label": "SSS Number"}, {"id": 1762161266817, "type": "text", "label": "PhilHealth Number", "options": [], "is_required": true, "customize_field_label": "PhilHealth Number"}, {"id": 1762161266818, "type": "text", "label": "Pag-IBIG Number", "options": [], "is_required": true, "customize_field_label": "Pag-IBIG Number"}, {"id": 1762161266819, "type": "text", "label": "TIN (Tax Identification Number)", "options": [], "is_required": true, "customize_field_label": "TIN (Tax Identification Number)"}, {"id": 1762161266820, "type": "text", "label": "SSS Contribution", "options": [], "is_required": true, "customize_field_label": "SSS Contribution"}, {"id": 1762161266821, "type": "text", "label": "PhilHealth Contribution", "options": [], "is_required": true, "customize_field_label": "PhilHealth Contribution"}, {"id": 1762161266822, "type": "text", "label": "Pag-IBIG Contribution", "options": [], "is_required": true, "customize_field_label": "Pag-IBIG Contribution"}, {"id": 1762161266823, "type": "text", "label": "Income Tax Deduction", "options": [], "is_required": true, "customize_field_label": "Income Tax Deduction"}], "customize_group_label": "Statutory Deductions"}, {"id": 1762161266915, "label": "Attendance & Leave Information", "fields": [{"id": 1762161266916, "type": "text", "label": "Work Hours", "options": [], "is_required": false, "customize_field_label": "Work Hours"}], "subGroups": [{"id": 1762161266917, "label": "Leave Type 1", "fields": [{"id": 1762161266918, "type": "text", "label": "Leave Type", "options": [], "is_required": true, "customize_field_label": "Leave Type"}, {"id": 1762161266919, "type": "text", "label": "Balance", "options": [], "is_required": true, "customize_field_label": "Balance"}], "customize_group_label": "Leave Type 1"}], "customize_group_label": "Attendance & Leave Information"}, {"id": 1762161267015, "label": "System & Access", "fields": [{"id": 1762161267016, "type": "select", "label": "Role", "options": ["Admin", "User", "Manager"], "is_required": true, "customize_field_label": "Role"}, {"id": 1762161267017, "type": "text", "label": "Username", "options": [], "is_required": true, "customize_field_label": "Username"}, {"id": 1762161267018, "type": "text", "label": "Password", "options": [], "is_required": true, "customize_field_label": "Password"}, {"id": 1762161267019, "type": "checkbox", "label": "System Access Enabled", "options": [], "is_required": false, "customize_field_label": "System Access Enabled"}], "customize_group_label": "System & Access"}, {"id": 1762161267115, "label": "Emergency Contact Details", "fields": [], "subGroups": [{"id": 1762161267116, "label": "Emergency Contact 1", "fields": [{"id": 1762161267117, "type": "text", "label": "Emergency Contact Name", "options": [], "is_required": true, "customize_field_label": "Emergency Contact Name"}, {"id": 1762161267118, "type": "text", "label": "Relationship", "options": [], "is_required": true, "customize_field_label": "Relationship"}, {"id": 1762161267119, "type": "text", "label": "Contact Number", "options": [], "is_required": true, "customize_field_label": "Contact Number"}, {"id": 1762161267120, "type": "text", "label": "Address", "options": [], "is_required": false, "customize_field_label": "Address"}], "customize_group_label": "Emergency Contact 1"}], "customize_group_label": "Emergency Contact Details"}, {"id": 1762161267215, "label": "Documents & Attachments", "fields": [{"id": 1762161267216, "type": "file", "label": "Upload ID Proofs", "options": [], "is_required": false, "customize_field_label": "Upload ID Proofs"}, {"id": 1762161267217, "type": "file", "label": "Upload Employment Contract", "options": [], "is_required": false, "customize_field_label": "Upload Employment Contract"}, {"id": 1762161267218, "type": "file", "label": "Upload Resume", "options": [], "is_required": false, "customize_field_label": "Upload Resume"}, {"id": 1762161267219, "type": "file", "label": "Upload Employee Image", "options": [], "is_required": false, "customize_field_label": "Upload Employee Image"}], "customize_group_label": "Documents & Attachments"}]', true);

// Realistic sample employees data
$employees = [
    [
        'employee_unique_id' => 'emp-100001',
        'employee_id' => 'EMP001',
        'username' => 'maria.santos',
        'first_name' => 'Maria',
        'middle_name' => 'Cruz',
        'last_name' => 'Santos',
        'date_of_birth' => '1990-05-15',
        'birth_place' => 'Manila',
        'sex' => 'Female',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'blood_type' => 'O+',
        'email' => 'maria.santos@example.com',
        'contact' => '+63 917 123 4567',
        'street' => '123',
        'street_name' => 'Rizal Avenue',
        'barangay' => 'Barangay 101',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'zip_code' => '1000',
        'job_role' => 'Software Engineer',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Engineering',
        'date_of_hire' => '2020-01-15',
        'monthly_salary' => '75000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '1234567890',
        'tax_status' => 'Married',
        'sss_number' => '34-1234567-8',
        'philhealth' => '12-345678901-2',
        'pagibig' => '121234567890',
        'tin' => '123-456-789-000',
        'sss_contribution' => '2400',
        'philhealth_contribution' => '1125',
        'pagibig_contribution' => '100',
        'income_tax' => '8500',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '15',
        'role' => 'User',
        'system_access' => true,
        'emergency_name' => 'Juan Santos',
        'emergency_relationship' => 'Husband',
        'emergency_contact' => '+63 917 765 4321',
        'emergency_address' => '123 Rizal Avenue, Manila'
    ],
    [
        'employee_unique_id' => 'emp-100002',
        'employee_id' => 'EMP002',
        'username' => 'juan.delacruz',
        'first_name' => 'Juan',
        'middle_name' => 'Pablo',
        'last_name' => 'Dela Cruz',
        'date_of_birth' => '1988-08-22',
        'birth_place' => 'Quezon City',
        'sex' => 'Male',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'blood_type' => 'A+',
        'email' => 'juan.delacruz@example.com',
        'contact' => '+63 918 234 5678',
        'street' => '456',
        'street_name' => 'EDSA',
        'barangay' => 'Diliman',
        'city' => 'Quezon City',
        'province' => 'Metro Manila',
        'zip_code' => '1100',
        'job_role' => 'Project Manager',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Operations',
        'date_of_hire' => '2019-03-10',
        'monthly_salary' => '85000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '2345678901',
        'tax_status' => 'Single',
        'sss_number' => '34-2345678-9',
        'philhealth' => '12-456789012-3',
        'pagibig' => '122345678901',
        'tin' => '234-567-890-111',
        'sss_contribution' => '2720',
        'philhealth_contribution' => '1275',
        'pagibig_contribution' => '100',
        'income_tax' => '10500',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '20',
        'role' => 'Manager',
        'system_access' => true,
        'emergency_name' => 'Maria Dela Cruz',
        'emergency_relationship' => 'Mother',
        'emergency_contact' => '+63 918 876 5432',
        'emergency_address' => '456 EDSA, Quezon City'
    ],
    [
        'employee_unique_id' => 'emp-100003',
        'employee_id' => 'EMP003',
        'username' => 'ana.garcia',
        'first_name' => 'Ana',
        'middle_name' => 'Luz',
        'last_name' => 'Garcia',
        'date_of_birth' => '1992-11-30',
        'birth_place' => 'Makati',
        'sex' => 'Female',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'blood_type' => 'B+',
        'email' => 'ana.garcia@example.com',
        'contact' => '+63 919 345 6789',
        'street' => '789',
        'street_name' => 'Ayala Avenue',
        'barangay' => 'San Antonio',
        'city' => 'Makati',
        'province' => 'Metro Manila',
        'zip_code' => '1200',
        'job_role' => 'HR Manager',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Human Resources',
        'date_of_hire' => '2021-06-01',
        'monthly_salary' => '80000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '3456789012',
        'tax_status' => 'Single',
        'sss_number' => '34-3456789-0',
        'philhealth' => '12-567890123-4',
        'pagibig' => '123456789012',
        'tin' => '345-678-901-222',
        'sss_contribution' => '2560',
        'philhealth_contribution' => '1200',
        'pagibig_contribution' => '100',
        'income_tax' => '9500',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '18',
        'role' => 'Manager',
        'system_access' => true,
        'emergency_name' => 'Roberto Garcia',
        'emergency_relationship' => 'Father',
        'emergency_contact' => '+63 919 987 6543',
        'emergency_address' => '789 Ayala Avenue, Makati'
    ],
    [
        'employee_unique_id' => 'emp-100004',
        'employee_id' => 'EMP004',
        'username' => 'carlos.reyes',
        'first_name' => 'Carlos',
        'middle_name' => 'Miguel',
        'last_name' => 'Reyes',
        'date_of_birth' => '1985-02-14',
        'birth_place' => 'Cebu City',
        'sex' => 'Male',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'blood_type' => 'AB+',
        'email' => 'carlos.reyes@example.com',
        'contact' => '+63 920 456 7890',
        'street' => '321',
        'street_name' => 'Colon Street',
        'barangay' => 'Cebu City Proper',
        'city' => 'Cebu City',
        'province' => 'Cebu',
        'zip_code' => '6000',
        'job_role' => 'Senior Developer',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Engineering',
        'date_of_hire' => '2018-09-15',
        'monthly_salary' => '90000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '4567890123',
        'tax_status' => 'Married',
        'sss_number' => '34-4567890-1',
        'philhealth' => '12-678901234-5',
        'pagibig' => '124567890123',
        'tin' => '456-789-012-333',
        'sss_contribution' => '2880',
        'philhealth_contribution' => '1350',
        'pagibig_contribution' => '100',
        'income_tax' => '11500',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '22',
        'role' => 'User',
        'system_access' => true,
        'emergency_name' => 'Carmen Reyes',
        'emergency_relationship' => 'Wife',
        'emergency_contact' => '+63 920 098 7654',
        'emergency_address' => '321 Colon Street, Cebu City'
    ],
    [
        'employee_unique_id' => 'emp-100005',
        'employee_id' => 'EMP005',
        'username' => 'lisa.tan',
        'first_name' => 'Lisa',
        'middle_name' => 'May',
        'last_name' => 'Tan',
        'date_of_birth' => '1993-07-08',
        'birth_place' => 'Davao City',
        'sex' => 'Female',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'blood_type' => 'O-',
        'email' => 'lisa.tan@example.com',
        'contact' => '+63 921 567 8901',
        'street' => '654',
        'street_name' => 'Roxas Avenue',
        'barangay' => 'Poblacion',
        'city' => 'Davao City',
        'province' => 'Davao del Sur',
        'zip_code' => '8000',
        'job_role' => 'Marketing Specialist',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Marketing',
        'date_of_hire' => '2022-02-20',
        'monthly_salary' => '65000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '5678901234',
        'tax_status' => 'Single',
        'sss_number' => '34-5678901-2',
        'philhealth' => '12-789012345-6',
        'pagibig' => '125678901234',
        'tin' => '567-890-123-444',
        'sss_contribution' => '2080',
        'philhealth_contribution' => '975',
        'pagibig_contribution' => '100',
        'income_tax' => '7200',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '12',
        'role' => 'User',
        'system_access' => true,
        'emergency_name' => 'Michael Tan',
        'emergency_relationship' => 'Brother',
        'emergency_contact' => '+63 921 109 8765',
        'emergency_address' => '654 Roxas Avenue, Davao City'
    ],
    [
        'employee_unique_id' => 'emp-100006',
        'employee_id' => 'EMP006',
        'username' => 'robert.lim',
        'first_name' => 'Robert',
        'middle_name' => 'James',
        'last_name' => 'Lim',
        'date_of_birth' => '1987-04-12',
        'birth_place' => 'Iloilo City',
        'sex' => 'Male',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'blood_type' => 'A-',
        'email' => 'robert.lim@example.com',
        'contact' => '+63 922 678 9012',
        'street' => '987',
        'street_name' => 'J.M. Basa Street',
        'barangay' => 'City Proper',
        'city' => 'Iloilo City',
        'province' => 'Iloilo',
        'zip_code' => '5000',
        'job_role' => 'Accountant',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Finance',
        'date_of_hire' => '2020-11-05',
        'monthly_salary' => '70000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '6789012345',
        'tax_status' => 'Married',
        'sss_number' => '34-6789012-3',
        'philhealth' => '12-890123456-7',
        'pagibig' => '126789012345',
        'tin' => '678-901-234-555',
        'sss_contribution' => '2240',
        'philhealth_contribution' => '1050',
        'pagibig_contribution' => '100',
        'income_tax' => '8000',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '16',
        'role' => 'User',
        'system_access' => true,
        'emergency_name' => 'Patricia Lim',
        'emergency_relationship' => 'Wife',
        'emergency_contact' => '+63 922 210 9876',
        'emergency_address' => '987 J.M. Basa Street, Iloilo City'
    ],
    [
        'employee_unique_id' => 'emp-100007',
        'employee_id' => 'EMP007',
        'username' => 'sophia.chan',
        'first_name' => 'Sophia',
        'middle_name' => 'Rose',
        'last_name' => 'Chan',
        'date_of_birth' => '1991-09-25',
        'birth_place' => 'Baguio City',
        'sex' => 'Female',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'blood_type' => 'B-',
        'email' => 'sophia.chan@example.com',
        'contact' => '+63 923 789 0123',
        'street' => '147',
        'street_name' => 'Session Road',
        'barangay' => 'Central Business District',
        'city' => 'Baguio City',
        'province' => 'Benguet',
        'zip_code' => '2600',
        'job_role' => 'UX Designer',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Design',
        'date_of_hire' => '2021-08-10',
        'monthly_salary' => '72000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '7890123456',
        'tax_status' => 'Single',
        'sss_number' => '34-7890123-4',
        'philhealth' => '12-901234567-8',
        'pagibig' => '127890123456',
        'tin' => '789-012-345-666',
        'sss_contribution' => '2304',
        'philhealth_contribution' => '1080',
        'pagibig_contribution' => '100',
        'income_tax' => '8400',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '17',
        'role' => 'User',
        'system_access' => true,
        'emergency_name' => 'David Chan',
        'emergency_relationship' => 'Father',
        'emergency_contact' => '+63 923 321 0987',
        'emergency_address' => '147 Session Road, Baguio City'
    ],
    [
        'employee_unique_id' => 'emp-100008',
        'employee_id' => 'EMP008',
        'username' => 'michael.ong',
        'first_name' => 'Michael',
        'middle_name' => 'Anthony',
        'last_name' => 'Ong',
        'date_of_birth' => '1989-12-18',
        'birth_place' => 'Bacolod City',
        'sex' => 'Male',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'blood_type' => 'O+',
        'email' => 'michael.ong@example.com',
        'contact' => '+63 924 890 1234',
        'street' => '258',
        'street_name' => 'Lacson Street',
        'barangay' => 'Downtown',
        'city' => 'Bacolod City',
        'province' => 'Negros Occidental',
        'zip_code' => '6100',
        'job_role' => 'Sales Manager',
        'reports_to' => '',
        'employment_type' => 'Full-Time',
        'department' => 'Sales',
        'date_of_hire' => '2019-05-22',
        'monthly_salary' => '88000',
        'pay_frequency' => 'Monthly',
        'payment_mode' => 'Bank Transfer',
        'bank_account' => '8901234567',
        'tax_status' => 'Married',
        'sss_number' => '34-8901234-5',
        'philhealth' => '12-012345678-9',
        'pagibig' => '128901234567',
        'tin' => '890-123-456-777',
        'sss_contribution' => '2816',
        'philhealth_contribution' => '1320',
        'pagibig_contribution' => '100',
        'income_tax' => '11000',
        'work_hours' => '40',
        'leave_type' => 'Vacation Leave',
        'leave_balance' => '21',
        'role' => 'Manager',
        'system_access' => true,
        'emergency_name' => 'Jennifer Ong',
        'emergency_relationship' => 'Wife',
        'emergency_contact' => '+63 924 432 1098',
        'emergency_address' => '258 Lacson Street, Bacolod City'
    ]
];

// Helper function to build answers JSON from employee data
function buildAnswers($employee, $templateStructure) {
    $answers = [];
    
    foreach ($templateStructure as $group) {
        $groupId = $group['id'];
        $answers[$groupId] = [];
        
        // Process regular fields
        if (isset($group['fields'])) {
            foreach ($group['fields'] as $field) {
                $fieldId = $field['id'];
                $label = $field['label'];
                $value = '';
                
                // Map field labels to employee data
                switch ($label) {
                    case 'Employee ID':
                        $value = $employee['employee_id'];
                        break;
                    case 'First Name':
                        $value = $employee['first_name'];
                        break;
                    case 'Middle Name':
                        $value = $employee['middle_name'] ?? '';
                        break;
                    case 'Last Name':
                        $value = $employee['last_name'];
                        break;
                    case 'Date of Birth':
                        $value = $employee['date_of_birth'];
                        break;
                    case 'Birth Place':
                        $value = $employee['birth_place'] ?? '';
                        break;
                    case 'Sex':
                        $value = $employee['sex'];
                        break;
                    case 'Civil Status':
                        $value = $employee['civil_status'];
                        break;
                    case 'Nationality':
                        $value = $employee['nationality'];
                        break;
                    case 'Blood Type':
                        $value = $employee['blood_type'] ?? '';
                        break;
                    case 'Email Address':
                        $value = $employee['email'];
                        break;
                    case 'Contact Number':
                        $value = $employee['contact'];
                        break;
                    case 'Street No./Lot No., Block No.':
                        $value = $employee['street'] ?? '';
                        break;
                    case 'Street Name':
                        $value = $employee['street_name'] ?? '';
                        break;
                    case 'Barangay':
                        $value = $employee['barangay'] ?? '';
                        break;
                    case 'Municipality/City':
                        $value = $employee['city'];
                        break;
                    case 'Province':
                        $value = $employee['province'];
                        break;
                    case 'Zip Code':
                        $value = $employee['zip_code'];
                        break;
                    case 'Job Role':
                        $value = $employee['job_role'];
                        break;
                    case 'Reports To':
                        $value = $employee['reports_to'] ?? '';
                        break;
                    case 'Employment Type':
                        $value = $employee['employment_type'];
                        break;
                    case 'Department':
                        $value = $employee['department'];
                        break;
                    case 'Date of Hire':
                        $value = $employee['date_of_hire'];
                        break;
                    case 'Monthly Salary':
                        $value = $employee['monthly_salary'];
                        break;
                    case 'Pay Frequency':
                        $value = $employee['pay_frequency'];
                        break;
                    case 'Payment Mode':
                        $value = $employee['payment_mode'];
                        break;
                    case 'Bank Account Number':
                        $value = $employee['bank_account'];
                        break;
                    case 'Tax Status':
                        $value = $employee['tax_status'];
                        break;
                    case 'SSS Number':
                        $value = $employee['sss_number'];
                        break;
                    case 'PhilHealth Number':
                        $value = $employee['philhealth'];
                        break;
                    case 'Pag-IBIG Number':
                        $value = $employee['pagibig'];
                        break;
                    case 'TIN (Tax Identification Number)':
                        $value = $employee['tin'];
                        break;
                    case 'SSS Contribution':
                        $value = $employee['sss_contribution'];
                        break;
                    case 'PhilHealth Contribution':
                        $value = $employee['philhealth_contribution'];
                        break;
                    case 'Pag-IBIG Contribution':
                        $value = $employee['pagibig_contribution'];
                        break;
                    case 'Income Tax Deduction':
                        $value = $employee['income_tax'];
                        break;
                    case 'Work Hours':
                        $value = $employee['work_hours'] ?? '';
                        break;
                    case 'Role':
                        $value = $employee['role'];
                        break;
                    case 'Username':
                        $value = $employee['username'];
                        break;
                    case 'Password':
                        $value = 'password123'; // Default password
                        break;
                    case 'System Access Enabled':
                        $value = $employee['system_access'] ? 'true' : 'false';
                        break;
                    default:
                        $value = '';
                }
                
                $answers[$groupId][$fieldId] = $value;
            }
        }
        
        // Process subGroups (like Leave Type 1, Emergency Contact 1)
        if (isset($group['subGroups'])) {
            foreach ($group['subGroups'] as $index => $subGroup) {
                $subGroupId = $subGroup['id'];
                $groupLabel = $group['label'];
                $subGroupLabel = "{$groupLabel}_{$index}";
                
                $answers[$subGroupLabel] = [];
                
                if (isset($subGroup['fields'])) {
                    foreach ($subGroup['fields'] as $field) {
                        $fieldId = $field['id'];
                        $label = $field['label'];
                        $value = '';
                        
                        if ($label === 'Leave Type') {
                            $value = $employee['leave_type'];
                        } elseif ($label === 'Balance') {
                            $value = $employee['leave_balance'];
                        } elseif ($label === 'Emergency Contact Name') {
                            $value = $employee['emergency_name'];
                        } elseif ($label === 'Relationship') {
                            $value = $employee['emergency_relationship'];
                        } elseif ($label === 'Contact Number') {
                            $value = $employee['emergency_contact'];
                        } elseif ($label === 'Address') {
                            $value = $employee['emergency_address'] ?? '';
                        }
                        
                        $answers[$subGroupLabel][$fieldId] = $value;
                    }
                }
            }
        }
    }
    
    return $answers;
}

// Generate SQL for inserting employees
$templateStructureJson = json_encode($templateStructure);

echo "-- Template Structure:\n";
echo "INSERT INTO employee_templates (id, company_id, name, structure, deleted, created_by, created, modified)\n";
echo "VALUES (1, 100000, 'employee', '{$templateStructureJson}'::jsonb, false, 'system', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)\n";
echo "ON CONFLICT (id) DO UPDATE SET structure = EXCLUDED.structure;\n\n";

echo "-- Delete old test employees\n";
echo "DELETE FROM employee_template_answers WHERE company_id = 100000 AND deleted = false;\n\n";

echo "-- Insert new realistic employees\n";
foreach ($employees as $employee) {
    $answers = buildAnswers($employee, $templateStructure);
    $answersJson = json_encode($answers);
    $answersJsonEscaped = str_replace("'", "''", $answersJson);
    
    echo "INSERT INTO employee_template_answers (company_id, employee_unique_id, employee_id, username, template_id, answers, deleted, created_by, created, modified)\n";
    echo "VALUES (100000, '{$employee['employee_unique_id']}', '{$employee['employee_id']}', '{$employee['username']}', 1, '{$answersJsonEscaped}'::jsonb, false, 'system', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);\n\n";
}

