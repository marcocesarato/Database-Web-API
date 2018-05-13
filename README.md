# PHP Database Web API
Author: __Marco Cesarato__

## Description
Dynamically generate RESTful APIs from the contents of a database table. Provides JSON, XML, and HTML. Supports most popular databases.

## What Problem This Solves
Creating an API to access information within existing database tables is laborious task, when done as a bespoke task. This is often dealt with by exporting the contents of the database as CSV files, and providing downloads of them as a “good enough” solution.

## How This Solves It
Database Web API acts as a filter, sitting between a database and the browser, allowing users to interact with that database as if it was a native API. The column names function as the key names. This obviates the need for custom code for each database layer.

When Alternative PHP Cache (APC) is installed, parsed data is stored within APC, which accelerates its functionality substantially. While APC is not required, it is recommended highly.

## Databases Supported
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
* If you want config an auth system edit `includes/classes/auth.class.php` based your needs and your dataset (the default configuration is read only but if you configure as the example below the `auth.class.php` you could insert/update and delete from your database)
* If you want enable the auth system rename `.htaccess_auth` to `.htaccess`
* Document the API

## Configuration
Edit `config.php` to include a single instance of the following for each dataset (including as many instances as you have datasets):

```php
define("__API_NAME__", "Database Web API");
define("__BASE_DIR__", "");
define("__DATASETS__", serialize(array(
	'dataset' => array(
		'name' => 'database_name',
		'username' => 'username',
		'password' => 'password',
		'server' => 'localhost',
		'port' => 3306,
		'type' => 'mysql',
		'table_list' => array(
			/** @example
				'users'
			 **/
		), // Whitelist (Allow only the tables in this list, if empty allow all)
		'table_blacklist' => array(
			/** @example
				'passwords'
			 **/
		),
		'column_list' => array(
			/** @example
				'users' => array(
					'username',
					'name',
					'surname'
				)
			 **/
		),  // Whitelist  (Allow only the columns in this list, if empty allow all)
		'column_blacklist' => array(
			/** @example
				'users' => array(
					'password',
				)
			 **/
		),
	),
)));
```
__Note:__ All fields (other than the dataset name) are optional and will default to the above.

### How configure the authentication system

The authentication system at the moment work with a sqlite database on your root folder (but if you want you can change it)  where are stored all tokens user info (user_id, is_admin and role_id) and client info (user_agent, last_access and date_created for manage the active sessions).

__Note:__ You have to remove the following line foreach methods listed to enable the auth

```php
return true; // <==== REMOVE
```


1. Edit method `public validate($query)`

   This function check if authentication is valid. Here an example:

   ```Php
   $user = strtolower($query['user_id']);
   
   $this->api = API::getInstance();
   $this->db = &$this->api->connect('database');
   
   $sth = $this->db->prepare("SELECT id, first_name, last_name, role_id, is_admin, user_hash FROM users WHERE (id = :user_id OR user_name = :username OR email1 = :email)");
   $sth->bindParam(':user_id', $user);
   $sth->bindParam(':username', $user);
   $sth->bindParam(':email', $user);
   
   $sth->execute();
   $user_row = $sth->fetch();
   
   if ($user_row) {
       $password = strtolower($query['password']);
       if ($user_row['user_hash'] == $password) {
		$token = $this->generateToken($user_row['id']);
		$this->user_id = $user_row['id'];
		$this->role_id = $user_row['role_id'];
		$this->is_admin = $user_row['is_admin'];
		$results = array((object)array(
		   "token" => $token,
		   "id" => $user_row['id'],
		   "first_name" => $user_row['first_name'],
		   "last_name" => $user_row['last_name'],
		   "role_id" => $user_row['role_id'],
		   "is_admin" => (($user_row['is_admin'] == 'on') ? true : false),
		));
		$renderer = 'render_' . $query['format'];
		die($this->api->$renderer($results, $query));
       }
   }
   Request::error("Invalid authentication!", 401);
   ```

   

2. Edit method `private validateToken($token)`

   This method validate the token. Here an example

   ```php
   try {
       $sth = $this->sqlite_db->prepare("SELECT * FROM tokens WHERE token = :token");
       $sth->bindParam(':token', $token);
       $sth->execute();
       $token_row = $sth->fetch();
   
       if ($token_row) {
   
           $this->api = API::getInstance();
           $this->db = &$this->api->connect('database');
           $sth = $this->db->prepare("SELECT id, role_id, is_admin  FROM users WHERE id = :user_id");
           $sth->bindParam(':user_id', $token_row['user_id']);
   
           $sth->execute();
           $user_row = $sth->fetch();
   
           if ($user_row) {
               $this->user_id = $user_row['id'];
               $this->role_id = $user_row['role_id'];
               $this->is_admin = (($user_row['is_admin'] == 'on') ? true : false);
               return true;
           }
   
       }
   	return false;
   } catch (PDOException $e) {
   	Request::error($e->getMessage(), 500);
   }
   ```

   

3. Edit `public sql_restriction($table, $permission = (string)(READ, MODIFY, DELETE))`

   This method add at the end of SELECT, UPDATE and DELETE queries some restriction based on permissions (you can do a subquery with the user/role id)

4. At the end you have to edit `public can_(read/write/modify/delete)($table)`

   These methods return if the user can read/insert/update and delete a table

   Default is read only

5. Rename `.htaccess_auth` to `.htaccess`



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
  order_by[] = 'username'
  ```

  for more specific order direction

  ```php
  order[username][table]     = 'users'
  order[username][direction] = 'DESC'
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

- Fetch search by coolumn: `/[token]/[database]/[table]/[column]/[value].[format]`

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
