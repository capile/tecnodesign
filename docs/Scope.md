<!--
---
title: Schema Scope
...
-->

# Schema Scope

Scope is the specific information to be queried from a model. Most of the time you don't need all the columns of one specific table, or would rather fetch a column from one relation to compliment the model and avoid mode queries to the database. It's even possible to query information differently, using database functions — for all of these you must define or use a specific scope.

Scope resolution must return an associative array containing the actual field names as values, optionally with the labels to be used as keys:

```
CountryModel::$schema['scope'] = array(
  'string' => array( 'country' ),
);
``` 

That is, if you query the database using the scope *`string`*, you'll only fetch the `country` column, nothing else. *`string`* is also a special keyword for scope names — it's usually used to translate a model object to string. 

Scope can be defined at the **`Model::$schema`** or directly at each method call. If you supply a string for a scope, then this should be the name of the scope defined at **`Model::$schema['scope']`**.

For example, consider a model for countries and a related model for companies, that has a foreign key that refers to the country:


### CountryModel

| Field   | Type         |
|---------|--------------|
| id      | char(2)      |
| country | varchar(100) |
| region  | varchar(100) |


### CompanyModel

| Field   | Type         |
|---------|--------------|
| id      | int          |
| name    | varchar(100) |
| country | varchar(100) |

If you want to fetch a list of companies per region, you could use the relation between these tables to gather this data:

```
CompanyModel::$schema['scope'] = array(
  'string' => array( 'name' ),
  'region' => array( 
    'Company name'=>'name', 
    'Region'=>'CountryModel.region _region'
  ),
);
```

if you render a table using that scope, you'd end up with two columns, one with the **Company name** header and the other with **Region**.

### Tips

1. When the scope is used to load data into the object model, you can rename the model property that will hold the data by adding it at the end of the scope column separated by space. In the above example, `_region` will be used, so the region names are written at each object under that property: `$Company->_region`.

2. Relation names are translated to a table alias when querying. The last column name is the actual column at the result:

   ```
   CompanyModel::find(null,0,'region');
   ``` 

   Would query as:

   ```
   select t.id, t.name, t0.region _region 
   from company_table as t 
     left outer join country_table as t0 on t0.id=t.country
   ``` 

3. The model will only accept data that is defined within its columns. `region`, for example, is not defined at `CompanyModel` so it would return an error when loaded in it. Properties that begin with underscore are allowed to be loaded without being previously defined at `Tecnodesign_Model` (that's the reason for the alias).

4. You can combine several columns and use database specific functions at the scope value. In these cases, isolate what are the actual column/relation names with either \` or enclose the names between `[]`:

   ```
   CompanyModel::$schema['scope'] = array(
     ...
     'country-region' => array( 
       'Company name'=>'name', 
       'Country/Region'=>"concatename(`CountryModel.country`, '/', `CountryModel.region`) _tmp"
     ),
   );
   ``` 
5. Database funcions are database specific! Above query could work on a MySQL database, but not at SQL Server (doesn't have the `concatenate` function).


## Advanced uses
