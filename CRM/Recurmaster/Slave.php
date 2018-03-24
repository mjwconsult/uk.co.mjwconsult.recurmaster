<?php

class CRM_Recurmaster_Slave {

  /**
   * Update all slave recurring contributions and contributions linked to the master
   * Create/Edit slave contributions as required to match the master
   * We assume that the master actually took the full amount
   *
   * @param $masterRecurId
   */
  public static function updateAllByMasterContribution($masterContributionDetails) {
    $linkedRecurs = CRM_Recurmaster_Master::getLinkedRecurring($masterContributionDetails['contribution_recur_id']);
    foreach ($linkedRecurs as $linkedRecurDetails) {
      self::update($masterContributionDetails, $linkedRecurDetails);
    }
  }

  /**
   * Create/update the contributions for the slave recurring contribution
   * Based on whether:
   * - contribution date matches slaveRecur next_sched_contribution_date
   *
   * We then update the contribution
   * TODO: When do we trigger a recalculation of recur dates? Not here as it will get called multiple times
   *
   * @param $masterRecurId
   * @param $slaveRecurDetails
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function update($masterContributionDetails, $slaveRecurDetails) {
    CRM_Recurmaster_Utils::log(__FUNCTION__ . ' updating recur ' . $slaveRecurDetails['id'], TRUE);

    $masterContributionDT = new DateTime($masterContributionDetails['receive_date']);
    $slaveRecurNextScheduledDT = new DateTime($slaveRecurDetails['next_sched_contribution_date']);

    if ($masterContributionDT->format('Ymd') !== $slaveRecurNextScheduledDT->format('Ymd')) {
      CRM_Recurmaster_Slave::log(__FUNCTION__ . ' Master contribution date does not match slave next scheduled date SR=' . $slaveRecurDetails['id'], TRUE);
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
    $slaveContributionParams['contribution_source'] = $slaveRecurDetails[CRM_Mjwshared_Fields::getCustomByName('description')];
    $slaveContributionParams['financial_type_id'] = CRM_Recurmaster_Settings::getValue('slave_financial_type');
    $slaveContributionParams['total_amount'] = $slaveRecurDetails['amount'];

    // Create/Edit slave contribution
    try {
      $contributionResult = civicrm_api3('Contribution', 'create', $slaveContributionParams);
    }
    catch (Exception $e) {
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ' Unable to create contribution for slave R=' . $slaveRecurDetails['id']);
    }
  }
}