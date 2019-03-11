<!--
---
title: Schema
...
-->
# Schema

A **Schema** defines a model, in ways that it can be created, queried, updated and removed. All model operations require that a schema be built for the provided model.

|     Attribute     |                                                                               Description                                                                                |                                   Example                                   |
|-------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------|
| database          | Required, String. Name of the database key in the [database configuration](Database).                                                                                                | `'studio'`                                                                  |
| className         | Optional, String. Class name to use when building this schema, defaults to the `Tecnodesign_Model` class it was called from.                                             | `'Profile'`                                                                 |
| tableName         | Required, String. Name of the table within the database. Will be used together with the `database` attribute to build the connection.                                    | `'profile'`                                                                 |
| view              | Optional, String. Query or another table name to overwrite the `tableName` in queries only (read operations)                                                             | `'select * from profile'`                                                   |
| properties        | Required, Indexed array or column names and their description.                                                                                                           | _see [Properties](#Properties)_                                       |
| patternProperties | Optional, Array. List of acceptable property names as regular expressions. If a property is not within `properties` and doesn't match a pattern, it should be rejected.  | `'/^_/'` *(allow properties starting with `_`)*                               |
| overlay           | Optional, Indexed array, used to overwrite `properties` when building forms and controlling output.                                                                      | _see [Properties](#Properties)_                                                                 |
| scope             | Optional, Indexed array of property collections. It can use only the name of the column or include a full overlay to be applied to the property when in this collection. | `[ 'string' => [ 'name' ] ]` _(use the `name` property when rendering the object)_ |
| relations         | Optional, Indexed array of foreign keys definitions.                                                                                                                     | _see below_                                                                 |
| events            | Optional, Indexed array of triggers for each event.                                                                                                                      |                                                                             |
| orderBy           | Optional, Indexed array for custom default sorting of the queries provided by this model.                                                                                |                                                                             |
| groupBy           | Optional, Indexed array for custom default grouping of the queries providede by this model.                                                                              |                                                                             |
| columns           | Deprecated, alias of `properties`                                                                                                                                        |                                                                             |
| form              | Deprecated, alias of `overlay`                                                                                                                                           |                                                                             |

## Properties

Each property is a definition of all the aspects that the named attribute should contain. No attribute is a requirement to be used, since their absence means the default value will be use instead. 

### bind _(string)_

Defaults to the string or attribute index if not present. Defines where to retrieve the information for this property in the backend. Usually column names in the database. However, it accepts the supported database functions and relation nesting with `.`.

Examples: 

```php
// same as [ 'bind' => 'id' ]
'id'              => 'id',

// This will set the `bind` to `Date` (case-sensitive)
'Date'            => [ 'type' => 'datetime' ]

// Using database functions
'First Name'      => [ 'bind' => 'first_name' ],
'Last Name'       => [ 'bind' => 'last_name' ],
'Full Name'       => [ 'bind' => "concat(`first_name`, ' ', `last_name`) as _full_name" ],

// Using relations
'Company Name'    => [ 'bind' => 'Companies.name _company_name' ],
'Company E-mail'  => [ 'bind' => '`Companies.email` _company_email' ],
'Company Tax Profile'  => '`Companies.TaxProfiles.name` _company_tax_profile',
```

Remember to always alias queries (for functions and relations), so that they have a simpler name at the model and can be retrieved and manipulated. Aliases should be in the bind definition, at the end, separated by a space and using only alphanumerical characters, _ and _. The preceeding ` as ` is optional. 

When you make queries that combine/use more than one property or functions, use ` before and after each property name or relation.

### alias

When defined means that the property should not be read/written to the backend, the aliased property should be used instead. Supports all the bind features like functions and relations.

### type

Defaults to `string`. Defines the property behavior on rendering and validation. Some types are only supported as overlays, these types will be converted to strings when dealing with the backend.

**Native Types**

- **bool** (aka _bit_) - should be stored as `1` or `0` in the backend.
- **int** (aka _integer_) - non-decimal numbers. The attribute `size` defines the number of digits to use.
- **number** (aka _decimal_ or _float_) - real numbers. The attribute `size` defines the number of digits, while the attribute `decimal` the number of decimal digits to use.
- **date** - dates, with Year, Month and Day as a string.
- **datetime** - date and time on the backend-defined timezone. This is not an unix timestamp (use `int` for them).
- **object** - serializable key-value pairs. The `serialize` attribute defines how they will be stored/parsed when reading and writing to the backend.
- **array** - serialzable lists (numerically indexed). The `serialize` attribute defines how they will be stored/parsed when reading and writing to the backend.
- **string** (aka _text_) - default type, it's a unicode text of any size (can be constrained with other properties). Other types that are not listed in this native list should be converted to strings before posted to the backend.

**Overlay Types**

- *form*
- *email*
- *url*
- *ip*
- *dns*
- *search*
- *file*
- *number*
- *tel*
- *range*
- *password*
- *date-select*
- *color*
- *phone*
- *html*
- *textarea*
- *none*
- *hidden-text*
- *radio*
- *bool*
- *checkbox*
- *select*
- *csrf*



### prefix



        $prefix=false,          // prefix to be added to the form field, useful for CSRF and subforms
        $id=false,              // field ID, usually automatically created from key index
        $alias,                 // supports bind from the model side
        $attributes=array(),    // element attributes, usually class names and data-*
        $placeholder=false,     // placeholder text
        $scope=false,           // scope to be used in references and sub forms
        $label=false,           // label, if not set will be build from $name
        $choices=false,         // for select, checkbox and radio types, the acceptable options (method or callback)
        $choicesFilter,         // filter for the choices, usually based on another property
        $serialize,             // if the contents should be serialized, and by which serialization method
        $tooltip=false,         // additional tooltips to be shown on focus
        $renderer=false,        // use another renderer instead of the template, accepts callbacks
        $error=false,           // field errors
        $filters=false,         // filters this field choices based on another field's value
        $dataprop,              //
        $class='',              // container class names (attribute value, use spaces for multiple classes)
        $template=false,        // custom template, otherwise, guess from $type
        $rules=false,           // validation rules, regular expression => message
        $_className,            // class name
        $multiple=false,        // for select and checkboxes, if accepts multiple values
        $required=false,        // if this field is mandatory (raises errors)
        $html_labels=false,     // if true, labels and other template contents won't be escaped
        $messages=null,         //
        $disabled=false,        // should updates be disabled?
        $readonly=false,        // makes this readonly
        $size=false,            // size, in bytes, for the contents of this field, for numeric types use $range
        $min_size=false,        // minimum size, in bytes, for the contents of this field, for numeric types use $range
        $value,                 // value of the field
        $range=false,           // range valudation rules = array($min, $max)
        $decimal=0,             // decimal values accepted
        $accept=false,          // content types accepted, used for file uploads
        $toAdd=null,            // for subforms
        $insert=true,
        $update=true,
        $before=false,          // content to be displayed before the field
        $fieldset=false,        // fieldset label this field belongs to
        $after=false,           // content to be displayed after the field
        $next,                  // tab order (use field name)
        $default,               // default field value
        $query;