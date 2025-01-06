<?php
/******************************************************************************
 *
 * Definition of the QRBG Service Access class for PHP.
 *
 * Designed and written by Sebastian Weber, Mar/2009.
 * Contact: webersebastian at yahoo.de
 *
 * Last revision: 2009-03-18
 * Version: 1.0
 *
 *****************************************************************************
 *
 * Copyright (c) 2009 Sebastian Weber <webersebastian at yahoo.de>
 * Portions (c) 2007 Radomir Stevanovic and Rudjer Boskovic Institute.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 *****************************************************************************
 *
 * Public function index (all functions return false on error):
 *
 * new QRBG ( username , password [, server] [, port] [, cachesize] )
 *     Initializes a new QRBG object. the server, port and cachesize arguments
 *     are optional.
 *     deafult values are: server=random.irb.hr, port=1227, cachesize=4096
 *
 * setCachesize ( cachesize )
 *     Set cachesize in bytes. To save resources, the cachesize should be
 *     adjusted to fit your needs. If you need a fixed amount of random data
 *     while your PHP-script executes, be sure to set the cachesize to this
 *     value. Setting the cachesize too low will result in multiple requests
 *     to the QRBG service, which will slow down you script; setting the value
 *     too high will request more data than needed from QRBG.
 *
 * getChar ()
 *     returns a single random char. Uses 1 byte from cache.
 *
 * getByte ()
 *     returns a single random byte: 8 bit, range [0 - 255]
 *     Uses 1 byte from cache.
 *
 * getInt ( [unsigned] )
 *     returns a single random 32-bit integer. The argument is optional and
 *     defaults to false. The range depends on the argument:
 *     unsigned = false or default: [-2147483648 - 2147483647]
 *     unsigned = true: [0 - 2147483647]
 *     Uses 4 bytes from cache.
 *
 * getFloat ()
 *     returns a single random floating point number: 32-bit precision,
 *     range [0 - 1) (0 inclusive, 1 exclusive)
 *     Uses 3 bytes from cache.
 *
 * getChars ( count )
 *     returns <count> random chars in a string of length <count>
 *     Uses <count> bytes from cache.
 *
 * rand( [max] [, modulo_bias_ok] [, tries_before_fallback])
 *     returns a single random integer between 0 and max. max defaults to
 *      PHP_INT_MAX.
 *     modulo_bias_ok defaults to false. Setting it to true will make the
 *      generator faster, but the call will only return unbiased data if
 *      max=2^n-1 with n being a natural number (true for PHP_INT_MAX)
 *     tries_before_fallback defaults to 10 and specifies the maximum number
 *      of tries to generate a random number between 0 and max before falling
 *      back to modulo, even if modulo_bias_ok is set to false (which always
 *      works but is slightly biased towards smaller numbers).
 *     Uses variable amount of bytes from cache.
 *
 * errstr ()
 *     get a descriptive error message of the last occurred error
 *
 *****************************************************************************
 * Usage examples:
 *****************************************************************************

 // *** print out 10 random numers in the range from 0 to 10 ***
 include "qrbg.php";
 $gen = new QRBG("user","passwd");  // instanciate QRBG object
                                    // with username, password (mandatory)
 $gen->setCachesize(10);            // set cache size to 10
 for ($i=0 ; $i<10 ; $i++) {
   $t=$gen->rand(30);               // get a random number between 0 and 30
   if ($t===false)                  // error check. The "===" is important!
     die ($gen->errstr()."\n");     // print out error message
   echo "$t\n";
 }

 // *** calculate PI with stochastic method ***
 include "qrbg.php";
 $gen = new QRBG("user","passwd");  // instanciate QRBG object
                                    // with username, password (mandatory)
 $inside=0; $total=10000;
 $gen->setCachesize($total*6);      // set cache size to $total*6 bytes
                                    // (we need $total*2 floats per 3 bytes)
 for ($i=0 ; $i<$total ; $i++) {    // generate $total random dots
   $x=$gen->getFloat();             // get a random x coordinate
   $y=$gen->getFloat();             // get a random y coordinate
   if ($x===false || $y===false)    // error check. The "===" is important!
     die ($gen->errstr()."\n");     // print out error message
   if ($x*$x+$y*$y<=1) $inside++;   // is dot at xy inside the circle?
 }
 echo "&pi; ~ ".(4*$inside/$total); // print result
 
 *****************************************************************************/

class QRBG {
	// ***************************
	// * constants
	// ***************************
	// the default cache size.
	const DEFAULT_CACHE_SIZE=4096;
	const DEFAULT_SERVER="random.irb.hr";
	const DEFAULT_PORT=1227;

	// **********************************
	// * public error description members
	// **********************************
	// if a call returns false, the values below are set to an error class, 
	// error number and a detailed reason number if applicable
	public $errclass=0;		// 1: Invalid argument, 2: Communication Error, 4: Service Error
	public $errno=0;
	public $reason=0;

	// ***************************
	// * public functions
	// ***************************
	public function __construct($u, $p, $s=QRBG::DEFAULT_SERVER, $o=QRBG::DEFAULT_PORT, $c=QRBG::DEFAULT_CACHE_SIZE) {
		if (strlen($u)<1 || strlen($u)>100) {
			$this->seterror(1,0,0,"Username may not be empty or exceed 100 characters");
			return false;}
 		if (strlen($p)<6 || strlen($p)>100) {
			$this->seterror(1,0,0,"Password must be at least 6, at most 100 characters long");
			return false;}
		if ($o<1 || $o>65535) {$this->seterror(1,0,0,"Invalid argument: Port number not valid ($o)");return false;}
		if (!$s) {$this->seterror(1,0,0,"Hostname may not be blank");return false;}
		$this->server=$s;
		$this->port=$o;
		$this->user=$u;
		$this->pass=$p;
		return ($this->setCachesize($c));
	}

	public function setCachesize($c) {
		if ($c<1) {$this->seterror(1,0,0,"New cache size not valid ($c)");return false;}
		$this->cachesize=$c;
		return (true);
	}

	public function getChar() {return ($this->acquireBytes(1));}

	public function getByte() {
		$i=$this->acquireBytes(1);
		return ($i===false?false:ord($i));
    }

	public function getInt($unsigned=false) {
		$i=$this->acquireBytes(4);
		return $i===false?false:($unsigned?0x7FFFFFFF & array_pop(unpack("N",$i)):array_pop(unpack("N",$i)));
	}

	public function getFloat() {
		$i=$this->acquireBytes(3);
		return $i==false?false:array_pop(unpack("f",pack("L",0x3F800000 | array_pop(unpack("N",chr(0).$i)))))-1.0;
	}

	public function rand($max=PHP_INT_MAX, $modulo_bias_ok=false, $tries_before_fallback=10) {
		if ($max<1 || $max > PHP_INT_MAX) {
			$this->seterror(1,0,0,"max must be >0 and may not exceed ".PHP_INT_MAX);
			return false;
		}
		$bits=floor(log($max-1,2)+1); // bits needed
		$bytes=ceil($bits/8); //bytes needed
		do {
			$t=$this->acquireBytes($bytes);
			if ($t===false) return false;
			$r=0;
			for ($i=0;$i<$bytes;$i++) $r=$r*256+ord(substr($t,$i,1)); // calculate number from string
			$r&=(1<<$bits)-1; // mask unneeded bits
		} while (!$modulo_bias_ok && $r>=$max && --$tries_before_fallback);
		return $r%$max;
	}

	public function getChars($count) {return $this->acquireBytes($count);}

	public function errstr() {
		switch ($this->errclass) {
		case 0:
			return "";
		case 1:
			return "Invalid argument: ".$this->errstr;
		case 2:
			return "IO error: ".$this->errstr;
		case 3:
			return "Service error: ".$this->service_errno_str[$this->errno]." - ".$this->remedy_str[$this->errno];
		}
	}
	
	// ***************************
	// * protected members
	// ***************************
	// QRBG service hostname
	protected $server="";
	// QRBG service port
	protected $port=0;
	// QRBG service credentials
	protected $user="",$pass="";
	// current cache size - see notes above
	protected $cachesize;
	
	// ***************************
	// * private members
	// ***************************
	private $cache="";
	private $errstr="";
	private $service_errno_str=array(
		"OK",
		"Service was shutting down",
		"Server was/is experiencing internal errors",
		"Service said we have requested some unsupported operation",
		"Service said we sent an ill-formed request packet",
		"Service said we were sending our request too slow",
		"Authentication failed",
		"User quota exceeded");
	private $remedy_str=array(
		"None",
		"Try again later",
		"Try again later",
		"Upgrade your client software",
		"Upgrade your client software",
		"Check your network connection",
		"Check your login credentials",
		"Try again later, or contact Service admin to increase your quota(s)"
	);

	// *******************************
	// * private & protected functions
	// *******************************
	protected function acquireBytes($count) {
		// $r="";for ($i=0;$i<$count;$i++) $r.=chr(mt_rand(0,255));return $r;
		if (strlen($this->cache)<$count && !$this->acquireBytesFromService(max($this->cachesize,$count))) return false;
		$r=substr($this->cache,0,$count);
		$this->cache=substr($this->cache,$count);
		return $r;
	}

	private function acquireBytesFromService($count) {
		// setup request
		/*
		Client first (and last) packet:
		Size [B]		Content
		--------------	--------------------------------------------------------
		1				operation, from OperationCodes enum
		if operation == GET_DATA_AUTH_PLAIN, then:
		2				content size (= 1 + username_len + 1 + password_len + 4)
		1				username_len (must be > 0 and <= 100)
		username_len	username (NOT zero padded!)
		1				password_len (must be >= 6 and <= 100)
		password_len	password in plain 8-bit ascii text (NOT zero padded!)
		4				bytes of data requested

		Server first (and last) packet:
		Size [B]		Content
		--------------	--------------------------------------------------------
		1				response, from ServerResponseCodes enum
		1				response details - reason, from RefusalReasonCodes
		4				data_len, bytes of data that follow
		data_len		data
		*/
		$s=
			chr(0).		// GET_DATA_AUTH_PLAIN
			pack("n",strlen($this->user)+strlen($this->pass)+6).
			chr(strlen($this->user)).
			$this->user.
			chr(strlen($this->pass)).
			$this->pass.
			pack("N",$count);

		// connect
		if (!($fp = stream_socket_client("tcp://".$this->server.":".$this->port, $errno, $errstr))) {
			$this->seterror(2,$errno,0,"could not connect to $this->server:$this->port - ".$errstr);
			return false;}

		// send request
		if (fwrite($fp,$s,strlen($s))!=strlen($s)) {
			$this->seterror(2,$errno,0,"connection closed while sending request");
			fclose($fp);return false;}

		// get response header - error codes
		$s=fread($fp,6);
		if (!$s) {
			$this->seterror(2,$errno,0,"connection closed while getting response header");
			fclose($fp);return false;}
		$a=unpack("C2",$s);
		if ($a[1] || $a[2]) {
			$this->seterror(3,$a[1],$a[2]);
			fclose($fp);return false;}
		$a=unpack("C2/Na",$s);

		// get random numbers
		$s=""; while(strlen($s)<$a["a"] && !feof($fp)) $s.=fread($fp,$a["a"]-strlen($s));
		if (!$s) {
			$this->seterror(2,$errno,0,"connection closed while getting response");
			fclose($fp);return false;}
		$this->cache.=$s;

		fclose($fp);return true;
	}
	
	private function seterror($c,$n,$r,$s="") {
		$this->errclass=$c;
		$this->errno=$n;
		$this->reason=$r;
		$this->errstr=$s;
	}
}
?>	