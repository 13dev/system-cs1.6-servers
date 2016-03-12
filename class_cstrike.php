<?php
/**
 * CStrike 1.6 Server Query class
 * 
 * @author Markus Schanz <coksnuss@gmx.de>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @subpackage cstrike
 * @version 1.0
 * @link http://developer.valvesoftware.com/wiki/Server_Queries Developerwiki
 */
class cstrike
{
	/**
	 * @var string IP Address or hostname of the server [e.g. 12.14.15.16 or cs.example.com]
	 */
	private $ip;
	
	/**
	 * @var int Port of the server [default: 27015]
	 */
	private $port;
	
	/**
	 * @var bool True if connection was successful, false on failure
	 */
	private $connect;
	
	/**
	 * @var bool|handle Contains the handle of the socket, or false on failure
	 */
	private $resource;
	
	/**
	 * @var string Contains the challenge number [HEX-String]
	 */
	private $challengeNumber;
	
	/**
	 * @var array Contains all availible serverdata
	 * [type]		= Packet ID [should be 49]
	 * [version]	= Protocol version that is used by the server
	 * [servername]	= Hostname of the server
	 * [map]		= Current map that is played
	 * [gamedir]	= Gamedirectory [usaly cstrike]
	 * [gamedesc]	= Short description of the game
	 * [appid]		= Steam Application ID
	 * [player_cur]	= Number of current players
	 * [player_max]	= Number of players the server can hold
	 * [bots]		= Current number of bots on the server
	 * [dedicated]	= "l" = listen, "d" = dedicated, "p" = SourceTV
	 * [system]		= "l" = Linux, "w" = Windows
	 * [password]	= 0 = No password, 1 = Password set
	 * [secure]		= 0 = No VAC, 1 = VAC activated
	 * [gameversion]= The version of the game
	 * 
	 * More array elements can differ from server to server since the extra data flag (EDF) determinate what data is send
	 */
	private $serverinfo;
	
	/**
	 * @var array Contains playerdata
	 * [type]			= Packet ID [should be 44]
	 * [player]			= Number of players that this array contains
	 * NOTE: If [player] is equal 0 [zero], the following array index does NOT exists!
	 * [players][n][index]		= Index for this entry [currently 0 for every entry]
	 * [players][n][name]		= The name of the player
	 * [players][n][kills]		= The kills that the player made already [still buggy if player made negative kills, e.g. -1]
	 * [players][n][connected]	= The time in seconds since the player is connected to the server
	 */
	private $playerinfo;
	
	/**
	 * @var array Contains servervariables
	 * [type]			= Packet ID [should be 45]
	 * [rule]			= Number of rules that this array contains
	 * [rules][n][name]	= Name of the rule / variable
	 * [rules][n][value]= Value of the rule / variable
	 */
	private $serverrules;
	
	/**
	 * Init
	 * 
	 * @param string $ip IP Address or hostname of the server [e.g. 12.14.15.16 or cs.example.com]
	 * @param int $port Port of the server [default: 27015]
	 * @return void
	 */
	public function __construct($ip, $port = 27015)
	{
		$this->ip = $this->isIp($ip) ? $ip : gethostbyname($ip);
		$this->port = $port;
		
		$this->serverinfo = array();
		$this->playerinfo = array();
		$this->serverrules = array();
	}
	
	/**
	 * Destruct
	 * 
	 * This will close all open connections
	 * Conections will be closed automaticaly after calling getInfo()
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		$this->close();
	}
	
	/**
	 * Returns an array with the choosen data
	 * [serverinfo] = {@see $serverinfo}
	 * [playerinfo] = {@see $playerinfo}
	 * [serverrules] = {@see $serverrules}
	 * Function returns array's based on the flag given by $info
	 * If $info is not specified, it returns all data
	 * 
	 * 1: Serverinfo
	 * 2: Playerinfo
	 * 4: Serverrules
	 * 
	 * @param int $info[optional]
	 * @return array
	 */
	public function getInfo($info = 7)
	{
		$this->connect = $this->connect();
		
		$this->getChallengeNumber();
		
		if($info & 1)
			$this->getServerinfo();
		
		if($info & 2)
			$this->getPlayerinfo();
			
		if($info & 4)
			$this->getServerrules();
		
		$rtn["serverinfo"] = $this->serverinfo;
		$rtn["playerinfo"] = $this->playerinfo;
		$rtn["serverrules"] = $this->serverrules;
		
		$this->close();
		
		return $rtn;
	}
	
	/**
	 * Creates a socket and then try to connect to the server
	 * 
	 * @return bool True on success, false on failure
	 */
	private function connect()
	{
		$this->resource = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		
		if($this->resource === false)
			return false;
		
		if(@socket_connect($this->resource, $this->ip, $this->port) === false)
			return false;
		
		return true;
	}
	
	/**
	 * Closes the socket connection
	 * 
	 * @return void
	 */
	private function close()
	{
		if($this->connect)
		{
			$this->connect = false;
			socket_shutdown($this->resource, 2);
			socket_close($this->resource);
		}
	}
	
	/**
	 * Executes an command and returns the result (header is already cut, so it begins with the packet type)
	 * Multiple packets get merged automatically
	 * 
	 * @param string $cmd The binary data to execute
	 * @return bool|string Result as hexstring, false on failure
	 */
	private function cmd($cmd)
	{
		if(!$this->connect)
			return false;
		
		if(@socket_write($this->resource, $cmd, 1400) === false)
			return false;
		
		if(($response = @socket_read($this->resource, 1400)) === false)
			return false;
		
		list($response) = unpack("H*0", $response);
		
		switch($this->getPacketData($response, "long"))
		{
			case "fffffffe":
				$this->getPacketData($response, "long");
				$num = $this->getPacketData($response, "byte");
				$this->getPacketData($response, "long");
				
				for($i = 1; $i < hexdec($num{1}); $i++)
				{
					if(($nextresponse = @socket_read($this->resource, 1400)) === false)
						return false;
					
					list($nextresponse) = unpack("H*0", $nextresponse);
					$this->getPacketData($nextresponse, "long");
					$this->getPacketData($nextresponse, "long");
					$this->getPacketData($nextresponse, "byte");
					
					$response .= $nextresponse;
				}
		}
		
		return $response;
	}
	
	/**
	 * Get the challenge number needed for queries
	 * 
	 * @return string The challenge number
	 */
	private function getChallengeNumber()
	{
		$data = $this->cmd("\xFF\xFF\xFF\xFF\x55\xFF\xFF\xFF\xFF");
		$this->getPacketData($data, "byte");
		$this->challengeNumber = $this->getPacketData($data, "long", false, false);
	}
	
	/**
	 * Get the serverinfo by sending the command "TSource Engine Query"
	 * The results are written into $serverinfo
	 * 
	 * @return void
	 */
	private function getServerinfo()
	{
		$data = $this->cmd("\xFF\xFF\xFF\xFFTSource Engine Query\x00");
		
		$rtn["type"]		= $this->getPacketData($data, "byte");
		$rtn["version"]		= $this->getPacketData($data, "byte", true);
		$rtn["servername"]	= $this->getPacketString($data);
		$rtn["map"]			= $this->getPacketString($data);
		$rtn["gamedir"]		= $this->getPacketString($data);
		$rtn["gamedesc"]	= $this->getPacketString($data);
		$rtn["appid"]		= $this->getPacketData($data, "short", true);
		$rtn["player_cur"]	= $this->getPacketData($data, "byte", true);
		$rtn["player_max"]	= $this->getPacketData($data, "byte", true);
		$rtn["bots"]		= $this->getPacketData($data, "byte", true);
		$rtn["dedicated"]	= chr($this->getPacketData($data, "byte", true));
		$rtn["system"]		= chr($this->getPacketData($data, "byte", true));
		$rtn["password"]	= $this->getPacketData($data, "byte", true);
		$rtn["secure"]		= $this->getPacketData($data, "byte", true);
		$rtn["gameversion"]	= $this->getPacketString($data);
		$rtn["ip"]			= $this->ip;
		
		// Extra data flag
		switch($this->getPacketData($data, "byte"))
		{
			case "80":
				$rtn["port"] = $this->getPacketData($data, "short", true);
				break;
			case "40":
				$rtn["specport"] = $this->getPacketData($data, "short", true);
				$rtn["specstr"] = $this->getPacketString($data);
				break;
			case "20":
				$rtn["gametag"] = $this->getPacketString($data);
		}
		
		$this->serverinfo = $rtn;
	}
	
	/**
	 * First this method gets the challenge number and write it into $challengeNumber
	 * After that it get the playerinfo by sending the byte 0x55 + $challengeNumber
	 * The results are written into $playerinfo
	 * 
	 * @return void
	 */
	private function getPlayerinfo()
	{
		$data = $this->cmd("\xFF\xFF\xFF\xFF\x55" . pack("H*", $this->challengeNumber));
		
		$rtn["type"]	= $this->getPacketData($data, "byte");
		$rtn["player"]	= $this->getPacketData($data, "byte", true);
		
		for($i = 0; $i < $rtn["player"]; $i++)
		{
			$rtn["players"][$i]["index"]	= $this->getPacketData($data, "byte", true);
			$rtn["players"][$i]["name"]	= $this->getPacketString($data);
			$rtn["players"][$i]["kills"]	= $this->getPacketData($data, "long", true);
			$rtn["players"][$i]["connected"]= $this->hex2float(hexdec($this->getPacketData($data, "float")));
		}
		
		$this->playerinfo = $rtn;
	}
	
	/**
	 * Get the serverrules by sending the byte 0x56 + $challengeNumber
	 * The results are written into $serverrules
	 * 
	 * @return void
	 */
	private function getServerrules()
	{
		$data = $this->cmd("\xFF\xFF\xFF\xFF\x56" . pack("H*", $this->challengeNumber));
		
		$rtn["type"] = $this->getPacketData($data, "byte");
		$rtn["rule"] = $this->getPacketData($data, "short", true);
		
		for($i = 0; $i < $rtn["rule"]; $i++)
		{
			$rtn["rules"][$i]["name"] = $this->getPacketString($data);
			$rtn["rules"][$i]["value"]= $this->getPacketString($data);
		}
		
		$this->serverrules = $rtn;
	}
	
	/**
	 * This method transform every byte in $hexstr into a ASCII char until the byte 0x00 is found
	 * The given param is given as a reference, and beeing cut!
	 * 
	 * @param string &$hexstr Datastring as hex values
	 * @return string String that was read
	 */
	private function getPacketString(&$hexstr)
	{
		$rtn = "";
		
		while(true)
		{
			$hex = $hexstr{0} . $hexstr{1};
			$hexstr = substr($hexstr, 2);
			if($hex == "00")
				break;
			
			$rtn .= chr(hexdec($hex));
		}
		
		return $rtn;
	}
	
	/**
	 * This method cut a byte / short / long from a datastring (given as hex values) and return it
	 * 
	 * @param string &$hexstr Datastring as hex values
	 * @param string $type Datatype [can be byte, short or long]
	 * @param bool $hexdec If true the value will be turned into an integer before returned, default: false
	 * @param bool $switchbytes If true the bytes are switched (FF 01 => 01 FF), default: true
	 * @return string Hex value that was read
	 */
	private function getPacketData(&$hexstr, $type, $hexdec = false, $switchbytes = true)
	{
		switch($type)
		{
			case "byte":
				$bytes = 1;
				break;
			case "short":
				$bytes = 2;
				break;
			case "long":
				$bytes = 4;
				break;
			default:
				$bytes = 4;
		}
		
		$rtn = "";
		
		for($i = 0; $i < $bytes; $i++)
		{
			if($switchbytes)
				$rtn = $hexstr{0} . $hexstr{1} . $rtn;
			else
				$rtn .= $hexstr{0} . $hexstr{1};
			
			$hexstr = substr($hexstr, 2);
		}
		
		if($hexdec)
			return hexdec($rtn);
		
		return $rtn;
	}
	
	/**
	 * This method is needed to convert the long, given at the playerinfo query, into a readable number
	 * The value is returned as an integer (NOT float!)
	 * 
	 * @param string $hex The hexvalues to convert
	 * @return int The converted value
	 */
	private function hex2float($hex)
	{
		$bin = str_pad(decbin($hex), 32, 0, STR_PAD_LEFT);
		
		$exp = bindec(substr($bin, 1, 8)) - 127;
		$mantisse = bindec(substr($bin, 9, 23));
		
		$float = (1 + ($mantisse / pow(2, 23))) * pow(2, $exp);
		$float = $bin{0} ? ($float * (-1)) : $float;
		
		return intval($float);
	}
	
	/**
	 * This method checks if the given parameter is an IP-Address or not
	 * 
	 * @param string $ip The string to check
	 * @return bool True if $ip is a IP-Address, false if not
	 */
	private function isIp($ip)
	{
		if(preg_match("/([0-9]{1,3}\.){3}[0-9]{1,3}/", $ip) == 1)
			return true;
		
		return false;
	}
}
?>