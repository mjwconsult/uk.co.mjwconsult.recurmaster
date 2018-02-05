<?php
use CRM_Recurmaster_ExtensionUtil as E;

/**
 * Job.ProcessRecurmaster API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_job_process_recurmaster_spec(&$spec) {
  $spec['recur_ids']['title'] = 'Master Recur IDs';
}

/**
 * Job.ProcessRecurmaster API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_process_recurmaster($params) {
  if (!isset($params['recur_ids'])) {
    $params['recur_ids'] = array();
  }
  if (!is_array($params['recur_ids'])) {
    $params['recur_ids'] = array($params['recur_ids']);
  }

  $returnValues = CRM_Recurmaster_Master::update($params['recur_ids']);

  // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
  return civicrm_api3_create_success($returnValues, $params, 'Job', 'ProcessRecurmaster');
}
