<html>
<head>
    <style>
        body {
            font-family: Tahoma, Verdana, Segoe, sans-serif;
            font-style: normal;
            font-variant: normal;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            text-align: left;
            padding: 8px;
        }

        tr:nth-child(even){background-color: #f2f2f2}

        th {
            background-color: #4CAF50;
            color: white;
        }
    </style>
</head

<body>
<?php
ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING);

//require_once 'header.php';

require_once "vendor/autoload.php";
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();


$sprint = isset($_GET['sprint']) ? $_GET['sprint']: null;

if(!$sprint){
    die("Please specify a Sprint!");
}

$baseuri = "https://" . getenv('JIRA_DOMAIN');

// Get Sprint Issues
$api = "/rest/greenhopper/1.0/rapid/charts/sprintreport?rapidViewId=15&sprintId=$sprint";

$response = \Httpful\Request::get($baseuri.$api)                  // Build a GET request...
    ->withoutStrictSsl()
    ->authenticateWith(getenv('JIRA_USERNAME'), getenv('JIRA_PASSWORD'))  // authenticate with basic auth...
    ->send();


$api = "/rest/agile/1.0/sprint/$sprint/issue";
$response_2 = \Httpful\Request::get($baseuri.$api)                  // Build a GET request...
    ->withoutStrictSsl()
    ->authenticateWith(getenv('JIRA_USERNAME'), getenv('JIRA_PASSWORD'))  // authenticate with basic auth...
    ->send();
$issues = $response_2->body->issues;

$issues_additional_details = array();

foreach($issues as $issue){
    $issues_additional_details[$issue->key]['issuelinks'] = $issue->fields->issuelinks;
    $issues_additional_details[$issue->key]['closedSprints'] = $issue->fields->closedSprints;
    $issues_additional_details[$issue->key]['currentStatus'] = $issue->fields->status->name;
}
//print_r($issues_additional_details);
//die;

echo "<h1>Sprint Name: ", $response->body->sprint->name, "</h1>";
//$stDate = new DateTime($response->body->sprint->startDate);
echo "<h3>Sprint Start: "   , $response->body->sprint->startDate, "</h3>";
//$endDate = new DateTime($response->body->sprint->endDate);
echo "<h3>Sprint End: "   , $response->body->sprint->endDate, "</h3>";

// echo "<h3> Number of Stories/Issues: ", $response->body->total, "</h3>";




$totalStoryPoints = 0;
$bugsPoint = 0;
$firstTimeRightPoint = 0;
$reopenedStoryPoints = 0;
$ftr = true; // First Time Right
$effectiveStoryPointsDone = 0;
$i = 0;


echo "<b>Completed Issues</b>";
$issues = $response->body->contents->completedIssues;
$issues = mergeIssueDetails($issues, $issues_additional_details);
$issueKeysAddedDuringSprint = $response->body->contents->issueKeysAddedDuringSprint;


$completedIssuesStat = showIssuesList($issues, $issueKeysAddedDuringSprint, "completed");
?>
<h3>Total Story Points: <?php echo $completedIssuesStat['totalStoryPoints'];?></h3>
<h3>Total Story Points in Bugs: <?php echo $completedIssuesStat['bugsStoryPoints'];?></h3>
<h3>Story Points Done First Time Right: <?php echo $completedIssuesStat['firstTimeRightStoryPoints'];?></h3>
<h3>FTR ratio: <?php echo round($completedIssuesStat['firstTimeRightStoryPoints']*100/$completedIssuesStat['totalStoryPoints'])?>%</h3>
<h3>Effective Story Points Done: <?php echo $completedIssuesStat['effectiveStoryPoints'];?></h3>

<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>
<hr/>
<br/><br/><br/><br/>
<?php
if( count($response->body->contents->issuesNotCompletedInCurrentSprint) >0 ){
    echo "<b>Issues Not Completed</b>";
    $issues = $response->body->contents->issuesNotCompletedInCurrentSprint;
    $issues = mergeIssueDetails($issues, $issues_additional_details);
    showIssuesList($issues, $issueKeysAddedDuringSprint, "not_completed");
}


if( count($response->body->contents->puntedIssues) >0 ){
    echo "<b>Issues Removed From Sprint</b>";
    $issues = $response->body->contents->puntedIssues;
    $issues = mergeIssueDetails($issues, $issues_additional_details);
    showIssuesList($issues, $issueKeysAddedDuringSprint, "removed");
}

function mergeIssueDetails($grasshopperIssues, $issues_additional_details){
    foreach($grasshopperIssues as &$issues){
        $key = $issues->key;
        $issues->issuelinks = $issues_additional_details[$key]['issuelinks'];
        $issues->closedSprints = $issues_additional_details[$key]['closedSprints'];
        $issues->currentStatus = $issues_additional_details[$key]['currentStatus'];;
    }
    return $grasshopperIssues;
}

function isEffective($issue){
    if($issue->typeName == 'Bug'){
        return false;
    }

    $ftr = isFirstTimeRight($issue);
    if($ftr!==true){
        return false;
    }

    return true;
}

function showIssuesList($issues, $issueKeysAddedDuringSprint, $type){
    $return = [
        "totalStoryPoints" => 0,
        "bugsStoryPoints" => 0,
        "firstTimeRightStoryPoints" => 0,
        "effectiveStoryPoints" => 0
    ];

    $i = 0;

    echo "<table border=1>";
    echo "<tr color='green'>";
    echo "
        <th>#</th>
        <th>Key</th>
        <th>Type</th>
        <th style='width:40%''>Summary</th>
        <th>Status</th>
        <th>Story Points</th>
        <th>First Time Right</th>";
    echo "</tr>";

    foreach($issues as $value){
        $ftr = isFirstTimeRight($value);
        $ftrStr = $ftr===true? "Yes":"No";
        if($type != "completed"){
            $ftrStr = "N/A";
        }

        $return['totalStoryPoints'] += getStoryPoints($value)['current'];

        if($value->typeName == 'Bug')
            $return['bugsStoryPoints'] += getStoryPoints($value)['current'];

        if($ftr === true){
            $return['firstTimeRightStoryPoints'] += getStoryPoints($value)['current'];
        }

        if( isEffective($value) ){
            $return['effectiveStoryPoints'] += getStoryPoints($value)['current'];
        }

        echo "<tr>";
        echo
        "
            <td>", ++$i, "</td>
            <td>", issueLink($value->key, $issueKeysAddedDuringSprint), "</td>
            <td>", $value->typeName, "</td>
            <td>", $value->summary, "</td>
            <td>", $value->statusName, "</td>
            <td>", getStoryPoints($value)['display'], "</td>
            <td>", $ftrStr , "</td>";
        echo "</tr>";
    }
    echo "</table>";

    return $return;
}

function getStoryPoints($issue){
    $storyPoints = ['initial'=>0, 'current'=>0];
    $storyPoints['initial'] = $issue->estimateStatistic->statFieldValue->value;
    $storyPoints['current'] = $issue->currentEstimateStatistic->statFieldValue->value;

    $storyPoints['display'] = $issue->currentEstimateStatistic->statFieldValue->value;
    if( $storyPoints['initial'] != $storyPoints['current']){
        $storyPoints['display'] = $storyPoints['initial']?$storyPoints['initial']:"- " . " -> " . $storyPoints['current'];
    }

    return $storyPoints;
}

function issueLink($issueKey, $issueKeysAddedDuringSprint) {
    if( $issueKeysAddedDuringSprint->$issueKey ){
        $issueKey = $issueKey . "*";
    }
    return "<a href='https://" . getenv('JIRA_DOMAIN')."/browse/" . $issueKey . "'>" . $issueKey . "</a>";
}

function isFirstTimeRight($issue) {
    // If the issue is not closed yet, it is not ftr
    if( strtolower($issue->status->name) != 'done' && strtolower($issue->status->name) != 'closed' ) {
        return false;
    }

    if( strtolower($issue->currentStatus) != 'done' && strtolower($issue->currentStatus) != 'closed' ) {
        return false;
    }

    // If the issue belongs to later Sprints, it is not ftr
    foreach($issue->closedSprints as $spr){
        if($spr->id > $_GET['sprint'])
            return false;
    }

    foreach ($issue->issuelinks as $linkedIssue) {
        if($linkedIssue->type->outward=="causes" && isset($linkedIssue->outwardIssue) ){
            return false;
        }
    }

    return true;
}
?>
</body>
</html>