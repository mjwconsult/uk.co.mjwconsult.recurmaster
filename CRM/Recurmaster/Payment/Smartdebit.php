<?php

class CRM_Recurmaster_Payment_Smartdebit extends CRM_Recurmaster_Payment {

  /**
   * Allow a different amount (eg. pro-rata amount) to be passed as first amount, but set regular amount to be
   *   amount defined for that membership type.
   * Call via hook_civicrm_smartdebit_alterVariableDDIParams(&$params, &$smartDebitParams)
   *
   * @param $smartDebitParams
   * @param $defaultAmount
   */
  public static function alterDefaultPaymentAmount(&$smartDebitParams, $defaultAmount) {
    if (CRM_Recurmaster_Settings::getValue('dryrun')) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' alterDefaultPaymentAmount: dryrun defaultAmount=' . $defaultAmount, FALSE);
      return;
    }

    $smartDebitParams['variable_ddi[default_amount]'] = $defaultAmount;
  }

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
    if (empty($recurContributionParams['trxn_id'])) {
      // We must have a reference_number to do anything.
      return;
    }

    // Get mandate details
    $smartDebitMandate = civicrm_api3('Smartdebit', 'getmandates', $recurContributionParams);
    if ($smartDebitMandate['count'] !== 1) {
      return;
    }
    $smartDebitParams = $smartDebitMandate['values'][$smartDebitMandate['id']];

    // Only update Live/New direct debits
    if (($smartDebitParams['current_state'] != CRM_Smartdebit_Api::SD_STATE_NEW) && ($smartDebitParams['current_state'] != CRM_Smartdebit_Api::SD_STATE_LIVE)) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . 'checkSubscription: Not updating ' . $recurContributionParams['trxn_id'] . ' because it is not live', TRUE);
      return;
    }

    $updateAmounts = self::checkPaymentAmounts($recurContributionParams, $smartDebitParams);

    // If anything has changed, trigger an update of the subscription.
    if ($updateAmounts) {
      self::updateSubscription($recurContributionParams, NULL);
    }
  }

  /**
   * Check if we need to call updateSubscription to update payment amounts.
   *  Note: We don't actually make changes here as calculations are done again when alterVariableDDIParams is called.
   *
   * @param $recurContributionParams
   * @param $smartDebitParams
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function checkPaymentAmounts($recurContributionParams, $smartDebitParams) {
    // Get the regular payment amount
    $defaultAmount = $smartDebitParams['default_amount'];
    if ($defaultAmount === NULL) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' checkPaymentAmounts: No defaultAmount calculated.', TRUE);
      return FALSE;
    }
    // Is the default_amount already matching what we calculated?
    if ($smartDebitParams['default_amount'] == $defaultAmount) {
      // No need to update subscription as the regular payment amount is already correct.
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' checkPaymentAmounts: Not updating ' . $recurContributionParams['trxn_id'] . ' as default_amount already matches.', TRUE);
      return FALSE;
    }

    // We don't set smartDebitParams['default_amount'] here as it's called again by alterVariableDDIParams where it actually gets set.
    CRM_Recurmaster_Utils::log(__FUNCTION__ . ' checkPaymentAmounts: UPDATE R' . $recurContributionParams['id'] . ': default_amount old=' .$smartDebitParams['default_amount'] . ' new=' . $defaultAmount, FALSE);
    return TRUE;
  }

  /**
   * Update the Smartdebit Subscription
   *
   * @param $recurContributionParams
   * @param null $startDate
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public static function updateSubscription($recurContributionParams, $startDate = NULL) {
    if (empty($recurContributionParams['payment_processor_id'])) {
      Civi::log()->error(__FUNCTION__ . ' updateSubscription: called without payment_processor_id');
      return;
    }
    $paymentProcessorObj = Civi\Payment\System::singleton()->getById($recurContributionParams['payment_processor_id']);

    if (CRM_Recurmaster_Settings::getValue('dryrun')) {
      $message = '';
      if (!empty($startDate)) {
        $message = 'startDate=' . $startDate . '; ';
      }
      $message .= 'recurParams=' . print_r($recurContributionParams, TRUE);

      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' updateSubscription: dryrun ' . $message, FALSE);
      return;
    }
    CRM_Core_Payment_Smartdebit::changeSubscription($paymentProcessorObj->getPaymentProcessor(), $recurContributionParams, $startDate);
  }

  /**
   * This function gets the next possible collection date
   *
   * @param array $recurContributionParams
   *
   * @return string Date formatted as Y-m-d (eg. 2018-10-20)
   * @throws \Exception
   */
  public static function getNextCollectionDate($recurContributionParams) {
    $collectionDateTime = CRM_Smartdebit_DateUtils::getNextAvailableCollectionDate();
    return $collectionDateTime->format('Y-m-d');
  }
}
