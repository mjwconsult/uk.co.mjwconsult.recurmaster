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
    $className = self::getPaymentClassName($recurContributionParams);
    if ($className) {
      $className::checkSubscription($recurContributionParams);
    }
  }

  /**
   * Retrieve the next possible collection date for the payment processor
   *
   * @param array $recurContributionParams
   *
   * @return bool|string Next collection date formatted as Y-m-d (eg. 2018-02-02)
   * @throws \CiviCRM_API3_Exception
   */
  public static function getNextCollectionDate($recurContributionParams) {
    $className = self::getPaymentClassName($recurContributionParams);
    if ($className) {
      return $className::getNextCollectionDate($recurContributionParams);
    }
    return FALSE;
  }

  /**
   * Return the recurmaster paymentprocessor classname (eg. CRM_Recurmaster_Payment_Smartdebit)
   * @param $recurContributionParams
   *
   * @return bool|string
   * @throws \CiviCRM_API3_Exception
   */
  private static function getPaymentClassName($recurContributionParams) {
    if (empty($recurContributionParams['payment_processor_type_id'])) {
      if (empty($recurContributionParams['payment_processor_id'])) {
        CRM_Recurmaster_Utils::log(__FUNCTION__ . ' missing payment_processor_type_id/payment_processor_id', TRUE);
        return FALSE;
      }
      else {
        $recurContributionParams['payment_processor_type_id'] = civicrm_api3('PaymentProcessor', 'getvalue', [
          'return' => "payment_processor_type_id",
          'id' => $recurContributionParams['payment_processor_id'],
        ]);
      }
    }

    $paymentProcessorTypeParams = [
      'id' => $recurContributionParams['payment_processor_type_id'],
      'return' => array('class_name'),
    ];
    $paymentProcessorType = civicrm_api3('PaymentProcessorType', 'getsingle', $paymentProcessorTypeParams);

    $className = 'CRM_Recurmaster_' . CRM_Utils_Array::value('class_name', $paymentProcessorType);
    if (!class_exists($className)) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' $className not implemented!', FALSE);
      return FALSE;
    }
    return $className;
  }

}
