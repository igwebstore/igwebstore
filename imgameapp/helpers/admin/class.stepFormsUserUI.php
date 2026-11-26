<?php
/**
 * Manage user-UI states for form configuration
 *
 * @author MartinHall
 */
ini_set('display_errors', 'On');
error_reporting(E_ALL);
if (!isset($root_path)) { $root_path = $_SERVER['DOCUMENT_ROOT']; }
include_once $root_path.'/domainSpecific/mySqlObject.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

class stepFormsUserUI {
  private $igrtSqli;
  private $formDef;
  private $userId;
  private $formType;
  private $exptId;
  private $userAccordionStates;
  
  
  private function processQsUIstates($i) {
    for ($j = 0; $j<count($this->formDef['pages'][$i]['questions']); $j++) {
      $this->formDef['pages'][$i]['questions'][$j]['questionAccordionClosed'] = $this->userAccordionStates->pageAccordions[$i]->qAccordions[$j]->status == 1;
    }
  }
  private function processPagesUIstates() {
    for ($i=0;$i<count($this->formDef['pages']); $i++) {
      $this->formDef['pages'][$i]['pageAccordionClosed'] = $this->userAccordionStates->pageAccordions[$i]->state->status == 1;
      $this->processQsUIstates($i);
    }
  }
  
  public function processUserUIStates($_formDef) {
    $this->formDef = $_formDef;
    
    $this->formDef['introPage']['recruitmentSection']['recruitmentSectionClosed'] = $this->userAccordionStates->topLevelStates->recruitmentSectionClosed == 1;

    $this->formDef['introPage']['introPageAccordionClosed'] = $this->userAccordionStates->topLevelStates->introPageAccordionClosed == 1;
    $this->formDef['introPage']['eligibilityQ']['eligibilitySectionClosed'] = $this->userAccordionStates->topLevelStates->eligibilitySectionClosed == 1;
    $this->formDef['introPage']['eligibilityQ']['optionsDef']['eligibilityOptionsAccordionClosed'] = $this->userAccordionStates->topLevelStates->eligibilityOptionsAccordionClosed == 1;
  
    $this->formDef['finalPage']['finalPageAccordionClosed'] = $this->userAccordionStates->topLevelStates->finalPageAccordionClosed == 1;
    
    $this->formDef['pagesAccordionClosed'] = $this->userAccordionStates->topLevelStates->pagesAccordionClosed == 1;
    $this->formDef['introPageAccordionClosed'] = $this->userAccordionStates->topLevelStates->introPageAccordionClosed == 1;
    $this->processPagesUIstates();
    
    return $this->formDef;
  }

  private function getPageCnt() {
    $result = $this->igrtSqli->query(sprintf("select * from fdStepFormsPages where exptId='%s' and formType='%s'", $this->exptId, $this->formType));
    return $result->num_rows;
  }
  private function getPageQuestionCnt($pageNo) {
    $result = $this->igrtSqli->query(sprintf("select * from fdStepFormsQuestions where exptId='%s' and formType='%s' and pNo='%s'", $this->exptId, $this->formType, $pageNo));
    return $result->num_rows;
  }
  private function getPageQuestionAccordionStates($pageNo) {
    $returnValue = [];
    $questionCnt = $this->getPageQuestionCnt($pageNo);
    for ($i=0; $i<$questionCnt; $i++) {
      $stateQry = sprintf("select * from ui_formDefPageQuestionControlStatus where userId='%s' and exptId='%s' and formType='%s' and pNo='%s' and qNo='%s'", $this->userId, $this->exptId, $this->formType, $pageNo, $i);
      $stateResult = $this->igrtSqli->query($stateQry);
      if ($stateResult->num_rows == 0) {
        $createQry = sprintf("insert into ui_formDefPageQuestionControlStatus (userId,exptId,formType,pNo,qNo,status) VALUES ('%s','%s','%s','%s','%s','1')", $this->userId, $this->exptId, $this->formType, $pageNo, $i);
        $this->igrtSqli->query($createQry);
        $stateResult = $this->igrtSqli->query($stateQry);
      }
      $state = $stateResult->fetch_object();
      $returnValue[] = $state;
    }
    return $returnValue;
  }
  private function getPageAccordionStates() {
    $returnValue = [];
    $pageCnt = $this->getPageCnt();
    for ($i=0; $i<$pageCnt; $i++) {
      $stateQry = sprintf("select * from ui_formDefPageControlUserStatus where userId='%s' and exptId='%s' and formType='%s' and pNo='%s'", $this->userId, $this->exptId, $this->formType, $i);
      $stateResult = $this->igrtSqli->query($stateQry);
      if ($stateResult->num_rows == 0) {
        $createQry = sprintf("insert into ui_formDefPageControlUserStatus (userId,exptId,formType,pNo,status) VALUES ('%s','%s','%s','%s','1')", $this->userId, $this->exptId, $this->formType, $i);
        $this->igrtSqli->query($createQry);
        $stateResult = $this->igrtSqli->query($stateQry);
      }
      $pageStates = new stdClass();
      $pageStates->state = $stateResult->fetch_object();;
      $pageStates->qAccordions = $this->getPageQuestionAccordionStates($i);
      $returnValue[] = $pageStates;
    }
    return $returnValue;
  }
  private function getUserAccordionStates() {
    $returnValue = new stdClass();
    $statesQry = sprintf("select * from ui_formDefControlUserStatus where userId='%s' and exptId='%s' and formType='%s'", $this->userId, $this->exptId, $this->formType);
    $statesResult = $this->igrtSqli->query($statesQry);
    if ($statesResult->num_rows == 0) {
      $createQry = sprintf("insert into ui_formDefControlUserStatus (userId, exptId, formType) VALUES ('%s','%s','%s')", $this->userId, $this->exptId, $this->formType);
      $this->igrtSqli->query($createQry);
    }
    $statesResult = $this->igrtSqli->query($statesQry);
    $returnValue->topLevelStates= $statesResult->fetch_object();
    $returnValue->pageAccordions = $this->getPageAccordionStates();
    return $returnValue;
  }
  
// </editor-fold>

// <editor-fold defaultstate="collapsed" desc=" constructor">
  
  function __construct($_igrtSqli, $_exptId, $_formType, $_userId) {
    $this->igrtSqli = $_igrtSqli;
    $this->userId = $_userId;
    $this->exptId = $_exptId;
    $this->formType = $_formType;
    $this->userAccordionStates = $this->getUserAccordionStates();
  }


// </editor-fold>

}

