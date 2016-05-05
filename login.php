<?php

    session_start();

    require_once("util.php");
    require_once("database.php");

    if($_REQUEST["language"])
    {
        $language = $_REQUEST["language"];
    }

    require_once('language.php');

    $html  = "<!DOCTYPE html>\n";
    $html .= "<html>\n";
    $html .= "    <head>\n";
    $html .= "        <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\">\n";
    $html .= "        <link rel=\"shortcut icon\" href=\"favicon.ico\">\n";
    $html .= "        <link rel=\"stylesheet\" href=\"layout.css\" type=\"text/css\">\n";
    $html .= "        <title>$title_text (v$version_text)</title>\n";
    $html .= "    </head>\n";
    $html .= "    <body>\n";

    $db = null;
    if (!installation_finished()) {
        $msg = $lang['incomplete-install'];
    } else {
        try {
            $db = connect();
        } catch (PDOException $e) {
            $msg = $lang['database-fail'] . "<br>" . $e->getMessage();
        }
    }

    if (!is_null($db)) {
        $num_users = $db->get_count("users");

        if ($num_users < 1)
        {
            $msg = $lang["no-data"];
        }
        else
        {
            unset($_SESSION['ID']);
            $html .= "        <div class=\"loginwindow\">\n";
            $html .= "            <h2>$title_text (v$version_text)</h2>\n";
            if($public_page == "no")
                $html .= "            $lang[page_private]\n";
            $html .= "            <form action=\"session.php\" method=\"post\">\n";
            $html .= "                <table>";
            $html .= "                    <tr>\n";
            $html .= "                        <td>\n";
            $html .= "                            $lang[login_username]: \n";
            $html .= "                        </td>\n";
            $html .= "                        <td>\n";
            $html .= "                            <input type=\"text\" name=\"username\" size=\"10\">\n";
            $html .= "                        </td>\n";
            $html .= "                    </tr>\n";
            $html .= "                    <tr>\n";
            $html .= "                        <td>\n";
            $html .= "                            $lang[login_password]: \n";
            $html .= "                        </td>\n";
            $html .= "                        <td>\n";
            $html .= "                            <input type=\"password\" name=\"password\" size=\"10\">\n";
            $html .= "                        </td>\n";
            $html .= "                    </tr>\n";
            $html .= "                    <tr>\n";
            $html .= "                        <td colspan=\"2\">\n";
            $html .= "                            <input type=\"submit\" value=\"$lang[login_button]\">\n";
            $html .= "                        </td>\n";
            $html .= "                    </tr>\n";
            $html .= "                </table>\n";
            $html .= "            </form>\n";
            $html .= "            </div>\n";
        }
    }
    if (isset($msg)) {
        $html .= "            <div style=\"text-align: center\">\n";
        $html .= "                $msg\n";
        $html .= "            </div>\n";
    }
	$html .= "         <!--       <div id=\"footertext\">\n";
    $html .= "                    $footer_text <a href=\"http://forum.xda-developers.com/showthread.php?t=340667\" target=\"_blank\">TrackMe</a>\n";
    $html .= "                </div>\n -->  ";
	//google analytics
	if(isset($googleanalyticsaccount)) {
	$html .= "<script type=\"text/javascript\">\n";
	$html .= "   var gaJsHost = ((\"https:\" == document.location.protocol) ? \"https://ssl.\" : \"http://www.\");\n";
	$html .= "   document.write(unescape(\"%3Cscript src='\" + gaJsHost + \"google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E\"));\n";
	$html .= "</script>\n";
	$html .= "<script type=\"text/javascript\">\n";
	$html .= "   var pageTracker = _gat._getTracker(\"$googleanalyticsaccount\");\n";
	$html .= "   pageTracker._initData();\n";
	$html .= "   pageTracker._trackPageview();\n";
	$html .= "</script>\n";
	}
    $html .= "            </body>\n";
    $html .= "        </html>\n";

    $db = null;  // Close database
    print $html;

?>
