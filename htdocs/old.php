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

require_once 'header.php';

require_once "vendor/autoload.php";
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();


$sprint = isset($_GET['sprint']) ? $_GET['sprint']: null;

if(!$sprint){
    die("Please specify a Sprint!");
}

$baseuri = "https://" . getenv('JIRA_DOMAIN');


// Get sprint Details
$api = "/rest/agile/1.0/sprint/$sprint";
$response = \Httpful\Request::get($baseuri.$api)                  // Build a GET request...
            ->withoutStrictSsl()
            ->authenticateWith(getenv('JIRA_USERNAME'), getenv('JIRA_PASSWORD'))  // authenticate with basic auth...
            ->send();

$sprintName = $response->body->name;

echo "<h1>Sprint Name: ", $response->body->name, "</h1>";
$stDate = new DateTime($response->body->startDate);
echo "<h3>Sprint Start: "   , $stDate->format('M j, Y'), "</h3>";
$endDate = new DateTime($response->body->endDate);
echo "<h3>Sprint End: "   , $endDate->format('M j, Y'), "</h3>";

// echo "EndDate Raw: ";
// var_dump($endDate);

// Get Sprint Issues
$api = "/rest/agile/1.0/sprint/$sprint/issue";
$response = \Httpful\Request::get($baseuri.$api)                  // Build a GET request...
            ->withoutStrictSsl()
            ->authenticateWith(getenv('JIRA_USERNAME'), getenv('JIRA_PASSWORD'))  // authenticate with basic auth...
            ->send();

//echo "<pre>";
//print_r($response->body);
//die;
echo "<h3> Number of Stories/Issues: ", $response->body->total, "</h3>";

echo "<table border=1>";
echo "<tr color='green'>";
echo "  <th>Key</th>
        <th>Type</th>
        <th>Resolution <br/>Within Sprint</th>
        <th>Current Status</th>
        <th style='width:40%''>Summary</th>
        <th>Story Points</th>
        <th>First Time Right</th>";
echo "</tr>";

$totalStoryPoints = 0;
$bugsPoint = 0;
$firstTimeRightPoint = 0;
$reopenedStoryPoints = 0;
$ftr = true; // First Time Right
$effectiveStoryPointsDone = 0;


foreach($response->body->issues as $value){
    if($value->key=='BWUI-871'){
//        echo "<pre>";
//        print_r($value);
//        die;
    }

    //if($value->fields->status->name=='Done'){

        foreach ($value->fields->closedSprints as $sprint) {
            if(isset($sprint->name) && strtolower($sprint->name) == strtolower($sprintName)){
                $value->fields->status->inSprintStatus = $sprint->state;
                if($sprint->state =='closed' || strtolower($sprint->state)=='done'){
                    //die();
                    $totalStoryPoints += $value->fields->customfield_10123;

                    if($ftr = isFirstTimeRight($value)){
                        $firstTimeRightPoint += $value->fields->customfield_10123;
                    }

                    if($value->fields->issuetype->name=="Bug"){
                        $bugsPoint += $value->fields->customfield_10123;
                    }
                }
            }
        }
    //}



    echo "<tr>";
    echo "  <td>", issueLink($value->key), "</td>
            <td>", $value->fields->issuetype->name, "</td>
            <td>", $value->fields->status->inSprintStatus, "</td>
            <td>", $value->fields->status->name, "</td>
            <td>", $value->fields->summary, "</td>
            <td>", $value->fields->customfield_10123, "</td>
            <td>", $ftr ? "Yes":"No", "</td>";
    echo "</tr>";

    // If the Issue is FTR and not a bug
    if($ftr && strtolower($value->fields->issuetype->name) != 'bug'){
        $effectiveStoryPointsDone += $value->fields->customfield_10123;
    }
}
echo "</table>";

echo "<br/><br/><br/><br/><br/><br/>";
echo "<h3>Effective Story Points Done: ", $effectiveStoryPointsDone, "</h3>";
echo "<h3>Stories NotDone/Reopened: ", ($totalStoryPoints-$effectiveStoryPointsDone-$bugsPoint);
echo "<h3>Bugs Done: ", $bugsPoint, "</h3>";
echo "<h3>Total Story Points : ", $totalStoryPoints, "</h3>";

if($totalStoryPoints>0){
    echo "<h3>First Time Right Done(%): ", round($firstTimeRightPoint*100/$totalStoryPoints);
}

function issueLink($issueKey) {
    return "<a href='https://" . getenv('JIRA_DOMAIN')."/browse/" . $issueKey . "'>" . $issueKey . "</a>";
}

function isFirstTimeRight($issue) {
//    if($issue->key == 'BWUI-868'){
//        echo "<pre>";
//        print_r($issue->fields->status->name);
//        die;
//    }

    // If the issue is not closed yet, it is not ftr
    if( strtolower($issue->fields->status->name) != 'done' && strtolower($issue->fields->status->name) != 'closed' ) {
        return false;
    }


    // If the issue belongs to later Sprints, it is not ftr
    foreach($issue->closedSprints as $spr){
        if($spr->id > $_GET['sprint'])
            return false;
    }

    foreach ($issue->fields->issuelinks as $linkedIssue) {
        if($linkedIssue->type->outward=="causes" && isset($linkedIssue->outwardIssue) ){
            return false;
        }
    }


    return true;
}
?>
</body>
</html>
