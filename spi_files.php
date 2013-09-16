<?php 
/**
	Copyright: Copyright © 2010 Catskin Studio
	Licence: see index.php for full licence details
 */
?>
<?php  
/*
If PHP is not properly recognizing the line endings when reading files either on or created by a Macintosh computer, 
enabling the auto_detect_line_endings run-time configuration option may help resolve the problem.
*/
ini_set('auto_detect_line_endings',true);

class spi_files {
	function spi_files($spi) {
		$this->html_get_path = $spi->html_get_path;
		$this->column_map = $spi->column_map;
		$this->remove_from_description = $spi->remove_from_description;
		$this->csv_get_path = $spi->csv_get_path;
		$this->Shopp = $spi->Shopp;
		$this->spi = $spi;		  
		
		
		 $this->delimiter = ','; 
			if($this->Shopp->Settings->get('catskin_importer_separator') == 'semicolon'){ 
				$this->delimiter = ';';		
			}
		
	}		
	
	function load_examine_csv($filename,$has_headers=true) {
		// var_dump($filename);
		// $row = 0;
		ini_set('memory_limit', 256*1024*1024);
		$examine_data = array();
		
		// $this->spi->log(' load_examine_csv start');
		
		// if (!$this->examine_data) $this->load_csv($filename, $start_at=1, $records=99999999,$has_header=true)
		
		if (($handle = fopen($this->csv_get_path . $filename, "r")) !== FALSE) {
			
			while (!feof($handle)) { 
				$line = $this->escape_quotes_csv(fgets($handle, 4096));
				if ($line) {
					$read_row = $this->csv_explode($line, $this->delimiter);
					//$read_row = str_getcsv($line, $this->delimiter);
					if (!$this->emptyArray($read_row)) $examine_data[] = $read_row;
				};
			}
			fclose($handle);
		} else {
			$_SESSION["spi_error"] = "CSV file could not be loaded...";
		}
		
		
		
		// $this->spi->log(' start column assign/load_examine_csv');
		
		$_SESSION['spi_files_col_count'] = count($examine_data[0]);

		if ($has_headers) $_SESSION['spi_files_header_row'] = array_shift($examine_data);

		$_SESSION['spi_files_row_count'] = count($examine_data);
		
		$_SESSION['spi_files_first_row'] = $examine_data[0];
		
		// $this->spi->log(' load_examine_csv end');
		return $examine_data;
	}
	function load_csv($filename, $start_at=1, $records=99999999,$has_header=true) {	 
		// $this->spi->log(' load_csv start');
		
		$row = $has_header ? 0 : 1;
		if (($handle = fopen($this->csv_get_path . $filename, "r")) !== FALSE) {
			while (!feof($handle)) { 
				$line = $this->escape_quotes_csv(fgets($handle, 4096)); 
				// $log = fopen($this->csv_get_path . 'parsed_log.csv', "w") or die("can't open file");
				// fwrite($log,$line);
				$read_row = $this->csv_explode($line, $this->delimiter);
				unset($line);
				// $read_row = str_getcsv($line, $this->delimiter);
				if ($row > 0 && $row >= $start_at && $row < ($start_at + $records)) {
					if(count($read_row)!==12) echo 'Bad data: ',var_dump($source,$line,$read_row);
					// else var_dump($read_row);
					// if(!$this->emptyArray($read_row)) $data[] = $this->map_columns($read_row);
					if(!$this->emptyArray($read_row)) $data[] = $read_row;
				}
				$row++;
			}
			fclose($handle);
		} else {
			$_SESSION["spi_error"] = "CSV file could not be loaded...";
		}
		return $data;
	}	

	function csv_explode($str, $delim=',', $enclose='"', $preserve=false){ 
	  $resArr = array(); 
	  $n = 0; 
	  $expEncArr = explode($enclose, $str); 
	  foreach($expEncArr as $EncItem){ 
	    if($n++%2){ 
	      array_push($resArr, array_pop($resArr) . ($preserve?$enclose:'') . $EncItem.($preserve?$enclose:'')); 
	    }else{ 
	      $expDelArr = explode($delim, $EncItem); 
	      array_push($resArr, array_pop($resArr) . array_shift($expDelArr)); 
	      $resArr = array_merge($resArr, $expDelArr); 
	    } 
	  } 
	  return $resArr; 
	} 
	
	function emptyArray($arr){
		foreach($arr as $val)
		{
			if(!empty($val)) return false;
		} 
		
		return true;
	}	

	
	function escape_quotes_csv($line)
	{
		// $a = ',""Lady\'s White 18 Karat Pendant  With 26=0.19Tw Round G Si1 "Diamonds And One ",Oval Aqua"","14K YG ANKLET, 9", PAGE 133.  Price reflects $50 off $100 purchase anniversary giftcard",""\n';
		$line = preg_replace_callback('|"(.*?)"[,\n]|',array($this,'fix_quotes'),$line);
		return substr($line,0,-1);
	}
	// function fix_quotes($match){
	// 	return '"'.str_replace('"',"\'",$match[1]).'",';
	// }
	function fix_quotes($match){
		$data = preg_replace_callback('/~([\d\w]{2})/',array($this,'convert_hex_codes'),$match[1]);
		$data = str_replace('"',"''",$data);
		return '"'.$data.'",';
	}
	function convert_hex_codes($value)
	{
		return chr(hexdec($value[1]));
	}
	
	function _escape_quotes_csv($a)
	{
		// $a = ',""Lady\'s White 18 Karat Pendant  With 26=0.19Tw Round G Si1 "Diamonds And One ",Oval Aqua"","14K YG ANKLET, 9", PAGE 133.  Price reflects $50 off $100 purchase anniversary giftcard",""\n';
		preg_match_all('/(")((.*?))("[\n,](?![^"\n]))/',$a,$m,PREG_SET_ORDER | PREG_OFFSET_CAPTURE); //var_dump($m);
		foreach($m as $s) {
			// echo ">>> {$s[2][0]}<br>";
			$f[]= $s[1][0].preg_replace('/(?!^)"(?!,$)/','\'\'',$s[2][0]).$s[4][0];
		} //var_dump($f);
		$c = $a; $l=0;
		foreach ($f as $k=>$g) {
			$s = $m[$k][0][1];
			$n = strlen($m[$k][0][0]);
			if (($t=strlen($g) - $n)>0) {
				$c = substr_replace($c,'',$s,$n); 
				$c = substr_replace($c,$g,$s+$l,0);
				$l = $t;
				// echo $g.'<br>',$m[$k][0][0].'<br>',$l.'<br>';
			}
		}
		// echo ">>>>>>>>>>>>>";
		// var_dump($a,$c);
		// $k=0;
		// echo "\n",substr($a,$m[$k][0][1],strlen($m[$k][0][0]));
		return $c;
	}

	function map_columns($c_row) {
		
	}	
					
	function load_html_from_file($filename) {
		ob_start();
		readfile($this->html_get_path . $filename);
		$contents = ob_get_clean();
		foreach ($this->remove_from_description as $str) {
			$contents = str_replace($str,"",$contents);
		}
		return ltrim($contents);
	}	
}
?>