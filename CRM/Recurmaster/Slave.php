<?php

class CRM_Recurmaster_Slave {

  /**
   * Is this recurring contribution of type "Slave"?
   * @param $recurId
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isSlaveRecur($recurId) {
    try {
      $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', [
        'id' => $recurId,
      ]);
    }
    catch (Exception $e) {
      return FALSE;
    }

    $paymentProcessor = \Civi\Payment\System::singleton()->getById($contributionRecur['payment_processor_id']);
    if ($paymentProcessor->getPaymentProcessor()['class_name'] == 'Payment_RecurmasterSlave') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Update all slave recurring contributions and contributions linked to the master
   * Create/Edit slave contributions as required to match the master
   * We assume that the master actually took the full amount
   *
   * @param $masterRecurId
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function updateAllByMasterContribution($masterContributionDetails) {
    $linkedRecurs = CRM_Recurmaster_Master::getLinkedRecurring($masterContributionDetails['contribution_recur_id']);
    if (!$linkedRecurs) {
      return;
    }
    foreach ($linkedRecurs as $linkedRecurDetails) {
      self::update($linkedRecurDetails, $masterContributionDetails);
    }
  }

  /**
   * Create/update the contributions for the slave recurring contribution
   * Based on whether:
   * - contribution date matches slaveRecur next_sched_contribution_date
   *
   * We then update the contribution
   *99
   * @param array $slaveRecurDetails ContributionRecur
   * @param array $masterContributionDetails Contribution
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function update($slaveRecurDetails, $masterContributionDetails = NULL) {
    CRM_Recurmaster_Utils::log(__FUNCTION__ . ' updating recur ' . $slaveRecurDetails['id'], TRUE);

    if (!$masterContributionDetails) {
      $masterRecurId = $slaveRecurDetails[CRM_Recurmaster_Utils::getMasterRecurIdCustomField()];
      if (empty($masterRecurId)) {
        CRM_Recurmaster_Utils::log(__FUNCTION__ . ' No master recur ID', TRUE);
        return;
      }
      $masterContributionParams = [
        'contribution_recur_id' => $masterRecurId,
        'options' => ['limit' => 1, 'sort' => "id DESC"],
      ];
      $masterContributionDetails = civicrm_api3('Contribution', 'getsingle', $masterContributionParams);
    }

    $masterContributionDT = new DateTime($masterContributionDetails['receive_date']);
    // If slave next_sched_contribution_date is not set then it's new and we haven't processed it yet.
    $slaveRecurNextScheduledDT = new DateTime(CRM_Utils_Array::value('next_sched_contribution_date', $slaveRecurDetails, $masterContributionDetails['receive_date']));

    if ($masterContributionDT->format('Ymd') !== $slaveRecurNextScheduledDT->format('Ymd')) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' Master contribution date does not match slave next scheduled date SR=' . $slaveRecurDetails['id'], TRUE);
      return;
    }

    // Got a match so check if we have a slave contribution already for this date?
    $existingSlaveContributionParams = array(
      'receive_date' => array('LIKE' => $slaveRecurNextScheduledDT->format('Y-m-d')),
      'options' => array('limit' => 1),
    );
    $existingContributionResult = civicrm_api3('Contribution', 'get', $existingSlaveContributionParams);
    if (!empty($existingContributionResult['id'])) {
      $slaveContributionParams['id'] = $existingContributionResult['id'];
    }

    // Prepare slave contribution params
    $sharedContributionParams = array(
      'contact_id',
      'currency',
      'payment_instrument_id',
      'receive_date',
      'total_amount',
      'contribution_status_id',
      'is_test'
    );
    foreach ($sharedContributionParams as $key) {
      $slaveContributionParams[$key] = $masterContributionDetails[$key];
    }
    $slaveContributionParams['contribution_recur_id'] = $slaveRecurDetails['id'];
    // If we don't have a description for the slave contribution, get it from the description field on the slave recur.
    // If that is empty too, copy it from the master contribution.
    if (empty($slaveContributionParams['contribution_source'])) {
      if (empty($slaveRecurDetails[CRM_Recurmaster_Utils::getCustomByName('description')])
        && (!empty($masterContributionDetails['contribution_source']))) {
        $slaveRecurDetails[CRM_Recurmaster_Utils::getCustomByName('description')] = $masterContributionDetails['contribution_source'];
        civicrm_api3('ContributionRecur', 'create', $slaveRecurDetails);
      }
      $slaveContributionParams['contribution_source'] = $slaveRecurDetails[CRM_Recurmaster_Utils::getCustomByName('description')];
    }
    $slaveContributionParams['total_amount'] = $slaveRecurDetails['amount'];
    // We always inherit the financial type from the recur
    $slaveContributionParams['financial_type_id'] = $slaveRecurDetails['financial_type_id'];

    // Create/Edit slave contribution
    try {
      civicrm_api3('Contribution', 'create', $slaveContributionParams);
    }
    catch (Exception $e) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' Unable to create contribution for slave R=' . $slaveRecurDetails['id'], FALSE);
    }
  }

  /**
   * Set the next scheduled date for the slave, based on when we want to take payment and what the master can do
   *
   * @param array $slaveRecurDetails
   *
   * @return array $slaveRecurDetails
   * @throws \CiviCRM_API3_Exception
   */
  public static function setNextScheduledDate($slaveRecurDetails) {
    /**
     * Get the details of the slave recur
     * Get the details of the master recur
     * If this is a new slave recur (next_sched_contribution_date < master next_sched_contribution_date)
     *   we need to update the date of the master to the earliest possible
     */
    $masterRecurId = CRM_Utils_Array::value(CRM_Recurmaster_Utils::getMasterRecurIdCustomField(), $slaveRecurDetails);
    if (empty($masterRecurId)) {
      return $slaveRecurDetails;
    }

    $slaveRecurDetails['next_sched_contribution_date'] = CRM_Recurmaster_Master::getNextAvailableContributionDate($masterRecurId, $slaveRecurDetails);

    // Set slave next scheduled date = master next scheduled date
    $masterRecurDetails = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $masterRecurId]);
    $slaveRecurDetails['next_sched_contribution_date'] = $masterRecurDetails['next_sched_contribution_date'];
    unset($slaveRecurDetails['next_sched_contribution']);

    return $slaveRecurDetails;
  }

}