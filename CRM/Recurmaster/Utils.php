<?php

class CRM_Recurmaster_Utils {

  /**
   * Return the field ID for $fieldName custom field
   *
   * @param $fieldName
   * @param bool $fullString
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function getField($fieldName, $fullString = TRUE) {
    if (!isset(Civi::$statics[__CLASS__][$fieldName])) {
      $field = civicrm_api3('CustomField', 'get', array(
        'name' => $fieldName,
      ));

      if (!empty($field['id'])) {
        Civi::$statics[__CLASS__][$fieldName]['id'] = $field['id'];
        Civi::$statics[__CLASS__][$fieldName]['string'] = 'custom_' . $field['id'];
      }
    }

    if ($fullString) {
      return Civi::$statics[__CLASS__][$fieldName]['string'];
    }
    return Civi::$statics[__CLASS__][$fieldName]['id'];
  }

  /**
   * Return the field ID for master_recur_id custom field
   *
   * @param bool $fullString If TRUE, return custom_XX instead of XX
   *
   * @return null|string
   * @throws \CiviCRM_API3_Exception
   */
  public static function getMasterRecurIdCustomField($fullString = TRUE) {
    return self::getField('master_recur_id', $fullString);
  }

}