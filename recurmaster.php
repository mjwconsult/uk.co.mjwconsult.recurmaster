<?php

require_once 'recurmaster.civix.php';
use CRM_Recurmaster_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function recurmaster_civicrm_config(&$config) {
  _recurmaster_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function recurmaster_civicrm_xmlMenu(&$files) {
  _recurmaster_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function recurmaster_civicrm_install() {
  _recurmaster_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function recurmaster_civicrm_postInstall() {
  _recurmaster_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function recurmaster_civicrm_uninstall() {
  _recurmaster_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function recurmaster_civicrm_enable() {
  _recurmaster_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function recurmaster_civicrm_disable() {
  _recurmaster_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function recurmaster_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _recurmaster_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function recurmaster_civicrm_managed(&$entities) {
  _recurmaster_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function recurmaster_civicrm_caseTypes(&$caseTypes) {
  _recurmaster_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function recurmaster_civicrm_angularModules(&$angularModules) {
  _recurmaster_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function recurmaster_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _recurmaster_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
 */
function recurmaster_civicrm_navigationMenu(&$menu) {
  $item[] =  array (
    'label' => ts('Master Recurring Contributions'), array('domain' => E::LONG_NAME),
    'name'       => E::SHORT_NAME,
    'url'        => 'civicrm/admin/recurmaster',
    'permission' => 'administer CiviCRM',
    'operator'   => NULL,
    'separator'  => NULL,
  );
  _recurmaster_civix_insert_navigation_menu($menu, 'Administer/CiviContribute', $item[0]);
  _recurmaster_civix_navigationMenu($menu);
}

function recurmaster_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  //create a Send Invoice link with the context of the participant's order ID (a custom participant field)
  switch ($objectName) {
    case 'Contribution':
      switch ($op) {
        case 'contribution.selector.recurring':
          $crid = $values['crid'];
          $cid = $values['cid'];

          if (CRM_Recurmaster_Master::isMasterRecur($crid)) {
            // We are a "Master" recurring contribution, so don't allow linking to others!
            return;
          }

          try {
            $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', array(
              'id' => $crid,
            ));
          }
          catch (CiviCRM_API3_Exception $e) {
            return;
          }

          if (empty($contributionRecur[CRM_Recurmaster_Utils::getMasterRecurIdCustomField(TRUE)])) {
            $links[] = array(
              'name' => ts('Link to Master Recurring Contribution'),
              'title' => ts('Link to Master Recurring Contribution'),
              'url' => 'civicrm/recurmaster/link',
              'qs' => "action=add&reset=1&cid={$cid}&selectedChild=contribute&crid={$crid}",
            );
          }
          else {
            $links[] = array(
              'name' => ts('Unlink from Master Recurring Contribution'),
              'title' => ts('Unlink from Master Recurring Contribution'),
              'url' => 'civicrm/recurmaster/link',
              'qs' => "action=delete&reset=1&cid={$cid}&selectedChild=contribute&crid={$crid}",
            );
          }

      }
      break;
  }
}

/**
 * Implementation of hook_civicrm_smartdebit_alterCreateVariableDDIParams
 * Called whenever a subscription at Smartdebit is being updated
 *
 * @param $recurParams
 * @param $smartDebitParams
 */
function recurmaster_civicrm_smartdebit_alterVariableDDIParams(&$recurParams, &$smartDebitParams, $op) {
  switch ($op) {
    case 'create':
    case 'update':
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ': recurParams: ' . print_r($recurParams, TRUE), TRUE);
      // Calculate the regular payment amount
      $nextAmount = $recurParams['amount'];
      if ($nextAmount === NULL) {
        return;
      }
      // Set the regular payment amount
      CRM_Recurmaster_Smartdebit::alterDefaultPaymentAmount($smartDebitParams, $nextAmount);
      CRM_Recurmaster_Utils::log(__FUNCTION__ . ': smartDebitParams: ' . print_r($smartDebitParams, TRUE));
      break;
  }
}

/**
 * Implementation of hook_civicrm_smartdebit_updateRecurringContribution
 * Called when recurring contributions are updated by Smartdebit
 *
 * @param $recurContributionParams
 *
 * @throws \CiviCRM_API3_Exception
 * @throws \Exception
 */
function recurmaster_civicrm_smartdebit_updateRecurringContribution(&$recurContributionParams) {
  CRM_Recurmaster_Smartdebit::checkSubscription($recurContributionParams);
}
