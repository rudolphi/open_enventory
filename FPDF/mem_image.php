<?php
require('html_table.php');

//Stream handler to read from global variables
class VariableStream
{
	var $varname;
	var $position;

	function stream_open($path, $mode, $options, &$opened_path)
	{
		$url = parse_url($path);
		$this->varname = $url['host'];
		if(!isset($_SESSION["tempstore"][$this->varname]))
		{
			trigger_error('Global variable '.$this->varname.' does not exist', E_USER_WARNING);
			return false;
		}
		$this->position = 0;
		return true;
	}

	function stream_read($count)
	{
		$ret = substr($_SESSION["tempstore"][$this->varname], $this->position, $count);
		$this->position += strlen($ret);
		return $ret;
	}

	function stream_eof()
	{
		return $this->position >= strlen($_SESSION["tempstore"][$this->varname]);
	}

	function stream_tell()
	{
		return $this->position;
	}

	function stream_seek($offset, $whence)
	{
		if($whence==SEEK_SET)
		{
			$this->position = $offset;
			return true;
		}
		return false;
	}
	
	function stream_stat()
	{
		return array();
	}
}

class PDF_MemImage extends PDF_HTML_Table
{
	function __construct($orientation='P', $unit='mm', $format='A4')
	{
		parent::__construct($orientation, $unit, $format);
		//Register var stream protocol
		stream_wrapper_register('var', 'VariableStream');
	}

	function MemImage($data, $x=null, $y=null, $w=0, $h=0, $link='')
	{
		//Display the image contained in $data
		$v = 'img'.md5($data);
		if (!isset($_SESSION["tempstore"])) {
			$_SESSION["tempstore"]=array(); // $GLOBALS is r/o from PHP 8.1.0 on
		}
		$_SESSION["tempstore"][$v] = $data;
		$a = getimagesize('var://'.$v);
		if(!$a)
			$this->Error('Invalid image data');
		$type = substr(strstr($a['mime'],'/'),1);
		$this->Image('var://'.$v, $x, $y, $w, $h, $type, $link);
		unset($_SESSION["tempstore"][$v]);
	}

	function GDImage($im, $x=null, $y=null, $w=0, $h=0, $link='')
	{
		//Display the GD image associated to $im
		ob_start();
		imagepng($im);
		$data = ob_get_clean();
		$this->MemImage($data, $x, $y, $w, $h, $link);
	}
	
	function Close()
	{
		stream_wrapper_unregister('var');
		parent::Close();
	}
}
?>
