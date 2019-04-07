# PHP Database Web API
![](cover.png)

**Version:** 0.5.67 beta

**Github:** https://github.com/marcocesarato/Database-Web-API

**Author:** Marco Cesarato

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

**EXAMPLE with explanation**
```php
define("__API_NAME__", "Database Web API"); // API Name

define("__AUTH__",  serialize(array( // Set null for disable authentication
    'sqlite' => false, // Enabled save token on SQLite file
    'sqlite_database' => 'api_token', // SQLite filename (only with sqlite = true)
    'api_database' => 'dataset', // Authentication database
    'api_table' => 'api_authentications', // API token table name
    'users' => array(
        'database' => 'dataset', // Database where users are stored
        'table' => 'users', // Table where users are stored
        'columns' => array(
            'id' => 'user_id', // Id column name
            'username' => 'user_name', // Username column name
            'password' => 'password', // Password column name
            'admin' => array('is_admin' => 1) // Admin bypass condition. With this condition true API bypass all black/whitelists and permissions. Set NULL for disable
        ),
        'search' => array('user_id', 'email', 'username'), // Search user by these fields
        'check' => array('active' => 1) // Some validation checks. In this case if the column 'active' with value '1'. Set NULL for disable
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
        'table_docs' => array(
        	/*
        	'table' => array(
        		"column" => array(
                    "description" => "Column description",
                    "example" => "1",
                ),
            ),
        	*/
        ), // For Autodocoumentation, url ex. /dataset/docs/table.html
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
**Note:** All fields of \_\_DATASETS\_\_ (except the name of database) are optional and will default to the above.

**Default dataset values:**
```php
array(
    'name' => null,
    'username' => 'root',
    'password' => 'root',
    'server' => 'localhost',
    'port' => 3306,
    'type' => 'mysql',
    'table_docs' => $docs['dataset'],
    'table_blacklist' => array(),
    'table_list' => array(),
    'column_blacklist' => array(),
    'column_list' => array(),
    'ttl' => 3600,
);
```



## API Structure

### Format availables:

- JSON

- XML

- HTML

  

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



### Generic URL format for all kind of request:

#### Standard

* Fetch all: `/[database]/[table].[format]`
* Fetch all with limit: `/[database]/[limit]/[table].[format]`
* Fetch: `/[database]/[table]/[ID].[format]`
* Fetch search by coolumn: `/[database]/[table]/[column]/[value].[format]`
* Documentation: `/[database]/docs/[table].[format]`

#### With Authentication

* Fetch all: `/[token]/[database]/[table].[format]`
* Fetch all with limit: `/[token]/[database]/[limit]/[table].[format]`
* Fetch: `/[token]/[database]/[table]/[ID].[format]`
* Fetch search by column: `/[token]/[database]/[table]/[column]/[value].[format]`
* Documentation: `/[token]/[database]/docs/[table].[format]`



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

### Advanced search

**Note:** These examples are valid only for **GET** and **PUT** requests

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

### Additional parameters

- `order_by`: column_name

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

- `direction`:  `ASC` or `DESC` (default `ASC`)

- `limit`: max elements to retrieve

ex: `/[database]/[tabel]/[colomn]/[value].[format]?order_by=[column]&direction=[direction]`

### Documentation

*PS:* Work only with pgsql and mysql database type at the moment

For get auto-documentation of a database table:

- Documentation URL format: `/[database]/docs/[table].[format]`

- Documentation URL format with Authentication: `/[token]/[database]/docs/[table].[format]`

For have a separated file where document your database you can use `/docs.php`

## POST Request

Insert data

**Single insert:**

- Select the table on URL: `/[database]/[table].[format]`
- Insert parameter: `insert[<column>] = <value>`

**Multiple insert:**

- Select dataset on URL: `/[database].[format]`
- Insert parameter: `insert[<table>][] = <value>`

**Multiple insert on the same table:**

- Select dataset on URL: `/[database].[format]`
- Insert parameter: `insert[<table>][<$i>][<column>] = <value>` 

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
insert[users][id]=1000&insert[users][username]=Marco&insert[users][email]=cesarato.developer@gmail.com&insert[users][password]=3vwjehvdfjhefejjvw&insert[users][is_active]=1&
insert[admin][user_id]=1000
```

**Multiple insert on the same table:**

```http
POST /dataset.json HTTP/1.1
Host: localhost
insert[users][0][username]=Marco&insert[users][0][email]=cesarato.developer@gmail.com&insert[users][0][password]=3vwjehvdfjhefejjvw&insert[users][0][is_active]=1&insert[users][1][username]=Brad&insert[users][1][email]=brad@gmail.com&insert[users][1][password]=erwerwerffweeqewrf&insert[users][1][is_active]=1
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

## Hooks

You can use this code for have a database instance and the current user authenticated row:

```php
$user = Auth::getUser(); // User row
$db = API::getDatabase('dataset'); // You can specify dataset. Return PDO Object
```

### Most important hooks

* `sql_restriction`

  **Description:** Return a string to append in where condition

  **Parameters:** \$table, \$permission

  **Options of *$permission*:**

  ```php
  case 'READ':
  case 'EDIT':
  case 'DELETE':
  ```
  **Return**

  ```php
  // All denied
  $sql = "'1' = '0'";
  // All allowed
  $sql = "'1' = '1'";
  ```
  **Examples:**

  ```php
  // Only Created
  $sql = 'created_by = '.$user['id'];
  // Only Team
  $sql = 'created_by IN ('.implode(',',$teams_ids).')';
  ```

* `can_read`

  **Description:** Return if can GET/SELECT

  **Parameters:** \$restriction, \$permission, \$table

  **Return:** Boolean

* `can_write`

  **Description:** Return if can POST/INSERT

  **Parameters:** \$permission, \$table

  **Return:** Boolean

* `can_edit`

  **Description:** Return if can PUT/UPDATE

  **Parameters:** \$permission, \$table

  **Return:** Boolean

* `can_delete`

* **Description:** Return if can DELETE

  **Parameters:** \$permission, \$table

  **Return:** Boolean

### All hooks

```php
/**
 * Custom API Call
 * @return mixed or die (with mixed return just skip to next action until 404 error)
 */
$hooks->add_action('custom_api_call','action_custom_api_call', 1);


/**
 * Add restriction on where conditions for each query
 * @param $restriction
 * @param $table
 * @param $permission
 * @return mixed
 */
$hooks->add_filter('sql_restriction','filter_sql_restriction');

/**
 * Return if can select
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_read','filter_can_read');

/**
 * Return if can insert
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_write','filter_can_write');

/**
 * Return if can update
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_edit','filter_can_edit');

/**
 * Return if can delete
 * @param $permission
 * @param $table
 * @return mixed
 */
$hooks->add_filter('can_delete','filter_can_delete');

/**
 * On read
 * @param $data
 * @param $table
 * @return mixed
 */
$hooks->add_filter('on_read','filter_on_read');

/**
 * On write
 * @param $data
 * @param $table
 * @return mixed
 */
$hooks->add_filter('on_write','filter_on_write');

/**
 * On edit
 * @param $data
 * @param $table
 * @return mixed
 */
$hooks->add_filter('on_edit','filter_on_edit');

/**
 * Validate token
 * @param $is_valid
 * @param $token
 * @return bool
 */
$hooks->add_filter('validate_token','filter_validate_token');


/**
 * Filter user auth login
 * @param $user_id
 * @return string
 */
$hooks->add_filter('auth_user_id','filter_auth_user_id');


/**
 * Bypass authentication
 * @param $bypass
 * @return bool
 */
$hooks->add_filter('bypass_authentication','filter_bypass_authentication');

/**
 * Check if is a login request
 * @param $is_valid_request
 * @param $query
 * @return string|false
 */
$hooks->add_filter('check_login_request','filter_check_login_request');
```


## API Client

### PHP API Client 

**Filename:** `apiclient.class.php`

**Class name:** APIClient

| Method        | Params                                         | Return | Description                                    |
| ------------- | ---------------------------------------------- | ------ | ---------------------------------------------- |
| getInstance   | -                                              | Void   | Returns static reference to the class instance |
| get           | \$table, \$format = 'json', \$params = array() | Object | Fetch data                                     |
| insert        | \$format = 'json', \$params = array()          | Object | Insert data                                    |
| update        | \$format = 'json', \$params = array()          | Object | Update data                                    |
| delete        | \$table, \$format = 'json', \$params = array() | Object | Delete data                                    |
| searchElement | \$key, \$value, \$array                        | Object | Search object in array                         |
| filterBy      | \$key, \$value, \$array, \$limit = null        | Array  | Filter results array by single key             |
| filter        | \$value, \$array, $limit = null                | Array  | Filter results array by multiple values        |

#### Usage

```Php
$api_client = APIClient::getInstance();

APIClient::$DEBUG = true;
APIClient::$URL = 'http://localhost';
APIClient::$ACCESS_TOKEN = '4gw7j8erfgerf6werf8fwerf8erfwfer';
APIClient::$DATASET = 'dataset';

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

<https://github.com/voku/php-hooks>