<?php

return [
  'civicaseAllowCaseLocks' => [
    'group_name' => 'CiviCRM Preferences',
    'group' => 'core',
    'name' => 'civicaseAllowCaseLocks',
    'type' => 'Boolean',
    'quick_form_type' => 'YesNo',
    'default' => FALSE,
    'html_type' => 'radio',
    'add' => '4.7',
    'title' => 'Allow cases to be locked',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'This will allow cases to be locked for certain contacts.',
    'help_text' => '',
  ],
  'civicaseAllowCaseWebform' => [
    'group_name' => 'CiviCRM Preferences',
    'group' => 'core',
    'name' => 'civicaseAllowCaseWebform',
    'type' => 'Boolean',
    'quick_form_type' => 'YesNo',
    'default' => FALSE,
    'html_type' => 'radio',
    'add' => '4.7',
    'title' => 'Trigger webform on Add Case',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'This setting allows the user to set a webform to be triggered when clicking the `Add Case` button on the Cases tab on the Contact.',
    'help_text' => '',
  ],
  'civicaseWebformUrl' => [
    'group_name' => 'CiviCRM Preferences',
    'group' => 'core',
    'name' => 'civicaseWebformUrl',
    'type' => 'String',
    'quick_form_type' => 'Element',
    'html_attributes' => [
      'size' => 64,
      'maxlength' => 64,
    ],
    'html_type' => 'text',
    'default' => '',
    'add' => '4.7',
    'title' => ' URL of the Webform',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => '',
    'help_text' => '',
  ],
];
