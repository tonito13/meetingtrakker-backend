<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\ConnectionHelper;

/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/config/bootstrap.php';

if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

// DebugKit skips settings these connection config if PHP SAPI is CLI / PHPDBG.
// But since PagesControllerTest is run with debug enabled and DebugKit is loaded
// in application, without setting up these config DebugKit errors out.
ConnectionManager::setConfig('test_debug_kit', [
    'className' => 'Cake\Database\Connection',
    'driver' => 'Cake\Database\Driver\Sqlite',
    'database' => TMP . 'debug_kit.sqlite',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

ConnectionManager::alias('test_debug_kit', 'debug_kit');

// Fixate now to avoid one-second-leap-issues
Chronos::setTestNow(Chronos::now());

// Fixate sessionid early on, as php7.2+
// does not allow the sessionid to be set after stdout
// has been written to.
session_id('cli');

// Connection aliasing needs to happen before migrations are run.
// Otherwise, table objects inside migrations would use the default datasource
ConnectionHelper::addTestAliases();

// Use migrations to build test database schema.
//
// Will rebuild the database if the migration state differs
// from the migration history in files.
//
// If you are not using CakePHP's migrations you can
// hook into your migration tool of choice here or
// load schema from a SQL dump file with
// Create tables manually using fixture definitions
$connection = ConnectionManager::get('test');

// Create users table
$connection->execute("
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    last_name VARCHAR(255) NOT NULL,
    birth_date DATE,
    birth_place VARCHAR(255),
    sex VARCHAR(10),
    civil_status VARCHAR(50),
    nationality VARCHAR(100),
    blood_type VARCHAR(5),
    email_address VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20),
    street_number VARCHAR(10),
    street_name VARCHAR(255),
    barangay VARCHAR(255),
    city_municipality VARCHAR(255),
    province VARCHAR(255),
    zipcode VARCHAR(10),
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    system_user_role VARCHAR(50) NOT NULL,
    system_access_enabled BOOLEAN DEFAULT TRUE,
    active BOOLEAN DEFAULT TRUE,
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create job_role_templates table
$connection->execute("
CREATE TABLE IF NOT EXISTS job_role_templates (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    name VARCHAR(150) NOT NULL,
    structure JSON NOT NULL,
    created_by VARCHAR(150) NOT NULL,
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create job_role_template_answers table
$connection->execute("
CREATE TABLE IF NOT EXISTS job_role_template_answers (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    job_role_unique_id VARCHAR(100) NOT NULL,
    template_id INTEGER NOT NULL,
    answers TEXT,
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create level_templates table
$connection->execute("
CREATE TABLE IF NOT EXISTS level_templates (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    structure TEXT,
    created_by VARCHAR(100),
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create role_levels table
$connection->execute("
CREATE TABLE IF NOT EXISTS role_levels (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    level_unique_id VARCHAR(100) NOT NULL,
    template_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    rank INTEGER,
    custom_fields TEXT,
    created_by VARCHAR(100),
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create employee_templates table
$connection->execute("
CREATE TABLE IF NOT EXISTS employee_templates (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_by VARCHAR(100),
    structure TEXT,
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create employee_template_answers table
$connection->execute("
CREATE TABLE IF NOT EXISTS employee_template_answers (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    employee_unique_id VARCHAR(100) NOT NULL,
    employee_id VARCHAR(100),
    template_id INTEGER NOT NULL,
    username VARCHAR(100),
    answers TEXT,
    full_name VARCHAR(255),
    deleted BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(100),
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create employee_answer_files table
$connection->execute("
CREATE TABLE IF NOT EXISTS employee_answer_files (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    answer_id INTEGER NOT NULL,
    group_id VARCHAR(255) NOT NULL,
    field_id VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path TEXT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size BIGINT NOT NULL,
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create scorecard_templates table
$connection->execute("
CREATE TABLE IF NOT EXISTS scorecard_templates (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    structure TEXT,
    created_by VARCHAR(100),
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create scorecards table
$connection->execute("
CREATE TABLE IF NOT EXISTS scorecards (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    scorecard_unique_id VARCHAR(100) NOT NULL,
    template_id INTEGER NOT NULL,
    employee_id VARCHAR(100),
    manager_id VARCHAR(100),
    title VARCHAR(255),
    description TEXT,
    status VARCHAR(50),
    period_start DATE,
    period_end DATE,
    created_by VARCHAR(100),
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Drop and recreate scorecard_template_answers table to ensure correct schema
$connection->execute('DROP TABLE IF EXISTS scorecard_template_answers');
$connection->execute("
CREATE TABLE scorecard_template_answers (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    scorecard_unique_id VARCHAR(150) NOT NULL,
    template_id INTEGER NOT NULL,
    assigned_employee_username VARCHAR(100),
    answers TEXT NOT NULL,
    created_by VARCHAR(150) NOT NULL,
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Drop and recreate scorecard_evaluations table to ensure correct schema
$connection->execute('DROP TABLE IF EXISTS scorecard_evaluations');
$connection->execute("
CREATE TABLE scorecard_evaluations (
    id SERIAL PRIMARY KEY,
    company_id INTEGER NOT NULL,
    scorecard_unique_id VARCHAR(255) NOT NULL,
    evaluator_username VARCHAR(255) NOT NULL,
    evaluated_employee_username VARCHAR(255) NOT NULL,
    grade VARCHAR(50),
    notes TEXT,
    status VARCHAR(50),
    evaluation_date TIMESTAMP,
    deleted BOOLEAN DEFAULT FALSE,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create audit_logs table
$connection->execute("
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    company_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    user_data JSONB,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_data JSONB,
    response_data JSONB,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create audit_log_details table
$connection->execute("
CREATE TABLE IF NOT EXISTS audit_log_details (
    id SERIAL PRIMARY KEY,
    audit_log_id INTEGER REFERENCES audit_logs(id) ON DELETE CASCADE,
    field_name VARCHAR(255) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    change_type VARCHAR(20) NOT NULL,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Truncate tables to ensure clean state (using CASCADE to handle foreign key constraints)
$connection->execute('TRUNCATE TABLE audit_log_details CASCADE');
$connection->execute('TRUNCATE TABLE audit_logs CASCADE');
$connection->execute('TRUNCATE TABLE scorecard_evaluations CASCADE');
$connection->execute('TRUNCATE TABLE scorecards CASCADE');
$connection->execute('TRUNCATE TABLE scorecard_template_answers CASCADE');
$connection->execute('TRUNCATE TABLE scorecard_templates CASCADE');
$connection->execute('TRUNCATE TABLE employee_answer_files CASCADE');
$connection->execute('TRUNCATE TABLE employee_template_answers CASCADE');
$connection->execute('TRUNCATE TABLE employee_templates CASCADE');
$connection->execute('TRUNCATE TABLE role_levels CASCADE');
$connection->execute('TRUNCATE TABLE level_templates CASCADE');
$connection->execute('TRUNCATE TABLE job_role_template_answers CASCADE');
$connection->execute('TRUNCATE TABLE job_role_templates CASCADE');
$connection->execute('TRUNCATE TABLE users CASCADE');

// (new Migrator())->run();
