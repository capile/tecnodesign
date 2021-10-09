<?php
/**
 * Calendar building and output methods
 * 
 * This package implements applications to build calendars in several formats.
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
class Tecnodesign_Calendar implements ArrayAccess
{
    private $_cache = null;
    private $_vars=array();
    private $_weeks=null;
    private $_days=null;
    private $_instance = null;
    private $_events=array();
    private $_start=null;
    private $_end=null;
    public static $week_days=array('sun'=>'Sunday','mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday'),
        $months=array('Jan'=>'January','Feb'=>'February','Mar'=>'March','Apr'=>'April','May'=>'May','Jun'=>'June','Jul'=>'July', 'Aug'=>'August', 'Sep'=>'September', 'Oct'=>'October', 'Nov'=>'November', 'Dec'=>'December'),
        $envelope           = true,
        $elCalendar         = 'div',
        $elMonth            = 'h3',
        $elWeek             = 'div',
        $elDay              = 'div',
        $attrCalendarClass  = 'tdz-calendar',
        $attrMonthClass     = 'tdz-c-month',
        $attrHeaderClass    = 'tdz-c-header',
        $attrWeekClass      = 'tdz-c-week',
        $attrDayClass       = 'tdz-c-day',
        $attrDayActiveClass = 'tdz-c-active',
        $continued = ' (continued)';


    public function __construct($vars)
    {
        if (is_array($vars)) {
            $this->_vars = $vars;
        }
        //Tecnodesign_Calendar::$_instance = $this;
    }

    /**
     * Renders calendar using the iCal format specification
     * 
     * @return string ical output
     */
    public function renderIcal()
    {
        $s = array();
        $s[]='BEGIN:VCALENDAR';
        $s[]='PRODID:' . ((isset($this->_vars['id']))?($this->_vars['id']):('-//Tecnodesign//Tecnodesign Calendar '.md5(tdz::scriptName()).'//EN'));
        $s[]='VERSION:2.0';
        $s[]='CALSCALE:GREGORIAN';
        $s[]='METHOD:PUBLISH';
        $tz = 0;
        if (isset($this->_vars['timezone'])) {
            $timezone =  $this->_vars['timezone'];
            $TZ = new DateTimeZone($timezone);
            $tz = -1*$TZ->getOffset(new DateTime((isset($this->_vars['start']))?($this->_vars['start']):('now'), new DateTimeZone('UTC')));
            // this should be set at every date, not only a global one
            unset($TZ);
        } else {
            $timezone = 'UTC';
        }
        $s[]='X-WR-TIMEZONE:'.$timezone;
        if (isset($this->_vars['name'])) {
            //$s[]='NAME:' . $this->_vars['name'];
            $s[]='X-WR-CALNAME:' . $this->_vars['name'];
        }
        if (isset($this->_vars['description'])) {
            //$s[]='DESCRIPTION:' . $this->_vars['description'];
            $s[]='X-WR-CALDESC:' . $this->_vars['description'];
        }
        if (isset($this->_vars['url'])) {
            $s[]='X-ORIGINAL-URL:' . tdz::buildUrl($this->_vars['url']);
            $s[]='URL:' . tdz::buildUrl($this->_vars['url']);
        }
        if (is_array($this->_vars['events'])) {
            foreach($this->_vars['events'] as $uid=>$e) {
                $start = false;
                if (isset($e['start']) && is_numeric($e['start'])) {
                    $start = $e['start'];
                } else if(isset($e['start'])) {
                    $start = strtotime($e['start']);
                }
                if (!$start) {
                    continue;
                }
                $end = false;
                if (isset($e['end']) && is_numeric($e['end'])) {
                    $end = $e['end'];
                } else if(isset($e['end'])) {
                    $end = strtotime($e['end']);
                }

                $time = (date('His', $start)>0 || date('His', $end)>0);
                if(isset($e['uid'])) {
                    $uid = $e['uid'];
                }
                $s[]='BEGIN:VEVENT';
                $s[]=($time)?('DTSTART:'.gmdate('Ymd\THis',$start+$tz).'Z'):('DTSTART;VALUE=DATE:'.date('Ymd',$start));
                $s[]=($time)?('DTEND:'.gmdate('Ymd\THis',$end+$tz).'Z'):('DTEND;VALUE=DATE:'.date('Ymd',$end));
                $s[]='DTSTAMP:' . gmdate('Ymd\THis') . 'Z';
                $s[]='UID:'.$uid;

                $ap=array('name'=>'CN', 'rsvp'=>'RSVP', 'type'=>'CUTYPE', 'role'=>'ROLE');
                if (isset($e['attendee']) && is_array($e['attendee'])) {
                	foreach($e['attendee'] as $a) {
                		if(!is_array($a)) {
                			$a=array('name'=>$a);
                		}
                		$as = 'ATTENDEE';
                		foreach($ap as $apn=>$apv) {
                			if(isset($a[$apn])) {
                				$v = $a[$apn];
                				if(is_bool($v)) {
                					$v = strtoupper(var_export($v, true));
                				} else {
                					$v = '"'.preg_replace('/\s+\"/', ' ', $v).'"';
                				}
                				$as.=';'.$apv.'='.$v;
                			}
                		}
                		if(isset($a['url'])) {
                			$as .= ':'.$a['url'];
                		}
                		$s[] = $as;
                	}
                }
                if (isset($e['organizer']) ) {
                	$a=$e['organizer'];
               		if(!is_array($a)) {
               			$a=array('name'=>$a);
               		}
               		$as = 'ORGANIZER';
               		foreach($ap as $apn=>$apv) {
               			if(isset($a[$apn])) {
               				$v = $a[$apn];
               				if(is_bool($v)) {
               					$v = strtoupper(var_export($v, true));
               				} else {
               					$v = '"'.preg_replace('/\s+\"/', ' ', $v).'"';
               				}
               				$as.=';'.$apv.'='.$v;
               			}
               		}
               		if(isset($a['url'])) {
               			$as .= ':'.$a['url'];
               		}
               		$s[] = $as;
                }
                $s[]='CLASS:PUBLIC';
                //$s[]='SEQUENCE:1';
                $lmod = false;
                if (isset($e['modified']) && is_numeric($e['modified'])) {
                    $lmod = $e['modified'];
                } else if(isset($e['modified'])) {
                    $lmod = strtotime($e['modified']);
                }
                if ($lmod) {
                    $s[]='LAST-MODIFIED:' . gmdate('Ymd\THis', $lmod) . 'Z';
                } else {
	                $created = false;
    	            if (isset($e['created']) && is_numeric($e['created'])) {
        	            $created = $e['created'];
            	    } else if(isset($e['created'])) {
                	    $created = strtotime($e['created']);
                	}
                	if ($created) {
                    	$s[]='LAST-MODIFIED:' . gmdate('Ymd\THis', $created) . 'Z';
                	}
                }
                if(isset($e['status'])) {
                	$s[]='STATUS:'.strtoupper($e['status']);
                } else {
	                $s[]='STATUS:CONFIRMED';
                }
                $s[]='SUMMARY:'.$e['summary'];
                if (isset($e['description'])) {
                    $s[] = 'DESCRIPTION:'.str_replace(array("\n", "\r"), array('\n', ''), $e['description']);
                }
                if (isset($e['location'])) {
                    $s[] = 'LOCATION:'.str_replace(array("\n", "\r"), array('\n', ''), $e['location']);
                }
                if (isset($e['categories'])) {
                    $s[] = 'CATEGORIES:'.strtoupper($e['categories']);
                }
                if (isset($e['url'])) {
                    $s[] = 'URL:'.tdz::buildUrl($e['url']);
                }
                $s[]='TRANSP:OPAQUE';
                if(isset($e['alarm']) && $e['alarm']) {
                    $alarm = $e['alarm'];
                    if(!is_array($alarm)) {
                        $alarm = array('description'=>$alarm);
                    }
                    $alarm += array('description'=>'Reminder','trigger'=>'-PT30M','action'=>'DISPLAY');
                    $s[]='BEGIN:VALARM';
                    $s[]='TRIGGER:'.$alarm['trigger'];
                    $s[]='ACTION:'.$alarm['action'];
                    $s[]='DESCRIPTION:'.$alarm['description'];
                    $s[]='END:VALARM';
                }




                $s[]='END:VEVENT';
            }
        }
        $s[]='END:VCALENDAR';
        $nl="\r\n";
        $limit=75;
        foreach($s as $k=>$v) {
            if(strlen($v)>$limit) {
                $s[$k]=tdz::wordwrap($v, $limit, $nl.' ', true);
            }
        }
        $s = implode($nl, $s);
        return $s;
    }
    
    public function renderPdf($source=false, $options=array())
    {
        $pdf = $this->getPdf($source, $options);
        tdz::download($source, null, $filename, 0, true);
    }
    
    /**
     * Renders calendar using the PDF format specification
     * 
     * @return string ical output
     */
    public function getPdf($source=false, $options=array())
    {
        
        if(!$source){
            $pdf = new Tecnodesign_Pdf($options);
        } else if(!is_object($source) || !($source instanceof Tecnodesign_Pdf)) {
            $pdf = new Tecnodesign_Pdf($options);
            $pdf->merge($source);
        } else {
            $pdf = $source;
        }
        //render

        return $pdf;

    }
    

    public function setStart($t)
    {
        if(!is_int($t)) $t = strtotime($t);

        if($t) {
            $this->_start = $t;
        }
    }

    public function setEnd($t)
    {
        if(!is_int($t)) $t = strtotime($t);

        if($t) {
            $this->_end = $t;
        }
    }

    /**
     * Returns a timestamp for when the calendar ends
     */
    public function getEnd()
    {
        if (is_null($this->_end)) {
            $this->_start = null;
            $this->getStart();
        }
        return $this->_end;
    }
    
    /**
     * Returns a timestamp for when the calendar begins
     */
    public function getStart()
    {
        if (is_null($this->_start)) {
            $minstart=false;
            $maxend=false;
            if (isset($this->_vars['events']) && is_array($this->_vars['events'])) {
                foreach($this->_vars['events'] as $e) {
                    $start = false;
                    if (isset($e['start']) && is_numeric($e['start'])) {
                        $start = $e['start'];
                    } else if(isset($e['start'])) {
                        $start = strtotime($e['start']);
                    }
                    if (!$start) {
                        continue;
                    } else if (!$minstart || $minstart > $start) {
                        $minstart = $start;
                    }
                    $end = false;
                    if (isset($e['end']) && is_numeric($e['end'])) {
                        $end = $e['end'];
                    } else if(isset($e['end'])) {
                        $end = strtotime($e['end']);
                    }
                    if ($end && (!$maxend || $end > $maxend)) {
                        $maxend = $end;
                    }
                }
            }
            if (!$minstart) {
                $minstart = time();
            }
            if (!$maxend) {
                $maxend = time();
            }
            if(is_null($this->_start)) {
                $this->_start = $minstart;
            }
            if(is_null($this->_end)) {
                $this->_end = $maxend;
            }
        }
        return $this->_start;
    }
    
    public static function parseDate($d)
    {
        if (preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})?$/', $d, $m)) {
            if(isset($m[3]) && $m[3]!='') {
                $d = strtotime("{$m[1]}-{$m[2]}-{$m[3]}");
            } else {
                $d = strtotime("{$m[1]}-{$m[2]}-01");
            }
        } else if (is_numeric($d)) {
            $d = (int) $d;
        } else {
            $d  =strtotime($d);
        }
        return $d;
    }
    
    
    public function getEventsByWeek($startWeek=null, $endWeek=null)
    {
        if(is_null($this->_weeks)) {
            $this->_weeks=array();
            $this->_days=array();
            if (isset($this->_vars['events']) && is_array($this->_vars['events'])) {
                foreach($this->_vars['events'] as $uid=>$e) {
                    $start = false;
                    if (isset($e['start']) && is_numeric($e['start'])) {
                        $start = $e['start'];
                    } else if(isset($e['start'])) {
                        $start = strtotime($e['start']);
                    }
                    if (!$start) {
                        continue;
                    }
                    $this->_vars['events'][$uid]['start']=$start;
                    $end = $start;
                    if (isset($e['end']) && is_numeric($e['end'])) {
                        $end = $e['end'];
                    } else if(isset($e['end'])) {
                        $end = strtotime($e['end']);
                    }
                    $this->_vars['events'][$uid]['end']=$end;
                    $w1 = date('YW', $start);
                    $w2 = date('YW', $end);
                    $d = $start;
                    while($d<=$start) {
                        $this->_days[date('Ymd',$d)][$uid]=$this->_vars['events'][$uid];
                        $d+=86400;
                    }
                    $this->_weeks[$w1][$uid]=$this->_vars['events'][$uid];
                    $w = $start + (86400*7);
                    while($w2>$w1) {
                        $w1 = date('YW', $w);
                        $w+=(86400*7);
                        $this->_weeks[$w1][$uid]=$this->_vars['events'][$uid];
                    }
                }
            }
        }
        if(!is_null($startWeek) || !is_null($endWeek)) {
            $r = array();
            foreach($this->_weeks as $i=>$o) {
                if($startWeek && $i<$startWeek) continue;
                else if($endWeek && $i > $endWeek) continue;
                $r[$i]=$o;
            }
            return $r;
        }

        return $this->_weeks;
    }
    

    /**
     * Renders the selected month, with all events that are linked to it
     *
     * @param mixed $month month position or date representation of the month
     * 
     * @return string selected month in HTML
     */
    public function renderMonth($month=0, $envelope = null)
    {
        $before = $after = null;
        if(is_null($envelope)) $envelope = static::$envelope;

        if($envelope) {
            $before = '<'.self::$elCalendar.' class="'.self::$attrCalendarClass.'">';
            $after = '</'.self::$elCalendar.'>';
        }

        $start = $this->getStart();
        if (is_int($month) && $month < 10000) {
            $startm=mktime(0,0,0,date('n',$start)+$month, 1, date('Y', $start));
        } else if(is_array($month)) {
        	$s = '';
        	foreach($month as $m) {
                $s .= $this->renderMonth($m, false);
        	}
        	return $before.$s.$after;
        } else {
            $month = Tecnodesign_Calendar::parseDate($month);
            $startm=mktime(0,0,0,date('n',$month), 1, date('Y', $month));
        }
        $starto=date('w',$startm);
        $start=mktime(0,0,0,date('m',$startm),1-$starto,date('Y',$startm));
        $end=mktime(0, 0, 0, date('n',$startm)+1, 0,   date('Y', $startm));
        $end0=$end;
        $end=mktime(0, 0, 0, date('n',$end)+1, 6-date('w',$end), date('Y', $end));
        $format = (isset($this->_vars['format']))?($this->_vars['format']):('extended');
        $ew = $this->getEventsByWeek((date('m',$start)=='01')?(date('Y00',$start)):(date('YW',$start)), date('YW',$end));
        $mn = static::$months[date('M', $startm)];
        $s = '<div class="'.static::$attrMonthClass.(($ew)?(' has-event'):('')).' ">'
           .   '<'.static::$elMonth.'>'.$mn.'</'.static::$elMonth.'>'
           .   '<'.static::$elWeek.' class="tdz-c-header '.static::$attrWeekClass.'">';
        foreach(static::$week_days as $wk=>$wn) {
            $s .= '<'.static::$elDay.' class="'.static::$attrDayClass.' '.$wk.'">'.$wn.'</'.static::$elDay.'>';
        }
        $s .= '</'.static::$elWeek.'>';

        $ed = $this->_days;
        $day=$start;
        $cm=date('m',$startm);
        $wds=array_keys(static::$week_days);
        $wi=0;
        while($day<=$end)
        {
            $wd=date('w',$day);
            $m=date('m',$day);
            $d=date('d',$day);
            if ($wd==0) {
                $wi++;
                $w = date('YW', $day+(6*86400));
                $class = ($format!='compact' && isset($ew[$w]))?(' lines'.count($ew[$w])):('');
                $s .= '<'.static::$elWeek.' class="'.static::$attrWeekClass.$class.'">';
            }
            $class=($m==$cm)?(' '.static::$attrDayActiveClass):('');
            $s .= '<'.static::$elDay.' class="'.static::$attrDayClass.' '.$wds[$wd].$class.'">';
            if($format=='compact') {
                $ds = date('Ymd', $day);
                $aclass='';
                if(($m==$cm) && isset($ed[$ds])) {
                    $ae = array_keys($ed[$ds]);
                    $href=false;
                    $atitle = '';
                    foreach($ed[$ds] as $e) {
                        $atitle .= (($atitle)?(', '):('')).$e['summary'];
                        if(isset($e['url'])){
                            $aclass.=' '.$e['class'];
                        }
                        if(!$href && isset($e['url'])){
                            $href = $e['url'];
                            break;
                        }
                    }
                    $aclass=($aclass)?(' class="'.trim($aclass).'"'):('');
                    $s .= '<a href="'.\tdz::xml($href).'" title="'.\tdz::xml($atitle).'" data-events="'.implode(',', $ae).'"'.$aclass.'>'.$d.'</a>';
                    unset($atitle, $ae);
                } else {
                    $s .= $d;
                }
                $s .= '</'.static::$elDay.'>';
            } else {
                $s .= $d.'</'.static::$elDay.'>';
            }
            if($wd==6) {
                $w = date('YW', $day);
                if($format!='compact' && isset($ew[$w])) {
                    $line = 1;
                    foreach ($ew[$w] as $uid=>$e) {
                        $class = 'event';
                        $es = $e['start'];
                        if ($e['start'] < $day - 6*86400) {
                            $es = $day - 6*86400;
                            $class .= ' continued';
                            $e['summary'] .= $this->continued;
                        }
                        $ewd = date('w',$es);
                        $ee = ((int)(($e['end'] - $es)/86400));
                        if ($ee + $ewd > 6) {
                            $ee = 6 - $ewd;
                            $class .= ' continue';
                        }
                        $class .= ' line'.$line++;
                        $a=array('class'=>"{$class} {$wds[$ewd]} duration{$ee}");
                        if (isset($e['class'])) {
                            $a['class'] .= " {$e['class']}";
                        }
                        if(isset($e['attributes'])) {
                            $a += $e['attributes'];
                            if(isset($e['attributes']['class'])) {
                                $a['class'] .= " {$e['attributes']['class']}";
                            }
                        }
                        $as = '';
                        foreach($a as $ak=>$av) {
                            $as .= ' '.$ak.'="'.tdz::xmlEscape($av).'"';
                        }
                        //  class=\"{$class} {$wds[$ewd]} duration{$ee}\"
                        $s .= "<a{$as}><span class=\"summary\">{$e['summary']}</span></a>";
                    }
                }
                $s .= '</'.static::$elWeek.'>';
            }
            $day=mktime(0,0,0,date('n',$day),date('j',$day)+1,date('Y',$day));
        }
        $s .= '</div>';

        return $before.$s.$after;
    }
    
    /**
     * Renders the selected month, with all events that are linked to it to PDF format
     *
     * @param mixed $month month position or date representation of the month
     * 
     * @return string selected month in HTML
     */
    public function renderMonthToPDF($month=0, $setup=array(), $pdf=null)
    {
        $start = $this->getStart();
        if (is_int($month) && $month < 10000) {
            $startm=mktime(0,0,0,date('n',$start)+$month, 1, date('Y', $start));
        } else {
            $month = Tecnodesign_Calendar::parseDate($month);
            $startm=mktime(0,0,0,date('n',$month), 1, date('Y', $month));
        }
        $starto=date('w',$startm);
        $start=mktime(0,0,0,date('m',$startm),1-$starto,date('Y',$startm));
        $end=mktime(0, 0, 0, date('n',$startm)+1, 0,   date('Y', $startm));
        $end=mktime(0, 0, 0, date('n',$end)+1, 6-date('w',$end), date('Y', $end));

        $month = static::$months[date('M', $startm)];
        $smonth = substr(static::$months[date('M', $startm)],0,3);
        $year = date('Y',$startm);
        
        $setup+=array(
            'table-width' => 120,
            'tr-height' => 120/7,
            'evt-height' => (120/7)/4,
        );

        $mlastletter = substr($smonth,-1);
        $mnspc = (in_array($mlastletter,array('b','i','l','t'))) ? (str_repeat('&nbsp;',5)) : (str_repeat('&nbsp;',3));
        $mn = '<span class="year">'.date('Y',$startm).$mnspc.'</span><br/>'.strtolower($smonth);
       
        $tdw = (isset($setup['table-width']) && is_numeric($setup['table-width'])) ? (' width="'.($setup['table-width']/7).'mm"') : (40);
        $trh = (isset($setup['tr-height']) && is_numeric($setup['tr-height'])) ? ($setup['tr-height']) : (40);
        
        $s .= '<table class="calendar">';
        $s .= '<tr class="month"><th><h3>'.$mn.'</h3></th>';
        for($i = 1; $i < 7; $i++ ){
            $s .='<th></th>';
        }
        $s .= '</tr>';
       
        //Dias da semana
        $s .= '<tr class="weekdays">';
        foreach(static::$week_days as $wk=>$wn) {
            $s .= '<th>'.strtoupper($wn).'</th>';
        }
        $s .= '</tr>';

        $ew = $this->getEventsByWeek();
        $day=$start;
        $cm=date('m',$startm);
        $wds=array_keys(static::$week_days);
        $wi=0;
        while($day<=$end)
        {
            $wd=date('w',$day);
            $m=date('m',$day);
            $d=date('d',$day);
            if ($wd==0) {
                $wi++;
                $w = date('YW', $day+(6*86400));
               // $class = (isset($ew[$w]))?(' lines'.count($ew[$w])):('');
               // $s .= ($wi%2)?("\n  <div class=\"week odd{$class}\">"):("\n  <div class=\"week even{$class}\">");
                 $s .= ($wi%2)?('<tr class="odd">'):('<tr class="even">');
            }
            $class=($m==$cm)?(static::$attrDayActiveClass):('');
            //$s .= "<td class=\"day {$wds[$wd]} {$class}\">{$d}</div>";
            //$s .= "<td class=\"day {$wds[$wd]} {$class}\"{$tdw}{$trh}>{$d}</td>";
            $s .= "<td class=\"day {$wds[$wd]} {$class}\"{$tdw}[[height]]>{$d}</td>";
            if($wd==6) {
                $w = date('YW', $day);
                //events
                if(isset($ew[$w])) {
                    $line = 1;
                    foreach ($ew[$w] as $uid=>$e) {
                        $class = '';
                        $es = $e['start'];
                        if ($e['start'] < $day - 6*86400) {
                            $es = $day - 6*86400;
                            $class .= ' continued';
                            $e['summary'] .= $this->continued;
                        }
                        $ewd = date('w',$es);
                        $ee = ((int)(($e['end'] - $es)/86400));
                        if ($ee + $ewd > 6) {
                            $ee = 6 - $ewd;
                            $class .= ' continue';
                        }
                        $line++;
                        //$class .= ' line'.$line++;
                        $a=array('class'=>"{$class}");
                        //if (isset($e['class'])) {
                        //    $a['class'] .= " {$e['class']}";
                        //}
                        if(isset($e['attributes'])) {
                            $a += $e['attributes'];
                            if(isset($e['attributes']['class'])) {
                                $a['class'] .= " {$e['attributes']['class']}";
                            }
                        }
                        $as = '';
                        foreach($a as $ak=>$av) {
                            $as .= ' '.$ak.'="'.tdz::xmlEscape($av).'"';
                        }
                        //exit(var_dump($e,$as));
                        //  class=\"{$class} {$wds[$ewd]} duration{$ee}\"
                        //$s .= "\n    <a{$as}><span class=\"summary\">{$e['summary']}</span></a>";
                    }
                }
                //verify the height
                if ($line*$setup['evt-height'] > $setup['tr-height']) {
                    $trh = (($line+1)*$setup['evt-height']);       
                    //exit(var_dump($trh));
                } 
                $s = str_replace('[[height]]',' height="'.$trh.'mm"', $s);
                
                $s .= "</tr>";
                
            }
            $day=mktime(0,0,0,date('n',$day),date('j',$day)+1,date('Y',$day));
        }
        $s .= "</table>";

        return $s;
    }    
    
    
    /**
     * Magic terminator. Returns the page contents, ready for output.
     * 
     * @return string page output
     */
    function __toString()
    {
        return $this->renderMonth(0);
    }

    /**
     * Magic setter. Searches for a set$Name method, and stores the value in $_vars
     * for later use.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function  __set($name, $value)
    {
        $m='set'.ucfirst($name);
        if (method_exists($this, $m)) {
            $this->$m($value);
        }
        $this->_vars[$name]=$value;
    }

    /**
     * Magic getter. Searches for a get$Name method, or gets the stored value in
     * $_vars.
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return mixed the stored value, or method results
     */
    public function  __get($name)
    {
        $m='get'.ucfirst($name);
        $ret = false;
        if (method_exists($this, $m)) {
            $ret = $this->$m();
        } else if (isset($this->_vars[$name])) {
            $ret = $this->_vars[$name]=$value;
        }
        return $ret;
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function offsetExists($name)
    {
        return isset($this->_vars[$name]);
    }
    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     * @see __get()
     */
    public function offsetGet($name)
    {
        return $this->__get($name);
    }
    /**
     * ArrayAccess abstract method. Sets parameters to the PDF.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     * 
     * @return void
     * @see __set()
     */
    public function offsetSet($name, $value)
    {
        return $this->__set($name, $value);
    }
    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return void
     */
    public function offsetUnset($name)
    {
        unset($this->_vars[$name]);
    }

}