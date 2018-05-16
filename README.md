# PHP Database Web API
Author: __Marco Cesarato__

## Description
Dynamically generate RESTful APIs from the contents of a database table. Provides JSON, XML, and HTML. Supports most popular databases.

## What problem this solves
Creating an API to access information within existing database tables is laborious task, when done as a bespoke task. This is often dealt with by exporting the contents of the database as CSV files, and providing downloads of them as a “good enough” solution.

## How this solves it
Database Web API acts as a filter, sitting between a database and the browser, allowing users to interact with that database as if it was a native API. The column names function as the key names. This obviates the need for custom code for each database layer.

When Alternative PHP Cache (APC) is installed, parsed data is stored within APC, which accelerates its functionality substantially. While APC is not required, it is recommended highly.

## Databases supported
* 4D
* CUBRID
* Firebird/Interbase
* IBM
* Informix
* MS SQL Server
* MySQL
* ODBC and DB2
* Oracle
* PostgreSQL
* SQLite

### Requirements
* PHP
* Database
* APC (optional)

## Installation
* Set the configuration on config.php (Follow the below example to register a new dataset in config.php
* If you want config an auth system you must compile on the config the constant \_\_AUTH\_\_ as on the example below
* If you want enable the auth system rename `.htaccess_auth` to `.htaccess`
* Document the API

## Configuration
Edit `config.php` to include a single instance of the following for each dataset (including as many instances as you have datasets):

__EXAMPLE with explanation__
```php
define("__API_NAME__", "Database Web API");
define("__BASE_DIR__", "");

define("__AUTH__",  serialize(array( // Set null for disable authentication
    'database' => 'dataset',
    'users' => array(
        'table' => 'users', // Table where users are stored
        'columns' => array(
            'id' => 'user_id',
            'password' => 'password',
            'dmin' => array('is_admin' => 'on') // Admin bypass all black/whitelists. Set NULL for disable
        ),
        'search' => array('user_id', 'email', 'username'), // Search user by these fields
        'check' => array('active' => 1) // Some validation checks. In this case if the column 'active' with value '1'. Set NULL for disable
    ),
    'callbacks' => array( // Functions stored in includes/callbacks.php that you can customize. Set NULL for disable (readonly)
        'sql_restriction' => 'callback_sql_restriction',
        'can_read' => 'callback_can_read',
        'can_write' => 'callback_can_write',
        'can_edit' => 'callback_can_edit',
        'can_delete' => 'callback_can_delete',
    ),
)));

define("__DATASETS__", serialize(array(
	'dataset' => array(
		'name' => 'database_name', // Database name
		'username' => 'user', // root is default
		'password' => 'passwd', // root is default
		'server' => 'localhost',  // localhost default
		'port' => 5432, // 3306 is default
		'type' => 'pgsql', // mysql is default
		'table_list' => array( // Tables's whitelist (Allow only the tables in this list, if empty allow all)
			'users'
		),
		'table_blacklist' => array( // Tables's blacklist
            'passwords'
		),
		'column_list' => array( // Columns's whitelist (Allow only the columns in this list, if empty allow all)
            'users' => array(
                'username',
                'name',
                'surname'
            )
		),
		'column_blacklist' => array( // Columns's blacklist
            'users' => array(
                'password',
            )
		),
	),
)));
```
___Note:__ All fields of \_\_DATASETS\_\_ (except the name of database) are optional and will default to the above._

__Default dataset values:__
```php
array(
    'name' => null,
    'username' => 'root',
    'password' => 'root',
    'server' => 'localhost',
    'port' => 3306,
    'type' => 'mysql',
    'table_blacklist' => array(),
    'table_list' => array(),
    'column_blacklist' => array(),
    'column_list' => array(),
    'ttl' => 3600,
);
```

### Callbacks

Callbacks availables (Prepared versions on `includes/callbacks.php`):

```php
function callback_sql_restriction($table, $permission)
function callback_can_read($table)
function callback_can_write($table){
function callback_can_edit($table)
function callback_can_delete($table)
```

You can use this code fo have a database instance and the current user authenticated row:

```php
$AUTH = Auth::getInstance();
$user = $AUTH->getUser(); // User row
$API = API::getInstance();
$db = $API->connect(); // You can specify dataset. Return PDO Object
```

__Note:__ All callbacks if return NULL will use default values with readonly permissions.

#### List

* `sql_restriction`

  **Description:** Return a string to append in where condition

  **Parameters:** \$table, \$permission

  **Options of *$permission*:**

  ```
  case 'READ':
  case 'WRITE':
  case 'EDIT':
  case 'DELETE':
  ```
  **Return**
  ```
   // All denied
  $sql = "'1' = '0'";
  // All allowed
  $sql = "'1' = '1'";
  ```

* `can_read`

  **Description:** Return if can GET/SELECT

  **Parameters:** \$table

  **Return:** Boolean

* `can_write`

  **Description:** Return if can POST/INSERT

  **Parameters:** \$table

  **Return:** Boolean

* `can_edit`

  **Description:** Return if can PUT/UPDATE

  **Parameters:** \$table

  **Return:** Boolean

* `can_delete`

* **Description:** Return if can DELETE

  **Parameters:** \$table

  **Return:** Boolean

#### Configuration

For implement the callbacks you need to add  the callbacks array to the \_\_AUTH\_\_ constant:

```php
'callbacks' => array( // Set NULL for disable (readonly)
     'sql_restriction' => 'callback_sql_restriction',
     'can_read' => 'callback_can_read',
     'can_write' => 'callback_can_write',
     'can_edit' => 'callback_can_edit',
     'can_delete' => 'callback_can_delete',
 ),
```

## API Structure

### Format availables:

- JSON
- XML
- HTML

### Generic URL format for all kind of request:

* Fetch all: `/[token]/[database]/[table].[format]`
* Fetch all with limit: `/[token]/[database]/[limit]/[table].[format]`
* Fetch: `/[token]/[database]/[table]/[ID].[format]`
* Fetch search by coolumn: `/[token]/[database]/[table]/[column]/[value].[format]`


### Advanced search:

__Note:__ These examples are valid only for **GET** and **PUT** requests

Search single value

```php
where[column]              = 1    // column = 1
where[column][=]           = 1    // column = 1
where[column][!]           = 1    // column != 1
where[column][>]           = 1    // column > 1
where[column][<]           = 1    // column < 1
where[column][%]           = "%1" // column LIKE "%1"
```

Search multiple values

```php
where[column]              = array(1,5,7)     // IN (...) (IN can be equal to an OR)
where[column][=]           = array(1,5,7)     // IN (...) 
where[column][!]           = array(1,5,7)     // NOT IN (...)
where[column][>]           = array(1,2)       // column > 1 AND column > 2
where[column][<]           = array(1,2)       // column < 1 AND column < 2
where[column][%]           = array("%1","%2") // column LIKE "%1" AND column LIKE "%2"
```

Specify column's table

```php
where['table.column'][=] = array(1,5,7)
```

Compare between two different table columns

```php
where['table_a.column_a'] = 'table_b.column_b'
```

Compare between different columns of main table

```php
where['column_a'] = 'table_a.column_b'
// OR
where['table_a.column_a'] = 'table_a.column_b'
    
// WRONG
where['column_a'] = 'column_b'
```



## Additional parameters

* `order_by`: column_name

  Can be and array or a string

  ```php
  order_by = 'username, name, surname'
  // OR
  order_by = array('username', 'name', 'surname')
  ```

  for more specific order direction

  ```php
  order_by['users.username'] = 'DESC'
  ```

* `direction`:  `ASC` or `DESC` (default `ASC`)

* `limit`: max elements to retrieve

ex: `/[database]/[tabel]/[colomn]/[value].[format]?order_by=[column]&direction=[direction]`

## Authentication

Authentication needed for browse the database.

The authentication permit to managed the privilege of the users (read, write, modify, delete)

- Authentication: `/auth/[password]/[id].[format]`

**Request example:**

```http
GET /auth/password/1265.json HTTP/1.1
Host: localhost
```

**Response example:**
```json
[{"token": "b279fb1d0708ed81e7a194e0c5d928b6"}]
```
 **Example of token usage on GET, POST, PUT and DELETE requests:**

```http
GET /bfee499dfa1387648ec8ce9d621db120/database/users.json` HTTP/1.1
Host: localhost
```



## GET Request

Retrieve data from dataset

- Fetch all: `/[token]/[database]/[table].[format]`

- Fetch all with limit: `/[token]/[database]/[limit]/[table].[format]`

- Fetch: `/[token]/[database]/[table]/[ID].[format]`

- Fetch search by column: `/[token]/[database]/[table]/[column]/[value].[format]`

- Fetch all joining table:

  ```js
  join[table] = array(
  	'on' => <column_id>,           // Column of the table joined
    	'value' => <value>,            // Column of main table or value
    	'method' => (left|inner|right) // Optional
  )
  ```

  **Example with value:**

  ```js
  join[users]['on'] = id
  join[users]['value'] = 1
  join[users]['method'] = 'INNER'
  ```

  **Example with column:**

  ```js
  join[users]['on'] = id            // Column of the table joined
  join[users]['value'] = user_id    // Column of the main table (no users)
  join[users]['method'] = 'INNER'
  ```

- Additional parameters

ex: `/[database]/[table]/[column]/[value].[format]?order_by=[column]&direction=[direction]`

**Examples of GET requests:**

```http
GET /dataset/users.json HTTP/1.1
Host: localhost
```

```http
GET /dataset/10/users.json HTTP/1.1
Host: localhost
```

```http
GET /dataset/users/1.json HTTP/1.1
Host: localhost
```

```http
GET /dataset/users/is_active/1.json?order_by=username&direction=desc HTTP/1.1
Host: localhost
```



## POST Request

Insert data

**Single insert:**

- Select the table on URL: `/[database]/[table].[format]`
- Insert parameter: `insert[<column>] = <value>`

**Multiple insert:**

- Select dataset on URL: `/[database].[format]`
- Insert parameter: `insert[<table>][<column>] = <value>`

**Note**: At the moment you can add only one row for table

**Examples of POST requests:**

**Single insert:**

```http
POST /dataset/users.json HTTP/1.1
Host: localhost
insert[username]=Marco&insert[email]=cesarato.developer@gmail.com&insert[password]=3vwjehvdfjhefejjvw&insert[is_active]=1
```

**Multiple insert:**

```http
POST /dataset.json HTTP/1.1
Host: localhost
insert[users][username]=Marco&insert[users][email]=cesarato.developer@gmail.com&insert[users][password]=3vwjehvdfjhefejjvw&insert[users][is_active]=1
```



## PUT Request

Update data

**Single update:**

- Select the row on URL: `/[database]/[table]/[id].[format]`
- Update parameter: `update[<column>] = <value>`

**Multiple update:**

- Select the dataset on URL: `/[database].[format]`
- Update parameter: `update[<table>][values][<column>] = <value>`
- Multiple update parameter conditions: `update[<table>][where][<column>] = <value>`

**Note**: At the moment you can update only one row for table

**Examples of PUT Requests:**

**Single Update:**

```http
PUT /dataset/users/1.json HTTP/1.1
Host: localhost
update['username']=Marco&update['email']=cesarato.developer@gmail.com&update['password']=3vwjehvdfjhefejjvw&update['is_active']=1
```

**Multiple Update:**

```http
PUT /dataset.json HTTP/1.1
Host: localhost
update[users][values][username]=Marco&update[users][values][email]=cesarato.developer@gmail.com&update[users][where][id]=1&update[cities][values][name]=Padova&update[cities][where][id]=1
```



## DELETE Request

Delete data

- Select the row on table: `/[database]/[table]/[id].[format]`

**Examples of DELETE Requests:**

```http
DELETE /dataset/users/1.json HTTP/1.1
Host: localhost
```



## API Client

### PHP API Client

__Filename:__ `apiclient.class.php`

__Class name:__ APIClient

| Method        | Params                                         | Return | Description                                    |
| ------------- | ---------------------------------------------- | ------ | ---------------------------------------------- |
| getInstance   | -                                              | Void   | Returns static reference to the class instance |
| fetch         | \$table, \$format = 'json', \$params = array() | Object | Fetch data                                     |
| searchElement | \$array, \$key, \$value                        | Object | Search object in array                         |

#### Usage

```Php
$api_client = APIClient::getInstance();

$api_client->DEBUG = true;
$api_client->URL = 'http://localhost';
$api_client->ACCESS_TOKEN = '4gw7j8erfgerf6werf8fwerf8erfwfer';
$api_client->DATASET = 'dataset';

$params = array(
    'where' => array(
        'type' => array('C', 'O', 'L'),
        'accounts_addresses.address' => array(
            '!' => '', // NOT NULL
        ),
    ),
    'join' => array(
        'accounts_addresses' => array(
            'on' => 'parent_id',
            'value' => 'id',
            'method' => 'LEFT'
        ),
        'accounts_agents' => array(
            'on' => 'parent_id',
            'value' => 'id'
        ),
    ),
    'order_by' => array(
        'address' => array(
            'table' => 'accounts_addresses',
            'direction' => 'DESC'
        ),
        'type' => array(
            'table' => 'accounts_addresses',
            'direction' => 'ASC'
        )
    ),
);
$records = $api_client->fetch('accounts', 'json', $params);
```



## Credits

https://github.com/project-open-data/db-to-api
