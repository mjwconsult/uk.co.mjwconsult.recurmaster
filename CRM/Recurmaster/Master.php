<?php
/**
 * Created by PhpStorm.
 * User: matthew
 * Date: 05/02/18
 * Time: 16:52
 */

class CRM_Recurmaster_Master {

  public static function update($recurIds = array()) {

    $contributionRecurParams = array(
      CRM_Recurmaster_Utils::getIsMasterCustomField(TRUE) => 1,
      'options' => array('limit' => 0),
    );

    if (!empty($recurIds) && is_array($recurIds)) {
      // Generate list of recur Ids
      $contributionRecurParams['id'] = array('IN' => $recurIds);
    }

    $contributionRecurs = civicrm_api3('ContributionRecur', 'get', $contributionRecurParams);

    if (empty($contributionRecurs['values'])) {
      Civi::log()->info('CRM_Recurmaster_Master: No master recurring contributions for processing');
      $return['count'] = 0;
      return $return;
    }

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
      civicrm_api3('ContributionRecur', 'create', $contributionRecur);
    }

    $return['count'] = count($contributionRecurs['values']);
    return $return;
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

}