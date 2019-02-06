/*
title: Form (Tecnodesign_Form)
*/
# Form (Tecnodesign_Form)

Validate and display forms.

## Creating a form

```php
$form = new Tecnodesign_Form([
    'method' => 'post',
    'fields' => [
        'field_name' => [
            'label' => 'Field Name',
            'type' => 'text',
            'value' => 'default value', // optional
        ],
    ],
]);
```

### Form configuration options

The options below are available for configuration

* `id` Register an update form? *(optional)*
* `buttons` Buttons available on form. 
If false, it will show no buttons. 
If is a string it will be used as label for the submit button.
If is an array it will be appended to the default submit button as submit buttons.
To change the `type` an `class` use the format `['button' => 'Genereic Button', 'reset' => 'Reset Buttton']`
 *(optional, default:`['submit' => '*Send']`)*
* `action` Action to be used *(optional, default: the current URL)*
* `method` Method to be used *(optional, default: `post`)*
* `model` 
* `fields` Array of fields to be used
* `attributes` 

#### Field configuration options

Each field in the form configuration can have the following options

* `credential` Credential to give access to the field

**See Tecnodesign_Form_Field for more options** 

## Validating a form

```php
if ( ($post = Tecnodesign_App::request('post')) && $form->validate($post)) {
    // process the $post
}
```
