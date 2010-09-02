<?php


abstract class Parser
{
	protected $h;
	protected $lineoffs = NULL;


	function findLines()
	{
		preg_match_all('/(\r\n|\n)/',$this->h,$matches, PREG_OFFSET_CAPTURE);



		$this->lineoffs = array(0);
		foreach($matches[0] as $ln)
		{
			$this->lineoffs[] = $ln[1];
		}
		$this->lineoffs[] = strlen($this->h);
	}

	function offsetToLine($i)
	{
		if ($this->lineoffs===NULL) $this->findLines();
		$line=0;
		while($line < count($this->lineoffs) && $i > $this->lineoffs[$line]) {$line++;}
		if ($line) $line--;
		assert('is_numeric($i)');
		return array($line+1,$i-$this->lineoffs[$line],$this->lineoffs[$line],$this->lineoffs[$line+1]); /* $this->lineoffs


		/*function binary_search($array, $element) {
	$low = 0;
	$high = count($array) - 1;
	while ($low <= $high) {
		$mid = (int)(($low + $high) / 2);  // C floors for you
		if ($element == $array[$mid]) {
			return $array[$mid];
		}
		else {
			if ($element < $array[$mid]) {
				$high = $mid - 1;
			}
			else {
				$low = $mid + 1;
			}
		}
	}
	return 0;  // $element not found
}
*/
	}

	abstract function parse($string);

	function match($p,&$i,&$keys=false)
	{
		assert('$p{0}=="/"');
		if ($p{1} == '<' && $this->h{$i} != '<') {return false;}
		if (!preg_match($p.'sS',$this->h,$matches,PREG_OFFSET_CAPTURE,$i))
		{
		//	d($p,'not matched at '.$i.' against '.q(substr($h,$i,40),35));
			return false;
		}
		if ($matches[0][1] != $i)
		{
		//	d('match of '.$p.' not at expected '.$i.' = '.q(substr($h,$i,40),35));
			return false;
		}
		//d($matches[0][0],('matched '.q($p).' from '.$i.' at '.$matches[0][1].' len '.strlen($matches[0][0	])));

		if ($keys !== false)
		{
			$keys = array();
			for($n=1;$n<count($matches);$n++) {$keys[] = $matches[$n][0];}

		}
		$i = $matches[0][1]+strlen($matches[0][0]);
		return true;
	}
}

abstract class ParserToken extends Parser
{
	protected $nextToken,$h,$i,$o;

	function getToken()
	{
		return $this->nextToken[0];
	}

	function getTokenVal($token=NULL)
	{
		if ($token !== NULL && $this->getToken() != $token) $this->expect($token);
		return $this->nextToken[1];
	}

	function getTokenValNext($token=NULL)
	{
		$val = $this->nextToken[1];
		if ($token !== NULL) $this->expect($token); else $this->nextToken(); /* expect() skips token */
		return $val;
	}

	function is($token)
	{
		return $this->nextToken[0]===$token;
	}
	function isVal($token,$val)
	{
		return $this->nextToken[0]===$token && $this->nextToken[1]==$val;
	}
	function isValNext($token,$val)
	{
		if ($this->nextToken[0]===$token && $this->nextToken[1]==$val) {$this->nextToken();return true;}
		return false;
	}

	function isNext($token)
	{
		if ($this->nextToken[0]===$token)
		{
			$this->nextToken();
			return true;
		}
		return false;
	}

	function expect($token)
	{
		if ($this->is($token)) return $this->nextToken();
		throw new Exception('expected '.$token);
	}


	function isIn(/*varargs*/)
	{
		$temp = func_get_args();
		if (in_array($this->nextToken[0],$temp,true)) return $this->nextToken[0];
		return false;
	}

	function isInNext(/*varargs*/)
	{
		$temp = func_get_args();
		if (in_array($this->nextToken[0],$temp,true))
		{
			$r = $this->nextToken[0];
			$this->nextToken();
			return $r;
		}
		return false;
	}

	abstract function nextToken();
}
