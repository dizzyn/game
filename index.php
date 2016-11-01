<?php


//--------------- MODEL---------------------------------------------------------

global $welcomeMessage, $teamList, $fileKey;
$publicGameKey = "0AvBjdeDScfvNdEpKOXZkYjFEbDVrX080OTBEQ2VHLWc";
$cityFileKey = "0AlldIuyJ6qegdDBaYzU3ZnFqMDcyOHRmbHBMemNPRnc";
$summitFileKey = "0AvBjdeDScfvNdFlaUkRrV0RiZExoazJPREpjMy1wRUE";
$MSDFileKey = "1lvsp1b1kZZINvwxi84XafFI4Qj-s3BqEqDflOA1gxeM";
$fileKey = $MSDFileKey;

function getWelcomeMessage() {
    global $welcomeMessage, $fileKey;

    if (empty($welcomeMessage)) {
        $result = file_get_contents("https://spreadsheets.google.com/feeds/list/" . $fileKey . "/1/public/values?alt=json", false);
        $xx = json_decode($result);
        $welcomeMessage = $xx->feed->entry[0]->{'gsx$welcomemessage'}->{'$t'};
    }

    return $welcomeMessage;
}

function getTeamList() {
    global $teamList, $fileKey;

    if (empty($teamList)) {

        $teamList = array();

        $result = file_get_contents("https://spreadsheets.google.com/feeds/list/" . $fileKey . "/2/public/values?alt=json", false);
        $xx = json_decode($result);

        foreach ($xx->feed->entry as $row) {
            $teamData = array();
            $teamData["teamname"] = $row->{'gsx$teamname'}->{'$t'};
            $teamData["description"] = $row->{'gsx$description'}->{'$t'};

            $teamList[] = $teamData;
        }
    }

    return $teamList;
}

function getTeamPlan($id) {
    global $fileKey;
    $teamList = getTeamList();

    $sheetId = 3 + $id;

    $team = $teamList[$id];
    $teamPlan = $team['teamPlan'];

    if (empty($teamPlan)) {
        $teamPlan = array();

        $result = file_get_contents("https://spreadsheets.google.com/feeds/list/" . $fileKey . "/" . $sheetId . "/public/values?alt=json", false);
        $xx = json_decode($result);

        foreach ($xx->feed->entry as $row) {
            $stage = array();
            $stage["type"] = $row->{'gsx$type'}->{'$t'};
            $stage["title"] = $row->{'gsx$title'}->{'$t'};
            $stage["desc"] = $row->{'gsx$desc'}->{'$t'};
            $stage["image"] = $row->{'gsx$image'}->{'$t'};
            $stage["password"] = $row->{'gsx$password'}->{'$t'};

            $teamPlan[] = $stage;
        }

        $team['teamPlan'] = $teamPlan;
    }

    return $teamPlan;
}

function getTeamName($id) {
    $teamList = getTeamList();
    return $teamList[$id]["teamname"];
}

function getStage($stages, $id) {
    return $stages[$id];
}

//--------------- CONTROLER-----------------------------------------------------
//session_start();
//session_set_cookie_params(60 * 60 * 44);

global $spreadsheet, $teamId, $stageId, $teamProgress, $messages, $backBtn;

$messages = array();

if (array_key_exists("team", $_COOKIE)) {
    $teamId = $_COOKIE["team"];
}

if (array_key_exists("stage", $_COOKIE)) {
    $stageId = $_COOKIE["stage"];
}

if (array_key_exists("teamProgress", $_COOKIE)) {
    $teamProgress = $_COOKIE["teamProgress"];
}

if (!is_numeric($teamProgress)) {
    $teamProgress = 0;
    setcookie("teamProgress", $teamProgress, time() + 3600 * 4);
}

//var_dump($stageId);
//var_dump(!is_numeric($stageId));
//var_dump(is_numeric($stageId));

if (!is_numeric($stageId)) {
    $stageId = $teamProgress;
    setcookie("stage", $stageId, time() + 3600 * 4);
}

function routeTo($route) {
    if ($route == 'dashboard') {
        renderDashboard();
    } else if ($route == 'stage') {
        renderStage();
    } else {
        renderIndex();
    }
}

function redirectTo($target) {
    header('Location:' . $target);
    die();
}

if (array_key_exists("action", $_GET)) {
    $action = $_GET["action"];
} else {
    $action = "";
}

if ($action == "records") {
    ?>

    <!DOCTYPE html>
    <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        </head>

        <body>

            <?php
            echo file_get_contents("gs://synkac-records/records.html");
            echo "</body></html>";
            die();
        } else if ($action == "choiceteam") { //team selected - go to the team dashboard
            if ($_COOKIE["team"] !== $_GET["id"]) {
                setcookie("team", $_GET["id"], time() + 3600 * 4);
                setcookie("stage", "", time() + 3600 * 4);
                setcookie("teamProgress", "", time() + 3600 * 4);
            }

            redirectTo("dashboard");
        } else if ($action == "choicestage") {

            setcookie("stage", $_GET["id"], time() + 3600 * 4);
            redirectTo("stage");
        } else if ($action == "continue") {

            $stageData = getStage(getTeamPlan($teamId), $stageId);

            if ($stageData["type"] == "password" && strtolower(trim($stageData["password"])) !== strtolower(trim($_GET["password"]))) {
                $messages[] = "Wrong password try again";
            } else {

                if ($stageData["type"] == "log") {

                    $src = file_get_contents("gs://synkac-records/records.html");

                    $options = [
                        "gs" => [
                            "Content-Type" => "text/html",
                            "Content-Encoding" => "utf-8",
                            "acl" => "public-read"
                        ]
                    ];
                    $ctx = stream_context_create($options);
                    $src = "- " . getTeamname($teamId) . " / " . $_GET["password"] . "<br/>" . $src;
                    file_put_contents("gs://synkac-records/records.html", $src, 0, $ctx);
                }

                if ($teamProgress < $stageId) {
                    //seems that there is a error
                } else if ($teamProgress == $stageId) {
                    $teamProgress = $teamProgress + 1;
                    setcookie("teamProgress", $teamProgress, time() + 3600 * 4);
                    setcookie("stage", $teamProgress, time() + 3600 * 4);
                } else { //user has more stages but playin' in history
                    setcookie("stage", $stageId + 1, time() + 3600 * 4);
                }

                redirectTo("stage");
            }
        }

//--------------- VIEWS---------------------------------------------------------

        function renderIndex() {

            echo "<nav class=\"navbar navbar-default navbar-collapse\" role=\"navigation\">";
            echo "<div class=\"navbar-header\">";
            echo "<a class=\"navbar-brand\" href=\"#\">Game</a>";
            echo "</div>";
            echo "</nav>";

            echo "<div class=\"welcome-message\">";
            echo "<h1>" . getWelcomeMessage() . "</h1>";
            echo "</div>";

            echo "<div class=\"list-group list-group-teams\">";

            $teams = getTeamList();
            $i = -1;
            foreach ($teams as $team) {
                $i = $i + 1;
                echo "<div class=\"list-group-item\">";
                echo "<h4 class=\"list-group-item-heading\">" . $team["teamname"] . "</h4>";
                echo "<p class=\"list-group-item-text\">" . $team["description"] . "</p>";
                echo "<a href=\"?action=choiceteam&id=" . $i . "\"><button class=\"btn btn-small btn-lg btn-block\">It's my team!</button></a></p>";
                echo "</div>";
            }

            echo "</div>";
        }

        function renderDashboard() {
            global $teamId, $teamProgress;

            echo "<nav class=\"navbar navbar-default navbar-collapse\" role=\"navigation\">";
            echo "<div class=\"navbar-header\">";

            echo "<a class=\"navbar-brand\" href=\"#\">Stages (" . getTeamname($teamId) . ")</a>";

            echo "</div>";
            echo "</nav>";

            global $backBtn;
            $backBtn = "<a href=\"/\">Change team</a>";

            echo "<ul class=\"stages\">";
            $plan = getTeamPlan($teamId, $spreadsheet);
            $i = -1;
            $stageCount = count(getTeamPlan($teamId));

            foreach ($plan as $row) {
                $i++;
                if ($i < $teamProgress) {
                    echo "<li class=\"passed\"><a href=\"?action=choicestage&id=" . $i . "\">" . $i . "</a></li>";
                } else if ($i == $teamProgress) { //actual stage
                    echo "<li class=\"" . ($stageCount - 1 == $i ? "passed" : "active") . "\"><a href=\"?action=choicestage&id=" . $i . "\">" . $i . "</a></li>";
                } else {
                    echo "<li><span>" . $i . "</span></li>";
                }
            }
            echo "</ul>";
        }

        function renderStage() {
            global $stageId, $teamId, $teamProgress;

            $stageData = getStage(getTeamPlan($teamId), $stageId);

            if (empty($stageData)) {
                echo "error could not find stage record";
            }

            echo "<nav class=\"navbar navbar-default navbar-collapse\" role=\"navigation\">";
            echo "<div class=\"navbar-header\">";
            echo "<div class=\"navbar-brand\">" . $stageId . " | " . $stageData["title"] . "</div>";
            echo "</div>";
            echo "</nav>";

            echo "<div class=\"stage\">";
            if (array_key_exists("image", $stageData)) {
                echo "<p class=\"thumbnail\"><img src=\"" . $stageData["image"] . "\" alt=\"\"/></h2>";
            }
            echo "<div class=\"desc\">" . $stageData["desc"] . "</div>";
            echo "</div>"; // .stage

            $type = $stageData["type"];

            if ($type == 'continue') {
                echo "<form method=\"get\" action=\"stage\">";
                echo "<input type=\"submit\" value=\"Continue\" class=\"btn btn-primary btn-lg btn-block\">";
                echo "<input type=\"hidden\" name=\"action\" value=\"continue\">";
                echo "</form>";
            } else if ($type == 'password') {
                echo "<form method=\"get\" action=\"stage\">";
                echo "<label>Insert password and continue</label>";
                echo "<input type=\"text\" name=\"password\" class=\"form-control input-lg\" placeholder=\"insert password\" style=\"margin-bottom:5px\">";
                echo "<input type=\"submit\" value=\"Continue\" class=\"btn btn-primary btn-lg btn-block\">";
                echo "<input type=\"hidden\" name=\"action\" value=\"continue\">";
                echo "</form>";
            } else if ($type == 'log') {
                echo "<form method=\"get\" action=\"stage\">";
                echo "<label>Insert record</label>";
                echo "<input type=\"text\" name=\"password\" class=\"form-control input-lg\" placeholder=\"Record\" style=\"margin-bottom:5px\">";
                echo "<input type=\"submit\" value=\"Continue\" class=\"btn btn-primary btn-lg btn-block\">";
                echo "<input type=\"hidden\" name=\"action\" value=\"continue\">";
                echo "</form>";
            }

            global $backBtn;
            $backBtn = "<a href=\"dashboard\">Back to dashboard</a>";
        }

//TEST SUITE
//echo "<br/><br/>Welcome Message<br/>";
//echo getWelcomeMessage();
//
//echo "<br/><br/>Team list<br/>";
//$teams = getTeamList();
//foreach ($teams as $team) {
//    foreach ($team as $key => $value) {
//        echo $key . ": " . $value . "<br/>";
//    }
//    echo "<br/>";
//}
//
//echo "<br/><br/>Team plan<br/>";
//$plan = getTeamPlan("0");
//foreach ($plan as $row) {
//    foreach ($row as $key => $value) {
//        echo $key . ": " . $value . "<br/>";
//    }
//    echo "<br/>";
//}
//die();
        ?>
        <!DOCTYPE html>
    <html>
        <head>
            <title><?php //echo resource                                                                              ?></title>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
            <meta http-equiv="X-UA-Compatible" content="IE=edge"/>

            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

            <script src="js/jquery.js" type="text/javascript"></script>
            <script src="js/init.js" type="text/javascript"></script>
            <script src="js/bootstrap.min.js" type="text/javascript"></script>

            <link rel="stylesheet" type="text/css" href="css/style.css"/>
        </head>

        <body class="page page-cards">
            <div class="page-inner">

                <?php
                global $messages;

//            echo $teamProgress . "-p<br/>";
//            echo $stageId . "-s<br/>";

                if (count($messages) > 0) {
                    foreach ($messages as $value) {
                        echo "<div class=\"alert alert-danger\">" . $value . "</div>";
                    }
                }

    //            var_dump($_SERVER);

                $routeTo = $_SERVER["REQUEST_URI"];

                $rpos = strpos($routeTo, "?");

                if ($rpos !== FALSE) {
                    $routeTo = substr($routeTo, 0, $rpos);
                }

                $routeTo = str_replace("/", "", $routeTo);

                routeTo($routeTo);
                ?>
                <div class="footer">
                    <?php
                    global $backBtn;
                    echo "<span class=\"back\">" . $backBtn . "</span>";
                    ?>
                    Need help? Call <a href="tel:+420605387276">605 387 276</a>
                </div>
            </div><!--/.page-inner -->
        </body>
    </html>
