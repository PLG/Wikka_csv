<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

//$DEBUG= 0;

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

if (!function_exists('rndw')) {
	function rndw($length=4) {
		return substr(str_shuffle("qwertyuiopasdfghjklzxcvbnm"),0,$length);
	}
}

if (!defined('PATTERN_ARGUMENT'))		define('PATTERN_ARGUMENT', '(?:;([^;\)\x01-\x1f\*\?\"<>\|]*))?');
if (!defined('PATTERN_CSS_DEFINITION'))	define('PATTERN_CSS_DEFINITION', '#!\s*((?:t[hrd])(?:\.\w*)?)\s*(\{.*\})');

if (preg_match('/^'.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.'$/su', ';'.$format_option, $args))
	list(, $arg1, $arg2, $arg3) = $args;

$delim= ($arg1 == "semi-colon") ? ";" : ",";

// https://www.phpliveregex.com
// https://www.regular-expressions.info/quickstart.html

// https://www.rexegg.com/regex-lookarounds.html
// asserts what precedes the ; is not a backslash \\\\, doesn't account for \\; (escaped backslash semicolon)
// OMFG! https://stackoverflow.com/questions/40479546/how-to-split-on-white-spaces-not-between-quotes
//
$regex_split_on_delim_not_between_quotes="(?<!\\\\)". $delim ."(?=(?:[^\"]*([\"])[^\"]*\\1)*[^\"]*$)";
$regex_escaped_delim="\\\\". $delim ."";

//---------------------------------------------------------------------------------------------------------------------

$array_csv_lines= preg_split("/[\n]/", $text);

$table_id= rndw(23);

// https://stackoverflow.com/questions/1028248/how-to-combine-class-and-id-in-css-selector
// table, th, td { border: 1px solid black; border-collapse: collapse; }
$css["th, td"]= "{ padding: 1px 10px 1px 10px; }";
$css["th"]= "{ background-color:#ccc; }"; 
$css["tr.even"]= "{ background-color:#ffe; }";
$css["tr.odd"]= "{ background-color:#eee; }";

foreach ($array_csv_lines as $row => $csv_line) 
{
	if ( preg_match('/^'.PATTERN_CSS_DEFINITION.'$/', $csv_line, $a_css) )
	{
		$css[ $a_css[1] ]= $a_css[2];
		//print "#". $table_id ." ". $a_css[1] ." ". $a_css[2] ."\n";

//		if ( preg_match_all('/background-color-?([\w]*)\s*:\s*(#[0-9a-fA-F]{3,6})\s*;/', $csv_line, $a_bkcolors) )
//			foreach ($a_bkcolors[0] as $i => $bkcolors) {
//				$css[ $a_t[1] ][ $a_bkcolors[1][$i] ]= "background-color:". $a_bkcolors[2][$i] ."; ";
//				print "css[". $a_t[1] ."][". $a_bkcolors[1][$i] ."]=". $css[ $a_t[1] ][ $a_bkcolors[1][$i] ] ."<br/>";
//			}

		unset($array_csv_lines[$row]);
		continue;
	}

	break;
}

print "<style>\n";
foreach ($css as $key => $rule)
	print "#". $table_id ." ". $key ." ". $rule ."\n";
print "</style>\n";

$comments= 0;

print "<table id=\"". $table_id ."\">\n";
foreach ($array_csv_lines as $row => $csv_line) 
{
	if (preg_match("/^#|^\s*$/",$csv_line)) {
		$comments++;
		continue;
	}

	print (($row+$comments)%2) ? "<tr class=\"even\" style=\"\" >" : "<tr class=\"odd\" style=\"\" >";

	foreach (preg_split("/". $regex_split_on_delim_not_between_quotes ."/", $csv_line) as $col => $cell)
	{
		//-------------------------------------------------------------------------------------------------------------
		// header

		if (preg_match("/^\"?\s*==(.*)==\s*\"?$/", $cell, $a_header)) 
		{
			$title= $a_header[1];

			if (preg_match("/([\/\\\\|])(.*)\\1$/", $title, $a_align)) 
			{
				print "<style>\n";
				switch ($a_align[1]) {
					case "/" :	print "#". $table_id ." .col". $col ." { text-align:right; }";	break;
					case "\\" :	print "#". $table_id ." .col". $col ." { text-align:left; }";	break;
					case "|" :	print "#". $table_id ." .col". $col ." { text-align:center; }";	break;
				}
				print "</style>\n";

				$title= $a_align[2];
			}

			if (!strcmp($title, "++TOTAL++"))
			{
				if (isset($total_col[$col]))
					print "<th class=\"row". $row ." col". $col ."\" >". sprintf("%0.2f", $total_col[$col]) ."</th>";
				else
					print "<th class=\"error row". $row ." col". $col ."\" >ERROR!</th>";

				continue;
			}

			if (preg_match("/^(.*)([+#])\\2$/", $title, $a_accum)) 
			{
				switch ($a_accum[2]) {
					case "#" :
						$DEBUG= 1; // drop through ...
					case "+" :
						$total_col[$col]= 0;
						break;
				}

				$title= $a_accum[1];
			}

			print "<th class=\"row". $row ." col". $col ."\" >". $this->htmlspecialchars_ent($title) ."</th>";
			continue;
		}

		//-------------------------------------------------------------------------------------------------------------
		// cell

		// if blank, print &nbsp;
		//
		if (preg_match("/^\s*$/",$cell)) 
		{
			print "<td class=\"row". $row ." col". $col ."\" >&nbsp;</td>";
			continue;
		}

		elseif (isset($total_col[$col]) && preg_match("/^\"?([\s\d+\-,.]+)\"?$/", $cell, $matches))
		{
			$title= $cell;
			$cell= preg_replace('/\s+/', '', $matches[1]);

			$format= "ERR";
			$nr= "ERROR!";

			if (preg_match("/^([+-]?)(\d{1,3}(\,\d{3})*|(\d+))(\.(\d{2}))?$/", $cell, $a_usa))
			{
				$format= "US";
				$i= $a_usa[1] . preg_replace('/,/', '', $a_usa[2]);
				$d= $a_usa[1] . $a_usa[5];
				$nr= intval($i) + (intval($d)/100);
				$total_col[$col]+= $nr;

				$cell= sprintf("%0.2f", $nr);
			}
			else 
			{
				//$cell_style.= $css["td"]["error"];
				$cell= "ERROR!";
			}

			print "<td class=\"row". $row ." col". $col ."\" title=\"". $title ."(". $format .")\" style=\"". (($nr <= 0) ? "background-color:#d30; " : "" ) . "\">". $cell ."</td>";

			continue;
		}
			/*
		elseif (isset($total_col[$col]) && preg_match("/^\"?([\s\d+\-,.]+)\"?$/", $cell, $matches))
		{
			if (preg_match("/^([+-]?)(\d{1,3}(\.\d{3})*|(\d+)),(\d{2})$/", $cell, $a_swe))
			{
				$format= "SE";
				$i= $a_swe[1] . preg_replace('/\./', '', $a_swe[2]);
				$d= $a_swe[1] . $a_swe[5];
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
				$cell_style.= "white-space:pre; ";
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

		print "<td class=\"row". $row ." col". $col ."\" >". $cell ."</td>";

		//print "<td id=\"". $id ."\" >ERROR!</td>"; // $this->htmlspecialchars_ent($cell)

	}
	print "</tr>\n";

}
print "</table>\n";

// https://www.w3schools.com/js/js_htmldom_html.asp
print "<script>document.getElementById(\"".$rndID."-r4:c0\").innerHTML = \"New text!\";</script>";
?>
