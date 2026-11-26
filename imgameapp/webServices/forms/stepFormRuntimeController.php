<?php
// -----------------------------------------------------------------------------
// 
// web service to support AJAX calls generated from form/survey 
//  
// -----------------------------------------------------------------------------
if (!isset($root_path)) { $root_path = $_SERVER['DOCUMENT_ROOT']; }
include_once $root_path.'/domainSpecific/mySqlObject.php';
require_once($root_path.'/helpers/forms/class.stepFormRuntimeController.php');
$messageType = $_GET['messageType'];
$content = isset($_GET['content']) ? $_GET['content'] : NULL;
$exptId = $_GET['exptId'];
$jType = $_GET['jType'];
$formType = $_GET['formType'];
    
  function processMessage($messageType, $exptId, $formType, $jType, $content) {
    global $igrtSqli;
    $stepFormRuntimeController = new stepFormRuntimeController($igrtSqli, $exptId, $formType, $jType);
    $msg = null;
    $pptNo = null;
    $stage = null;
    $pageFurniture = null;
    switch ($messageType) {
      case "formSettings": {
        $msg = $stepFormRuntimeController->getStepFormRuntimeSettings();
        break;
      }
      default : {
        //uncaught, but pass back to JS
        $msg = "<message><messageType>blank</messageType><content>0</content></message>";
      }
    }
    return $msg;
  }

$retMsg = processMessage($messageType, $exptId, $formType, $jType, $content);
//$retMsg = 'hello';
echo $retMsg;
