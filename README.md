# MPDO - My Personal PDO

Small Database Manipulation which looks like Laravel Eloquent syntax. 
Based on PHP-PDO library.

[![Latest Stable Version][ico-stable]][link-packagist]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![License][ico-license]][link-packagist]

[![Issues][ico-issues]][link-issues]
[![Forks][ico-forks]][link-forks]
[![Stars][ico-stars]][link-stars]

## Install

```
composer require joyltonmaciel/mpdo:dev-master
```

## The dotEnv Settings

At the root of the project, create a file called _.env_ with the following content:

```
DB_DRIVER=[pgsql]
DB_HOST=[localhost] 
DB_USER=[data_base_user_name]
DB_PASS=[data_base_user_name_password]  
```

## Usage

**Connect to Database**

```
require_once __DIR__ . '/../vendor/autoload.php';
use Mpdo\MDB;
$db = new MDB($dbname);
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

**WhereIn**

```
$db->table('tablename')
    ->whereIn('id', [34, 52])
    ->get();
```

**WhereNotIn**

```
$db->table('tablename')
    ->whereNotIn('id', [34, 52])
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

**Samples**

```
$resp = $db
	->table('folhas')
	->select('folhas.folhaid')
	->select('folhas.mes')
	->select('folhas.ano')
	->select('fp_afastamentos.afastid')
	->select('fp_afastamentos.afastamento')
	->select('fp_afastamentos.termino')
	->select('folhasdescricao.usuarioid')
	->select('folhasdescricao.contratoid')
	->where('folhas.folhaid', $values->key)
	->join('folhasdescricao', 'folhasdescricao.folhaid', '=', 'folhas.folhaid')
	->join('fp_afastamentos', 'fp_afastamentos.afastid', '=', 'folhasdescricao.afastid')
	->groupBy('folhas.folhaid, folhas.mes, folhas.ano, fp_afastamentos.afastid')
	->groupBy('folhasdescricao.usuarioid, folhasdescricao.contratoid')
	->groupBy('fp_afastamentos.afastamento')
	->groupBy('fp_afastamentos.termino')
	->Key('afastid')
	->get();
```

```
$resp = $db
	->table("(select folhaid, to_date(ano || '-' || mes || '-01', 'yyyy-mm-dd') as datafolha from folhas) as tableA")
	->join('folhas', 'folhas.folhaid', '=', 'tableA.folhaid')
	->Key('folhaid')
	->where('tpfolha', 0)
	->where('datafolha', '<', '2020-04-01')
	->orderBy('ano', 'desc')
	->orderBy('mes', 'desc')
	->get(3);
```

```
$resp = $mdb
	->table('folhasdescricao')
	->select('folhaid, usuarioid, contratoid, rescisaoid, afastid')
	->where('folhasdescricao.folhaid', $folha->folhaid)
	->whereRaw("(folhasdescricao.rescisaoid>0 or folhasdescricao.afastid>0)")
	->groupBy('folhaid, usuarioid, contratoid, rescisaoid, afastid')
	->debug(true)
	->Key('contratoid')
	->get();
```

```
$resc = $mdb
	->table('fp_salmatern')
	->Key('contratoid')
	->select('fp_salmatern.contratoid, fp_salmatern.datefr')
	->join('folhasdescricao', 'fp_salmatern.contratoid', '=', 'folhasdescricao.contratoid')
	->where('folhasdescricao.folhaid', $folha->folhaid)
	->where('folhasdescricao.tp_rubrica', '=', '1')
	->whereRaw('folhasdescricao.rubricaid in (select rubricaid from fp_rubricas where rubrica_ident=2)')
	->orWhereRaw("(datefr>='" . $competencia->inicial . "' and datefr<='" . $competencia->final . "')")
	->orWhereRaw("(datefr<='" . $competencia->inicial . "' and dateto>='" . $competencia->final . "')")
	->orWhereRaw("(dateto>='" . $competencia->inicial . "' and dateto<='" . $competencia->final . "')")
	->debug(true)
	->get();
```

## New features

**Modifications Accessors & Mutators**

**whereIn**

from

```
select * FROM brands WHERE id in ( select brand_id from products WHERE category_id IN (220, 222, 223) GROUP by brand_id )
```

to

```
->whereIn('id', function ($query) {
    $query
        ->select('brand_id')
        ->from('products')
        ->whereIn('category_id', [220, 222, 223])
        ->groupBy('brand_id');
})->get();
```

## Credits

Joylton Maciel (owner and developer)
**maciel** dot inbox at gmail dot com 
