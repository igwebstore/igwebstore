<?php
/**
*/
ini_set('display_errors', 'On');
error_reporting(E_ALL);
if (!isset($root_path)) { $root_path = $_SERVER['DOCUMENT_ROOT']; }
include_once $root_path.'/helpers/parseJSON.php';
include_once $root_path.'/helpers/admin/class.userManagement.php';
include_once $root_path.'/helpers/admin/enums.php';

class rgConfigurator {
	public $userManager;
  private $igrtSqli;
	private $rgID;
	private $uid;
	private $permissions;
	private $userPermissions; // complex object with lists of different permission views
	private $systemUsers;     // list of system users which may be required in some functions

	// <editor-fold defaultstate="collapsed" desc=" private members to create json as required for pages">

	private function AddExperimentNames($filteredEGs) {
		$allExperimentDefinitions = $this->GetExperimentDefinitions();
		for ($i =0 ; $i<count($filteredEGs); $i++) {
			// count == 1 mean 1 null entry - ignore and clean
			if (count($filteredEGs[$i]->exptList) > 1) {
				for($j=0; $j<count($filteredEGs[$i]->exptList); $j++) {
					$filteredEGs[$i]->exptList[$j] = $this->GetExperimentDefinition($allExperimentDefinitions, $filteredEGs[$i]->exptList[$j]);
				}
			}
			else {
				$filteredEGs[$i]->exptList =[];
			}
			if (count($filteredEGs[$i]->notAssociated) > 1) {
				for($j=0; $j<count($filteredEGs[$i]->notAssociated); $j++) {
					$filteredEGs[$i]->notAssociated[$j] = $this->GetExperimentDefinition($allExperimentDefinitions, $filteredEGs[$i]->notAssociated[$j]);
				}
			}
			else {
				$filteredEGs[$i]->notAssociated = [];
			}
		}
 		return $filteredEGs;
	}

	private function GetExperimentDefinition($allDetails, $exptId) {
		foreach ($allDetails as $allDetail) {
			if ($allDetail['exptId'] == $exptId) {
				return ['exptId' => $allDetail['exptId'], 'title'=> $allDetail['title']];
			}
		}
		//return null;
	}

	private function GetExperimentDefinitions() {
		$exptQry = "SELECT exptId, title FROM igExperiments ORDER BY exptId DESC";
		$exptResult = $this->igrtSqli->query($exptQry);
		return $exptResult->fetch_all(MYSQLI_ASSOC);
	}

	private function GetAllExperimentIDs() {
		$expts = [];
		$qry = "SELECT exptId FROM igExperiments order by exptId ASC";
		$qryResult = $this->igrtSqli->query($qry);
		while($row = $qryResult->fetch_object()) {
			array_push($expts, $row->exptId);
		}
		return $expts;
	}

	private function GetExperimentsForGroups() {
		$groupQry = "SELECT gt.id, gt.GroupName, et.exptId FROM igExperimentGroups as gt LEFT JOIN igExperimentGroupMappings as et ON gt.id = et.groupId ORDER BY gt.id ASC, et.exptId DESC";
		$groupResult = $this->igrtSqli->query($groupQry);
		return $groupResult->fetch_all(MYSQLI_ASSOC);
	}

	private function GetExperimentsNotForGroups($filteredEGs) {
		$allExperiments = $this->GetAllExperimentIDs();
		for ($i =0 ; $i<count($filteredEGs); $i++) {
			$diffs = array_diff($allExperiments, $filteredEGs[$i]->exptList);
			$filteredEGs[$i]->notAssociated = [];
			foreach ($diffs as $key=>$value) {
				array_push($filteredEGs[$i]->notAssociated, $value);
			}
			$filteredEGs[$i]->notAssociated = array_reverse($filteredEGs[$i]->notAssociated);
		}
		return $filteredEGs;
	}

	private function UserHasPermission($groupId) {
		if ($this->permissions == 1024) {
			return true;
		}
		foreach ($this->userPermissions->userGroupMemberships as $ugmId) {
			if ($groupId == $ugmId) {
				return true;
			}
		}
		return false;
	}

	private function GetUIStatus($groupId) {
		$uiQry = sprintf("SELECT * FROM ui_exptGroup2UserMappings WHERE uid='%s' AND groupId='%s'", $this->uid, $groupId);
		$uiResult = $this->igrtSqli->query($uiQry);
		if ($uiResult) {
			$uiRow = $uiResult->fetch_object();
			return $uiRow->isClosed == 1;
		}
		else {
			$uiInsert = sprintf("INSERT INTO ui_exptGroup2UserMappings (uid, groupId, isClosed) VALUES('%s', '%s', '1')", $this->uid, $groupId);
			$this->igrtSqli->query($uiInsert);
			return true;
		}
	}

	private function GetEmailFromSystemUser($id) {
		foreach ($this->systemUsers as $systemUser) {
			if ($systemUser['id'] == $id)
				return $systemUser['email'];
		}
		return "";
	}

	// </editor-fold>


	// <editor-fold defaultstate="collapsed" desc=" ctor and public interfaces">

	public function UpdateGroupName($content) {
		$update = sprintf("UPDATE igExperimentGroups SET GroupName='%s' WHERE id = '%s'", $content[1], $content[0]);
		$this->igrtSqli->query($update);
	}

	public function InsertGroupName($content) {
		$insert = sprintf("INSERT INTO igExperimentGroups (GroupName) VALUES('%s')", $content[0]);
		$this->igrtSqli->query($insert);
	}

	public function UpdateMapping($content) {
		if ($content[2] == 1) {
			$insertSql = sprintf("INSERT INTO igExperimentGroupMappings (groupId, exptId) VALUES('%s', '%s')", $content[0], $content[1]);
			$this->igrtSqli->query($insertSql);
		}
		else {
			$deleteSql = sprintf("DELETE FROM igExperimentGroupMappings WHERE groupId = '%s' AND exptId = '%s'", $content[0], $content[1]);
			$this->igrtSqli->query($deleteSql);
		}
	}

	public function UpdateMembership($content) {
		if ($content[2] == 1) {
			$insertSql = sprintf("INSERT INTO igExperimentGroupMembers (groupId, userId) VALUES('%s', '%s')", $content[0], $content[1]);
			$this->igrtSqli->query($insertSql);
		}
		else {
			$deleteSql = sprintf("DELETE FROM igExperimentGroupMembers WHERE groupId = '%s' AND userId = '%s'", $content[0], $content[1]);
			$this->igrtSqli->query($deleteSql);
		}
	}

	public function UpdateExperimentMapping($content) {
		if ($content[2] == 1) {
			$insertSql = sprintf("INSERT INTO igExperimentUserMembers (exptId, userId) VALUES('%s', '%s')", $content[0], $content[1]);
			$this->igrtSqli->query($insertSql);
		}
		else {
			$deleteSql = sprintf("DELETE FROM igExperimentUserMembers WHERE exptId = '%s' AND userId = '%s'", $content[0], $content[1]);
			$this->igrtSqli->query($deleteSql);
		}
	}

	public function GetFilteredGroupExperiments($returnType) {

		$filteredEGs = [];

		$currentGroupDef = new stdClass;
		$currentGroupDef->groupID = -1;
		$currentGroupDef->groupName = '';
		$currentGroupDef->isClosed = true;
		$currentGroupDef->exptList = [];

		$egMappings = $this->GetExperimentsForGroups();

		foreach ($egMappings as $egMapping) {
			if ($egMapping['id'] != $currentGroupDef->groupID) {
				if ($currentGroupDef->groupID > -1) {

					// decide whether to add the finished group to thee current user's list
					if ($this->UserHasPermission($currentGroupDef->groupID)) {
						// get/create ui status for this expt-group and uid
						$currentGroupDef->isClosed = $this->GetUIStatus($currentGroupDef->groupID);
						array_push($filteredEGs, $currentGroupDef);
					}
				}

				// build next group definition
				$currentGroupDef = new stdClass;
				$currentGroupDef->groupID = $egMapping['id'];
				$currentGroupDef->groupName = $egMapping['GroupName'];
				$currentGroupDef->exptList = [];
				array_push($currentGroupDef->exptList, $egMapping['exptId']);
			}
			else {
				array_push($currentGroupDef->exptList, $egMapping['exptId']);
			}
		}
		// decide whether to add the final group to the current user's list
		if ($this->UserHasPermission($currentGroupDef->groupID)) {
			array_push($filteredEGs, $currentGroupDef);
			$currentGroupDef->isClosed = $this->GetUIStatus($currentGroupDef->groupID);
		}

		// now build up list of experiments not associated with each group
		$filteredEGs = $this->GetExperimentsNotForGroups($filteredEGs);

		// now add names to each expt reference - these go into ObservableArray, so avoids having to build viewmodels in the the js
		$filteredEGs = $this->AddExperimentNames($filteredEGs);

		$experimentgroups = new stdClass();
		$experimentgroups->groups = $filteredEGs;
		//$experimentgroups->allExperiments = $this->GetExperimentDefinitions();
		return $returnType == returnAsJSON ? json_encode($experimentgroups) : $experimentgroups;
	}

	public function GetExperimentGroups($returnType) {
		$experimentgroups = new stdClass();
		$experimentgroups->groups = [];

		$groupsSql = "SELECT * FROM igExperimentGroups";
		$groupsResult = $this->igrtSqli->query($groupsSql);
		while ($groupRow = $groupsResult->fetch_object()) {
			array_push($experimentgroups->groups, ['id' => $groupRow->id, 'groupname' => $groupRow->GroupName]);
		}

		return $returnType == returnAsJSON ? json_encode($experimentgroups) : $experimentgroups;
	}

	public function GetGroupMemberships($returnType) {
		$memberships = [];
		$groupsQry = "SELECT * FROM igExperimentGroups";
		$groupsResult = $this->igrtSqli->query($groupsQry);
		while ($groupsRow = $groupsResult->fetch_object()) {
			$membership = new stdClass();
			$membership->id = $groupsRow->id;
			$membership->groupName = $groupsRow->GroupName;
			$membership->members = [];
			$membership->nonmembers = [];

			$membersQry = sprintf("SELECT * FROM igExperimentGroupMembers WHERE groupId = '%s'", $groupsRow->id);
			$membersResult = $this->igrtSqli->query($membersQry);
			while ($memberRow = $membersResult->fetch_object()) {
				array_push($membership->members, ['id' => $memberRow->userId, 'email' => $this->GetEmailFromSystemUser($memberRow->userId) ]);
			}
			foreach($this->systemUsers as $systemUser) {
				$isMember = false;
				foreach($membership->members as $member) {
					if ($member['id'] == $systemUser['id'])
						$isMember = true;
				}
				if (!$isMember) {
					array_push($membership->nonmembers, ['id' => $systemUser['id'], 'email' => $systemUser['email']]);
				}
			}
			array_push($memberships, $membership);
		}
		return $returnType == returnAsJSON ? json_encode($memberships) : $memberships;
	}

	public function GetExperimentMemberships($returnType) {
		$experiments = [];
		$exptSql = "SELECT * FROM igExperiments";
		$exptResult = $this->igrtSqli->query($exptSql);
		while ($exptRow = $exptResult->fetch_object()) {
			$expt = new stdClass();
			$expt->id = $exptRow->exptId;
			$expt->title = $exptRow->title;
			$expt->members = [];
			$expt->nonmembers = [];

			$userSql = sprintf("SELECT * FROM igExperimentUserMembers WHERE exptId = '%s'", $exptRow->exptId);
			$userResult = $this->igrtSqli->query($userSql);
			while ($userRow = $userResult->fetch_object()) {
				array_push($expt->members, ['id'=>$userRow->userId, 'email' => $this->GetEmailFromSystemUser($userRow->userId)] );
			}


			foreach ($this->systemUsers as $systemUser) {
				$isMember = false;
				foreach ($expt->members as $member) {
					if ($systemUser['id'] == $member['id']) {
						$isMember = true;
					}
				}
				if (!$isMember) {
					array_push($expt->nonmembers, ['id'=>$systemUser['id'], 'email' => $systemUser['email']] );
				}
			}
			array_push($experiments, $expt);
		}

		return $returnType == returnAsJSON ? json_encode($experiments) : $experiments;
	}


	public function __construct($igrtSqli, $uid, $rgID, $permissions) {
    $this->igrtSqli = $igrtSqli;
		$this->rgID = $rgID;
		$this->uid = $uid;
		$this->permissions = $permissions;
		$this->userManager = new userManagement($uid);  // is public as may be used from top level controller
		$this->userPermissions = $this->userManager->GetUserPermissions();
		$this->systemUsers = $this->userManager->GetSystemUsers();

	}

// </editor-fold>

}