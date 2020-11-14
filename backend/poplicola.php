<?php

class Contribution {
	public static function ValidateContributionRow($ContributionRow) {
		if (!isset($ContributionRow['ContributionDate']) || !isset($ContributionRow['MecId']) || !isset($ContributionRow['ReportId']) || !isset($ContributionRow['Amount'])) {
			throw new Exception("Missing info for contribution");
		}
		if (!isset($ContributionRow['ContributionData']) || !is_array($ContributionRow['ContributionData'])) {
			throw new Exception("Missing ContributionData");
		}
	}
}


function InsertContributions($ContributionRows) {
	if (empty($ContributionRows)) { return array(); }
	$Query = "INSERT INTO contribution (ContributorId,ContributionDate,MecId,ReportId,ZipCode,IsInKind,Amount,ContributionData) VALUES ";
	$ParamsForBinding = array();
	$ParamTypesString = '';
	foreach ($ContributionRows as $Index => $ThisContribution) {
		Contribution::ValidateContributionRow($ThisContribution);

		// Add parameters for bind_param
		$ParamTypesString .= "issisiis";
		$ParamsForBinding[] = isset($ThisContribution['ContributorId']) && !$ThisContribution['ContributorId'] ? $ThisContribution['ContributorId'] : null;
		$ParamsForBinding[] = $ThisContribution['ContributionDate'];
		$ParamsForBinding[] = $ThisContribution['MecId'];
		$ParamsForBinding[] = $ThisContribution['ReportId'];
		$ParamsForBinding[] = isset($ThisContribution['ZipCode']) ? $ThisContribution['ZipCode'] : null;
		$ParamsForBinding[] = isset($ThisContribution['IsInKind']) && $ThisContribution ? 1 : 0;
		$ParamsForBinding[] = $ThisContribution['Amount'];
		$ParamsForBinding[] = json_encode($ThisContribution['ContributionData']);
		$Query .= '(?,?,?,?,?,?,?,?)';
		if ($Index+1 == count($ContributionRows)) {
			$Query .= ";";
		} else {
			$Query .= ",";
		}
	}

	global $dbConnection;
	$stmt =  $dbConnection->stmt_init();
	if ($stmt->prepare($Query)) {
		// Using reflection to allow arbitrary number of params: https://www.php.net/manual/en/mysqli-stmt.bind-param.php#104073
		$ref = new ReflectionClass('mysqli_stmt');
		$method = $ref->getMethod("bind_param");
		array_unshift($ParamsForBinding, $ParamTypesString);
		// Suppressing error here because it yells at me for this, I hope this causes no problems lol
		@$method->invokeArgs($stmt, $ParamsForBinding);
		$stmt->execute();
		return count($ContributionRows);
	} else {
		throw new Exception("Problem with insert: ".$stmt->error);
	}
}

function GetContribution($ContributionId) {
	global $dbConnection;
	$stmt = $dbConnection->prepare('SELECT * FROM contribution WHERE ContributionId = ?');
	$stmt->bind_param('i', $ContributionId);
	$stmt->execute();
	$result = $stmt->get_result();
	return $result->fetch_assoc();
}

// Returns keyed on zip
function GetDonationsPerZip($MecId, $StartDate, $EndDate) {
	global $dbConnection;
	$stmt = $dbConnection->prepare('SELECT ZipCode, SUM(Amount) AS TotalFromZip FROM contribution WHERE MecId = ? AND ContributionDate > ? AND ContributionDate < ? GROUP BY ZipCode ORDER BY TotalFromZip DESC');
	$stmt->bind_param('sss', $MecId, $StartDate, $EndDate);
	$stmt->execute();
	$result = $stmt->get_result();
	$ReturnArray = array();
	while ($row = $result->fetch_assoc()) {
		$ReturnArray[$row['ZipCode']] = $row;
	}
	return $ReturnArray;
}

class Election {
	function __construct() {

	}

	public static function GetElection($ElectionId) {
		global $dbConnection;
		$stmt = $dbConnection->prepare('SELECT * FROM election WHERE ElectionId = ?');
		$stmt->bind_param('i', $ElectionId);
		$stmt->execute();
		$result = $stmt->get_result();
		return $result->fetch_assoc();
	}
}

class Candidate {
	// Returns keyed on candidate Id
	static function GetCandidates($CandidateIdArray) {
		if (!is_array($CandidateIdArray) || empty($CandidateIdArray)) { return array(); }
		global $dbConnection;
		$Placeholders = implode(",", array_map(function(){ return "?"; }, $CandidateIdArray));
		$ParamTypesString = implode("", array_map(function(){ return "i"; }, $CandidateIdArray));
		$stmt =  $dbConnection->stmt_init();
		$Query = 'SELECT * FROM candidate WHERE CandidateId IN ('.$Placeholders.')';
		if ($stmt->prepare($Query)) {
			$ref = new ReflectionClass('mysqli_stmt');
			$method = $ref->getMethod("bind_param");
			$ParamsForBinding = $CandidateIdArray;
			array_unshift($ParamsForBinding, $ParamTypesString);
			@$method->invokeArgs($stmt, $ParamsForBinding);
			$stmt->execute();
			$result = $stmt->get_result();
			$ReturnArray = array();
			while ($row = $result->fetch_assoc()) {
				$ReturnArray[$row['CandidateId']] = $row;
			}
			return $ReturnArray;
		} else {
			throw new Exception("(#0uh020)");
		}
	}
}

?>