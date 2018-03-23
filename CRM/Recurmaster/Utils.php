<?php

class CRM_Recurmaster_Utils {

  /**
   * Return the field ID for master_recur_id custom field
   *
   * @param bool $fullString If TRUE, return custom_XX instead of XX
   *
   * @return null|string
   * @throws \CiviCRM_API3_Exception
   */
  public static function getMasterRecurIdCustomField($fullString = TRUE) {
    return CRM_Mjwshared_Fields::getCustomByName('master_recur_id', $fullString);
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

}