<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

// Copy the code below into a file named formatters/csv.php
// And give it the same file permissions as the other files in that directory.

//$DEBUG= 0;

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

if (!function_exists('rndw')) {
	function rndw($length=4) {
		return substr(str_shuffle("qwertyuiopasdfghjklzxcvbnm"),0,$length);
	}
}

if (!defined('PATTERN_ARGUMENT')) define('PATTERN_ARGUMENT', '(?:;([^;\)\x01-\x1f\*\?\"<>\|]*))?');

if (preg_match('/^'.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.'$/su', ';'.$format_option, $args))
	list(, $arg1, $arg2, $arg3) = $args;

$delim= ($arg1 == "semi-colon") ? ";" : ",";

$comments= 0;
$rndID= rndw();

$style["th"][""]= "background-color:#ccc; ";
$style["th"]["error"]= "background-color:#d30; ";
$style["tr"]["even"]= "background-color:#ffe; ";
$style["tr"]["odd"]= "background-color:#eee; ";
$style["td"]["error"]= "background-color:#d30; ";

// https://www.phpliveregex.com
// https://www.regular-expressions.info/quickstart.html

// https://www.rexegg.com/regex-lookarounds.html
// asserts what precedes the ; is not a backslash \\\\, doesn't account for \\; (escaped backslash semicolon)
// OMFG! https://stackoverflow.com/questions/40479546/how-to-split-on-white-spaces-not-between-quotes
//
$regex_split_on_delim_not_between_quotes="(?<!\\\\)". $delim ."(?=(?:[^\"]*([\"])[^\"]*\\1)*[^\"]*$)";
$regex_escaped_delim="\\\\". $delim ."";

print "<table><tbody>\n";
foreach ($array_csv_lines= preg_split("/[\n]/", $text) as $row => $csv_line) 
{
	if (preg_match("/^#|^\s*$/",$csv_line)) 
	{
		if ( preg_match('/^#!\s*(t[hrd])\s*{/', $csv_line, $a_t) )
			if ( preg_match_all('/background-color-?([\w]*)\s*:\s*(#[0-9a-fA-F]{3,6})\s*;/', $csv_line, $a_bkcolors) )
				foreach ($a_bkcolors[0] as $i => $bkcolors) 
				{
					$style[ $a_t[1] ][ $a_bkcolors[1][$i] ]= "background-color:". $a_bkcolors[2][$i] ."; ";
					// print "style[". $a_t[1] ."][". $a_bkcolors[1][$i] ."]=". $style[ $a_t[1] ][ $a_bkcolors[1][$i] ] ."<br/>";
				}

		$comments++;
		continue;
	}

	print (($row+$comments)%2) ? "<tr style=\"". $style["tr"]["even"] ."\">" : "<tr style=\"". $style["tr"]["odd"] ."\">";

	foreach (preg_split("/". $regex_split_on_delim_not_between_quotes ."/", $csv_line) as $col => $cell)
	{
		$id= $rndID."-r".$row.":c".$col;

		if ($row == $comments)
			$style[$col]= "padding: 1px 10px 1px 10px; ";

		//-------------------------------------------------------------------------------------------------------------
		// header

		if (preg_match("/^\"?\s*==(.*)==\s*\"?$/", $cell, $a_header)) 
		{
			$title= $a_header[1];

			if (preg_match("/([\/\\\\|])(.*)\\1$/", $title, $a_align)) 
			{
				switch ($a_align[1]) {
					case "/" :	$style[$col].= "text-align:right; ";	break;
					case "\\" :	$style[$col].= "text-align:left; ";		break;
					case "|" :	$style[$col].= "text-align:center; ";	break;
				}

				$title= $a_align[2];
			}
/*
			if (!strcmp($title, "++TOTAL++"))
			{
				if (isset($total_i[$col]))
					print "<th style=\"". $style["th"][""] . $style[$col] ."\">". sprintf("%0.2f", $total_i[$col] + ($total_d[$col]/100)) ."</th>";
				else
					print "<th style=\"". $style["th"]["error"] . $style[$col] ."\">ERROR!</th>";

				continue;
			}

			if (preg_match("/^(.*)([+#])\\2$/", $title, $a_accum)) 
			{
				switch ($a_accum[2]) {
					case "#" :
						$DEBUG= 1; // drop through ...
					case "+" :
						$total_i[$col]= 0;
						$total_d[$col]= 0;
						break;
				}

				$title= $a_accum[1];
			}
*/
			print "<th id=\"". $id ."\" style=\"". $style["th"][""] . $style[$col] ."\">". $this->htmlspecialchars_ent($title) ."</th>";
			continue;
		}

		//-------------------------------------------------------------------------------------------------------------
		// cell

		// if blank, print &nbsp;
		//
		if (preg_match("/^\s*$/",$cell)) 
		{
			print "<td id=\"". $id ."\" style=\"". $style[$col] ."\">&nbsp;</td>";
			continue;
		}
/*		
		elseif ($total[$col] && preg_match("/^\"?([\s\d+\-,.]+)\"?$/", $cell, $matches))
		{
			$matches_nows= preg_replace('/\s+/', '', $matches[1]);

			if (preg_match("/^([+-]?)(\d{1,3}(\.\d{3})*|(\d+)),(\d{2})$/", $matches_nows, $swe))
			{
				$format= "SE";
				$i= $swe[1] . preg_replace('/\./', '', $swe[2]);
				$d= $swe[1] . $swe[5];
			}
			elseif (preg_match("/^([+-]?)(\d{1,3}(\,\d{3})*|(\d+))(\.(\d{2}))?$/", $matches_nows, $usa))
			{
				$format= "US";
				$i= $usa[1] . preg_replace('/,/', '', $usa[2]);
				$d= $usa[1] . $usa[5];
			}
			else
			{
				$total[$col]= -1;
				print "<td style=\"". $style[$col] ."\">".$this->htmlspecialchars_ent($matches_nows)."</td>";
				continue;
			}

			$total_i[$col]+= intval($i);
			$total_d[$col]+= intval($d);
			$nr= $i + ($d/100);

			if ($DEBUG == 1)
				print "<td style=\"". $style[$col] ."\">". $cell ."(". $format .")= " . sprintf("%.2f", $nr) ."+= ". $total_i[$col] ." ". $total_d[$col] ."</td>";
			else
				print "<td title=\"". $cell ."(". $format .")\" style=\"". (($nr <= 0) ? "background-color:#d30; " : "" ) . $style[$col] ."\">". sprintf("%.2f", $nr) ."</td>";

			continue;
		}
*/		
		// extract the cell out of it's quotes
		//
        if (preg_match("/^\s*(\"?)(.*?)\\1\s*$/", $cell, $matches))
		{
			if ($matches[1] == "\"")
			{
				$style[$col].= "white-space:pre; ". $style[$col];
				$cell= $matches[2];
			}
			else
				$cell= preg_replace("/". $regex_escaped_delim ."/", $delim, $matches[2]);
		}

		// test for CamelLink
		//
		if (preg_match_all("/\[\[([[:alnum:]]+)\]\]/", $cell, $all_links))
		{
			foreach ($all_links[1] as $i => $camel_link) 
				$cell = preg_replace("/\[\[". $camel_link ."\]\]/", $this->Link($camel_link), $cell);
		}		
		// test for [[url|label]]
		//
		elseif (preg_match_all("/\[\[(.*?\|.*?)\]\]/", $cell, $all_links))
		{
			foreach ($all_links[1] as $i => $url_link) 
				if(preg_match("/^\s*(.*?)\s*\|\s*(.*?)\s*$/su", $url_link, $matches)) {
					$url = $matches[1];
					$text = $matches[2];
					$cell = $this->Link($url, "", $text, TRUE, TRUE, '', '', FALSE);	
				}
		}		
		else
			$cell= $this->htmlspecialchars_ent($cell);

		print "<td id=\"". $id ."\" style=\"". $style[$col] ."\">". $cell ."</td>";

		//print "<td id=\"". $id ."\" style=\"". $style["td"]["error"] . $style[$col] ."\">ERROR!</td>"; // $this->htmlspecialchars_ent($cell)

	}
	print "</tr>\n";

}
print "</tbody></table>\n";
?>
