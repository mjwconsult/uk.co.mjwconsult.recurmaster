<?php
/**
 * Created by PhpStorm.
 * User: matthew
 * Date: 05/02/18
 * Time: 16:52
 */

class CRM_Recurmaster_Master {

  /**
   * Update the master recurring contributions and update scheduled dates for all linked recurring contributions
   *
   * @param array $recurIds
   *
   * @return array|mixed
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public static function update($recurIds = array()) {
    if (!empty($recurIds) && is_array($recurIds)) {
      // Generate list of recur Ids
      $contributionRecurParams = array(
        'id' => array('IN' => $recurIds),
        'options' => array('limit' => 0),
      );
      $contributionRecurs = civicrm_api3('ContributionRecur', 'get', $contributionRecurParams);
      $contributionRecurs = CRM_Utils_Array::value('values', $contributionRecurs);
    }
    else {
      $contributionRecurs = self::getMasterRecurringContributionsbyContact();
    }

    if (empty($contributionRecurs)) {
      Civi::log()->info('CRM_Recurmaster_Master: No master recurring contributions for processing');
      return array();
    }

    $recurs = array();
    foreach ($contributionRecurs as $id => $contributionRecur) {
      $originalContributionRecur = $contributionRecur;
      $linkedRecurs = self::getLinkedRecurring($id);
      $amount = 0;
      foreach ($linkedRecurs as $lId => &$lDetail) {
        $lDetail = self::setNextPaymentDate($lDetail, $contributionRecur);
        if (self::takeLinkedPayment($lDetail, $contributionRecur)) {
          $amount += $lDetail['amount'];
        }
      }
      $contributionRecur['amount'] = $amount;
      if ($contributionRecur != $originalContributionRecur) {
        Civi::log()->debug('CRM_Recurmaster_Master::update: Calculated amount for R' . $contributionRecur['id'] . ' is ' . $contributionRecur['amount']);
        $recurResult = civicrm_api3('ContributionRecur', 'create', $contributionRecur);

        $paymentProcessor = Civi\Payment\System::singleton()->getById($contributionRecur['payment_processor_id']);
        if ($paymentProcessor->supports('EditRecurringContribution')) {
          $message = '';
          $updateSubscription = $paymentProcessor->changeSubscriptionAmount($message, $contributionRecur);
        }
        if (is_a($updateSubscription, 'CRM_Core_Error')) {
          CRM_Core_Error::displaySessionError($updateSubscription);
          $status = ts('Could not update the Recurring contribution details');
          $msgTitle = ts('Update Error');
          $msgType = 'error';
        }
        $recurs = CRM_Utils_Array::value('values', $recurResult);
      }
    }
    return $recurs;
  }

  /**
   * Return an array of linked recurring contribution details
   *
   * @param $masterId
   *
   * @return array|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function getLinkedRecurring($masterId) {
    $contributionRecurParams = array(
      CRM_Recurmaster_Utils::getMasterRecurIdCustomField(TRUE) => $masterId,
      'options' => array('limit' => 0),
    );

    $contributionRecurs = civicrm_api3('ContributionRecur', 'get', $contributionRecurParams);

    if (!empty($contributionRecurs['values'])) {
      return $contributionRecurs['values'];
    }
    else {
      return NULL;
    }
  }

  /**
   * Determine whether the linked payment should be taken or not.
   * Check that the currency is the same and the next scheduled date matches
   *
   * @param array $lDetail Linked recurring contribution details
   * @param array $mDetail Master recurring contribution details
   *
   * @return bool
   */
  private static function takeLinkedPayment($lDetail, $mDetail) {
    // Does currency match?
    if ($lDetail['currency'] !== $mDetail['currency']) {
      Civi::log()->debug('CRM_Recurmaster_Master: Currency does not match (' . $lDetail['id'] . '/' . $mDetail['id']);
      return FALSE;
    }

    // We don't care about the time, only the date:
    $linkedDate = new DateTime($lDetail['next_sched_contribution_date']);
    $masterDate = new DateTime($mDetail['next_sched_contribution_date']);
    $linkedDate->setTime(0,0,0);
    $masterDate->setTime(0,0,0);
    // Do the next scheduled contribution dates match for linked and master?
    if ($linkedDate == $masterDate) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Updates the next_sched_contribution date for the linked recurring contribution
   *  based on frequency.  Note the master must have next_sched_contribution_date set.
   *
   * @param $lDetail
   * @param $mDetail
   *
   * @return bool|array FALSE or updated $lDetail array with next_sched_date
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  private static function setNextPaymentDate($lDetail, $mDetail) {
    if (empty($mDetail['next_sched_contribution_date'])) {
      Civi::log()->error('recurmaster: Cannot use as master if next_sched_contribution_date is not set R' . $mDetail['id']);
      return FALSE;
    }

    // No scheduled contribution date, so we calculate one as follows:
    // 1. Get next date for master (subtract lead time).
    // 2. If frequency matches, set next date = master next date
    // 3. If frequency doesn't match, calculate next date

    // 1. Get next available date for master
    $nextMasterScheduledDateString = $mDetail['next_sched_contribution_date'];
    // TODO: Modify this date to include a lead time
    $nextMasterScheduledDate = new DateTime($nextMasterScheduledDateString);

    // Does frequency match?
    if (($lDetail['frequency_unit'] === $mDetail['frequency_unit'])
        && ($lDetail['frequency_interval'] === $mDetail['frequency_interval'])) {
      // Everything matches - easy! Take a linked payment every time we take a master payment.
      return self::updateNextScheduledDate($lDetail, $nextMasterScheduledDateString);
    }
    elseif (($lDetail['frequency_unit'] === 'month')
            && ($mDetail['frequency_unit'] === $lDetail['frequency_unit'])
            && ($lDetail['frequency_interval'] > $mDetail['frequency_interval'])) {
      // We are only taking linked payment once every frequency_interval months(s)
      $lastPaymentDateString = self::getLastPaymentDate($lDetail);
      if (!$lastPaymentDateString) {
        // First payment, we'll take it as soon as possible.
        return self::updateNextScheduledDate($lDetail, $nextMasterScheduledDateString);
      }
      else {
        // We need to calculate the next payment date based on last payment date and frequency
        $lastPaymentDate = new DateTime($lastPaymentDateString);
        // This returns a positive value if lastPaymentDate is earlier than nextMasterScheduledDate
        $interval = $lastPaymentDate->diff($nextMasterScheduledDate);

        $months = $interval->format('m');
        if ($months >= $lDetail['frequency_interval']) {
          // We've waited long enough, time to take another payment
          return self::updateNextScheduledDate($lDetail, $nextMasterScheduledDateString);
        }
        if (($months > 0) && ($months < $lDetail['frequency_interval'])) {
          // Calculate and update the next scheduled date
          $nextScheduledDate = clone($nextMasterScheduledDate);
          $nextScheduledDate->modify(new DateInterval('p' . $months . 'm'));
          return self::updateNextScheduledDate($lDetail, $nextScheduledDate->format('Y-m-d H:i:s'));
        }
      }
    }
    elseif (($lDetail['frequency_unit'] === 'year') && ($mDetail['frequency_unit'] === 'month')) {
      // We are only taking linked payment once every frequency_interval year(s)
      $lastPaymentDateString = self::getLastPaymentDate($lDetail);
      if (!$lastPaymentDateString) {
        // First payment, we'll take it as soon as possible.
        return self::updateNextScheduledDate($lDetail, $nextMasterScheduledDateString);
      }
      else {
        // We need to calculate the next payment date based on last payment date and frequency
        $lastPaymentDate = new DateTime($lastPaymentDateString);
        // This returns a positive value if lastPaymentDate is earlier than nextMasterScheduledDate
        $interval = $lastPaymentDate->diff($nextMasterScheduledDate);

        $years = $interval->format('y');
        if ($years >= $lDetail['frequency_interval']) {
          // We've waited long enough, time to take another payment
          return self::updateNextScheduledDate($lDetail, $nextMasterScheduledDateString);
        }
        if (($years > 0) && ($years < $lDetail['frequency_interval'])) {
          // Calculate and update the next scheduled date
          $nextScheduledDate = clone($nextMasterScheduledDate);
          $nextScheduledDate->modify(new DateInterval('p' . $years . 'm'));
          return self::updateNextScheduledDate($lDetail, $nextScheduledDate->format('Y-m-d H:i:s'));
        }
      }
    }
    elseif (($lDetail['frequency_unit'] === 'month') && ($mDetail['frequency_unit'] === 'year')) {
      Civi::log()->error('recurmaster: Unsupported frequency for linked payments. Linked: ' . print_r($lDetail,TRUE) . ' Master: ' . print_r($mDetail, TRUE));
      return FALSE;
    }
  }

  /**
   * Updates the next_sched_contribution_date for a recurring contribution via API
   * @param $recur
   * @param $nextScheduledDateString
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private static function updateNextScheduledDate($recur, $nextScheduledDateString) {
    $recur['next_sched_contribution_date'] = $nextScheduledDateString;
    // Set the next scheduled date
    civicrm_api3('ContributionRecur', 'create', $recur);
    return $recur;
  }

  /** Get the date of the last payment for the linked recurring contribution
   *
   * @param array $lDetail Linked recurring details
   * @return string|bool Last payment date as string or FALSE

   * @throws \CiviCRM_API3_Exception
   */
  private static function getLastPaymentDate($lDetail) {
    // Do we have a completed contribution?  Get the latest one if we do.
    $contributionResult = civicrm_api3('Contribution', 'get', array(
      'contribution_recur_id' => $lDetail['id'],
      'contribution_status_id' => "Completed",
      'options' => array('limit' => 1, 'sort' => "contribution_id DESC"),
    ));
    if (isset($contributionResult['id'])) {
      $lastPaymentDateString = $contributionResult['values'][$contributionResult['id']]['receive_date'];
      return $lastPaymentDateString;
    }

    // Otherwise, no payments have been taken, so we'll need to take this payment at the next available date.
    return FALSE;

  }

  /**
   * Is this recurring contribution a master?
   * Yes, if it is one of the payment processor types listed in settings
   *
   * @param $recurId
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isMasterRecurByRecurId($recurId) {
    // Get recurring contributions by contact Id where payment processor is in list of master recurring contributions
    $paymentProcessorTypes = (string) CRM_Recurmaster_Settings::getValue('paymentprocessortypes');
    if (empty($paymentProcessorTypes)) {
      return FALSE;
    }
    $paymentProcessorTypes = explode(',', $paymentProcessorTypes);
    $paymentProcessors = civicrm_api3('PaymentProcessor', 'get', array(
      'return' => array("id"),
      'payment_processor_type_id' => array('IN' => $paymentProcessorTypes),
    ));

    $paymentProcessorIds = array_keys($paymentProcessors['values']);

    $recurParams = array(
      'payment_processor_id' => array('IN' => $paymentProcessorIds),
      'id' => $recurId,
    );
    try {
      civicrm_api3('ContributionRecur', 'getsingle', $recurParams);
    }
    catch (Exception $e) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Is this recurring contribution a master?
   * Yes, if it is one of the payment processor types listed in settings
   *
   * @param $paymentProcessorId
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isMasterRecurByPaymentProcessorId($paymentProcessorId) {
    // Get recurring contributions by contact Id where payment processor is in list of master recurring contributions
    $paymentProcessorTypes = (string) CRM_Recurmaster_Settings::getValue('paymentprocessortypes');
    if (empty($paymentProcessorTypes)) {
      return FALSE;
    }
    $paymentProcessorTypes = explode(',', $paymentProcessorTypes);
    $paymentProcessors = civicrm_api3('PaymentProcessor', 'get', array(
      'return' => array("id"),
      'payment_processor_type_id' => array('IN' => $paymentProcessorTypes),
    ));

    if (array_key_exists($paymentProcessorId, $paymentProcessors['values'])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get recurring contributions by contact Id where payment processor is in list of master recurring contributions
   *
   * @param null $contactId
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function getMasterRecurringContributionsbyContact($contactId = NULL) {
    $paymentProcessorTypes = CRM_Recurmaster_Settings::getValue('paymentprocessortypes');
    if (empty($paymentProcessorTypes)) {
      Civi::log()->warning('Recurmaster - no master payment processors - is recurmaster configured?');
      return [];
    }
    $paymentProcessorTypes = explode(',', $paymentProcessorTypes);
      $paymentProcessors = civicrm_api3('PaymentProcessor', 'get', array(
      'return' => array("id"),
      'payment_processor_type_id' => array('IN' => $paymentProcessorTypes),
    ));

    $paymentProcessorIds = array_keys($paymentProcessors['values']);

    $recurParams = array(
      'payment_processor_id' => array('IN' => $paymentProcessorIds),
      'options' => array('limit' => 0),
      'contribution_status_id' => array('IN' => array("In Progress", "Pending")),
    );
    if (!empty($contactId)) {
      $recurParams['contact_id'] = $contactId;
    }
    $contributionRecurRecords = civicrm_api3('ContributionRecur', 'get', $recurParams);
    if (empty($contributionRecurRecords['values'])) {
      return array();
    }
    return $contributionRecurRecords['values'];
  }

  /**
   * Get list of recurring contribution records for contact
   *
   * @param $contactId
   * @param null $recurId
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContactMasterRecurringContributionList($contactId, $recurId = NULL) {
    if ($recurId) {
      // Retrieve a specific recurring contribution
      $contributionRecurRecords = civicrm_api3('ContributionRecur', 'get', array(
        'id' => $recurId,
        'contact_id' => $contactId,
      ));
      if (!empty($contributionRecurRecords['values'])) {
        $contributionRecurRecords = $contributionRecurRecords['values'];
      }
    }
    else {
      $contributionRecurRecords = self::getMasterRecurringContributionsbyContact($contactId);
    }

    $cRecur = array();
    foreach ($contributionRecurRecords as $contributionRecur) {
      // Get payment processor name used for recurring contribution
      try {
        if (empty($contributionRecur['payment_processor_id'])) {
          $paymentProcessorName = 'Offline';
        }
        else {
          $processor = \Civi\Payment\System::singleton()
            ->getById($contributionRecur['payment_processor_id']);
          $processorDetails = $processor->getPaymentProcessor();
          $paymentProcessorName = $processorDetails['name'];
        }
      }
      catch (CiviCRM_API3_Exception $e) {
        // Invalid payment processor, ignore this recur record
        continue;
      }
      $contributionStatus = CRM_Core_PseudoConstant::getLabel('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $contributionRecur['contribution_status_id']);
      // Create display name for recurring contribution
      $cRecur[$contributionRecur['id']] = $paymentProcessorName . '/'
        . $contributionStatus . '/'
        . CRM_Utils_Money::format($contributionRecur['amount'],$contributionRecur['currency'])
        . '/every ' . $contributionRecur['frequency_interval'] . ' ' . $contributionRecur['frequency_unit']
        . '/' . CRM_Utils_Array::value('trxn_id', $contributionRecur);
    }
    if ($recurId) {
      return reset($cRecur);
    }
    return $cRecur;
  }

}
