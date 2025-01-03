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
  //Iterate through participant info
  foreach ($participantInfo as $pId => $pInfo) {
    if (!$pInfo['contribution_id'] || !$pId) {
      $participantInfo[$pId]['success'] = 0;
      continue;
    }

    if ($pInfo['partial_payment_pay']) {
      //Update contribution and participant status for pending from pay later registrations
      if ($pInfo['payLater']) {
        /** Using DAO instead of API
         * API does not allow changing the status from 'Pending from pay later' to 'Partially Paid'
         * */
        $contributionStatuses = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
        $updateContribution = new CRM_Contribute_DAO_Contribution();
        $contributionParams = array('id' => $pInfo['contribution_id'],
          'contact_id' => $pInfo['cid'],
          'contribution_status_id' => array_search('Partially paid', $contributionStatuses),
        );

        $updateContribution->copyValues($contributionParams);
        $updateContribution->save();

        //Update participant Status from 'Pending from Pay Later' to 'Partially Paid'
        $pendingPayLater = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_ParticipantStatusType', 'Pending from pay later', 'id', 'name');
        $partiallyPaid = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_ParticipantStatusType', 'Partially paid', 'id', 'name');
        $participantStatus = CRM_Core_DAO::getFieldValue('CRM_Event_BAO_Participant', $pId, 'status_id', 'id');

        if ($participantStatus == $pendingPayLater) {
          CRM_Event_BAO_Participant::updateParticipantStatus($pId, $pendingPayLater, $partiallyPaid, TRUE);
        }
      }
      //Making sure that payment params has the correct amount for partial payment
      $paymentParams['total_amount'] = $pInfo['partial_payment_pay'];

      //Add additional financial transactions for each partial payment
      $trxnRecord = CRM_Contribute_BAO_Contribution::recordAdditionalPayment($pInfo['contribution_id'], $paymentParams, 'owed', $pId);

      if ($trxnRecord->id) {
        $participantInfo[$pId]['success'] = 1;
        $participantInfo[$pId]['trxn'] = $trxnRecord;
      }
    }
  }
  return $participantInfo;
}

// /**
//  * Implements hook_civicrm_postInstall().
//  *
//  * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
//  */
// function payment_civicrm_postInstall() {
//   _payment_civix_civicrm_postInstall();
// }
