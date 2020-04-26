<?php

class CRM_ManualDirectDebit_Page_BatchSubmissionQueue extends CRM_Core_Page {

  private $queue;

  private $batchId;

  public function __construct($title = NULL, $mode = NULL) {
    parent::__construct($title, $mode);

    $this->queue = CRM_ManualDirectDebit_Queue_BatchSubmission::getQueue();
    $this->batchId =  CRM_Utils_Request::retrieveValue('batchId', 'Int');
  }

  public function run() {
    if (!$this->validBatchStatus()) {
      return FALSE;
    }

    $this->addTasksToQueue();
    $this->runQueue();
  }

  private function validBatchStatus() {
    return in_array($this->getBatchStatus(), ['Open', 'Reopened']);
  }

  private function getBatchStatus() {
    $statusID = CRM_Core_DAO::getFieldValue('CRM_Batch_DAO_Batch', $this->batchId, 'status_id');
    $batchStatuses = CRM_Core_PseudoConstant::get('CRM_Batch_DAO_Batch', 'status_id', [
      'labelColumn' => 'name',
      'status' => " v.value={$statusID}",
    ]);
    return $batchStatuses[$statusID];
  }

  private function getBatchType() {
    $batch = CRM_Batch_DAO_Batch::findById($this->batchId);
    $batchTypes = CRM_Core_PseudoConstant::get('CRM_Batch_DAO_Batch', 'type_id', ['labelColumn' => 'name']);
    return $batchTypes[$batch->type_id];
  }

  private function addTasksToQueue() {
    $batchType = $this->getBatchType();
    switch ($batchType) {
      case 'instructions_batch':
        $this->addInstructionsQueueTasks();
        break;
      case 'dd_payments':
        $this->addPaymentsQueueTasks();
        break;
    }

    $this->enqueueBatchSubmissionCompletionTask();
  }

  private function addInstructionsQueueTasks() {
    $batchTransaction = new CRM_ManualDirectDebit_Batch_Transaction(
      $this->batchId,
      ['entityTable' => 'civicrm_value_dd_mandate'],
      ['mandate_id' => 1],
      ['mandate_id' => 'civicrm_value_dd_mandate.id as mandate_id']
    );
    $rows = $batchTransaction->getRows();
    foreach ($rows as $row) {
      $this->enqueueInstructionsQueueTask($row);
    }
  }

  private function enqueueInstructionsQueueTask($row) {
    $processingMessage = '';
    if (!empty($row['mandate_id'])) {
      $processingMessage = 'Processing mandate with Id : ' . $row['mandate_id'];
    }

    $task = new CRM_Queue_Task(
      ['CRM_ManualDirectDebit_Queue_Task_BatchSubmission_InstructionItem', 'run'],
      [$row],
      $processingMessage
    );
    $this->queue->createItem($task);
  }

  private function addPaymentsQueueTasks() {
    $batchTransaction = new CRM_ManualDirectDebit_Batch_Transaction(
      $this->batchId,
      ['entityTable' => 'civicrm_contribution'],
      ['mandate_id' => 1, 'contribute_id' => 1],
      [
        'mandate_id' => 'civicrm_value_dd_mandate.id as mandate_id',
        'contribute_id' => 'civicrm_contribution.id as contribute_id',
      ]
    );
    $rows = $batchTransaction->getRows();

    foreach ($rows as $row) {
      $this->enqueuePaymentsQueueTask($row);
    }
  }


  private function enqueuePaymentsQueueTask($row) {
    $processingMessage = '';
    if (!empty($row['contribute_id'])) {
      $processingMessage = 'Processing contribution with Id : ' . $row['contribute_id'];
    }

    $task = new CRM_Queue_Task(
      ['CRM_ManualDirectDebit_Queue_Task_BatchSubmission_PaymentItem', 'run'],
      [$row],
      $processingMessage
    );
    $this->queue->createItem($task);
  }

  private function enqueueBatchSubmissionCompletionTask() {
    $task = new CRM_Queue_Task(
      ['CRM_ManualDirectDebit_Queue_Task_BatchSubmission_Completion', 'run'],
      [$this->batchId],
      'Batch Submission Complete'
    );
    $this->queue->createItem($task);
  }

  private function runQueue() {
    $runner = new CRM_Queue_Runner([
      'title' => ts('Submitting the batch, this may take a while depending on how many records are processed ..'),
      'queue' => $this->queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEnd' => array('CRM_ManualDirectDebit_Page_BatchSubmissionQueue', 'onEnd'),
      'onEndUrl' => CRM_Utils_System::url('civicrm/direct_debit/batch-transaction', ['reset' => 1, 'action' => 'view', 'bid' => $this->batchId]),
    ]);

    $runner->runAllViaWeb();
  }

  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    $message = ts('Batch Submission Completed');
    CRM_Core_Session::setStatus($message, '', 'success');
  }
}
