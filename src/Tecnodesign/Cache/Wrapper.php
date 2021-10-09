<?php
/**
 * Variable Caching and retieving
 *
 * This package implements a common interface for caching both in files or memory
 *
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
class Tecnodesign_Cache_Wrapper
{
    private $cn='Tecnodesign_Cache', $key, $p, $w=false, $stat, $size;
    protected static $val=array();

    public function dir_closedir()
    {tdz::debug(__METHOD__.', '.__LINE__);}
    public function dir_opendir ( string $path , $options )
    {tdz::debug(__METHOD__.', '.__LINE__);}
    public function dir_readdir ()
    {tdz::debug(__METHOD__.', '.__LINE__);}
    public function dir_rewinddir ()
    {tdz::debug(__METHOD__.', '.__LINE__);}
    public function mkdir ( string $path , int $mode , int $options )
    {tdz::debug(__METHOD__.', '.__LINE__);}
    public function rename ( string $path_from , string $path_to )
    {tdz::debug(__METHOD__.', '.__LINE__);}
    public function rmdir ( string $path , int $options )
    {tdz::debug(__METHOD__.', '.__LINE__);}

    public function stream_open($url, $mode, $options, &$opened_path)
    {
        $this->p = 0;
        if(strpos($mode, 'w')!==false) {
            $this->url($url);
            self::$val[$this->key]='';
            $this->w = true;
        } else {
            $this->url_stat($url, null, true);
        }
        return true;
    }

    public function stream_read($l)
    {
        $r = substr(self::$val[$this->key], $this->p, $l);
        $this->p += strlen($r);
        return $r;
    }

    public function stream_write($s)
    {
        if(!isset(self::$val[$this->key])) self::$val[$this->key]='';
        self::$val[$this->key] .= $s;
        $this->w = true;
        $this->p += strlen($s);
        return strlen($s);
    }

    public function stream_close()
    {
        if($this->w) {
            $cn = $this->cn;
            $cn::set($this->key, self::$val[$this->key], Tecnodesign_Cache::$timeout);
            unset($cn);
        }
        unset(self::$val[$this->key]);
    }

    public function stream_tell()
    {
        return $this->p;
    }

    public function stream_eof()
    {
        return $this->p >= strlen(self::$val[$this->key]);
    }

    public function stream_seek($o, $w)
    {
        switch ($w) {
            case SEEK_SET:
                if ($o < strlen(self::$val[$this->key]) && $o >= 0) {
                     $this->p = $o;
                     return true;
                } else {
                     return false;
                }
                break;

            case SEEK_CUR:
                if ($o >= 0) {
                     $this->p += $o;
                     return true;
                } else {
                     return false;
                }
                break;

            case SEEK_END:
                if (strlen(self::$val[$this->key]) + $o >= 0) {
                     $this->p = strlen(self::$val[$this->key]) + $o;
                     return true;
                } else {
                     return false;
                }
                break;

            default:
                return false;
        }
    }

    public function stream_metadata($path, $option, $var) 
    {
        return false;
    }

    /**
     * Checks if given cache exists, and its metadata
     *
     * @param string $path
     * @param int $flags
     * @return array
     */
    public function url_stat ($url, $flags=null, $fetch=false)
    {
        if(is_null($this->stat) || ($this->key!=$url && 'cache:/'.$this->key!=$url)) {
            $url = $this->url($url);
            $cn = $this->cn;
            $m = $cn::lastModified($url, Tecnodesign_Cache::$timeout);
            if($m===false) {
                return false;
            } else {
                $m = (int) $m;
            }
            if($fetch) self::$val[$this->key] = $cn::get($url, Tecnodesign_Cache::$timeout);
            $this->stat = array(
              'dev' => 1,
              'ino' => 1,
              'mode' => 33206,
              'nlink' => 1,
              'uid' => 1,
              'gid' => 1,
              'rdev' => 1,
              'size' => (isset(self::$val[$this->key]))?(strlen(self::$val[$this->key])):($cn::size($url)),
              'atime' => $m,
              'mtime' => $m,
              'ctime' => $m,
              'blksize' => 4096,
              'blocks' => 8,
            );
            $this->stat += array_values($this->stat);
        }
        return $this->stat;
    }

    public function stream_stat()
    {
        if($this->stat) return $this->stat;
        return false;
    }

    public function url($url)
    {
        if(substr($url, 0, 7)=='cache:/') {
            $this->cn = 'Tecnodesign_Cache';
            $url = substr($url, 7);
        }
        $this->key = $url;
        return $url;
    }

    public static function stat($url)
    {
        $w = new Wrapper();
        $r = $w->url_stat($url);
        if($r) $w->stream_close();
        return $r;
    }
}

