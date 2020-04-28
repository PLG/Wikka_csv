<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

if (!function_exists('rndw')) {
	function rndw($length=4) {
		return substr(str_shuffle("qwertyuiopasdfghjklzxcvbnm"),0,$length);
	}
}

//TODO:
$tmp= '.,2'; #,###.###
$currency_grouping= '\.';
$currency_decimal= '\,';
$currency_places= 2;

if (!defined('PATTERN_ARGUMENT'))		define('PATTERN_ARGUMENT', '(?:;([^;\)\x01-\x1f\*\?"<>\|]*))?');
if (!defined('PATTERN_CSS_DEFINITION'))	define('PATTERN_CSS_DEFINITION', '#!\s*(t[hrd](?:\.\w*)?|(?:\.\w*))\s*(\{.*\})');
if (!defined('PATTERN_CURRENCY'))		define('PATTERN_CURRENCY', '([+-]?)(\d{1,3}(?:'. $currency_grouping .'\d{3})*|(?:\d+))(?:'. $currency_decimal .'(\d{'. $currency_places .'}))?');

//TODO:
if (!function_exists('parse_currency')) {
	function parse_currency($cell, $grouping) 
	{
		if (preg_match('/^'.PATTERN_CURRENCY.'$/', $cell, $a_currency))
		{
			//TODO:
			$format= 'US';

			$cell= $a_currency[1] . preg_replace('/'.$grouping.'/', '', $a_currency[2]);
			if ( isset($a_currency[3]) )
				$cell.= '.'. $a_currency[3];

			return floatval( $cell );
		}

		return "ERROR!";
	}
}

if (preg_match('/^'.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.'$/su', ';'.$format_option, $args))
	list(, $arg1, $arg2, $arg3) = $args;

$delim= ($arg1 == 'semi-colon') ? ';' : ',';

// https://www.phpliveregex.com
// https://www.regular-expressions.info/quickstart.html

// https://www.rexegg.com/regex-lookarounds.html
// asserts what precedes the ; is not a backslash \\\\, doesn't account for \\; (escaped backslash semicolon)
// OMFG! https://stackoverflow.com/questions/40479546/how-to-split-on-white-spaces-not-between-quotes
//
//TODO:
$regex_split_on_delim_not_between_quotes='(?<!\\\)'. $delim .'(?=(?:[^"]*(["])[^"]*\\1)*[^"]*$)';
$regex_escaped_delim='\\\\'. $delim .'';

//---------------------------------------------------------------------------------------------------------------------

$array_csv_lines= preg_split("/[\n]/", $text);

$table_id= rndw(23);

// https://stackoverflow.com/questions/1028248/how-to-combine-class-and-id-in-css-selector
//TODO: table, th, td { border: 1px solid black; border-collapse: collapse; }
$css['th, td']= '{ padding: 1px 10px 1px 10px; }';
$css['th']= '{ background-color:#ccc; }'; 
$css['tr.even']= '{ background-color:#ffe; }';
$css['tr.odd']= '{ background-color:#eee; }';
$css['.warning']= '{ background-color:#f00; }';

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
	print '#'. $table_id .' '. $key .' '. $rule ."\n";
print "</style>\n";

$comments= 0;

print '<table id="'. $table_id .'">'. "\n";
foreach ($array_csv_lines as $row => $csv_line) 
{
	if (preg_match('/^#|^\s*$/',$csv_line)) {
		$comments++;
		continue;
	}

	print (($row+$comments)%2) ? '<tr class="even" >' : '<tr class="odd" >';

	foreach (preg_split('/'. $regex_split_on_delim_not_between_quotes .'/', $csv_line) as $col => $csv_cell)
	{
		//-------------------------------------------------------------------------------------------------------------
		// header

		$cell= trim($csv_cell);

		if (preg_match('/^("?)\s*==(.*)==\s*\1$/', $cell, $a_header)) 
		{
			$title= trim($a_header[2]);

			if (preg_match('/([\/\\\\|])(.*)\1$/', $title, $a_align)) 
			{
				print "<style>\n";
				switch ($a_align[1]) {
					case '/' :	print '#'. $table_id .' .col'. $col .' { text-align:right; }';	break;
					case '\\' :	print '#'. $table_id .' .col'. $col .' { text-align:left; }';	break;
					case '|' :	print '#'. $table_id .' .col'. $col .' { text-align:center; }';	break;
				}
				print "</style>\n";

				$title= $a_align[2];
			}

			if (0 == strcmp($title, '++TOTAL++'))
			{
				if (( isset($total_col[$col])  && 0 != strcmp($total_col[$col],"ERROR!") )
				xor ( isset($total_row[$row])  && 0 != strcmp($total_row[$row],"ERROR!") ))
				{
					if ( isset($total_col[$col]) ) {
						print '<th class="row'. $row .' col'. $col .'" >'. sprintf("%0.2f", $total_col[$col]) .'</th>';
						unset($total_col[$col]);
					}
					else { // if ( isset($total_row[$row]) ) // because its xor
						print '<th class="row'. $row .' col'. $col .'" >'. sprintf("%0.2f", $total_row[$row]) .'</th>';
						unset($total_row[$row]);
					}

					continue;
				}

				print '<th class="warning row'. $row .' col'. $col .'" >ERROR!</th>';

				unset($total_col[$col]);
				unset($total_row[$row]);

				continue;
			}

			if (preg_match('/^(.*)\s*([+#])\2$/', $title, $a_accum)) {
				$title= $a_accum[1];
				$total_col[$col]= 0;
			}
			elseif (preg_match('/^([+#])\1(.*)\s*$/', $title, $a_accum)) {
				$title= $a_accum[2];
				$total_row[$row]= 0;
			}

			print '<th class="row'. $row .' col'. $col .'" >'. $this->htmlspecialchars_ent($title) .'</th>';
			continue;
		}

		//-------------------------------------------------------------------------------------------------------------
		// cell

		$cell_style='';

		// extract the cell out of it's quotes
		//
        if (preg_match('/^\s*("?)(.*?)\1\s*$/', $cell, $matches))
		{
			if ($matches[1] == '"')
			{
				$cell_style.= 'white-space:pre; ';
				$cell= $matches[2];
			}
			else
				$cell= preg_replace('/'. $regex_escaped_delim .'/', $delim, $matches[2]);
		}

		if ( isset($total_col[$col]) || isset($total_row[$row]) )
		{
			$title= $cell;

			//$nr= parse_currency($cell, $currency_grouping);
			//var_dump($nr);

			if (preg_match('/^'.PATTERN_CURRENCY.'$/', $cell, $a_currency))
			{
				//TODO:
				$format= 'US';

				$cell= $a_currency[1] . preg_replace('/'.$currency_grouping.'/', '', $a_currency[2]);
				if ( isset($a_currency[3]) )
					$cell.= '.'. $a_currency[3];

				$nr= floatval( $cell );

				if ( isset($total_col[$col]) && 0 != strcmp($total_col[$col],"ERROR!") )
					$total_col[$col]+= $nr;

				if ( isset($total_row[$row]) && 0 != strcmp($total_row[$row],"ERROR!") )
					$total_row[$row]+= $nr;

				print '<td class="'. (($nr <= 0) ? 'warning' : '' ) .' row'.$row .' col'.$col .'" title="'. $title .'('. $format .')" >'. sprintf('%0.2f', $nr) .'</td>';
				continue;
			}

			if ( isset($total_col[$col]) )
				$total_col[$col]= "ERROR!";

			if ( isset($total_row[$row]) )
				$total_row[$row]= "ERROR!";

			print '<td class="warning row'.$row .' col'.$col .'" title="'. $title .'(ERR)" >ERROR!</td>';
			continue;
		}

		// if blank, print &nbsp;
		//
		if (preg_match('/^\s*$/',$cell)) 
		{
			print '<td class="row'. $row .' col'. $col .'" >&nbsp;</td>';
			continue;
		}

		// test for CamelLink
		//
		if (preg_match_all('/\[\[([[:alnum:]]+)\]\]/', $cell, $all_links))
		{
			foreach ($all_links[1] as $i => $camel_link) 
				$cell = preg_replace('/\[\['. $camel_link .'\]\]/', $this->Link($camel_link), $cell);
		}		
		// test for [[url|label]]
		//
		elseif (preg_match_all('/\[\[(.*?\|.*?)\]\]/', $cell, $all_links))
		{
			foreach ($all_links[1] as $i => $url_link) 
				if(preg_match('/^\s*(.*?)\s*\|\s*(.*?)\s*$/su', $url_link, $matches)) {
					$url = $matches[1];
					$text = $matches[2];
					$cell = $this->Link($url, '', $text, TRUE, TRUE, '', '', FALSE);	
				}
		}		
		else
			$cell= $this->htmlspecialchars_ent($cell);

		print '<td class="row'. $row .' col'. $col .'" style="'. $cell_style .'" >'. $cell .'</td>';

	}
	print "</tr>\n";

}
print "</table>\n";

// https://www.w3schools.com/js/js_htmldom_html.asp
print '<script>document.getElementById("'. $rndID. '-r4:c0").innerHTML = "New text!";</script>';
?>
