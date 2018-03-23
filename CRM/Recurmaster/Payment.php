<?php

/**
 * Class CRM_Recurmaster_Payment
 *
 * Parent class that allows implementation of multiple payment processor handlers
 *
 */
class CRM_Recurmaster_Payment {

  /**
   * Check subscription for smartdebit
   * This function updates amounts and payment date at smartdebit based on conditions
   *
   * @param $recurContributionParams
   * @param $paymentDate
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public static function checkSubscription(&$recurContributionParams) {
    if (empty($recurContributionParams['payment_processor_type_id'])) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' missing payment_processor_type_id', TRUE);
      return;
    }

    $paymentProcessorTypeParams = array(
      'id' => $recurContributionParams['payment_processor_type_id'],
      'return' => array('class_name'),
    );
    $paymentProcessorType = civicrm_api3('PaymentProcessorType', 'getsingle', $paymentProcessorTypeParams);

    $className = 'CRM_Recurmaster_' . CRM_Utils_Array::value('class_name', $paymentProcessorType);
    if (!class_exists($className)) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' $className not implemented!', FALSE);
      return;
    }
    $className::checkSubscription($recurContributionParams);
  }

}
