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
  public static function getCustomByName($fieldName, $fullString = TRUE) {
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
    return self::getCustomByName('master_recur_id', $fullString);
  }

  /**
   * Output log messsages
   *
   * @param $logMessage
   * @param $debug
   */
  public static function log($logMessage, $debug) {
    if (!$debug) {
      Civi::log()->info($logMessage);
    }
    elseif ($debug && (CRM_Recurmaster_Settings::getValue('debug'))) {
      Civi::log()->debug($logMessage);
    }
  }

  /**
   * Return true if dateOlder < dateNewer
   *
   * @param $dateOlder
   * @param $dateNewer
   *
   * @return bool
   */
  public static function dateLessThan($dateOlder, $dateNewer) {
    $date1DT = new DateTime($dateOlder);
    $date2DT = new DateTime($dateNewer);
    if ($date1DT < $date2DT) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Return true if date1 == date2. If $time=FALSE, ignore time difference
   *
   * @param $date1
   * @param $date2
   * @param bool $time
   *
   * @return bool
   */
  public static function dateEquals($date1, $date2, $time = FALSE) {
    $date1DT = new DateTime($date1);
    $date2DT = new DateTime($date2);
    if (!$time) {
      $date1DT->setTime(0, 0, 0);
      $date2DT->setTime(0, 0, 0);
    }

    if ($date1DT == $date2DT) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Validate parameters passed in by hooks, as they can vary especially with custom fields
   *
   * @param $params
   *
   * @return mixed
   */
  public static function validateHookParams($params) {
    foreach ($params as $key => $value) {
      // Custom fields may be named custom_X_X if loaded straight from form
      if (substr($key, 0, 7) === 'custom_') {
        $customParts = explode('_', $key);
        if (count($customParts) > 1) {
          $params[$customParts[0] . '_' . $customParts[1]] = $value;
        }
      }
    }
    return $params;
  }
}