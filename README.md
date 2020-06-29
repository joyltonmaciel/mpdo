# MPDO - My Personal PDO

Database Manipulation which looks like Laravel Eloquent syntax.

# New features

Modifications
Accessors & Mutators

### Install

```
composer require joyltonmaciel/mpdo:dev-master
```

### The dotEnv Settings

At the root of the project, create a file called _.env_ with the following content:

```
DB_DRIVER=[pgsql]
DB_HOST=[localhost] 
DB_USER=[data_base_user_name]
DB_PASS=[data_base_user_name_password]  
```

### Usage

**Connect to Database**

```
require_once __DIR__ . '/../vendor/autoload.php';
use Mpdo\MDB;
$db = new MDB($dbname);
```

**Join**

```
$db->table('tableA')
    ->join('tableB', 'tableB.id', '=', 'tableA.compid')
```

```
$db->table('tableA as A')
    ->join('tableB as B', 'B.compid', '=', 'A.compid')
```

**Where**

```
$db->table('folhas')
    ->where('tpfolha', 0)
    ->where('folhaid', '<=', $folhaid)
    ->get();
```

**whereRaw**

```
$db->table('tableA')
    ->whereRaw("select * from tableA where id=25 and name='John'")
    ->get();
```

**orWhere**

```
$db->table('tableA')
    ->where('id', 50)
    ->orWhere('id', 34)
    ->get();
```

**orWhereRaw**

```
$db->table('tableA')
    ->orWhereRaw("select * from tableA where id=25 and name='John'")
    ->get();
```

**get**

The get method get records from table. The parameter of get method limit the amount of records returned.

_Return all records (no parameter is passed):_

```
$db->table('folhas')
    ->orderBy('folhaid', 'desc')
    ->get();
```


_Return only 3 records:_

```
$db->table('TableA')
    ->orderBy('id', 'desc')
    ->get(3);
```

**insert**

```
$dados = new stdClass();
$dados->{'tabela'} = new stdClass();
$dados->{'tabela'}->nome = 'Joao';
$dados->{'tabela'}->date = '2020-01-01';
$dados->{'tabela'}->valid = true;
$db->insert($dados);
```

**update**

```
$db->table('tabela')->where('id', $id)->update(['field_name' => $value]);
```

**delete**

```
$db->table('table')->where('id', $id)->delete();
```
