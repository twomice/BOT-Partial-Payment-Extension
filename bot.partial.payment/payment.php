<?php

require_once 'payment.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function payment_civicrm_config(&$config) {
  _payment_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function payment_civicrm_install() {
  return _payment_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function payment_civicrm_enable() {
  return _payment_civix_civicrm_enable();
}

/**
 * Function to process partial payments
 * @param $paymentParams - Payment Processor parameters
 * @param $participantInfo - participantID as key and contributionID, ContactID, PayLater, Partial Payment Amount
 * @return $participantInfo array with 'Success' flag
 * */
function process_partial_payments($paymentParams, $participantInfo) {
  foreach ($participantInfo as $pId => $pInfo) {
    if ($pInfo['partial_payment_pay']) {
      if ($pInfo['payLater']) {
        $contributionStatuses = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
        //Update contribution status from pending to partially paid
        $updateContribution = new CRM_Contribute_DAO_Contribution();
        $contributionParams = array(
          'id' => $pInfo['contribution_id'],
          'contact_id' => $pInfo['cid'],
          'contribution_status_id' => array_search('Partially paid', $contributionStatuses),
        );
        $updateContribution->copyValues($contributionParams);
        $t = $updateContribution->save();
        //Update participant Status from 'Pending from Pay Later' to 'Partially Paid'
        $pendingPayLater = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_ParticipantStatusType', 'Pending from pay later', 'id', 'name');
        $partiallyPaid = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_ParticipantStatusType', 'Partially paid', 'id', 'name');
        $participantStatus = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_Participant', $pId, 'status_id', 'id');

        if ($participantStatus == $pendingPayLater) {
          CRM_Event_BAO_Participant::updateParticipantStatus($pId, $pendingPayLater, $partiallyPaid, TRUE);
        }
      }

      //Add additional financial transactions for partial payments
      $paymentParams['total_amount'] = $pInfo['partial_payment_pay'];

      //recordAdditionalPayment method no longer supported as of CiviCRM 5.18.x
      //$trxnRecord = CRM_Contribute_BAO_Contribution::recordAdditionalPayment( $pInfo['contribution_id'], $paymentParams, 'owed', $pId );
      $paymentParams['participant_id'] = $pId;
      $paymentParams['contribution_id'] = $pInfo['contribution_id'];

      try {
        $trxnRecord = civicrm_api3('Payment', 'create', $paymentParams);
      }
      catch (CiviCRM_API3_Exception $e) {
        $error = $e->getMessage();
        CRM_Core_Error::debug_var("Trxn Record", $trxnRecord);
        CRM_Core_Error::debug_var("API Exception error", $error);
      }
    }
  }
}

// /**
//  * Implements hook_civicrm_postInstall().
//  *
//  * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
//  */
// function payment_civicrm_postInstall() {
//   _payment_civix_civicrm_postInstall();
// }
