<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

// https://blog.teamtreehouse.com/how-to-debug-in-php
// ini_set('display_errors', 'On');
// error_reporting(E_ALL | E_STRICT);

//---------------------------------------------------------------------------------------------------------------------

// https://www.phpliveregex.com
// https://www.regular-expressions.info/quickstart.html

if (!defined('PATTERN_ARGUMENT'))		define('PATTERN_ARGUMENT', '(?:;([^;\)\x01-\x1f\*\?"<>\|]*))?');
if (!defined('PATTERN_SPILL_GROUP'))	define('PATTERN_SPILL_GROUP', '([^\)]*)');
if (!defined('PATTERN_NO_ESC'))			define('PATTERN_NO_ESC', '(?<!\\\)');
if (!defined('PATTERN_CURRENCY_FORMAT')) define('PATTERN_CURRENCY_FORMAT', '\'((?:US|SE)(?:,\s*(?:US|SE))*)\'');
//TODO: PATTERN_CSS_DEFINITION is still no broad enough: td#E4 .row4.col3
if (!defined('PATTERN_CSS_DEFINITION'))	define('PATTERN_CSS_DEFINITION', '#!\s*(a(?:\:\w*)?|table, th, td|th, td|t[hrd](?:\.\w*)?|(?:\.\w*))\s*(\{.*\})');
if (!defined('PATTERN_CSS_IDENTIFIER'))	define('PATTERN_CSS_IDENTIFIER', '-?[_a-zA-Z]+[_a-zA-Z0-9-]*');
if (!defined('CSS_ID_DELIM'))			define('CSS_ID_DELIM', '-');
if (!defined('PATTERN_XL_ID'))			define('PATTERN_XL_ID', '[A-Z]+[\d]+');

if (preg_match('/^'.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_SPILL_GROUP.'$/su', ';'.$format_option, $args))
	list(, $arg1, $arg2, $arg3, $arg4, $invalid) = $args;

$DELIM= ($arg1 == 'semi-colon') ? ';' : ',';

$rndw= function ($length=4) {
	return substr(str_shuffle("qwertyuiopasdfghjklzxcvbnm"),0,$length);
};

$ID_TABLE= $rndw(23);
$arg2= preg_replace('/^(.*)\..*$/', '\1', $arg2); // should be .csv, but remove any extension
if (preg_match('/^'.PATTERN_CSS_IDENTIFIER.'$/', $arg2, $a_table_id))
	$ID_TABLE= $a_table_id[0];

// https://www.rexegg.com/regex-lookarounds.html
// asserts what precedes the ; is not a backslash \\\\, doesn't account for \\; (escaped backslash semicolon)
// OMFG! https://stackoverflow.com/questions/40479546/how-to-split-on-white-spaces-not-between-quotes
//
$PATTERN_NO_SPLIT_QUOTED_DELIM= PATTERN_NO_ESC . $DELIM .'(?=(?:[^"]*(["])[^"]*\1)*[^"]*$)';
$PATTERN_ESC_DELIM='\\\\'. $DELIM .'';

$ARRAY_CODE_LINES= preg_split("/[\n]/", $text);
$comments= 0;

//---------------------------------------------------------------------------------------------------------------------

$currency_formats['US']= array(',', '.', 2);
$currency_formats['SE']= array('.', ',', 2);

$selected_formats= array('US');
if (preg_match('/^'.PATTERN_CURRENCY_FORMAT.'$/', $arg3, $a_selected))
	$selected_formats= explode(',', $a_selected[1]);

// https://www.php.net/manual/en/functions.anonymous.php
//if (!function_exists('parse_currency')) { } // doesn't see global scope variables, support 'static'
$parse_currency= function ($cell) use (&$currency_formats, &$selected_formats) 
{
	foreach ($selected_formats as $format)
	{
		list($grouping, $decimal, $places)= $currency_formats[trim($format)];
		
		if ($grouping == '.') $grouping= '\.';
		if ($decimal  == '.') $decimal= '\.';

		$PATTERN_CURRENCY= '([+-]?)\s*(\d{1,3}(?:'. $grouping .'\d{3})*|(?:\d+))(?:'. $decimal .'(\d{'. $places .'}))?';

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

// https://stackoverflow.com/questions/1028248/how-to-combine-class-and-id-in-css-selector
//$css['table, th, td']= '{ border: 1px solid black; border-collapse: collapse; }';
$css['th, td']= '{ padding: 1px 10px 1px 10px; }';
$css['th']= '{ background-color:#ccc; }'; 
$css['tr.even']= '{ background-color:#ffe; }';
$css['tr.odd']= '{ background-color:#eee; }';
$css['.warning']= '{ background-color:#f00; }';
$css['.total']= '{ border: 1px solid black; border-collapse: collapse; }';
$css['a:link']= '{ color: blue; }';
$css['a:visited']= '{ color: blue; }';

foreach ($ARRAY_CODE_LINES as $row => $csv_line) 
{
	if ( preg_match('/^'.PATTERN_CSS_DEFINITION.'$/', $csv_line, $a_css) )
	{
		$css[ $a_css[1] ]= $a_css[2];

		unset($ARRAY_CODE_LINES[$row]);
		$comments++;
		continue;
	}

	if (preg_match('/^#/',$csv_line))
		print 'ERROR: invalid CSS directive: \''. $csv_line .'\'' ."\n";

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
foreach ($ARRAY_CODE_LINES as $csv_row => $csv_line) 
{
	if (preg_match('/^#js!/', $csv_line, $js_line))
		break;

	unset($ARRAY_CODE_LINES[$csv_row]);

	if (preg_match('/^#|^\s*$/',$csv_line)) {
		$comments++;
		continue;
	}

	$row= $csv_row - $comments;

	print ($row %2) ? '<tr class="even" >' : '<tr class="odd" >';

	foreach (preg_split('/'. $PATTERN_NO_SPLIT_QUOTED_DELIM .'/', $csv_line) as $col => $csv_cell)
	{
		$xl_id= $spreadsheet_baseZ($col) . $row;
		$id= $ID_TABLE . CSS_ID_DELIM . $xl_id;

		$cell_style='';
		$quotes= '';

		// extract the cell out of it's quotes
		//
        if (preg_match('/^\s*("?)(.*?)\1\s*$/', $csv_cell, $matches))
		{
			$quotes= $matches[1];
			$cell= $matches[2];

			if ($quotes == '"')
				$cell_style.= 'white-space:pre; ';
		}

		//-------------------------------------------------------------------------------------------------------------
		// header

		if (preg_match('/^\s*==(.*)==\s*$/', $cell, $a_header)) 
		{
			$header= trim($a_header[1]);
			$header= preg_replace('/'.PATTERN_NO_ESC.'\$ID/', $xl_id, $header);

			if (preg_match('/([\/\\\\|])(.*)\1$/', $header, $a_align)) 
			{
				print "<style>";
				switch ($a_align[1]) {
					case '/' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:right; }';	break;
					case '\\' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:left; }';	break;
					case '|' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:center; }';	break;
				}
				print "</style>\n";

				$header= $a_align[2];
			}

			if (preg_match('/(?:\$(\w*))?\[\.\.\.\]/', $header, $a_header_t))
			{
				$var= $a_header_t[1];	

				if (( isset($total_col[$col]) && !$error_col[$col] )
				xor ( isset($total_row[$row]) && !$error_row[$row] ))
				{
					if ( isset($total_col[$col]) ) {
						print '<th id="'. $id .'" class="total row'. $row .' col'. $col .'" title="['. $xl_id .']" >'. sprintf("%0.2f", $total_col[$col]) .'</th>';
						if (isset( $var ))
							print '<div id="'. $ID_TABLE . CSS_ID_DELIM . $var .'" hidden>'. $total_col[$col] .'</div><script>var '. $var .'= '. $total_col[$col] .';</script>';
						unset($total_col[$col]);
					}
					else { // if ( isset($total_row[$row]) ) // because its xor
						print '<th id="'. $id .'" class="total row'. $row .' col'. $col .'" title="['. $xl_id .']" >'. sprintf("%0.2f", $total_row[$row]) .'</th>';
						if (isset( $var ))
							print '<div id="'. $ID_TABLE . CSS_ID_DELIM . $var .'" hidden>'. $total_row[$row] .'</div><script>var '. $var .'= '. $total_row[$row] .';</script>';
						unset($total_row[$row]);
					}

					continue;
				}

				print '<th id="'. $id .'" class="warning total row'. $row .' col'. $col .'" title="['. $xl_id .']" >ERROR!</th>';

				if ( isset($total_col[$col]) )
					unset($total_col[$col]);

				if ( isset($total_row[$row]) ) 
					unset($total_row[$row]);

				continue;
			}

			if (preg_match('/^(.*)\s*'.PATTERN_NO_ESC.'([+#])\2$/', $header, $a_accum)) {
				$header= $a_accum[1];
				$total_col[$col]= 0;
				$error_col[$col]= false;
			}
			elseif (preg_match('/^'.PATTERN_NO_ESC.'([+#])\1(.*)\s*$/', $header, $a_accum)) {
				$header= $a_accum[2];
				$total_row[$row]= 0;
				$error_row[$row]= false;
			}

			if ($quotes != '"')
				$header= preg_replace('/[\\\](.)/', '\1', $header);

			print '<th id="'. $id .'" class="row'. $row .' col'. $col .'" style="'. $cell_style .'" >'. $this->htmlspecialchars_ent($header) .'</th>';
			continue;
		}

		//-------------------------------------------------------------------------------------------------------------
		// cell

		if ($quotes != '"')
			$cell= preg_replace('/[\\\](.)/', '\1', $cell);

		if ( isset($total_col[$col]) || isset($total_row[$row]) )
		{
			if (trim($cell) == '_') {
				print '<td id="'. $id .'" class="accu row'.$row .' col'.$col .'" title="['. $xl_id .']" >&nbsp;</td>';
				continue;
			}

			$title= $cell;
			list($success, $nr, $format)= $parse_currency($cell);

			if ( isset($total_col[$col]) && !$error_col[$col] )
			{
				if ($success)
					$total_col[$col]+= $nr;
				else
					$error_col[$col]= true;
			}

			if ( isset($total_row[$row]) && !$error_row[$row] )
			{
				if ($success)
					$total_row[$row]+= $nr;
				else
					$error_row[$row]= true;
			}

			if (!$success)	{
				print '<td id="'. $id .'" class="accu warning row'.$row .' col'.$col .'" title="['. $xl_id .'] '. $title .'('. $format .')" >ERROR!</td>';
				continue;
			}

			print '<td id="'. $id .'" class="accu '. (($nr <= 0) ? 'warning' : '' ) .' row'.$row .' col'.$col .'" title="['. $xl_id .'] '. $title .'('. $format .')" >'. sprintf('%0.2f', $nr) .'</td>';
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
		if (preg_match_all('/\[\[(.*?)\]\]/', $cell, $all_links))
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

//---------------------------------------------------------------------------------------------------------------------

// https://www.w3schools.com/js/js_htmldom_html.asp
// https://playcode.io/

$print_javascript= function () use (&$ARRAY_CODE_LINES, &$ID_TABLE)
{
	$declared_names= array();
	$assigned_names= array();
	foreach ($ARRAY_CODE_LINES as $js_line) 
	{
		if (preg_match_all('/\$\(\'#('.PATTERN_CSS_IDENTIFIER.')\s*(\w*)\'\)|'.PATTERN_XL_ID.'/', $js_line, $a_vars)) 
			for($i = 0, $size = count($a_vars[0]); $i < $size; ++$i) 
			{
				$name= $a_vars[0][$i];
				if (empty( $a_vars[1][$i] ))
					$declared_names[ $name ]= $name;
				else {
					$var= '$'. preg_replace('/[-]/', '_', $a_vars[1][$i]) . '_' . $a_vars[2][$i];
					$declared_names[ $name ]= $var;
				}
			}

		if (preg_match_all('/('.PATTERN_XL_ID.')\s*=/', $js_line, $a_vars))
			foreach ($a_vars[1] as $name)
				$assigned_names[ $name ]= $name;
	}

	asort($declared_names);
	asort($assigned_names);

	// print <script/>
	//

	// https://www.thoughtco.com/and-in-javascript-2037515
	print '<script>' . "\n" .'function $(x) { return document.getElementById(x); }'. "\n";
	foreach ($declared_names as $name => $var) 
	{
		if (preg_match('/\$\(\'#('.PATTERN_CSS_IDENTIFIER.')\s*(\w*)\'\)/', $name, $a_css_id))
		{
			$selector= $a_css_id[1] . CSS_ID_DELIM . $a_css_id[2];
			print 'var '. $var .'= ('. $var.'_td= $("'. $selector .'")) ? '. $var.'_td.innerHTML : undefined; '. $var.'_td= undefined;' ."\n";
		}
		else 
			print 'var '. $name .'= ('. $name.'_td= $("'. $ID_TABLE .'-'. $name .'")) ? '. $name.'_td.innerHTML : undefined; '. $name.'_td= undefined;' ."\n";
	}

	foreach ($ARRAY_CODE_LINES as $lnr => $js_line) 
	{
		if (!preg_match('/^#js!\s*(.*)$/', $js_line, $a_jscode))
			break;

		$js= $a_jscode[1];

		if (preg_match('/^\s*\/\//', $js, $a_jscode))
			continue;

		if (preg_match_all('/\$\(\'#'.PATTERN_CSS_IDENTIFIER.'\s*\w*\'\)/', $js, $a_vars)) 
			foreach ($a_vars[0] as $name)
				$js= str_replace($name, $declared_names[ $name ], $js);

		//TODO: Number(Math.round(spK+'e2')+'e-2').toFixed(2); does this work?
		// Escape the Math.fxn() calls, if the line qualifies, then print the unescaped $js_line
		//
		$js_esc_math= preg_replace('/(Math\.|Number)([^\(]*)\(([^\)]*)\)/U', '\1\2"\3"', $js);
		if (preg_match('/^[\$\w=\s\/;+\'"*!|&^%\.-]*$/', $js_esc_math, $a_js)) {
			print $js."\n";
			continue;
		}
		
		print '</script>' ."\n";
		print 'ERROR: line '. $lnr .': \''. $js_line .'\'' ."\n";
		return;
	}

	foreach ($assigned_names as $name) 
		print 'if ('. $name.'_td= $("'. $ID_TABLE .'-'. $name .'")) '. $name.'_td.innerHTML= '. $name .'; '. $name.'_td= undefined;' . "\n";

	$output= '';
	foreach ($declared_names as $name => $var) 
		$output.= $var .'= ';
	if (!empty($output)) print $output ."undefined;\n";

	print '</script>' ."\n";
};

$print_javascript();
?>
