# Workmatica Backend Template

## Quick Start

### 1. Run Backend
```bash
# inside project directory run
cd docker
docker compose up --build -d
```

### 2. Access Backend
- **API Base URL**: http://localhost:97/api
- **Health Check**: http://localhost:97

### 3. Stop Backend
```bash
docker compose down
```

## Detailed Setup Guide

### Prerequisites
- Docker and Docker Compose installed
- Database template running (Workmatica-Database-Template)
- PostgreSQL database with imported data

### Step-by-Step Setup

#### Step 1: Configure Database Connection
Update `config/datasources.php` to connect to local database:

```php
$dataSources['default'] = setupDataSource('postgres_workmatica_template', 'workmatica_user', 'securepassword', 'workmatica');
$dataSources['test'] = setupDataSource('postgres_workmatica_template', 'workmatica_user', 'securepassword', 'workmatica');
$dataSources['client_200001'] = setupDataSource('postgres_workmatica_template', 'workmatica_user', 'securepassword', '200001');
```

#### Step 2: Start Backend Container
```bash
cd Workmatica-Backend-Template/docker
docker compose up --build -d
```

#### Step 3: Verify Container Running
```bash
docker ps --filter "name=workmatica_backend_template"
```
Expected output:
- `workmatica_backend_template` (ports 97:80, 456:443)

#### Step 4: Test API Connection
```bash
# Test basic connectivity
curl -I http://localhost:97

# Test API endpoint (should return 401 Unauthorized)
curl -I http://localhost:97/api/users
```

#### Step 5: Verify Database Connection
```bash
# Check backend logs for database connection
docker logs workmatica_backend_template
```

## API Endpoints

### Authentication
- **POST** `/api/users/login.json` - User login
- **POST** `/api/users/logout.json` - User logout

### Users
- **GET** `/api/users` - List users (requires authentication)
- **POST** `/api/users` - Create user
- **GET** `/api/users/{id}` - Get user details
- **PUT** `/api/users/{id}` - Update user
- **DELETE** `/api/users/{id}` - Delete user

### Employees
- **GET** `/api/employees` - List employees
- **POST** `/api/employees` - Create employee
- **GET** `/api/employees/{id}` - Get employee details
- **PUT** `/api/employees/{id}` - Update employee
- **DELETE** `/api/employees/{id}` - Delete employee

### Job Roles
- **GET** `/api/job-roles` - List job roles
- **POST** `/api/job-roles` - Create job role
- **GET** `/api/job-roles/{id}` - Get job role details
- **PUT** `/api/job-roles/{id}` - Update job role
- **DELETE** `/api/job-roles/{id}` - Delete job role

## Database Configuration

### Main Database (workmatica)
- **Host**: postgres_workmatica_template
- **Port**: 5432
- **Database**: workmatica
- **Username**: workmatica_user
- **Password**: securepassword

### Client Database (200001)
- **Host**: postgres_workmatica_template
- **Port**: 5432
- **Database**: 200001
- **Username**: workmatica_user
- **Password**: securepassword

## Ports Used
- **97**: Backend HTTP (Apache)
- **456**: Backend HTTPS (Apache)

## Authentication

### Login Request
```bash
curl -X POST http://localhost:97/api/users/login.json \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=your_username&password=your_password"
```

### Response Format
```json
{
  "token": "jwt_token_here",
  "user": {
    "id": 1,
    "username": "user",
    "role": "admin"
  }
}
```

### Using Token
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost:97/api/users
```

## Troubleshooting

### Container Issues
```bash
# Restart containers
docker compose down
docker compose up -d

# Check logs
docker logs workmatica_backend_template
```

### Database Connection Issues
```bash
# Verify database is running
docker ps --filter "name=workmatica_template_postgres_database"

# Test database connection from backend
docker exec workmatica_backend_template php -r "
\$dsn = 'pgsql:host=postgres_workmatica_template;port=5432;dbname=workmatica;user=workmatica_user;password=securepassword';
try {
    \$pdo = new PDO(\$dsn);
    echo 'Database connection successful\n';
} catch (PDOException \$e) {
    echo 'Connection failed: ' . \$e->getMessage() . '\n';
}
"
```

### API Issues
```bash
# Check if backend is responding
curl -I http://localhost:97

# Check Apache error logs
docker exec workmatica_backend_template tail -f /var/log/apache2/error.log
```

## File Structure
```
Workmatica-Backend-Template/
├── config/
│   ├── app.php
│   ├── app_local.php
│   ├── datasources.php
│   └── routes.php
├── src/
│   ├── Controller/
│   │   └── Api/
│   ├── Model/
│   └── View/
├── docker/
│   ├── docker-compose.yml
│   └── Dockerfile
└── tests/
```

## Development

### Local Development
```bash
# Start development server
bin/cake server -p 8765

# Run tests
vendor/bin/phpunit
```

### Docker Development
```bash
# Rebuild container after changes
docker compose down
docker compose up --build -d

# View logs in real-time
docker logs -f workmatica_backend_template
```
