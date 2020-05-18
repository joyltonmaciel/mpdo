# MPDO - My Personal PDO

Database Manipulation which looks like Laravel Eloquent syntax.

**Install:**

composer require joyltonmaciel/mpdo:dev-master

**Usage:**

_Join_

```
    $db->table('tableA')
        ->join('tableB', 'tableB.id', '=', 'tableA.compid')
```

```
    $db->table('tableA as A')
        ->join('tableB as B', 'B.compid', '=', 'A.compid')
```

_Where_

```
    $db->table('folhas')
        ->where('tpfolha', 0)
        ->where('folhaid', '<=', $folhaid)
        ->get();
```

_get_

The get method get records from table. The parameter of get method limit the amount of records returned.

Return all records (no parameter is passed):

```
    $db->table('folhas')
        ->orderBy('folhaid', 'desc')
        ->get();
```


Return only 3 records:

```
    $db->table('folhas')
        ->orderBy('folhaid', 'desc')
        ->get(3);
```

_insert_

```
    $dados = new stdClass();
    $dados->{'tabela'} = new stdClass();
    $dados->{'tabela'}->nome = 'Joao';
    $dados->{'tabela'}->date = '2020-01-01';
    $dados->{'tabela'}->valid = true;
    $db->insert($dados);
```

_update_

```
    $db->table('tabela')->where('id', $id)->update(['field_name' => $value]);
```

_delete_

```
    $db->table('table')->where('id', $id)->delete();
```
