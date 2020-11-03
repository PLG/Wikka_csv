<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

// https://blog.teamtreehouse.com/how-to-debug-in-php
// ini_set('display_errors', 'On');
// error_reporting(E_ALL | E_STRICT);

//---------------------------------------------------------------------------------------------------------------------

// https://www.phpliveregex.com
// https://www.php.net/manual/en/function.preg-quote.php
// https://www.regular-expressions.info/quickstart.html
// https://www.regular-expressions.info/recurse.html

if (!defined('PATTERN_ARGUMENT'))			define('PATTERN_ARGUMENT', '(?:;([^;\)\x01-\x1f\*\?"<>\|]*))?');
if (!defined('PATTERN_SPILL_GROUP'))		define('PATTERN_SPILL_GROUP', '([^\)]*)');
if (!defined('PATTERN_NO_ESC'))				define('PATTERN_NO_ESC', '(?<!\\\)');
if (!defined('PATTERN_FORMATS'))			define('PATTERN_FORMATS', 'US0|US2|USX|EUX|USD|SEK');
if (!defined('PATTERN_CURRENCY_FORMAT'))	define('PATTERN_CURRENCY_FORMAT', '\'((?:'.PATTERN_FORMATS.')(?:,\s*(?:'.PATTERN_FORMATS.'))*)\'');
if (!defined('PATTERN_CURRENCIES'))			define('PATTERN_CURRENCIES', 'USD|SEK');
if (!defined('PATTERN_NUMBER_FORMAT'))		define('PATTERN_NUMBER_FORMAT', '\[#(?:,##)?([.,\'\s])#{3}(?:([.,\'])(#+|#~))?\]('.PATTERN_CURRENCIES.')?');
if (!defined('PATTERN_CSS_IDENTIFIER'))		define('PATTERN_CSS_IDENTIFIER', '-?[_a-zA-Z]+[_a-zA-Z0-9-]*');
if (!defined('PATTERN_CSS_DECLARATION'))	define('PATTERN_CSS_DECLARATION', '(?:a|table|t[hrd])?(?:[:\.#]'.PATTERN_CSS_IDENTIFIER.')*');
if (!defined('PATTERN_CSS_RULE'))			define('PATTERN_CSS_RULE', '('.PATTERN_CSS_DECLARATION.'(?:,\s*'.PATTERN_CSS_DECLARATION.')*)\s*(\{.*\})');
if (!defined('PATTERN_IDENTIFIER'))			define('PATTERN_IDENTIFIER', '[a-zA-Z_]\w*');
if (!defined('PATTERN_XL_ID'))				define('PATTERN_XL_ID', '(?<!\[)\\$([A-Z]+[\d]+)');
if (!defined('PATTERN_SIMPLE_VAR'))			define('PATTERN_SIMPLE_VAR', '(?<!\[)\$(\b'.PATTERN_IDENTIFIER.'\b)(?!\'\])(?!\s*\.)'); // word boundaries, not ending with '] or .
if (!defined('PATTERN_TABLE_VAR'))			define('PATTERN_TABLE_VAR', '\$\[\'(?:(?:#('.PATTERN_CSS_IDENTIFIER.')\s*)?('.PATTERN_IDENTIFIER.')|(?R))*\'\]'); // recursive

if (!defined('CSS_ID_DELIM'))				define('CSS_ID_DELIM', '-');
if (!defined('ID'))							define('ID', 'id');
if (!defined('TITLE'))						define('TITLE', 'title');
if (!defined('CLASSES'))					define('CLASSES', 'class');
if (!defined('STYLE'))						define('STYLE', 'style');

$pagevars= $this->pagevars;
// $pagevar_value= $this->GetPageVariable($pagevar_key, ''); // $GLOBALS['wakka']->GetPageVariable(...)
$replace_pagevars= function($text) use (&$pagevars)
{
	foreach ($pagevars as $key => $value)
		$text= preg_replace('/'.PATTERN_NO_ESC.'\{\$'. $key .'\}/', $value, $text);

	return $text;
};

if (preg_match('/^'.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_SPILL_GROUP.'$/su', ';'.$format_option, $args))
	list(, $arg1, $arg2, $arg3, $arg4, $invalid) = $args;

$DELIM= ($arg1 == 'semi-colon') ? ';' : ',';
$arg2= $replace_pagevars($arg2);
$arg3= $replace_pagevars($arg3);

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
foreach ($ARRAY_CODE_LINES as $row => $csv_line) 
	$ARRAY_CODE_LINES[$row]= $replace_pagevars($csv_line);

$comments= 0;

//---------------------------------------------------------------------------------------------------------------------

$number_formats['US0']= '[#,###]';
$number_formats['US2']= '[#,###.##]';
$number_formats['USX']= '[#,###.#~]';

$number_formats['EUX']= '[#.###,#~]';

if (!defined('FORMAT_DEFAULT')) define('FORMAT_DEFAULT', 'US2');

// https://www.thefinancials.com/Default.aspx?SubSectionID=curformat
// [#,###.###]BHD [#,###.##] [#.###,##] [# ###.##]AUD [#,##,###.##]INR [#.###]CLP [#,###]JPY [# ###]LBP
$number_formats['USD']= '[#,###.##]USD';
$number_formats['SEK']= '[#.###,##]SEK';

$currency_locale['USD']= 'us-US';
//$currency_locale['SEK']= 'de-DE'; // produces "#.###,## SEK" with Number().toLocaleString()
$currency_locale['SEK']= 'sv-SE'; // produces "# ###,## kr" with Number().toLocaleString()

$parse_number_format= function($format) use (&$number_formats)
{
	preg_match('/^'.PATTERN_NUMBER_FORMAT.'$/', $number_formats[$format], $a_separators);
	return $a_separators;
};


$selected_formats= array(FORMAT_DEFAULT);
if (preg_match('/^'.PATTERN_CURRENCY_FORMAT.'$/', $arg3, $a_selected))
	$selected_formats= explode(',', $a_selected[1]);
array_walk($selected_formats, function(&$arrValue) { $arrValue = trim($arrValue);} );

$js_toFixed= function($element, $a_options) use (&$currency_locale, &$parse_number_format)
{
	list($format, $display_currency)= $a_options;
	list(, $grouping, $decimal, $places, $currency)= $parse_number_format( $format );

	if (!strcmp($places, '#~'))
		return $element;

	// this outputs grouping symbols, which makes it really hard to add them up in js e.g., Number("2,123.00") is NaN
	//$toConversion= '.toLocaleString(undefined, {minimumFractionDigits: '. strlen($places) .', maximumFractionDigits: '. strlen($places) .'})';
	$toConversion= '.toFixed('.strlen($places).')'; // NOTE: .toFixed(...) returns a string!

	if (!strcmp($display_currency, 'ISO4217') )
		$toConversion.= ' +\' '. $currency .'\'';
	elseif (!strcmp($display_currency, 'locale') )
		// https://stackoverflow.com/questions/9372624/formatting-a-number-as-currency-using-css
		$toConversion= '.toLocaleString(\''. $currency_locale[$currency] .'\', { style: \'currency\', currency: \''. $currency .'\' })';

	return 'Number('. $element .')'. $toConversion;
};

if (!defined('CURRENCY_DISPLAY')) define('CURRENCY_DISPLAY', 'ISO4217');

// https://www.php.net/manual/en/functions.anonymous.php
//if (!function_exists('parse_number')) { } // doesn't see global scope variables, support 'static'
$parse_number= function ($searchable_formats, $cell) use (&$parse_number_format) 
{
	foreach ($searchable_formats as $format)
	{
		list(, $grouping, $decimal, $places)= $parse_number_format($format);
		
		$grouping= preg_quote($grouping);
		$decimal= preg_quote($decimal);

		if (!strcmp($places, '#~'))
			$pattern_number= '([+-]?)\s*(\d{1,3}(?:'. $grouping .'\d{3})*|(?:\d+))(?:'. $decimal .'(\d+))?';
		else
			$pattern_number= '([+-]?)\s*(\d{1,3}(?:'. $grouping .'\d{3})*|(?:\d+))(?:'. $decimal .'(\d{'. strlen($places) .'}))?(?:\s*([A-Z]{3}))?';

		if (preg_match('/^'.$pattern_number.'$/', $cell, $a_cell))
		{
			list(, $posneg, $whole, $frac, $currency)= $a_cell;

			if (strlen($currency) && strcmp($currency, $format))
				continue;

			$cell= $posneg . preg_replace('/'.$grouping.'/', '', $whole);
			if ( strlen($frac) )
				$cell.= '.'. $frac;

			return array(true, floatval($cell), $format, (strlen($currency) != 0) );
		}
	}

	return array(false, $cell, 'ERR', '');
};

//---------------------------------------------------------------------------------------------------------------------

$_= function($var) {
	return '$'. $var;
};

$replace_camel_url_links= function ($cell)
{
	// test of [[CamelLink]] and [[URL|name]]
	//
	if (preg_match_all('/\[\[([^|\]]*)(?:\|([^\]]*))?\]\]/', $cell, $a_links))
	{
		list($found, $links, $names)= $a_links;
		foreach ($found as $idx => $found1)
		{
			if ( !strlen($names[$idx]) )
				$cell= preg_replace('/'.preg_quote($found[$idx],'/').'/', $this->Link($links[$idx]), $cell);

			else
				$cell= preg_replace('/'.preg_quote($found[$idx],'/').'/', $this->Link($links[$idx], '', $names[$idx], TRUE, TRUE, '', '', FALSE), $cell);
		}

		return array(true, $cell);
	}

	return array(false, $cell);
};

$escaped_css_id_var= function ($css_id, $var) {
	return preg_replace('/['. CSS_ID_DELIM .']/', '_', $css_id) . '_' . $var;
};


$qualified_var= function($ID_TABLE, $simple_var, $table_name, $table_var) use (&$escaped_css_id_var)
{
	if (strlen($simple_var))
		return array($escaped_css_id_var($ID_TABLE, $simple_var), $simple_var);

	elseif (strlen($table_var))
	{
		if (strlen($table_name))
			return array($css_id_var= $escaped_css_id_var($table_name, $table_var), $escaped_css_id_var($table_name, $table_var));
		else
			return array($escaped_css_id_var($ID_TABLE, $table_var), $table_var);
	}

	return array($escaped_css_id_var($ID_TABLE, 'ERR'), 'ERR');
};

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
//$css['.negative']= '{ background-color:#f00; }';
$css['.negative']= '{ background-color:#fcc; border: 2px solid red; border-collapse: collapse; }';
// if you want to make negative headers different from negative cells
//$css['td.negative']= '{ background-color:#fcc; border: 2px solid red; border-collapse: collapse; }';
//$css['th.negative']= '{ background-color:red; border: 2px solid black; border-collapse: collapse; }';
$css['.total']= '{ border: 1px solid black; border-collapse: collapse; }';
$css['a:link']= '{ color: blue; }';
$css['a:visited']= '{ color: blue; }';

foreach ($ARRAY_CODE_LINES as $row => $csv_line) 
{
	if ( preg_match('/^#css!\s*'.PATTERN_CSS_RULE.'$/', $csv_line, $a_css) )
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

$TD_ws= "\n\t"; 
//$TD_ws= ''; 
$js_script= '';

$total= array(array());

print '<script>function $(x) { return document.getElementById(x); }; var tcol={}; var trow={};</script>'. "\n";
print '<table id="'. $ID_TABLE .'">'. "\n";
// https://www.thoughtco.com/and-in-javascript-2037515
foreach ($ARRAY_CODE_LINES as $csv_row => $csv_line) 
{
	if (preg_match('/^#js!/', $csv_line, $js_line))
		break;

	unset($ARRAY_CODE_LINES[$csv_row]);

	if (preg_match('/^#/', $csv_line)) {
		$comments++;
		continue;
	}

	$row= $csv_row - $comments;

	// if blank line, print empty row ... else ...
	//
	if (preg_match('/^\s*$/', $csv_line)) 
		print '<tr class="empty" >';
	else 
		print ($row %2) ? '<tr class="even" >' : '<tr class="odd" >';


	foreach (preg_split('/'. $PATTERN_NO_SPLIT_QUOTED_DELIM .'/', $csv_line) as $col => $csv_cell)
	{
		$a_dimensions= array(
				'col' => array('|++', $col),
				'row' => array('++_', $row),
			);

		$attr[CLASSES]= ' row'. $row .' col'. $col;

		$xl_id= $spreadsheet_baseZ($col) . $row;

		$attr[ID]= $ID_TABLE . CSS_ID_DELIM . $xl_id;
		$attr[TITLE]= '['. $xl_id .']';

		$attr[STYLE]='';

		$tag= 'td';
		$tag_style= '';
		$tag_script= '';

		$quotes= '';

		// extract the cell out of it's quotes
		//
        if (preg_match('/^\s*("?)(.*?)\1\s*$/', $csv_cell, $a_matches))
		{
			list(, $quotes, $cell)= $a_matches;

			if (!strlen($cell))
				$cell= '&nbsp;';

			if ($quotes == '"')
				$attr[STYLE].= ' white-space:pre;';
		}

		//-------------------------------------------------------------------------------------------------------------
		// header

		if (preg_match('/^\s*==\s*(.*?)\s*==\s*$/', $cell, $a_header)) 
		{
			list(,$cell)= $a_header;

			$tag= 'th';

			if (preg_match('/([\/\\\\|])(.*)\1$/', $cell, $a_nonvar)) 
			{
				list(, $align, $cell)= $a_nonvar;

				switch ($align) {
					case '/' :	$tag_style.= '#'. $ID_TABLE .' .col'. $col .' { text-align:right; }';	break;
					case '\\' :	$tag_style.= '#'. $ID_TABLE .' .col'. $col .' { text-align:left; }';	break;
					case '|' :	$tag_style.= '#'. $ID_TABLE .' .col'. $col .' { text-align:center; }';	break;
				}
			}

			//TODO: undefined all tcol and trow in js at the end of the script

			$pattern_rowcol_accu['col']= '(.*)\s*'.PATTERN_NO_ESC.'([+])\2('.PATTERN_FORMATS.')?';
			$pattern_rowcol_accu['row']= '('.PATTERN_FORMATS.')?'.PATTERN_NO_ESC.'([+])\2(.*)\s*';

			foreach ($a_dimensions as $rowcol => list(, $idx) )
				if (preg_match('/^'. $pattern_rowcol_accu[$rowcol] .'$/', $cell, $a_accum)) 
				{
					if (!strcmp($rowcol, 'col'))
						list(, $cell, $plus, $format)= $a_accum;
					else
						list(, $format, $plus, $cell)= $a_accum;

					$total[$rowcol][$idx]= array(false, $selected_formats[0]);
					if (strlen($format))
					{
						$total[$rowcol][$idx]= array(true, $format);
						if (!in_array($format, $selected_formats))
							$cell= 'SYNTAX!';
					}
					$tag_script.= 't'.$rowcol.'['.$idx.']= Number(0); '. "\n";
					break;
				}
		}

		//-------------------------------------------------------------------------------------------------------------
		// not header

		else
		{
		}

		//-------------------------------------------------------------------------------------------------------------
		//

		$replaced= false;

		$cell= preg_replace('/'.PATTERN_NO_ESC.'\$ID/', $xl_id, $cell);

		// READ into variable [$decl_var=value]
		//
		if (preg_match('/^\[(?:\$('.PATTERN_IDENTIFIER.'))?=(.*)?\]$/', $cell, $a_read_var))
		{
			list(, $decl_var, $decl_value)= $a_read_var;

			// if cell contains [...=|++] or [...=++_] (vertical or horizontal accummulation marker)
			//			
			if ( ($decl_value == $a_dimensions['col'][0]) || ($decl_value == $a_dimensions['row'][0]) )
			{ 
				$cell= 'SYNTAX!';

				foreach ($a_dimensions as $rowcol => list($marker, $idx) )
					if ( $decl_value == $marker && isset($total[$rowcol][$idx]) )
					{
						list($rowcol_curr_set, $rowcol_currency)= $total[$rowcol][$idx];
						$a_options= $rowcol_curr_set ? array($rowcol_currency,CURRENCY_DISPLAY) : array($selected_formats[0],'');

						$attr[CLASSES].= ' total';
						$cell= 'ERROR!';
						$replaced= true;

						$tag_script.= 'if (t'.$rowcol.'['.$idx.'] !== undefined) '.
							'$("'. $attr[ID] .'").innerHTML= '. $js_toFixed('t'.$rowcol.'['.$idx.']', $a_options) .'; '. "\n";

						unset( $total[$rowcol][$idx] );
					}
			}

			// assign a value [...=decl_value]
			//
			else 
			{
				if (!strlen($decl_value))
					$decl_value= '&nbsp;';

				$cell= $decl_value;

				list($success, $nr, $format, $currency)= $parse_number($selected_formats, $cell);
				if ($success)
					$attr[CLASSES].= (($nr < 0) ? ' negative' : '' );
			}

			// variable assignment [$decl_var=...]
			//
			if (strlen( $decl_var ))
				$tag_script.= 'var '. $_($escaped_css_id_var($ID_TABLE, $decl_var)) .'= $("'. $attr[ID] .'"); '. "\n";
		}

		// WRITE out variable $var0 $['var0'] or $['#table var0']
		//
		if (preg_match('/^(-)?\s*(?:'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.')$/', $cell, $a_write_var))
		{
			list(, $neg, $simple_var, $table_name, $table_var)= $a_write_var;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			$cell= 'ERROR!';

			// adding a dash for the negative causes javascript to convert the innerHTML string to a number, resulting in the toFixed(2) being removed. String addition instead.
			$tag_script.= 'if (typeof('. $_($css_id_var) .') !== \'undefined\') $("'. $attr[ID] .'").innerHTML= \''. $neg .'\'+ '. $_($css_id_var) .'.innerHTML; '. "\n";
			$tag_script.= 'if ( !isNaN(Number($("'. $attr[ID] .'").innerHTML)) ) '
				.'if (Number($("'. $attr[ID] .'").innerHTML) < 0) $("'. $attr[ID] .'").classList.add("negative"); else $("'. $attr[ID] .'").classList.remove("negative");'. "\n";
			
			$replaced= true;
		}

		// calculate totals
		//
		if ( isset($total['col'][$col]) || isset($total['row'][$row]) )
		{
			// if both column and row having formatting, choose which?
			//
			if ( isset($total['col'][$col]) && isset($total['row'][$row]) )
			{
				list($col_curr_set, )= $total['col'][$col];
				list($row_curr_set, )= $total['row'][$row];

				if ($col_curr_set && $row_curr_set)
					$total_rowcol= $total['col'][$col]; // prioritize column over row formatting
				else
					$total_rowcol= ($col_curr_set) ? $total['col'][$col] : $total['row'][$row];
			}
			else
				$total_rowcol= (isset($total['col'][$col])) ? $total['col'][$col] : $total['row'][$row];

			list($rowcol_curr_set, $rowcol_currency)= $total_rowcol;
			$searchable_formats= ($rowcol_curr_set) ? array($rowcol_currency) : $selected_formats;
			$a_options= ($rowcol_curr_set) ? array($searchable_formats[0],CURRENCY_DISPLAY) : array($searchable_formats[0],'');

			$attr[CLASSES].= ' accu';
	
			if ($replaced)
			{
				$nr= ' $("'. $attr[ID] .'").innerHTML ';

				$success= true;
			}
			else
			{
				if ( ($cell == '_') || ($tag == 'th') )
					goto no_total;

				list($success, $nr, $nr_currency, $nr_curr_set)= $parse_number($searchable_formats, $cell); 

				$attr[TITLE].= ' \''. $cell .'\' '. $nr_currency . ($nr_curr_set ? '(!)' : '') .'';
				$attr[CLASSES].= ($success && $nr < 0) ? ' negative' : '';

				if ($nr_curr_set)
					$a_options= array($searchable_formats[0],CURRENCY_DISPLAY);
			}

			foreach ($a_dimensions as $rowcol => list(, $idx) )
				if ( isset($total[$rowcol][$idx]) )
				{
					//TODO if USD is appended to the end of a cell you will get '234.34 USD' when reading out a variable. Users will need string parsing tools.

					if ($success)
						$tag_script.= 'if (t'.$rowcol.'['.$idx.'] !== undefined) t'.$rowcol.'['.$idx.']+= Number('. $nr .'); '.
							'if (isNaN(t'.$rowcol.'['.$idx.'])) t'.$rowcol.'['.$idx.']= undefined; '. "\n";
					else
					{
						$tag_script.= 't'.$rowcol.'['.$idx.']= undefined; '. "\n";
						$cell= 'ERROR!';
					}
				}

			if ($replaced && !$rowcol_curr_set)
				goto no_total;

			if ($success)
				$tag_script.= '$("'. $attr[ID] .'").innerHTML= '. $js_toFixed($nr, $a_options) .'; '. "\n";

			no_total:
		}

		//-------------------------------------------------------------------------------------------------------------
		// Output

		if ($quotes != '"')
			$cell= preg_replace('/[\\\](.)/', '\1', $cell);

		$cell= $this->htmlspecialchars_ent($cell);

		list(, $cell)= $replace_camel_url_links($cell);

		print $TD_ws; 
		print ((!strlen($tag_style)) ? '' : '<style>'. $tag_style .'</style>');
		print '<'. $tag .' id="'. $attr[ID] .'" class="'. $attr[CLASSES] .'" title="'. $attr[TITLE] .'" style="'. $attr[STYLE] .'" >'. $cell .'</'. $tag .'>';
		print ((!strlen($tag_script)) ? '' : "\n". '<script>'. $tag_script .'</script>');

	}
	print "</tr>\n";

}
print "</table>\n";

//---------------------------------------------------------------------------------------------------------------------

// https://www.w3schools.com/js/js_htmldom_html.asp
// https://playcode.io/ promo code: b3M3O5bR
// https://www.w3schools.com/html/tryit.asp?filename=tryhtml_default

$print_javascript= function () use (&$_, &$escaped_css_id_var, &$qualified_var, &$ARRAY_CODE_LINES, &$ID_TABLE)
{
	foreach ($ARRAY_CODE_LINES as $lnr => $js_line) 
	{
		if (preg_match('/^#js!\s*(.*?)(?:\s*\/\/|$)/', $js_line, $a_code))
			list(, $js_line)= $a_code;
		else
		{
			print 'ERROR: line '. $lnr .': \''. $js_line .'\'' ."\n";
			return;
		}

		// preg_replace here, removes duplicates of the same declaration: $var2 and $['var2'] and $['#var-text var2']
		//
		$js_line= preg_replace('/\[\'(#'.$ID_TABLE.')?\s*([a-zA-Z_]\w*)\'\]/', '$2', $js_line);

		if (preg_match('/^(?!\s*\/\/|\s*$)/', $js_line))
			$ARRAY_CODE_LINES[$lnr]= $js_line;
		else
			unset($ARRAY_CODE_LINES[$lnr]);
	}

	$declared_names= array();
	$assigned_names= array();
	foreach ($ARRAY_CODE_LINES as $lnr => $js_line) 
	{
		if (preg_match_all('/'.PATTERN_XL_ID.'|'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'/', $js_line, $a_vars)) 
			$declared_names= array_merge($declared_names, $a_vars[0]);
		if (preg_match_all('/('.PATTERN_XL_ID.'|'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.')\s*=/', $js_line, $a_vars))
			$assigned_names= array_merge($assigned_names, $a_vars[1]);
	}

	$declared_names= array_unique($declared_names);
	sort($declared_names);

	$assigned_names= array_unique($assigned_names);
	sort($assigned_names);

	// print <script/>
	//

	// declare a variable for each variable declared in a js directive
	//
	print '<script>' . "\n";
	foreach ($declared_names as $name) 
	{
		if (preg_match('/^'.PATTERN_XL_ID.'|'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'$/', $name, $a_name)) 
		{
			list(, $xl_id, $simple_var, $table_name, $table_var)= $a_name;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			if (strlen($xl_id))
				print 'var '. $xl_id .'= ('. $_($xl_id).'= $("'. $ID_TABLE .'-'. $xl_id .'")) ? '. $_($xl_id) .'.innerHTML : undefined; '. $_($xl_id) .'= undefined;' ."\n";
			else
				print 'var '. $var .'= (typeof('. $_($css_id_var) .') !== \'undefined\') ? '. $_($css_id_var) .'.innerHTML : undefined;' ."\n";
		}
	}

	// output each line of code; replace fxns/vars and check allowed characters
	//
	foreach ($ARRAY_CODE_LINES as $lnr => $js_line) 
	{
		$js= $js_line;

		if (preg_match_all('/'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'/', $js, $a_name)) 
		{
			foreach ($a_name[0] as $idx => $name)
			{
				list($simple_var, $table_name, $table_var)= array($a_name[1][$idx], $a_name[2][$idx], $a_name[3][$idx]);
				list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

				$js= str_replace( $name, $var, $js);
			}
		}

		// Escape the Math.fxn() calls, if the line qualifies, then print the unescaped $js_line
		//
		$js_esc_math= preg_replace('/(Math\.|Number|toFixed)([^\(]*)\(([^\)]*)\)/U', '\1\2"\3"', $js);
		if (preg_match('/^[\$\w=\s\/;+\'"*!|&^%\.-]*$/', $js_esc_math, $a_js)) {
			print $js ."\n";
			continue;
		}
		
		print '</script>' ."\n";
		print 'ERROR: line '. $lnr .': \''. $js_line .'\'' ."\n";
		return;
	}

	// push results back into html foreach assigned variable in js (with an = sign)
	//
	foreach ($assigned_names as $name) 
	{
		if (preg_match('/^'.PATTERN_XL_ID.'|'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'$/', $name, $a_name)) 
		{
			list(, $xl_id, $simple_var, $table_name, $table_var)= $a_name;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			if (strlen($xl_id))
			{
				$var= $xl_id;
				print 'if ('. $_($var) .'= $("'. $ID_TABLE .'-'. $var .'")) { '. 
					'if (typeof(Number('. $var.'))==\'number\') '.
						'if ('. $var.'<0) '. $_($var) .'.classList.add("negative"); '.
						'else '. $_($var) .'.classList.remove("negative"); '.
					$_($var) .'.innerHTML= '. $var .'; '.
				'} '. $_($var) .'= undefined;' ."\n";
			}
			else
			{
				print 'if (typeof('. $_($css_id_var) .') !== \'undefined\') { '. 
					'if (typeof(Number('. $var.'))==\'number\') '.
						'if ('. $var.'<0) '. $_($css_id_var) .'.classList.add("negative"); '.
						'else '. $_($css_id_var) .'.classList.remove("negative"); '.
					$_($css_id_var) .'.innerHTML= '. $var .'; '.
				'}' ."\n";
			}
		}
	}

	// clean-up; undeclare all declared variables 
	//
	$output= '';
	foreach ($declared_names as $name) 
	{
		if (preg_match('/^'.PATTERN_XL_ID.'|'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'$/', $name, $a_name)) 
		{
			list(, $xl_id, $simple_var, $table_name, $table_var)= $a_name;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			if (strlen($xl_id))
				$output.= $xl_id .'= ';
			else
				$output.= $var .'= ';
		}
	}
	if (strlen($output)) print $output ."undefined;\n";

	print '</script>' ."\n";
};

$print_javascript();
?>
