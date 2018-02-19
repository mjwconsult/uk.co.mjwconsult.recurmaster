<?php
/**
 * Created by PhpStorm.
 * User: matthew
 * Date: 05/02/18
 * Time: 16:52
 */

class CRM_Recurmaster_Master {

  public static function update($recurIds = array()) {
    if (!empty($recurIds) && is_array($recurIds)) {
      // Generate list of recur Ids
      $contributionRecurParams = array(
        'id' => array('IN' => $recurIds),
        'options' => array('limit' => 0),
      );
      $contributionRecurs = civicrm_api3('ContributionRecur', 'get', $contributionRecurParams);
    }
    else {
      $contributionRecurs = self::getMasterRecurringContributions();
    }

    if (empty($contributionRecurs['values'])) {
      Civi::log()->info('CRM_Recurmaster_Master: No master recurring contributions for processing');
      return array();
    }

    $recurs = array();
    foreach ($contributionRecurs['values'] as $id => $contributionRecur) {
      $linkedRecurs = self::getLinkedRecurring($id);
      $amount = 0;
      foreach ($linkedRecurs as $lId => $lDetail) {
        if (self::takeLinkedPayment($lDetail, $contributionRecur)) {
          $amount += $lDetail['amount'];
        }
      }
      $contributionRecur['amount'] = $amount;
      Civi::log()->debug('CRM_Recurmaster_Master::update: Calculated amount for R' . $contributionRecur['id'] . ' is ' . $contributionRecur['amount']);
      $recurResult = civicrm_api3('ContributionRecur', 'create', $contributionRecur);
      $recurs = CRM_Utils_Array::value('values', $recurResult);
    }
    return $recurs;
  }

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

  public static function takeLinkedPayment($lDetail, $mDetail) {
    // Does currency match?
    if ($lDetail['currency'] !== $mDetail['currency']) {
      Civi::log()->debug('CRM_Recurmaster_Master: Currency does not match (' . $lDetail['id'] . '/' . $mDetail['id']);
      return FALSE;
    }
    // Does frequency match?
    // TODO: Support non-matching frequencies
    return TRUE;
  }

  public static function isMasterRecur($recurId) {
    // Get recurring contributions by contact Id where payment processor is in list of master recurring contributions
    $paymentProcessorTypes = explode(',', CRM_Recurmaster_Settings::getValue('paymentprocessortypes'));
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
      $contributionRecurRecord = civicrm_api3('ContributionRecur', 'getsingle', $recurParams);
    }
    catch (Exception $e) {
      return FALSE;
    }
    return TRUE;
  }

  public static function getMasterRecurringContributions($contactId = NULL) {
    // Get recurring contributions by contact Id where payment processor is in list of master recurring contributions
    $paymentProcessorTypes = explode(',', CRM_Recurmaster_Settings::getValue('paymentprocessortypes'));
    $paymentProcessors = civicrm_api3('PaymentProcessor', 'get', array(
      'return' => array("id"),
      'payment_processor_type_id' => array('IN' => $paymentProcessorTypes),
    ));

    $paymentProcessorIds = array_keys($paymentProcessors['values']);

    $recurParams = array(
      'payment_processor_id' => array('IN' => $paymentProcessorIds),
      'options' => array('limit' => 0),
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
   * @param $contactID
   * @return mixed
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
      $contributionRecurRecords = self::getMasterRecurringContributions($contactId);
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