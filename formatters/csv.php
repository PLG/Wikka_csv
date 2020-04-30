<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

//---------------------------------------------------------------------------------------------------------------------

// https://www.phpliveregex.com
// https://www.regular-expressions.info/quickstart.html

if (!defined('ERROR'))					define('ERROR', 'error');
if (!defined('PATTERN_ARGUMENT'))		define('PATTERN_ARGUMENT', '(?:;([^;\)\x01-\x1f\*\?"<>\|]*))?');
if (!defined('PATTERN_SPILL_GROUP')) define('PATTERN_SPILL_GROUP', '([^\)]*)');
if (!defined('PATTERN_CSS_DEFINITION'))	define('PATTERN_CSS_DEFINITION', '#!\s*(table, th, td|th, td|t[hrd](?:\.\w*)?|(?:\.\w*))\s*(\{.*\})');

if (preg_match('/^'.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_SPILL_GROUP.'$/su', ';'.$format_option, $args))
	list(, $arg1, $arg2, $arg3, $arg4, $invalid) = $args;

$DELIM= ($arg1 == 'semi-colon') ? ';' : ',';

// https://www.rexegg.com/regex-lookarounds.html
// asserts what precedes the ; is not a backslash \\\\, doesn't account for \\; (escaped backslash semicolon)
// OMFG! https://stackoverflow.com/questions/40479546/how-to-split-on-white-spaces-not-between-quotes
//
$PATTERN_NO_SPLIT_QUOTED_DELIM='(?<!\\\)'. $DELIM .'(?=(?:[^"]*(["])[^"]*\1)*[^"]*$)';
$PATTERN_ESC_DELIM='\\\\'. $DELIM .'';

$ARRAY_CSV_LINES= preg_split("/[\n]/", $text);
$comments= 0;

//---------------------------------------------------------------------------------------------------------------------

$currency_formats['US']= array('\,', '\.', 2);
$currency_formats['SE']= array('\.', '\,', 2);

$selected_formats= array('US','SE');

//if (!function_exists('parse_currency')) { } // doesn't see global scope variables, support 'static'
// https://www.php.net/manual/en/functions.anonymous.php
//
$parse_currency= function ($cell) use (&$currency_formats, &$selected_formats) 
{
	foreach ($selected_formats as $format)
	{
		list($grouping, $decimal, $places)= $currency_formats[$format];
		$PATTERN_CURRENCY= '([+-]?)(\d{1,3}(?:'. $grouping .'\d{3})*|(?:\d+))(?:'. $decimal .'(\d{'. $places .'}))?';

		if (preg_match('/^'.$PATTERN_CURRENCY.'$/', $cell, $a_currency))
		{
			$cell= $a_currency[1] . preg_replace('/'.$grouping.'/', '', $a_currency[2]);
			if ( isset($a_currency[3]) )
				$cell.= '.'. $a_currency[3];

			return array(true, floatval($cell), $format);
		}
	}

	return array(false, $cell, 'ERR');
};

//---------------------------------------------------------------------------------------------------------------------

// https://stackoverflow.com/questions/3302857/algorithm-to-get-the-excel-like-column-name-of-a-number
$spreadsheet_baseZ= function ($n) {
    for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
        $r = chr($n%26 + 0x41) . $r;
    return $r;
};

$rndw= function ($length=4) {
	return substr(str_shuffle("qwertyuiopasdfghjklzxcvbnm"),0,$length);
};

$ID_TABLE= $rndw(23);

// https://stackoverflow.com/questions/1028248/how-to-combine-class-and-id-in-css-selector
//$css['table, th, td']= '{ border: 1px solid black; border-collapse: collapse; }';
$css['th, td']= '{ padding: 1px 10px 1px 10px; }';
$css['th']= '{ background-color:#ccc; }'; 
$css['tr.even']= '{ background-color:#ffe; }';
$css['tr.odd']= '{ background-color:#eee; }';
$css['.warning']= '{ background-color:#f00; }';
$css['.total']= '{ border: 1px solid black; border-collapse: collapse; }';

foreach ($ARRAY_CSV_LINES as $row => $csv_line) 
{
	if ( preg_match('/^'.PATTERN_CSS_DEFINITION.'$/', $csv_line, $a_css) )
	{
		$css[ $a_css[1] ]= $a_css[2];

		unset($ARRAY_CSV_LINES[$row]);
		$comments++;
		continue;
	}

	if (preg_match('/^#/',$csv_line))
		print "Invalid CSS directive: [". $csv_line ."]\n";

	break;
}

print "<style>\n";
foreach ($css as $key => $rule) 
{
	foreach (explode(',', $key) as $tag) 
		$key= preg_replace('/'.trim($tag).'/', '#'.$ID_TABLE.' '.trim($tag), $key);
	print $key .' '. $rule ."\n";
}
print "</style>\n";

//---------------------------------------------------------------------------------------------------------------------

print '<table id="'. $ID_TABLE .'">'. "\n";
foreach ($ARRAY_CSV_LINES as $csv_row => $csv_line) 
{
	if (preg_match('/^#|^\s*$/',$csv_line)) {
		$comments++;
		continue;
	}

	$row= $csv_row - $comments;

	print ($row %2) ? '<tr class="even" >' : '<tr class="odd" >';

	foreach (preg_split('/'. $PATTERN_NO_SPLIT_QUOTED_DELIM .'/', $csv_line) as $col => $csv_cell)
	{
		$id= $ID_TABLE ."-". $spreadsheet_baseZ($col) . $row;

		//-------------------------------------------------------------------------------------------------------------
		// header

		$cell= trim($csv_cell);

		if (preg_match('/^("?)\s*==(.*)==\s*\1$/', $cell, $a_header)) 
		{
			$title= trim($a_header[2]);

			if (preg_match('/([\/\\\\|])(.*)\1$/', $title, $a_align)) 
			{
				print "<style>";
				switch ($a_align[1]) {
					case '/' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:right; }';	break;
					case '\\' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:left; }';	break;
					case '|' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:center; }';	break;
				}
				print "</style>\n";

				$title= $a_align[2];
			}

			if (0 == strcmp($title, '++TOTAL++'))
			{
				if (( isset($total_col[$col]) && !$total_col[ERROR] )
				xor ( isset($total_row[$row]) && !$total_row[ERROR] ))
				{
					if ( isset($total_col[$col]) ) {
						print '<th id="'. $id .'" class="total row'. $row .' col'. $col .'" >'. sprintf("%0.2f", $total_col[$col]) .'</th>';
						unset($total_col[$col]);
					}
					else { // if ( isset($total_row[$row]) ) // because its xor
						print '<th id="'. $id .'" class="total row'. $row .' col'. $col .'" >'. sprintf("%0.2f", $total_row[$row]) .'</th>';
						unset($total_row[$row]);
					}

					continue;
				}

				print '<th id="'. $id .'" class="warning total row'. $row .' col'. $col .'" >ERROR!</th>';

				if ( isset($total_col[$col]) )
					unset($total_col[$col]);

				if ( isset($total_row[$row]) ) 
					unset($total_row[$row]);

				continue;
			}

			if (preg_match('/^(.*)\s*([+#])\2$/', $title, $a_accum)) {
				$title= $a_accum[1];
				$total_col[$col]= 0;
				$total_col[ERROR]= false;
			}
			elseif (preg_match('/^([+#])\1(.*)\s*$/', $title, $a_accum)) {
				$title= $a_accum[2];
				$total_row[$row]= 0;
				$total_row[ERROR]= false;
			}

			print '<th id="'. $id .'" class="row'. $row .' col'. $col .'" >'. $this->htmlspecialchars_ent($title) .'</th>';
			continue;
		}

		//-------------------------------------------------------------------------------------------------------------
		// cell

		$cell_style='';
		$quotes= '';

		// extract the cell out of it's quotes
		//
        if (preg_match('/^\s*("?)(.*?)\1\s*$/', $cell, $matches))
		{
			$quotes= $matches[1];

			if ($quotes == '"')
			{
				$cell_style.= 'white-space:pre; ';
				$cell= $matches[2];
			}
			else
				$cell= preg_replace('/'. $PATTERN_ESC_DELIM .'/', $DELIM, $matches[2]);
		}

		if ( isset($total_col[$col]) || isset($total_row[$row]) )
		{
			if (trim($cell) == '_') {
				print '<td id="'. $id .'" class="accu row'.$row .' col'.$col .'" >&nbsp;</td>';
				continue;
			}

			$title= $cell;
			list($success, $nr, $format)= $parse_currency($cell);

			if ( isset($total_col[$col]) && !$total_col[ERROR] )
			{
				if ($success)
					$total_col[$col]+= $nr;
				else
					$total_col[ERROR]= true;
			}

			if ( isset($total_row[$row]) && !$total_row[ERROR] )
			{
				if ($success)
					$total_row[$row]+= $nr;
				else
					$total_row[ERROR]= true;
			}

			if (!$success)	{
				print '<td id="'. $id .'" class="accu warning row'.$row .' col'.$col .'" title="'. $title .'('. $format .')" >ERROR!</td>';
				continue;
			}

			print '<td id="'. $id .'" class="accu '. (($nr <= 0) ? 'warning' : '' ) .' row'.$row .' col'.$col .'" title="'. $title .'('. $format .')" >'. sprintf('%0.2f', $nr) .'</td>';
			continue;
		}

		// if blank, print &nbsp;
		//
		if (preg_match('/^\s*$/',$cell)) 
		{
			print '<td id="'. $id .'" class="row'. $row .' col'. $col .'" >&nbsp;</td>';
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

		print '<td id="'. $id .'" class="row'. $row .' col'. $col .'" style="'. $cell_style .'" >'. $cell .'</td>';

	}
	print "</tr>\n";

}
print "</table>\n";

// https://www.w3schools.com/js/js_htmldom_html.asp
print '<script>document.getElementById("'. $ID_TABLE. '-A4").innerHTML = "New text!";</script>';
?>
