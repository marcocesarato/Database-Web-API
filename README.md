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
* Set the configuration on config.php (Follow the below example to register a new dataset in config.php. Tip: It's best to provide read-only database credentials here.)
* Edit the `includes/classes/auth.class.php` based your needs and your dataset
* Document the API.

## How to Register a Dataset
Edit `config.php` to include a a single instance of the following for each dataset (including as many instances as you have datasets):

```php
$databases = array(
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
		'secret_table'
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
);
```
__Note:__ All fields (other than the dataset name) are optional and will default to the above.

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
where[column]              = array(1,5,7)     // IN (...)
where[column][=]           = array(1,5,7)     // IN (...)
where[column][!]           = array(1,5,7)     // NOT IN (...)
where[column][>]           = array(1,2)       // column > 1 AND column > 2
where[column][<]           = array(1,2)       // column < 1 AND column < 2
where[column][%]           = array("%1","%2") // column LIKE "%1" AND column LIKE "%2"
```

Specify column's table

```php
where[id][=]           = array(1,5,7)
where[id][table]       = 'my_table'
```

Compare between two different table columns

```php
where[column_a][table]          = 'table_a'
where[column_a][=][table]       = 'table_b'
where[column_a][=][column]      = 'column_b'
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

* `direction`:  `asc` o `desc` (default `asc`)

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
  	'on' => <column_id>,
    	'value' => <value>,           // Colonna tabella o id ad esempio
    	'method' => (left|inner|right) // Opzionale
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
  join[users]['on'] = id        // Colonna della tabella da agganciare
  join[users]['value'] = user_id    // Colonna della tabella principale (no users)
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

- Select the row on URL: `/[database]/[tabella]/[id].[formato]`
- Update parameter: `update[<nome_colonna>] = <valore>`

**Multiple update:**

- Select the dataset on URL: `/[database].[formato]`
- Update parameter: `update[<nome tabella>][values][<nome_colonna>] = <valore>`
- Multiple update parameter conditions: `update[<nome_tabella>][where][<nome_colonna>] = <valore>`

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
update[users][][values][username]=Marco&update[users][values][email]=cesarato.developer@gmail.com&update[users][where][id]=1&update[cities][values][name]=Padova&update[cities][where][id]=1
```



## DELETE Request

Delete data

- Select the row on table: `/[database]/[tabella]/[id].[formato]`

**Examples of DELETE Requests:**

```http
DELETE /dataset/users/1.json HTTP/1.1
Host: localhost
```



## Credits

https://github.com/project-open-data/db-to-api
