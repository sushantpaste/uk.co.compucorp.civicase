<?php

abstract class CRM_Civicase_Form_Report_BaseExtendedReport extends CRM_Civicase_Form_Report_ExtendedReport {

  protected $aggregateDateFields;

  protected $dateSqlGrouping = [
    'month' => "%Y-%m",
    'year' => "%Y"
  ];

  protected $dataFunctions = [
    'COUNT' => 'COUNT',
    'COUNT UNIQUE' => 'COUNT UNIQUE',
    'SUM' => 'SUM'
  ];

  protected $dateGroupingOptions = ['month' => 'Month', 'year' => 'Year'];


  public function __construct() {
    parent::__construct();
    $this->addResultsTab();
  }

  /**
   * Add the results tab to the tabs list.
   */
  protected function addResultsTab() {
    $this->tabs['Results'] = [
      'title' => ts('Results'),
      'tpl' => 'Results',
      'div_label' => 'set-results',
    ];
  }

  /**
   * Function that allows additional filter fields provided by extending class to be added to the
   * where clause for the report.
   */
  abstract protected function addAdditionalFiltersToWhereClause();

  /**
   * Returns additional filter fields provided by extending report class.
   *
   * @return array
   */
  abstract protected function getAdditionalFilterFields();


  /**
   * This function provides the template name to use for the filter fields. Overriding
   * this will allow extending class to provide its own default filter template in case
   * it needs to provide additional filter fields.
   * q
   * @return string
   */
  protected function getFiltersTemplateName() {
    return 'Filters';
  }

  /**
   *  Add the fields to select the aggregate fields to the report.
   *
   * This function is overridden because of a bug that does not allow the custom fields to
   * appear in the Filters tab in the base class.
   */
  protected function addAggregateSelectorsToForm() {
    if (!$this->isPivot) {
      return;
    }
    $aggregateColumnHeaderFields = $this->getAggregateColumnFields();
    $aggregateRowHeaderFields = $this->getAggregateRowFields();

    foreach ($this->_customGroupExtended as $key => $groupSpec) {
      $customDAOs = $this->getCustomDataDAOs($groupSpec['extends']);
      foreach ($customDAOs as $customField) {
        $tableKey = $customField['prefix'] . $customField['table_name'];
        $prefix = $customField['prefix'];
        $fieldName = 'custom_' . ($prefix ? $prefix . '_' : '') . $customField['id'];
        $this->addCustomTableToColumns($customField, $customField['table_name'], $prefix, $customField['prefix_label'], $tableKey);
        $this->_columns[$tableKey]['metadata'][$fieldName] = $this->getCustomFieldMetadata($customField, $customField['prefix_label']);
        if (!empty($groupSpec['filters'])) {
          $this->_columns[$tableKey]['metadata'][$fieldName]['is_filters'] = TRUE;
          $this->_columns[$tableKey]['metadata'][$fieldName]['extends_table'] = $this->_columns[$tableKey]['extends_table'];
          $this->_columns[$tableKey]['filters'][$fieldName] = $this->_columns[$tableKey]['metadata'][$fieldName];
        }
        $this->metaData['metadata'][$fieldName] = $this->_columns[$tableKey]['metadata'][$fieldName];
        $this->metaData['metadata'][$fieldName]['is_aggregate_columns'] = TRUE;
        $this->metaData['metadata'][$fieldName]['table_alias'] = $this->_columns[$tableKey]['alias'];
        $this->metaData['aggregate_columns'][$fieldName] = $this->metaData['metadata'][$fieldName];
        $this->metaData['filters'][$fieldName] = $this->metaData['metadata'][$fieldName];
        $customFieldTitle = $customField['prefix_label'] . $customField['title'] . ' - ' . $customField['label'];
        $aggregateRowHeaderFields[$fieldName] = $customFieldTitle;
        if (in_array($customField['html_type'], ['Select', 'CheckBox'])) {
          $aggregateColumnHeaderFields[$fieldName] = $customFieldTitle;
        }
      }

    }

    $this->addSelect(
      'aggregate_column_headers',
      [
        'entity' => '',
        'option_url' => NULL,
        'label' => ts('Aggregate Report Column Headers'),
        'options' => $aggregateColumnHeaderFields,
        'id' => 'aggregate_column_headers',
        'placeholder' => ts('- select -'),
        'class' => 'huge',
      ],
      FALSE
    );
    $this->addSelect(
      'aggregate_row_headers',
      [
        'entity' => '',
        'option_url' => NULL,
        'label' => ts('Row Fields'),
        'options' => $aggregateRowHeaderFields,
        'id' => 'aggregate_row_headers',
        'placeholder' => ts('- select -'),
        'class' => 'huge',
      ],
      FALSE
    );

    $this->addSelect(
      'aggregate_column_date_grouping',
      [
        'entity' => '',
        'option_url' => NULL,
        'label' => ts('Date Grouping'),
        'options' => $this->dateGroupingOptions,
        'id' => 'aggregate_column_date_grouping',
        'placeholder' => ts('- select -'),
      ],
      TRUE
    );

    $this->addSelect(
      'aggregate_row_date_grouping',
      [
        'entity' => '',
        'option_url' => NULL,
        'label' => ts('Date Grouping'),
        'options' => $this->dateGroupingOptions,
        'id' => 'aggregate_row_date_grouping',
        'placeholder' => ts('- select -'),
      ],
      FALSE
    );

    $this->addSelect(
      'data_function_field',
      [
        'entity' => '',
        'option_url' => NULL,
        'label' => ts('Data Function Fields'),
        'options' => $aggregateRowHeaderFields,
        'id' => 'data_function_fields',
        'placeholder' => ts('- select -'),
        'class' => 'huge',
      ],
      TRUE
    );

    $this->addSelect(
      'data_function',
      [
        'entity' => '',
        'option_url' => NULL,
        'label' => ts('Data Functions'),
        'options' => $this->dataFunctions,
        'id' => 'data_functions',
        'placeholder' => ts('- select -'),
        'class' => 'huge',
      ],
      TRUE
    );
    $this->add('hidden', 'charts');
    $this->_columns[$this->_baseTable]['fields']['include_null'] = [
      'title' => 'Show column for unknown',
      'pseudofield' => TRUE,
      'default' => TRUE,
    ];
    $this->tabs['Aggregate'] = [
      'title' => ts('Pivot table'),
      'tpl' => 'Aggregates',
      'div_label' => 'set-aggregates',
    ];

    $this->assign('aggregateDateFields', json_encode(array_flip($this->aggregateDateFields)));
    $this->assignTabs();
  }

  /**
   * This function is overridden because of a bug that selects wrong data for custom fields
   * extending an entity when there are multiple instances of the Entity in columns.
   * For example, there are more than one Contact Entity columns, for Case client contact, and also
   * Case roles contacts, the custom field value for the other Contact custom fields is selected
   * wrongly because the db alias of the first Contact entity is used in all case. This is fixed
   * by using the table key to form the alias rather than the original table name which is same for
   * all Contact entity data.
   *
   * @param string $field
   * @param string $prefixLabel
   * @param string $prefix
   *
   * @return mixed
   */
  protected function getCustomFieldMetadata($field, $prefixLabel, $prefix = '') {
    $field = array_merge($field, [
      'name' => $field['column_name'],
      'title' => $prefixLabel . $field['label'],
      'dataType' => $field['data_type'],
      'htmlType' => $field['html_type'],
      'operatorType' => $this->getOperatorType($this->getFieldType($field), [], []),
      'is_fields' => TRUE,
      'is_filters' => TRUE,
      'is_group_bys' => FALSE,
      'is_order_bys' => FALSE,
      'is_join_filters' => FALSE,
      'type' => $this->getFieldType($field),
      'dbAlias' => $prefix . $field['table_key'] . '.' . $field['column_name'],
      'alias' => $prefix . $field['table_name'] . '_' . 'custom_' . $field['id'],
    ]);
    $field['is_aggregate_columns'] = in_array($field['html_type'], ['Select', 'Radio']);

    if (!empty($field['option_group_id'])) {
      if (in_array($field['html_type'], [
        'Multi-Select',
        'AdvMulti-Select',
        'CheckBox',
      ])) {
        $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT_SEPARATOR;
      }
      else {
        $field['operatorType'] = CRM_Report_Form::OP_MULTISELECT;
      }

      $ogDAO = CRM_Core_DAO::executeQuery("SELECT ov.value, ov.label FROM civicrm_option_value ov WHERE ov.option_group_id = %1 ORDER BY ov.weight", [
        1 => [$field['option_group_id'], 'Integer'],
      ]);
      while ($ogDAO->fetch()) {
        $field['options'][$ogDAO->value] = $ogDAO->label;
      }
    }

    if ($field['type'] === CRM_Utils_Type::T_BOOLEAN) {
      $field['options'] = [
        '' => ts('- select -'),
        1 => ts('Yes'),
        0 => ts('No'),
      ];
    }
    return $field;
  }

  /**
   * This function is overridden because there is an issue with the naming for the
   * custom group panel labels on the filter section in the UI. The group title for the
   * custom groups can not be passed in when defining the fields hence the need to override
   * this function.
   *
   * @param string $field
   * @param string $currentTable
   * @param string $prefix
   * @param string $prefixLabel
   * @param string $tableKey
   */
  protected function addCustomTableToColumns($field, $currentTable, $prefix, $prefixLabel, $tableKey) {
    $entity = $field['extends'];
    if (in_array($entity, ['Individual', 'Organization', 'Household'])) {
      $entity = 'Contact';
    }
    if (!isset($this->_columns[$tableKey])) {
      $this->_columns[$tableKey]['extends'] = $field['extends'];
      $this->_columns[$tableKey]['grouping'] = $prefix . $field['table_name'];
      $this->_columns[$tableKey]['group_title'] = $field['table_label'];
      $this->_columns[$tableKey]['name'] = $field['table_name'];
      $this->_columns[$tableKey]['fields'] = [];
      $this->_columns[$tableKey]['filters'] = [];
      $this->_columns[$tableKey]['join_filters'] = [];
      $this->_columns[$tableKey]['group_bys'] = [];
      $this->_columns[$tableKey]['order_bys'] = [];
      $this->_columns[$tableKey]['aggregates'] = [];
      $this->_columns[$tableKey]['prefix_label'] = $field['prefix_label'];
      $this->_columns[$tableKey]['prefix'] = $prefix;
      $this->_columns[$tableKey]['table_name'] = $currentTable;
      $this->_columns[$tableKey]['alias'] = $prefix . $currentTable;
      $this->_columns[$tableKey]['extends_table'] = $prefix . CRM_Core_DAO_AllCoreTables::getTableForClass(CRM_Core_DAO_AllCoreTables::getFullName($entity));
    }
  }

  /**
   * This function is overridden because of custom JOINs for the
   * Case activity pivot report that are not available in base class.
   *
   * @return array
   */
  public function getAvailableJoins() {
    $availableJoins = parent::getAvailableJoins();

    $joins = [
      'relationship_from_case' => [
        'callback' => 'joinRelationshipFromCase',
      ],
      'case_role_contact' => [
        'callback' => 'joinCaseRolesContact',
      ]
    ];

    return array_merge($availableJoins, $joins);
  }

  /**
   * Function  overridden to allow NULL values in the results rows to show as 'NULL'
   * rather than as an empty string.
   *
   * @param array $rows
   */
  public function alterRollupRows(&$rows) {
    if (count($rows) === 1) {
      // If the report only returns one row there is no rollup.
      return;
    }
    array_walk($rows, [$this, 'replaceNullRowValues']);
    $groupBys = array_reverse(array_fill_keys(array_keys($this->_groupByArray), NULL));
    $firstRow = reset($rows);
    foreach ($groupBys as $field => $groupBy) {
      $fieldKey = isset($firstRow[$field]) ? $field : str_replace([
        '_YEAR',
        '_MONTH',
      ], '_start', $field);
      if (isset($firstRow[$fieldKey])) {
        unset($groupBys[$field]);
        $groupBys[$fieldKey] = $firstRow[$fieldKey];
      }
    }
    $groupByLabels = array_keys($groupBys);

    $altered = [];
    $fieldsToUnSetForSubtotalLines = [];
    //on this first round we'll get a list of keys that are not groupbys or stats
    foreach (array_keys($firstRow) as $rowField) {
      if (!array_key_exists($rowField, $groupBys) && substr($rowField, -4) != '_sum' && !substr($rowField, -7) != '_count') {
        $fieldsToUnSetForSubtotalLines[] = $rowField;
      }
    }

    $statLayers = count($this->_groupByArray);

    if (count($this->_statFields) == 0) {
      return;
    }

    foreach (array_keys($rows) as $rowNumber) {
      $nextRow = CRM_Utils_Array::value($rowNumber + 1, $rows);
      if ($nextRow === NULL && empty($this->rollupRow)) {
        $this->updateRollupRow($rows[$rowNumber], $fieldsToUnSetForSubtotalLines);
      }
      else {
        $this->alterRowForRollup($rows[$rowNumber], $nextRow, $groupBys, $rowNumber, $statLayers, $groupByLabels, $altered, $fieldsToUnSetForSubtotalLines);
      }
    }
  }

  /**
   * Overridden to allow the alterRollupRows function use this function since the
   * original function in base class is private and the `alterRollupRows` won't work
   * without this.
   *
   * @param array $row
   * @param array $nextRow
   * @param array $groupBys
   * @param mixed $rowNumber
   * @param mixed $statLayers
   *
   * @param mixed $groupByLabels
   * @param mixed $altered
   * @param mixed $fieldsToUnSetForSubtotalLines
   *
   * @return mixed
   */
  private function alterRowForRollup(&$row, $nextRow, &$groupBys, $rowNumber, $statLayers, $groupByLabels, $altered, $fieldsToUnSetForSubtotalLines) {
    foreach ($groupBys as $field => $groupBy) {
      if (($rowNumber + 1) < $statLayers) {
        continue;
      }
      if (empty($row[$field]) && empty($row['is_rollup'])) {
        $valueIndex = array_search($field, $groupBys) + 1;
        if (!isset($groupByLabels[$valueIndex])) {
          return;
        }
        $groupedValue = $groupByLabels[$valueIndex];
        if (!($nextRow) || $nextRow[$groupedValue] != $row[$groupedValue]) {
          $altered[$rowNumber] = TRUE;
          $this->updateRollupRow($row, $fieldsToUnSetForSubtotalLines);
        }
      }
      $groupBys[$field] = $row[$field];
    }
  }

  /**
   * Replace NULL row values with the 'NULL' keyword
   */
  private function replaceNullRowValues(&$row, $key) {
    foreach ($row as $field => $value) {
      if (is_null($value)) {
        $row[$field] = 'NULL';
      }
    }
  }

  /**
   * Add Select for pivot chart style report
   *
   * @param string $fieldName
   * @param string $dbAlias
   * @param array $spec
   *
   * @throws Exception
   */
  function addColumnAggregateSelect($fieldName, $dbAlias, $spec) {
    if (empty($fieldName)) {
      $this->addAggregateTotal($fieldName);
      return;
    }
    $spec['dbAlias'] = $dbAlias;
    $options = $this->getCustomFieldOptions($spec);

    if (!empty($this->_params[$fieldName . '_value']) && CRM_Utils_Array::value($fieldName . '_op', $this->_params) == 'in') {
      $options['values'] = array_intersect_key($options, array_flip($this->_params[$fieldName . '_value']));
    }

    $filterSpec = [
      'field' => ['name' => $fieldName],
      'table' => ['alias' => $spec['table_name']],
    ];

    if ($this->getFilterFieldValue($spec)) {
      // for now we will literally just handle IN
      if ($filterSpec['field']['op'] == 'in') {
        $options = array_intersect_key($options, array_flip($filterSpec['field']['value']));
        $this->_aggregatesIncludeNULL = FALSE;
      }
    }

    $aggregates = [];
    foreach ($options as $optionValue => $optionLabel) {
      $fieldAlias = str_replace([
        '-',
        '+',
        '\/',
        '/',
        ')',
        '(',
      ], '_', "{$fieldName}_" . strtolower(str_replace(' ', '', $optionValue)));

      $selectSql = $this->getColumnSqlAggregateExpression($spec, $dbAlias, $fieldAlias, $optionValue, $optionLabel);
      $aggregateExpression = rtrim($selectSql , "AS {$fieldAlias} ");
      $aggregateExpression = ltrim($aggregateExpression, " , ");

      $aggregates[] =  $aggregateExpression;
      $this->_select .= $selectSql ;
      $this->_columnHeaders[$fieldAlias] = [
        'title' => !empty($optionLabel) ? $optionLabel : 'NULL',
        'type' => CRM_Utils_Type::T_INT,
      ];
      $this->_statFields[] = $fieldAlias;
    }

    if ($this->_aggregatesAddTotal) {
      $this->addAggregateTotalField($fieldName, $aggregates);
    }
  }

  /**
   * Returns the SQL aggregate expression for a selected column field. The overral expression will depend on
   * the data aggregate function used, the field to aggregate on (if applicable).
   *
   * @param array $spec
   * @param string $dbAlias
   * @param string $fieldAlias
   * @param mixed $optionValue
   *
   * @return string
   */
  private function getColumnSqlAggregateExpression($spec, $dbAlias, $fieldAlias, $optionValue, $optionLabel) {
    $dataFunction = $this->_params['data_function'];
    $field = $dbAlias;
    $value = $optionValue;
    $operator = '=';

    if (!empty($spec['htmlType']) && in_array($spec['htmlType'], ['CheckBox', 'MultiSelect'])){
      $value = "'%" . CRM_Core_DAO::VALUE_SEPARATOR . $optionValue . CRM_Core_DAO::VALUE_SEPARATOR . "%'";
      $operator = 'LIKE';
    }

    if (!empty($spec['html']['type']) && $spec['html']['type'] == 'Select Date') {
      $dateGrouping = $this->_params['aggregate_column_date_grouping'];
      $field = "DATE_FORMAT({$dbAlias}, '{$this->dateSqlGrouping[$dateGrouping]}')";
    }

    if (is_null($optionLabel)) {
      $operator = 'IS NULL';
      $value = '';
    }

    if ($dataFunction === 'COUNT') {
      return $this->getSqlAggregateForCount($field, $value, $operator, $fieldAlias);
    }

    if ($dataFunction === 'COUNT UNIQUE') {
      return $this->getSqlAggregateForCountUnique($field, $value, $operator, $fieldAlias);
    }

    if ($dataFunction === 'SUM') {
      return $this->getSqlAggregateForSum($field, $value, $operator, $fieldAlias);
    }
  }

  /**
   * Returns the SQL expression for COUNT aggregate
   *
   * @param string $field
   * @param mixed $value
   * @param string $operator
   * @param string $fieldAlias
   *
   * @return string
   */
  protected function getSqlAggregateForCount($field, $value, $operator, $fieldAlias) {
    $value = !empty($value) ? "'{$value}'" : '';
    return " , SUM( CASE WHEN {$field} {$operator} $value THEN 1 ELSE 0 END ) AS $fieldAlias ";
  }

  /**
   * Returns the SQL expression for COUNT UNIQUE aggregate
   *
   * @param string $field
   * @param mixed $value
   * @param string $operator
   * @param string $fieldAlias
   *
   * @return string
   */
  protected function getSqlAggregateForCountUnique($field, $value, $operator, $fieldAlias) {
    $value = !empty($value) ? "'{$value}'" : '';
    $dataFunctionFieldAlias = $this->getDbAliasForAggregateOnField();

    return " , COUNT( DISTINCT CASE WHEN {$field} {$operator} $value THEN {$dataFunctionFieldAlias} END ) AS $fieldAlias ";
  }

  /**
   * Returns the SQL expression for SUM aggregate
   *
   * @param string $field
   * @param mixed $value
   * @param string $operator
   * @param string $fieldAlias
   *
   * @return string
   */
  protected function getSqlAggregateForSum($field, $value, $operator, $fieldAlias) {
    $value = !empty($value) ? "'{$value}'" : '';
    $dataFunctionFieldAlias = $this->getDbAliasForAggregateOnField();

    return  " , SUM( CASE WHEN {$field} {$operator} $value THEN {$dataFunctionFieldAlias} ELSE 0 END ) AS $fieldAlias ";
  }

  /**
   * Returns the db Alias for the field on which to aggregate on.
   *
   * @return string
   */
  private function getDbAliasForAggregateOnField() {
    $dataFunctionField = $this->_params['data_function_field'];
    $specs = $this->getMetadataByType('metadata')[$dataFunctionField];

    return $specs['dbAlias'];
  }

  /**
   *  This function is overridden because we need to extend the functionality by providing a
   * function to fetch options when a date field is selected as a column header field.
   *
   * @param array $spec
   *
   * @return array
   */
  protected function getCustomFieldOptions($spec) {
    $options = [];
    if (!empty($spec['options'])) {
      return $spec['options'];
    }

    if ($spec['type'] == CRM_Report_Form::OP_DATE) {
      return $this->getDateColumnOptions($spec);
    }

    // Data type is set for custom fields but not core fields.
    if (CRM_Utils_Array::value('data_type', $spec) == 'Boolean') {
      $options = [
        'values' => [
          0 => ['label' => 'No', 'value' => 0],
          1 => ['label' => 'Yes', 'value' => 1],
        ],
      ];
    }
    elseif (!empty($spec['options'])) {
      foreach ($spec['options'] as $option => $label) {
        $options['values'][$option] = [
          'label' => $label,
          'value' => $option,
        ];
      }
    }
    else {
      if (empty($spec['option_group_id'])) {
        throw new Exception('currently column headers need to be radio or select');
      }
      $options = civicrm_api('option_value', 'get', [
        'version' => 3,
        'options' => ['limit' => 50,],
        'option_group_id' => $spec['option_group_id'],
      ]);
    }

    return $options['values'];
  }

  /**
   * Returns options for a date field when selected as a column header.
   *
   * @param array $spec
   *
   * @return array
   */
  public function getDateColumnOptions($spec) {
    $this->from();
    $this->where();
    $dateGrouping = $this->_params['aggregate_column_date_grouping'];
    $select = "SELECT DISTINCT DATE_FORMAT({$spec['dbAlias']}, '{$this->dateSqlGrouping[$dateGrouping]}') as date_grouping";
    $sql = "{$select} {$this->_from} {$this->_where} ORDER BY date_grouping ASC";
    if (!$this->_rollup) {
      $sql .= $this->_limit;
    }

    $result = CRM_Core_DAO::executeQuery($sql);
    $options = [];
    while ($result->fetch()) {
      $options[$result->date_grouping] = $result->date_grouping;
    }

    return $options;
  }

  /**
   * Adds the SQl expression for the total aggregate for the column fields for each row in the
   * result set.
   *
   * @param string $fieldName
   * @param array $aggregates
   */
  protected function addAggregateTotalField($fieldName, $aggregates) {
    $fieldAlias = "{$fieldName}_total";
    $sumOfAggregates =  implode(' + ', $aggregates);
    $this->_select .= ', ' . "{$sumOfAggregates} as {$fieldAlias}";
    $this->_columnHeaders[$fieldAlias] = [
      'title' => ts('Total'),
      'type' => CRM_Utils_Type::T_INT,
    ];

    $this->_statFields[] = $fieldAlias;
  }

  /**
   * This function is overridden to allow date fields to be part of fields to be selected in the
   * column header fields which is not possible in the original function in base class.
   *
   * @param array $specs
   * @param string $tableName
   * @param string|null $daoName
   * @param string|null $tableAlias
   * @param array $defaults
   * @param array $options
   *
   * @return array
   */
  protected function buildColumns($specs, $tableName, $daoName = NULL, $tableAlias = NULL, $defaults = [], $options = []) {

    if (!$tableAlias) {
      $tableAlias = str_replace('civicrm_', '', $tableName);
    }
    $types = ['filters', 'group_bys', 'order_bys', 'join_filters', 'aggregate_columns', 'aggregate_rows'];
    $columns = [$tableName => array_fill_keys($types, [])];
    if (!empty($daoName)) {
      $columns[$tableName]['bao'] = $daoName;
    }
    $columns[$tableName]['alias'] = $tableAlias;
    $exportableFields = $this->getMetadataForFields(['dao' => $daoName]);

    foreach ($specs as $specName => $spec) {
      $spec['table_key'] = $tableName;
      unset($spec['default']);
      if (empty($spec['name'])) {
        $spec['name'] = $specName;
      }
      if (empty($spec['dbAlias'])) {
        $spec['dbAlias'] = $tableAlias . '.' . $spec['name'];
      }
      $daoSpec = CRM_Utils_Array::value($spec['name'], $exportableFields, CRM_Utils_Array::value($tableAlias . '_' . $spec['name'], $exportableFields, []));
      $spec = array_merge($daoSpec, $spec);
      if (!isset($columns[$tableName]['table_name']) && isset($spec['table_name'])) {
        $columns[$tableName]['table_name'] = $spec['table_name'];
      }

      if (!isset($spec['operatorType'])) {
        $spec['operatorType'] = $this->getOperatorType($spec['type'], $spec);
      }
      foreach (array_merge($types, ['fields']) as $type) {
        if (isset($options[$type]) && !empty($spec['is_' . $type])) {
          // Options can change TRUE to FALSE for a field, but not vice versa.
          $spec['is_' . $type] = $options[$type];
        }
        if (!isset($spec['is_' . $type]))    {
          $spec['is_' . $type] = FALSE;
        }
      }

      $fieldAlias = (empty($options['no_field_disambiguation']) ? $tableAlias . '_' : '') . $specName;
      $spec['alias'] = $tableName . '_' . $fieldAlias;
      if ($this->isPivot && (!empty($spec['options']) || $spec['operatorType'] == CRM_Report_Form::OP_DATE)) {
        $spec['is_aggregate_columns'] = TRUE;
        $spec['is_aggregate_rows'] = TRUE;

        if ($spec['operatorType'] == CRM_Report_Form::OP_DATE) {
          $this->aggregateDateFields[] = $fieldAlias;
        }
      }
      $columns[$tableName]['metadata'][$fieldAlias] = $spec;
      $columns[$tableName]['fields'][$fieldAlias] = $spec;
      if (isset($defaults['fields_defaults']) && in_array($spec['name'], $defaults['fields_defaults'])) {
        $columns[$tableName]['metadata'][$fieldAlias]['is_fields_default'] = TRUE;
      }

      if (empty($spec['is_fields']) || (isset($options['fields_excluded']) && in_array($specName, $options['fields_excluded']))) {
        $columns[$tableName]['fields'][$fieldAlias]['no_display'] = TRUE;
      }

      if (!empty($spec['is_filters']) && !empty($spec['statistics']) && !empty($options) && !empty($options['group_by'])) {
        foreach ($spec['statistics'] as $statisticName => $statisticLabel) {
          $columns[$tableName]['filters'][$fieldAlias . '_' . $statisticName] = array_merge($spec, [
            'title' => ts('Aggregate filter : ') . $statisticLabel,
            'having' => TRUE,
            'dbAlias' => $tableName . '_' . $fieldAlias . '_' . $statisticName,
            'selectAlias' => "{$statisticName}({$tableAlias}.{$spec['name']})",
            'is_fields' => FALSE,
            'is_aggregate_field_for' => $fieldAlias,
          ]);
          $columns[$tableName]['metadata'][$fieldAlias . '_' . $statisticName] = $columns[$tableName]['filters'][$fieldAlias . '_' . $statisticName];
        }
      }

      foreach ($types as $type) {
        if (!empty($spec['is_' . $type])) {
          if ($type === 'join_filters') {
            $fieldAlias = 'join__' . $fieldAlias;
          }
          $columns[$tableName][$type][$fieldAlias] = $spec;
          if (isset($defaults[$type . '_defaults']) && isset($defaults[$type . '_defaults'][$spec['name']])) {
            $columns[$tableName]['metadata'][$fieldAlias]['default'] = $defaults[$type . '_defaults'][$spec['name']];
          }
        }
      }
    }
    $columns[$tableName]['prefix'] = isset($options['prefix']) ? $options['prefix'] : '';
    $columns[$tableName]['prefix_label'] = isset($options['prefix_label']) ? $options['prefix_label'] : '';
    if (isset($options['group_title'])) {
      $groupTitle = $options['group_title'];
    }
    else {
      // We can make one up but it won't be translated....
      $groupTitle = ucfirst(str_replace('_', ' ', str_replace('civicrm_', '', $tableName)));
    }
    $columns[$tableName]['group_title'] = $groupTitle;

    return $columns;
  }

  /**
   * Function is overrridden to allow row total to be re-calculated since the
   * SQL WITH ROLLUP Group function does not yield reliable results for the row totals based
   * on new Data aggregate functions introduced.
   *
   * @param array $rows
   * @param bool $pager
   */
  public function formatDisplay(&$rows, $pager = TRUE) {
    // set pager based on if any limit was applied in the query.
    if ($pager) {
      $this->setPager();
    }

    // unset columns not to be displayed.
    foreach ($this->_columnHeaders as $key => $value) {
      if (!empty($value['no_display'])) {
        unset($this->_columnHeaders[$key]);
      }
    }

    // unset columns not to be displayed.
    if (!empty($rows)) {
      foreach ($this->_noDisplay as $noDisplayField) {
        foreach ($rows as $rowNum => $row) {
          unset($this->_columnHeaders[$noDisplayField]);
        }
      }
    }

    // build array of section totals
    $this->sectionTotals();

    //adjust row total
    $this->adjustRowTotal($rows);

    // process grand-total row
    $this->grandTotal($rows);

    // use this method for formatting rows for display purpose.
    $this->alterDisplay($rows);
    CRM_Utils_Hook::alterReportVar('rows', $rows, $this);

    // use this method for formatting custom rows for display purpose.
    $this->alterCustomDataDisplay($rows);
  }

  /**
   * Since we have introduced other data aggregate functions like COUNT UNIQUE, SUM,
   * the SQL WITH ROLLUP Group function does not yield reliable results for the row totals.
   * This function sums the individual column totals and adjusts the total accordingly.
   *
   * @param array $rows
   */
  private function adjustRowTotal(&$rows) {
    if (empty($rows)) {
      return;
    }
    //the rollup row is the last row.
    end($rows);
    $rollupRowKey = key($rows);
    reset($rows);
    $rollupRow = $rows[$rollupRowKey];
    unset($rows[$rollupRowKey]);
    $adjustedRollup = [];
    foreach ($rollupRow as $key => $value) {
      $adjustedRollup[$key] =  array_sum(array_column($rows, $key));
    }

    $rows[$rollupRowKey] = $adjustedRollup;
  }

  /**
   * Overriden so we can add some more default values.
   *
   * @param bool $freeze
   *
   * @return array
   */
  public function setDefaultValues($freeze = TRUE) {
    parent::setDefaultValues();
    $this->_defaults['data_function'] = 'COUNT';
    $this->_defaults['aggregate_column_date_grouping'] = 'month';
    $suffix = $this->_aliases[$this->_baseTable] == 'civicrm_contact' ? '_contact_id' : '_id';
    $this->_defaults['data_function_field'] = $this->_aliases[$this->_baseTable] . $suffix;
    $this->_defaults['charts'] = TRUE;

    return $this->_defaults;
  }

  /**
   * Overridden so that when custom fields are selected to be aggregated on,
   * the SQL joins for the custom field table will be included in the overral query.
   *
   * @param string $table
   *
   * @return bool
   */
  protected function isCustomTableSelected($table) {
    $selected = array_merge(
      $this->getSelectedFilters(),
      $this->getSelectedFields(),
      $this->getSelectedOrderBys(),
      $this->getSelectedAggregateRows(),
      $this->getSelectedDataFunctionField(),
      $this->getSelectedAggregateColumns(),
      $this->getSelectedGroupBys()
    );
    foreach ($selected as $spec) {
      if ($spec['table_name'] == $table) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns the metadata for the selected data function field.
   *
   * @return array
   */
  protected function getSelectedDataFunctionField() {
    $metadata = $this->getMetadataByType('metadata');
    if (empty($this->_params['data_function_field']) || !isset($metadata[$this->_params['data_function_field']])) {
      return [];
    }

    return [$this->_params['data_function_field'] => $metadata[$this->_params['data_function_field']]];
  }

  /**
   * Overridden so that the template file name is gotten from the extended report class within
   * Civicase.
   *
   * @return string
   */
  public function getTemplateFileName() {
    $defaultTpl = parent::getTemplateFileName();

    if (in_array($this->_outputMode, ['print', 'pdf'])) {
      if ($this->_params['templates']) {
        $defaultTpl = 'CRM/Civicase/Form/Report/CustomTemplates/' . $this->_params['templates'] . '.tpl';
      }
    }

    if (!CRM_Utils_File::isIncludable('templates/' . $defaultTpl)) {
      $defaultTpl = 'CRM/Report/Form.tpl';
    }

    return $defaultTpl;
  }

  /**
   * Overridden to allow date row date fields to be grouped on month/year.
   *
   * @param string $tableAlias
   * @param array $selectedField
   * @param string $fieldAlias
   * @param string $title
   */
  protected function addRowHeader($tableAlias, $selectedField, $fieldAlias, $title = '') {
    if (empty($tableAlias)) {
      $this->_select = 'SELECT 1 '; // add a fake value just to save lots of code to calculate whether a comma is required later
      $this->_rollup = NULL;
      $this->_noGroupBY = TRUE;
      return;
    }

    $this->_select = "SELECT {$selectedField['dbAlias']} as $fieldAlias ";
    if (!empty($selectedField['html']['type']) && $selectedField['html']['type'] == 'Select Date') {
      $dateGrouping = $this->_params['aggregate_row_date_grouping'];
      if (!empty($dateGrouping)) {
        $this->_select = "SELECT DATE_FORMAT({$selectedField['dbAlias']}, '{$this->dateSqlGrouping[$dateGrouping]}') as $fieldAlias";
      }
    }

    if (!in_array($fieldAlias, $this->_groupByArray)) {
      $this->_groupByArray[] = $fieldAlias;
    }
    $this->_groupBy = "GROUP BY $fieldAlias " . $this->_rollup;
    $this->_columnHeaders[$fieldAlias] = ['title' => $title,];
    $key = array_search($fieldAlias, $this->_noDisplay);
    if (is_int($key)) {
      unset($this->_noDisplay[$key]);
    }
  }

  /**
   * This function is overridden so that we can a report class can define additional extra filters
   * and modify the where clause.
   */
  public function storeWhereHavingClauseArray() {
    $filters = $this->getSelectedFilters();
    foreach ($filters as $filterName => $field) {
      if (!empty($field['pseudofield'])) {
        continue;
      }
      $clause = NULL;
      $clause = $this->generateFilterClause($field, $filterName);
      if (!empty($clause)) {
        $this->whereClauses[$filterName] = $clause;
        if (CRM_Utils_Array::value('having', $field)) {
          $this->_havingClauses[$filterName] = $clause;
        }
        else {
          $this->_whereClauses[] = $clause;
        }
      }
    }

    $this->addAdditionalFiltersToWhereClause();
  }

  /**
   * This function is overridden so as to allow the extending report class to provide the
   * filters template to use for the filters.
   *
   * Also overridden to allow fields extending contacts, i.e custom fields and contact fields
   * to be sorted into a separate array so that when more than one contact entity is joined to
   * the report, the filter fields can be organized and displayed per contact entity.
   */
  public function addFilters() {
    foreach (['filters', 'join_filters'] as $filterString) {
      $filters = $filterGroups = [];
      $filterExtendsContactGroup = [];
      $filtersGroupedByTableKeys = [];
      $count = 1;
      foreach ($this->getMetadataByType($filterString) as $fieldName => $field) {
        $table = $field['table_name'];
        $filterExtendsContact = FALSE;
        if ($filterString === 'filters') {
          $filterExtendsContact = (!empty($field['extends']) && in_array($field['extends'], ['Individual', 'Household', 'Organization'])) ||
            $field['table_name'] == 'civicrm_contact';
          $filterGroups[$table] = [
            'group_title' => $this->_columns[$field['table_key']]['group_title'],
            'use_accordian_for_field_selection' => TRUE,
            'group_extends_contact' => $filterExtendsContact
          ];

          if ($filterExtendsContact) {
            $filterExtendsContactGroup[$field['table_key']] = [
              'group_field_label' => !empty($this->_columns[$field['table_key']]['prefix_label']) ?
                $this->_columns[$field['table_key']]['prefix_label'] : '',
            ];
          }
        }
        $prefix = ($filterString === 'join_filters') ? 'join_filter_' : '';
        $filters[$table][$prefix . $fieldName] = $field;
        if ($filterExtendsContact) {
          $filtersGroupedByTableKeys[$table][$field['table_key']][$prefix . $fieldName] = $field;
        }

        $this->addFilterFieldsToReport($field, $fieldName, $table, $count, $prefix);
      }

      if (!empty($filters) && $filterString == 'filters') {
        $this->tabs['Filters'] = [
          'title' => ts('Filters'),
          'tpl' => $this->getFiltersTemplateName(),
          'div_label' => 'set-filters',
        ];
        $this->assign('filterGroups', $filterGroups);
        $this->assign('filterExtendsContactGroup', $filterExtendsContactGroup);
        $this->assign('filtersGroupedByTableSets', $filtersGroupedByTableKeys);
      }
      $this->assign($filterString, $filters);
    }
  }

  /**
   * This function is overridden so that the additional filters provided by
   * report class extending this class will be part of the statistics filter
   * array and the label and values will be visible on the report UI.
   *
   * Also data function and data aggregate field are added to the groups statistics array.
   *
   * @return array
   */
  public function statistics(&$rows) {
    $stats = parent::statistics($rows);

    foreach ($this->getAdditionalFilterFields() as $key => $value) {
      if (!empty($this->_params[$key])) {
        $stats['filters'][] = [
          'title' => $value['label'],
          'value' => 'is equal to ' . $this->_params[$key]
        ];
      }
    }

    $stats['groups'][] = [
      'title' => 'Aggregate Function',
      'value' => $this->_params['data_function']
    ];

    if ($this->_params['data_function'] !== 'COUNT') {
      $stats['groups'][] = [
        'title' => 'Aggregate Field On',
        'value' => $this->getTitleForAggregateOnField()
      ];
    }

    return $stats;
  }

  /**
   * Returns the field title for the aggregate on field
   *
   * @return string
   */
  private function getTitleForAggregateOnField() {
    $dataFunctionField = $this->_params['data_function_field'];
    $specs = $this->getMetadataByType('metadata')[$dataFunctionField];

    return $specs['title'];
  }
}
