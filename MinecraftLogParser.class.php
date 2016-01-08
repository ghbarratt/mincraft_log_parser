<?php


class MinecraftLogParser
{

	private $verbose = false;
	private $debugging = false;

	private $warnings = array();
	private $errors = array();
	
	private $data = array();
	private $mc_server_name = null;
	private $path = null;
	private $results_mode = null;

	
	public function __construct($path=null, $result_mode=null, $verbose=null, $mc_server_name=null, $debugging=null)
	{
		if($path!==null) $this->path = $path;
		if($result_mode!==null) $this->result_mode = $result_mode;
		if($verbose!==null) $this->verbose = $verbose;
		if($mc_server_name!==null) $this->mc_server_name = $mc_server_name;
		if($debugging!==null) $this->debugging = $debugging;
	}// constructor


	public function parse($path=null, $result_mode=null, $mc_server_name=null) 
	{

		$uuid_mapping = array(); 
		$player_data = array();
		$online = array();
		$online_timeline = array();
		$max_online = array();
		$current_nicknames = array();
		$nicknames = array();
		
		if(empty($path) && !empty($this->path)) $path = $this->path; 
		if(empty($result_mode) && !empty($this->result_mode)) $result_mode = $this->result_mode; 

		$original_cwd = getcwd();

		//echo 'DEBUG path: '.$path."\n";

		if(rtrim($path, DIRECTORY_SEPARATOR)=='.' || empty($path)) $path = getcwd();
		else chdir($path);
		
		if(empty($result_mode)) $result_mode = 'summarized'; 

		if($mc_server_name!==null) $this->mc_server_name = $mc_server_name;
		else if(strpos($path, DIRECTORY_SEPARATOR)!==false)
		{
			// guess the mc_server_name name using the patch
			$path_parts = explode(DIRECTORY_SEPARATOR, $path);
			$part_index = array_search('logs', $path_parts);
			if($part_index>0) $this->mc_server_name = $path_parts[$part_index-1];
		}

		if($this->verbose>1) echo 'NOTICE mc_server_name was determined to be: '.$this->mc_server_name."\n";

		$filenames = glob('*-*-*-*.log*');
		asort($filenames);

		foreach ($filenames as $filename)
		{
			$pattern = '/([\d]+-[\d]+-[\d]+)(?:-([\d]+))?\.([\S]+)$/';
			$found = preg_match($pattern, $filename, $matches);
			if($found)
			{
				$file_date = $matches[1];
				if($this->verbose>1) echo 'NOTICE Parsing file: '.$filename.' (determined date '.$file_date.")\n";
			}
			else continue;

			$fi = new SplFileInfo($filename);
			if($this->verbose>2) echo 'NOTICE extension is: '.$fi->getExtension(). "\n";
			if(stripos($fi->getExtension(), 'gz')!==false)
			{
				$lines = gzfile($filename);
			}
			else
			{
				$lines = file($filename);
			}

			
			if($this->verbose>1) echo 'NOTICE '.$filename.' has ' .count($lines) . " lines \n";
			
			$next_line_data = null;

			foreach($lines as $l)
			{
	
				//DEBUG
				/*if(strpos($l, 'I AM A SERVER')!=false)// && strpos($l, '/nick')!==false )
				{
					echo "\n !!!!!!!!!!!!!!!!!!!!!!!!!!!! THIS IS IT THIS IS IT THIS IS IT !!!!!!!!!!!!!!!!!!!!!!! \n\n";
					echo "DEBUG LINE:\n";
					echo $l;
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo 'DEBUG currently online';
					print_r($online);
					//exit;
					//$this->debugging = 5;
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					usleep(3200);
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
					echo "\n";
				}*/

				$line_data = $next_line_data;
				$next_line_data = $this->parseLine($l);

				if(count($line_data))
				{
					if(empty($line_data['ign']) && !empty($line_data['nick']))
					{	
						$original_nick = $line_data['nick'];
						$color_tags =         array('[0;30;22m', '[0;34;22m', '[0;32;22m', '[0;36;22m', '[0;31;22m', '[0;35;22m', '[0;33;22m', '[0;37;22m', '[0;30;1m', '[0;34;1m', '[0;32;1m', '[0;36;1m', '[0;31;1m', '[0;35;1m', '[0;33;1m', '[0;37;1m', '[5m', '[21m', '[9m', '[4m', '[3m', '[m');
						$color_replacements = array('&0',        '&1',        '&2',        '&3',        '&4',        '&5',        '&6',        '&7',        '&8',       '&9',       '&a',       '&b',       '&c',       '&d',       '&e',       '&f',       '&k',  '&l',   '&m',  '&n',  '&o',  '&r');
						$line_data['nick'] = str_replace($color_tags, $color_replacements, $line_data['nick']);
						$line_data['nick'] = preg_replace('/[^\x20-\x7E]/', '', $line_data['nick']);
						if(strpos($line_data['nick'], '~')!==false) 
						{
							$nick_parts = explode('~', $line_data['nick']);
							$line_data['nick'] = $nick_parts[1];
						}
						else
						{
							//echo 'DEBUG current nick: '.$line_data['nick']."\n";
							$count = true;
							while($count)
							{
								$line_data['nick'] = preg_replace('/^&[0-9a-z]/', '', $line_data['nick'], -1, $count);
							}
							//echo 'DEBUG original nick: '.$original_nick."\n";
						}
						//$line_data['nick'] = preg_replace('/^~/', '', $line_data['nick']);
						
						$line_data['nick'] = preg_replace('/&r$/', '', $line_data['nick']);
						if($this->debugging>1 && strpos($line_data['nick'], '[0;')!==false) 
						{
							echo "\n !!!!!!!!!!!!!!!!!!!!!!!!!!!! THIS IS IT THIS IS IT THIS IS IT !!!!!!!!!!!!!!!!!!!!!!! \n\n";
							echo 'DEBUG Trying to find nickname: "'.$line_data['nick']."\" in :\n";
							print_r($current_nicknames);
							sleep(2);
						}
						if(in_array($line_data['nick'], $current_nicknames)) $line_data['uuid'] = array_search($line_data['nick'], $current_nicknames); 
						else 
						{
							$found_match = false;
							// Okay now we are REALLY DESPERATE!
							if(!$found_match)
							{
								foreach($online as $uuid)
								{
									foreach($uuid_mapping as $temp_uuid=>$igns)
									{
										if($temp_uuid==$uuid)
										{
											foreach($igns as $temp_ign)
											{
												if(stripos($temp_ign, $line_data['nick'])!==false)
												{
													//echo "DEBUG 2\n";
													$found_match = true;
													$line_data['uuid'] = $uuid;
													break;		
												}
											}
											if($found_match) break;
										}
									}
									if($found_match) break;
								}
							}
							if(!$found_match)
							{
								foreach($online as $uuid)
								{
 									if(!empty($current_nicknames[$uuid]) && strlen(trim($line_data['nick']))>2 && $this->removeFormatters(strtolower($line_data['nick']))==$this->removeFormatters(strtolower($current_nicknames[$uuid])))
									{
										//echo "DEBUG 3\n";
										$found_match = true;
										$line_data['uuid'] = $uuid;
										break;		
									}
								}
							}
							
							// So so so desperate here
							if(!$found_match)
							{
								foreach($online as $uuid)
								{
									foreach($uuid_mapping as $temp_uuid=>$igns)
									{
										if($temp_uuid==$uuid)
										{
											foreach($igns as $temp_ign)
											{
												if(stripos($line_data['nick'], $temp_ign)!==false)
												{
													//echo "DEBUG 4\n";
													$found_match = true;
													$line_data['uuid'] = $uuid;
													break;		
												}
											}
											if($found_match) break;
										}
									}
									if($found_match) break;
								}
							}
							
		
							
							// DARN Now we have to start going through ALL the nicknames :-(
							if(!$found_match)
							{
								foreach($nicknames as $uuid=>$nick_counts)
								{
									foreach($nick_counts as $nick=>$nick_count)
									{
										if($line_data['nick']==$nick)
										{
											//echo "DEBUG SAD\n";
											$found_match = true;
											$line_data['uuid'] = $uuid;
											break;
										}
									}
									if($found_match) $break;
								}
							}
							



							if($found_match)
							{
								//echo 'DEBUG replacing '.$line_data['uuid'].' nickname: '.$current_nicknames[$line_data['uuid']].' with '.$line_data['nick']."\n";
								//echo $line_data['line']."\n";
								//sleep(2);
								$current_nicknames[$line_data['uuid']] = $line_data['nick'];
								// Also add to the bigger list of nicknames
								if(empty($nicknames[$line_data['uuid']])) $nicknames[$line_data['uuid']] = array();
								if(array_key_exists($line_data['nick'], $nicknames[$line_data['uuid']])) $nicknames[$line_data['uuid']][$line_data['nick']] += 1;
								else $nicknames[$line_data['uuid']][$line_data['nick']] = 1;
							}
							else if($this->debugging>1) 
							{
								echo "DEBUG line:\n";
								print_r($line_data['line']);
								echo "\n";
								echo 'DEBUG DID NOT FIND nickname: "'.$line_data['nick']."\" among current_nicknames\n";
								echo "DEBUG Current Nicknames:\n";
								print_r($current_nicknames);
								//if($line_data['nick']=='NotCinder')
								sleep(5);
								
							}
						}
						
					}

					if(empty($line_data['ign'])) 
					{
						if(in_array($line_data['uuid'], array_keys($uuid_mapping))) $line_data['ign'] = $uuid_mapping[$line_data['uuid']][0];
						else 
						{
							$this->warnings[] = 'Unable to determine ign for uuid: '.$line_data['uuid'];
							//print_r($uuid_mapping);
						}
						//echo 'DEBUG uuid: '.$line_data['uuid'].' has ign: '.$line_data['ign']."\n";
					}

					//if(stripos($line_data['line'], 'Wideline')!==false) 
					//{
						//echo 'DEBUG ign: '.$line_data['ign'].' line: '.$line_data['line']."\n";
						//sleep(1);
					//}
					if(!empty($line_data['ign']))
					{
						if(count($next_line_data) && $next_line_data['line_type']=='command_denial')
						{
							if($line_data['line_type']!='command' || $next_line_data['ign']!=$line_data['ign']) $this->warnings[] = 'A command was denied for '.$next_line_data['ign'].' but previous line was: "'.$line_data['line'].'"';
							else
							{
								//if(stripos($line_data['base_command'],'/nick')===0) echo "!!!!!!!!!!!!!!!!!!!  DEBUG THIS IS IT! DEBUG\n";
								if($this->verbose>2) echo 'NOTICE The command '.$line_data['line'].' was denied for '.$line_data['ign']."\n";
								if($this->verbose>2) echo 'NOTICE '.$next_line_data['line']."\n";
								$line_data['denied'] = true;
							}
						}
						if($line_data['line_type']=='uuid')
						{
							// We want the latest first
							if(empty($current_nicknames[$line_data['uuid']])) $current_nicknames[$line_data['uuid']] = $line_data['ign'];
							if(empty($uuid_mapping[$line_data['uuid']])) $uuid_mapping[$line_data['uuid']] = array();
							if(!in_array($line_data['ign'], $uuid_mapping[$line_data['uuid']])) array_unshift($uuid_mapping[$line_data['uuid']], $line_data['ign']); 
						}

						// uuid is critical
						if(empty($line_data['uuid']))
						{
							foreach($uuid_mapping as $uuid=>$igns)
							{
								foreach($igns as $ign)
								{
									if($ign==$line_data['ign']) 
									{
										$line_data['uuid'] = $uuid;
										break;
									}
								}			
								if(!empty($line_data['uuid'])) break;
							}
						}

						if(empty($line_data['uuid']))
						{
							$this->warnings[] = 'Do not have a uuid for line but ign is '.$line_data['ign'];
							//echo 'DEBUG line was: '.$line_data['line']."\n";
						}
						else
						{
							$line_data['date'] = $file_date;
							if(!empty($line_data['time'])) 
							{
								$line_data['timestamp'] = strtotime($file_date.' '.$line_data['time']);
								if($this->verbose>3) echo 'NOTICE Time for line determined to be: '.strtotime($file_date.' '.$line_data['time']).' using "'.$file_date.' '.$line_data['time']."\"\n";
								if($line_data['line_type']=='login' || $line_data['line_type']=='logout')
								{
									if ($line_data['line_type']=='login' && !in_array($line_data['uuid'], $online))
									{
										$online[] = $line_data['uuid'];
										if(empty($max_online['players']) || count($online) > count($max_online['players'])) $max_online = array('timestamp'=>$line_data['timestamp'], 'players'=>$online);
										if($this->debugging>2) echo 'DEBUG added '.$line_data['uuid'].' to online at '.$line_data['timestamp']."\n";
										$online_timeline[$line_data['timestamp']] = count($online);
									}
									if ($line_data['line_type']=='logout' && in_array($line_data['uuid'], $online))
									{
										if($this->debugging>2) echo 'DEBUG removed '.$line_data['uuid'].' from online at '.$line_data['timestamp']."\n";
										unset($online[array_search($line_data['uuid'], $online)]);
										$online_timeline[$line_data['timestamp']] = count($online);
									}
									$time_events[$line_data['uuid']][$line_data['timestamp']][] = $line_data;
								}
							}
							if($line_data['line_type']=='command')
							{	
								// Note nick command is expected to work like Essentials nick
								if (stripos($line_data['base_command'],'/nick')===0 && !empty($line_data['command']) && empty($line_data['denied']))
								{
									//echo 'DEBUG line '.$line_data['line']."\n";

									$command = preg_replace('/[\s]+/', ' ', $line_data['command']);
									$command_parts = explode(' ', trim($command));
									if(!empty($command_parts[2]) && strtolower($command_parts[2])=='on') unset($command_parts[2]);

									$line_data['nick_target'] = $line_data['ign'];
									$nick_target_uuid = false;
									
									if(count($command_parts)>1)
									{
										// It is very simple, if the first argument is 
										$nick = $command_parts[1];
										$target_match = false;
										if(count($command_parts)>2)
										{
											foreach($uuid_mapping as $uuid=>$igns) 
											{
												foreach($igns as $ign)	
												{
													if
													(
														strtolower($command_parts[1])==strtolower($ign)
														||
														(!empty($current_nicknames[$uuid]) && strtolower($command_parts[1])==strtolower($current_nicknames[$uuid]))
														||
														(strlen($command_parts[1])>2 && in_array($ign, $online) && stripos($ign, $command_parts[1])!==false)
													)
													{
														$target_match = true;
														$line_data['nick_target'] = $ign;
														$nick_target_uuid = $uuid;
														$nick = $command_parts[2];
														break;
													}
												}	
												if($target_match) break;
											}
										}
								
										if(strtolower($nick)=='off' || strpos($nick, 'off>')!==false)
										{
											$nick = $line_data['ign'];
											$line_data['nick_target'] = $line_data['ign'];
										}

										if(empty($nick_target_uuid) && !empty($line_data['nick_target']))
										{
											$target_match = false;
											foreach($uuid_mapping as $uuid=>$igns) 
											{
												foreach($igns as $ign)	
												{
													if(strtolower($line_data['nick_target'])==strtolower($ign))
													{
														$target_match = true;
														$nick_target_uuid = $uuid;
														break;
													}
												}	
												if($target_match) break;
											}
											
										}  
										//echo 'DEBUG nick_target_uuid: '.$nick_target_uuid."\n";
										//echo 'DEBUG nick: '.$nick."\n";

										// TODO: A nick cannot be someone else's IGN
										
										$current_nicknames[$nick_target_uuid] = $nick;
										//echo "DEBUG current_nicknames:\n";
										//print_r($current_nicknames);
										if(!array_key_exists($nick_target_uuid, $nicknames)) $nicknames[$nick_target_uuid] = array();
										if(array_key_exists($nick, $nicknames[$nick_target_uuid])) $nicknames[$nick_target_uuid][$nick] += 1;
										else $nicknames[$nick_target_uuid][$nick] = 1;
		
									}
								}
								else if (($line_data['base_command']=='/msg' || $line_data['base_command']=='/tell' || $line_data['base_command']=='/m' || $line_data['base_command']=='/t' || $line_data['base_command']=='/whisper') && !empty($line_data['command']) && empty($line_data['denied']))
								{
									$pattern = '@^/[\S]+[\s]+([\S]+)[\s]+(.+)$@';
									$found = preg_match($pattern, $line_data['command'], $matches);
									if($found)
									{
										$player_data[$line_data['uuid']]['chat_messages'][$line_data['timestamp']] = array('message'=>$matches[2], 'to'=>$matches[1], 'type'=>'msg');
										//echo 'DEBUG Found msg: '.$matches[2].' to: '.$matches[1]."\n";
										$chat_target_uuid = false;
										foreach($online as $temp_uuid)
										{
											$temp_igns = $uuid_mapping[$temp_uuid];
											if(in_array($matches[1], $temp_igns)) 
											{
												$chat_target_uuid = $temp_uuid;
												break;
											}
										}
										if($chat_target_uuid)
										{
											$last_chat_target[$line_data['uuid']] = $matches[1];
											$last_chat_target[$chat_target_uuid] = $line_data['ign'];
										}
									}
								}
								else if (($line_data['base_command']=='/r' || $line_data['base_command']=='/reply') && !empty($line_data['command']) && empty($line_data['denied']))
								{
									$pattern = '@^/[\S]+[\s]+(.+)$@';
									$found = preg_match($pattern, $line_data['command'], $matches);
									if($found)
									{	
										$player_data[$line_data['uuid']]['chat_messages'][$line_data['timestamp']] = array('message'=>$matches[1], 'to'=>(empty($last_chat_target[$line_data['uuid']]) ? 'UNKNOWN' : $last_chat_target[$line_data['uuid']].' (UNCERTAIN)'), 'type'=>'r');
										//echo 'DEBUG Found r: '.$matches[1]."\n";
									}
								}
							}
							else if ($line_data['line_type']=='chat')
							{
								if($this->debugging>5) echo 'DEBUG adding line to '.$line_data['uuid'].'\'s chat: '.$line_data['chat_message']."\n";
								$player_data[$line_data['uuid']]['chat_messages'][$line_data['timestamp']] = array('message'=>$line_data['chat_message'], 'to'=>'EVERYONE','type'=>'chat');
							}
							else if ($line_data['line_type']=='death')
							{
								$player_data[$line_data['uuid']]['deaths'][$line_data['death_method']][$line_data['timestamp']] = (!empty($line_data['death_extra']) ? $line_data['death_extra'] : '');
							}
							
							if(in_array($line_data['uuid'], $online)) $player_data[$line_data['uuid']][(empty($line_data['denied']) ? '' : 'denied_').$line_data['line_type']][] = $line_data;
						}// has uuid
					}
				}
			}
			//echo $filename.' has ' .filesize($filename) . " bytes\n";

		}


		foreach($nicknames as $uuid=>&$nicks)
		{
			arsort($nicks);
			//echo 'DEBUG nicknames for '.$uuid.": \n";
			//print_r($nicks);
			$player_data[$uuid]['nicknames'] = $nicks;
		}

		foreach($time_events as $uuid=>&$te)
		{
			$time_data[$uuid] = array();
			ksort($te);
			$time_data = $this->getTimeData($te);
			foreach($time_data as $tdk=>$tdv) $player_data[$uuid][$tdk] = $tdv;
			//echo 'DEBUG time_data for '.$uuid.": \n";
			//print_r($time_data);
		}

		foreach($uuid_mapping as $uuid=>$igns)
		{
			$player_data[$uuid]['igns'] = $igns;
			$player_data[$uuid]['current_nickname'] = $current_nicknames[$uuid];
		}

		foreach($player_data as $uuid=>&$pd)
		{
			if(!empty($pd['chat_messages'])) ksort($pd['chat_messages']);
			//else if($this->verbose>3 || $this->debugging>1) echo 'DEBUG Apparently there are no chat messages for '.$uuid."\n";
		}

		//echo "DEBUG datai for player:\n";
		//print_r($player_data['RuggedSurvivor']['chat_messages']);
		
		//echo "DEBUG online_timeline:\n";
		//print_r($online_timeline);
		
		//echo "DEBUG max_online:\n";
		//print_r($max_online);

		chdir($original_cwd);

		if(!empty($results_mode)) $results_mode = strtolower($results_mode);

		$this->data = array('server_data'=>array('max_online'=>$max_online, 'online_timeline'=>$online_timeline, 'uuids'=>$uuid_mapping));
		if($this->results_mode=='full') $this->data['player_data'] = $player_data;
		else $this->data['summarized_player_data'] = $this->getSummaryForPlayerData($player_data);
	
		return $this->data;

	}


	private function parseLine($line)
	{
		$data = array();
		//echo 'DEBUGGING line: '.$line;

		//[00:23:01] [Server thread/INFO]: Sugarpop20[/67.188.18.150:55722] logged in with entity id 123904 at ([hub]215.23264611314224, 58.0, 2066.9385436210773)

		// IMPORTANT - Currently assumes that a line can only match one pattern
		// List the important patterns first
		$patterns = array
		(
			'uuid' => array
			(
				'pattern' => '/UUID of player ([\S]+) is ([0-9a-z]{8}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{12})/',
				'mapping' => array('line','ign','uuid'),
			),
			'login' => array
			(
				'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[([^]]+)\]:[\s]+([^[]+)\[/?([\d]+\.[\d]+\.[\d]+\.[\d]+):?([\d]+)?\][\s]+logged in with entity id[\s]+([\d]+)[\s]+at[\s]+\(\[([^]]*)\]([\S]+),[\s]*([\S]+),[\s]*([\S]+)\)@',
				'mapping' => array('line','time','message_type','ign','ip','port','entity_id','world','x','y','z'),
			),
			'logout' => array
			(
				'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[([^]]+)\]:[\s]+([A-Za-z0-9\_]+)[\s]+lost connection:[\s]+(.*)@',
				'mapping' => array('line','time','message_type','ign','logout_how'),
			),
			'command' => array
			(
				'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[(Server thread/INFO)\]:[\s]+([\S]+) issued server command:[\s]+((/?[\S]+).*)@',
				'mapping' => array('line','time','message_type','ign','command','base_command'),
			),
			'command_denial' => array
			(
				'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[([^]]+)\]:[\s]+[^c]+c([\S]+)[\s]+.*4was denied access to command\.$@',
				'mapping' => array('line','time','message_type','ign'),
			),
			'death' => array
			(
				'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[(Server thread/INFO)\]:[\s]+(?:§[0-9a-z]{1}([\S]+)§[0-9a-z]{1}|([\S]+))[\s]+(?:(died)|(drowned)|tried to swim in (lava)|was (slain|shot|burnt|blown up) (?:by|to a crisp))(?:[\s]+(.*))?@',
				'mapping' => array('line','time','message_type','ign','ign','death_method','death_method','death_method','death_method','death_extra'),
			),
			'kick' => array
			(
				'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[([^]]+)\]:[\s]+(?:§[0-9a-z]Player§[0-9a-z][\s]+)?(?:§[0-9a-z])?(?:~)?([^ ^§]+)(?:§[0-9a-z])?[\s]+(?:§[0-9a-z])?kicked[\s]+([\S]+)[\s]+for[\s]+(.*).$@',
				'mapping' => array('line','time','message_type','kicking','ign','kick_reason'),
			),
			'chat' => array
			(
				//'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[([^]]+)\]:[\s]+(?:<([^>]+)>|(?:(?:[^~]+~|.+;[\d]+m)(.+)\^\[\[m>))[\s]+(.+)\^\[\[m$@',
				'pattern' => '@^\[([\d]+:[\d]+:[\d]+)\][\s]+\[([^]]+)\]:[\s]+[^<]*<([\S]+)>[\s]+(.+)$@',
				'mapping' => array('line','time','message_type','nick','chat_message'),
			),

		);

		$data = array();
		foreach($patterns as $p_type=>$p)
		{
			$found = preg_match($p['pattern'], $line, $matches);
			if($found)
			{
				foreach($matches as $mi=>$m)
				{
					if(!empty($m)) $data[$p['mapping'][$mi]] = $m; 
				}
				$data['line_type'] = $p_type;
				//if($p_type=='command' && $data['base_command']=='/nick')
				//{
					//echo 'DEBUG Found a '.$p_type.": \n"; 
					//print_r($data);
				//}
				break;
			}
		}
		return $data;
	}


	private function getTimeData($te)
	{
		$data = array();
		$data['first_login'] = false;
		$data['last_logout'] = false;
		$data['seconds_played'] = 0;
		$data['days_active'] = false;
		$on_server = false;

		// IMPORTANT $te is expected to be sorted on key BEFORE here
		ksort($te);

		foreach($te as $timestamp=>$e_set)
		{
			foreach($e_set as $e)
			{
				if(!empty($timestamp))
				{
					if(!is_array($data['days_active'])) $data['days_active'] = array();
					if(!in_array(date('Y-m-d', $timestamp), $data['days_active'])) $data['days_active'][] = date('Y-m-d', $timestamp);
				}
				if ($e['line_type']=='login')
				{
					if($on_server && $on_server!=$timestamp) $this->warnings[] = 'No logout found between '.$on_server.' and '.$timestamp."\n";
					$on_server = $timestamp;

					if ($data['first_login']===false || $timestamp<$data['first_login']) $data['first_login'] = $timestamp;
					
				}
				else if ($e['line_type']=='logout')
				{
					if(!$on_server) $this->warnings[] = 'No login found for logout at '.$timestamp."\n";
					else $data['seconds_played'] += ($timestamp - $on_server);
					$on_server = false;
					if ($data['last_logout']===false || $timestamp>$data['last_logout']) $data['last_logout'] = $timestamp;
				}
			}
		}

		return $data;	
	}


	private function getSummaryForPlayerData($player_data)
	{
		$summary = array();
	
		$pull_straight = array('igns', 'first_login', 'last_logout', 'seconds_played', 'days_active', 'chat_messages', 'current_nickname', 'nicknames', 'deaths', 'kick');

		// For each player we want:
		//	1. "Time Data"
		//      2. nicknames
		//      3. chat messages
		//	4. favorite commands
		//	5. most denied commands

		foreach($player_data as $uuid=>$pd)
		{
			foreach($pull_straight as $ps)
			{
				if(!empty($pd[$ps])) $summary[$uuid][$ps] = $pd[$ps];
				else $this->warnings[] = 'Unable to pull '.$ps.' from original data for '.$uuid.' ('.$pd['igns'][0].')';
			}
			if(!empty($pd['command'])) $summary[$uuid]['favorite_commands'] = $this->orderByFrequency($pd['command'], 'base_command', 10); 
			if(!empty($pd['denied_command'])) $summary[$uuid]['most_denied_commands'] = $this->orderByFrequency($pd['denied_command'], 'base_command', 10);		
		}

		return $summary;		
	}


	private function orderByFrequency($data, $what, $limit=false)
	{
		$results = array();
		foreach($data as $d)
		{
			if(empty($d[$what])) $this->warnings[] = 'DEBUG what: '.$what." is empty or missing\n";
			else 
			{
				if(empty($results[$d[$what]])) $results[$d[$what]] = 1; 
				else $results[$d[$what]]++;
			}
		}
		arsort($results);
		if($limit && is_numeric($limit)) $results = array_slice($results, 0, $limit);
		return $results;
	}


	private function removeFormatters($text)
	{
		//echo 'DEBUG Came in with: '.$text."\n";
		$count = true;
		while($count)
		{
			$text = preg_replace('/&[0-9,a-z]/', '', $text, -1, $count);
		}
		$text = str_replace('~', '', $text);
		//echo 'DEBUG Leaving with: '.$text."\n";
		return $text;
	}


	public function getData()
	{
		return $this->data;
	}


	public function saveDataToDatabase($dbh, $mc_server_name=null)
	{
		if(!is_object($dbh)) 
		{
			throw new Exception('You must set dbh as a PDO instance');
			return false;
		}

		if(empty($mc_server_name))
		{
			if(!empty($this->mc_server_name)) $mc_server_name = $this->mc_server_name;
			else
			{
				throw new Exception('You must specify a Minecraft server name');
				return false;
			}
		}

		$sth = $dbh->prepare('SELECT id FROM mc_servers WHERE name = :mc_server_name');	
		$sth->execute(array(':mc_server_name' => $mc_server_name));
		$mc_server_id = $sth->fetchColumn();
		if(!$mc_server_id)
		{
			throw new Exception('There is no Minecraft server named "'.$mc_server_name.'"');
                        return false;
		}

		if(!is_array($this->data) && count($this->data)<1)
		{
			throw new Exception('You must first parse the log to get the data');
			return false;
		}
		else
		{
			if($this->results_mode=='full') $player_data_key = 'player_data';
			else $player_data_key = 'summarized_player_data';	
			foreach($this->data[$player_data_key] as $uuid=>$pd)
			{
				if(!empty($pd['igns']))
				{
					$sth = $dbh->prepare('SELECT name FROM players WHERE uuid = :uuid');	
					$sth->execute(array(':uuid' => $uuid));
					$ign = $sth->fetchColumn();
					if($ign===false)
					{
						// No match. Do an insert
						$stmt = $dbh->prepare('INSERT INTO players (uuid, name, old_names) VALUES (:uuid, :name, :old_names)');
						if(!$stmt->execute(array(':uuid' => $uuid, ':name'=>$pd['igns'][0], ':old_names'=>(count($pd['igns'])>1 ? implode(', ',array_splice($pd['igns'], 1)) : null))))
						$this->warnings[] = 'Trouble inserting player with uuid: '.$uuid;
					}
					else if($ign!==$pd['igns'][0])
					{
						// UUID match, but not an IGN match
						// Player likely updated their IGN
						//$this->warnings[] = 'uuid: '.$uuid.' has player entry, but the ign is not: "'.$pd['igns'][0].'" (it is '.$ign.')';
						$stmt = $dbh->prepare('UPDATE players set name=:name, old_names=:old_names, names_last_checked=NULL WHERE uuid = :uuid');
						if(!$stmt->execute(array(':uuid' => $uuid, ':name'=>$pd['igns'][0], ':old_names'=>(count($pd['igns'])>1 ? implode(', ',array_splice($pd['igns'], 1)) : null))))
						$this->warnings[] = 'Trouble updating player with uuid: '.$uuid;
					}
				}

				$sth = $dbh->prepare('SELECT count(*) FROM player_data WHERE player_uuid = :uuid');	
				$sth->execute(array(':uuid' => $uuid));
				$match_count = $sth->fetchColumn();
				if($match_count)
				{
					// TODO Something more intelligent
					// but for now, just delete iti pre-existing player_data rows
					$stmt = $dbh->prepare('DELETE FROM player_data WHERE player_uuid = :uuid');
					if(!$stmt->execute(array(':uuid' => $uuid)))
					$this->warnings[] = 'Trouble deleting player_data for uuid: '.$uuid;
				}

				if(empty($pd['days_active'])) $pd['days_active'] = null;			
				if(empty($pd['chat_messages'])) $pd['chat_messages'] = null;			
				if(empty($pd['nicknames'])) $pd['nicknames'] = null;			
				if(empty($pd['favorite_commands'])) $pd['favorite_commands'] = null;			
				if(empty($pd['most_denied_commands'])) $pd['most_denied_commands'] = null;			
				if(empty($pd['deaths'])) $pd['deaths'] = null;			
				if(empty($pd['kick'])) $pd['kick'] = null;			
	
				// Do an insert of the player data
				$stmt = $dbh->prepare('INSERT INTO player_data (player_uuid, mc_server_id, first_login, last_logout, seconds_played, days_active, chat_messages, current_nickname, nicknames, favorite_commands, most_denied_commands, deaths, kicks) VALUES (:uuid, :mc_server_id, :first_login, :last_logout, :seconds_played, :days_active, :chat_messages, :current_nickname, :nicknames, :favorite_commands, :most_denied_commands, :deaths, :kicks)');
				if(!$stmt->execute(array(':uuid' => $uuid, ':mc_server_id'=>$mc_server_id, ':first_login'=>date('Y-m-d H:i:s', $pd['first_login']), ':last_logout'=>date('Y-m-d H:i:s', $pd['last_logout']), ':seconds_played'=>$pd['seconds_played'], ':days_active'=>serialize($pd['days_active']), ':chat_messages'=>serialize($pd['chat_messages']), ':current_nickname'=>$pd['current_nickname'], ':nicknames'=>serialize($pd['nicknames']), ':favorite_commands'=>serialize($pd['favorite_commands']), ':most_denied_commands'=>serialize($pd['most_denied_commands']), ':deaths'=>serialize($pd['deaths']), ':kicks'=>serialize($pd['kick']))))
				$this->warnings[] = 'Trouble inserting player with uuid: '.$uuid;
			}
			return true;
		}
		
	}
}
