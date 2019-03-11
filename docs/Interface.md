<!--
---
title: Interface
...
-->
# Interface

Tecnodesign framework provides a simple way of developing interfaces that are based on the models and its relations.

It's easier to create a custom *`InterfaceClass`* for each project, that extends `Tecnodesign_Interface` and customize its behaviors and add new features. 


Each interface instance is defined by a YAML file at `config:tecnodesign:data-dir`. Suppose we have a `country.yml` file with this information:

```
---
title: Countries
model: CountryModel
credential: [ Administrator ]
options:
  scope: string
  list: [ country, region ]
  search: [ country, region ]
actions:
  create: false
  delete: false
  company:
    title: Companies
    relation: CompanyModel

```

So, if you want to create a basic CRUD-like interface for managing the countries, simply add this content to your page:

```
echo InterfaceClass::run('country'); 
```

All the definitions will be loaded from the model and interface configuration file. If it's the above example, you'd have the default actions available, with the `create` and `delete` actions disabled and a new interface for managing the related `CompanyModel`.

If you want to load all of your eligible interfaces, you may instead simply run:

```
echo InterfaceClass::run();
```

And it would display a list of interfaces to be loaded.

### URL patterns

Suppose you create an administrative interface at `http://example.com/admin` and load all interfaces in it. As the interface and its properties are loaded, the URL will be filled with patterns:

    /[interface1]/[interface1 action]/[interface1 id]/[interface2]/[interface2 action]...

So you could update the country **Brazil (br)** at: `http://example.com/admin/country/update/br`, and list all companies within this country at: `http://example.com/admin/country/preview/br/company/list`.

### Interface instance properties (YAML)

By default, the `Interface` component will try to build everything based on the model schema. You cn override and control specifically what should be done at the interface by updating its `.yml` file. 

|  Property  |           Type          |                                                      Description                                                      |
|------------|-------------------------|-----------------------------------------------------------------------------------------------------------------------|
| title      | *(optional)* string     | Title to be used for interface listings. If empty or not set, will default to the model label.                        |
| model      | string                  | Class name of the model. Should be a `Tecnodesign_Model` instance.                                                    |
| credential | *(optional)* array/bool | Credentials needed to access this interface. Might also be `true` for authenticated users.                            |
| options    | *(optional)* array      | Additional interface options. Here you can override the model schema scope and add form options.                      |
| actions    | *(optional)* array      | Each action configuration or relation description (described below). Defaults to `InterfaceClass::$actionsAvailable`. |

Each action defined might be a new action specific for this interface, a relation definition (that will trigger a sub interface) or customization options for the existing interfaces (like setting credentials for a specific action). 

### Interface action

Existing actions and new actions can be customized to add new functionalities and applications to the interface:

| Property   | Type                    | Description                                                                                                               |
|------------|----------------------   |---------------------------------------------------------------------------------------------------------------------------|
| position   | *(optional)* integer    | Action order in relation to other actions. If the same position occurs, the actions will be ordered by occurrence.        |
| action     | string                  | Method to be invoked at the model or interface instance before the action is rendered.                                    |
| identified | *(optional)* bool       | If this action requires that the instance has an `id` to be invoked.                                                      |
| batch      | *(optional)* bool       | If this action can be applied to more than one record at once.                                                            |
| renderer   | *(optional)* string     | Method to be invoked at the model or interface instance to render the action. Might also be a template name to be called. |
| credential | *(optional)* array/bool | Credentials needed to access this action. If not set, uses the interface instance credentials.                            |
