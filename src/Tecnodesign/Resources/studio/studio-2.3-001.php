<?php
if(!class_exists('tdz')) {
    $tdz = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))).'/tdz.php';
    if(file_exists($f=preg_replace('#/capile/tecnodesign/tdz.php$#', '/autoload.php', $tdz))) $tdz = $f;
    $env='dev';
    define('TDZ_CLI', true);
    require_once $tdz;
    tdz::$log = 1;
    $argv = $_SERVER['argv'];
    while($arg=array_pop($argv)) {
        if(preg_match('/^[a-z0-9\-]+$/i', $arg) && file_exists($f=TDZ_APP_ROOT.'/config/'.$arg.'.yml')) {
            $cfg = $arg;
            break;
        }
        unset($arg, $f);
    }
    if(isset($cfg)) {
        $app = tdz::app($f, $cfg, $env);
    }
    if(Tecnodesign_Studio::VERSION<2.3) return false;
}
$cid = tdz::getApp()->studio['connection'];
$conn = tdz::connect($cid);
tdz::setConnection('', $conn);
$driver = (isset(tdz::$database[$cid]['dsn']))?(preg_replace('/\:.*/', '', tdz::$database[$cid]['dsn'])):('');
if($driver=='sqlite') {
    $unsigned=$auto_increment=$comment_created=$comment_updated=$comment_expired=$comment_sortable=$comment_versionable=$comment_serializable='';
} else {
    $auto_increment=' auto_increment comment \'auto-increment\'';
    $comment_created=' comment \'timestampable: before-insert\'';
    $comment_updated=' comment \'timestampable\'';
    $comment_expired=' comment \'soft-delete\'';
    $comment_sortable=' comment \'sortable\'';
    $comment_versionable=' comment \'versionable\'';
    $comment_serializable=' comment \'serializable: yaml\'';
    $unsigned=' unsigned';
}
//$trans=Tecnodesign_Model::beginTransaction($conn);
$args = (TDZ_CLI) ?Tecnodesign_App::request('argv') :[];
$tns = array();
foreach(Tecnodesign_Database::getTables($cid) as $t) {
    if(is_array($t)) $t = $t['table_name'];
    $tns[$t] = $t;
}

$H = Tecnodesign_Query::handler($cid);

if(!($S=$H->getTableSchema('tdz_entries'))) {
    $q = array(
"create table tdz_entries (
  id bigint(20) not null{$auto_increment},
  title varchar(200) null default null,
  summary text null default null,
  link varchar(200) null default null,
  source varchar(200) null default null,
  format varchar(100) null default null,
  published datetime null default null,
  language varchar(10) null default null,
  type varchar(100) null default null,
  master varchar(100) null default null,
  version bigint(20) null default null{$comment_versionable},
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_expired},
  primary key (id)
)",
    'create index tdz_entries_link_idx on tdz_entries(link asc)',
    'create index tdz_entries_type_idx on tdz_entries(type asc)',
    'create index tdz_entries_format_idx on tdz_entries(format asc)',
    'create index tdz_entries_published_idx on tdz_entries(published asc)',
    'create index tdz_entries_updated_idx on tdz_entries(updated asc)'
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_Entry'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_entries_version'))) {
    $q = array(
"create table tdz_entries_version (
  id bigint(20) not null default '0',
  title varchar(200) null default null,
  summary text null default null,
  link varchar(200) null default null,
  source varchar(200) null default null,
  format varchar(100) null default null,
  published datetime null default null,
  language varchar(10) null default null,
  type varchar(100) null default null,
  master varchar(100) null default null,
  version bigint(20) not null default '0',
  created datetime not null,
  updated datetime not null,
  expired datetime null default null,
  primary key (id, version),
  constraint tdz_entries_version__entry 
    foreign key (id) 
    references tdz_entries (id) 
    on delete cascade on update cascade
)",
  'create index tdz_entries_version_entry_idx on tdz_entries_version(id asc)',
  'create index tdz_entries_version_link_idx on tdz_entries_version(link asc)',
  'create index tdz_entries_version_updated_idx on tdz_entries_version(updated asc)',
  'create index tdz_entries_version_version_idx on tdz_entries_version(version asc)',
  'create index tdz_entries_version_first_published_idx on tdz_entries_version(id asc, published asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: ~'";
    tdz::query($q);
}

$contents = (in_array('--contents', $args));
if(!($S=$H->getTableSchema('tdz_contents'))) {
    $q = array(
"create table tdz_contents (
  id bigint(20) not null{$auto_increment},
  entry bigint(20) null default null,
  slot varchar(50) null default null,
  content_type varchar(100) null default null,
  source varchar(200) null default null,
  content longtext null default null,
  position varchar(250) null default null{$comment_sortable},
  published datetime null default null,
  show_at text null default null,
  hide_at text null default null,
  version bigint(20) null default null{$comment_versionable},
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_expired},
  primary key (id),
  constraint tdz_contents__entry
    foreign key (entry)
    references tdz_entries (id))",
  'create index tdz_contents_position_idx on tdz_contents(position asc)',
  'create index tdz_contents_slot_idx on tdz_contents(slot asc)',
  'create index tdz_contents_entry_idx on tdz_contents(entry asc)',
  'create index tdz_contents_published_idx on tdz_contents(published asc)',
  'create index tdz_contents_updated_idx on tdz_contents(updated asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_Content'";
    tdz::query($q);
} else {
    $contents = true;
    if(!isset($S['properties']['source'])) {
        $q = 'alter table tdz_contents add source varchar(200) null default null';
        if($driver=='mysql') $q .= ' after content_type';
        tdz::query($q);
    }
    if(!isset($S['properties']['attributes'])) {
        $q = 'alter table tdz_contents add attributes varchar(200) null default null';
        if($driver=='mysql') $q .= ' after source';
        tdz::query($q);
    }
}

if(!($S=$H->getTableSchema('tdz_contents_version'))) {
    $q = array(
"create table tdz_contents_version (
  id bigint(20) not null default '0',
  entry bigint(20) null default null,
  slot varchar(50) null default null,
  content_type varchar(100) null default null,
  source varchar(200) null default null,
  content longtext null default null,
  position varchar(250) null default null,
  published datetime null default null,
  show_at text null default null,
  hide_at text null default null,
  version bigint(20) not null default '0',
  created datetime not null,
  updated datetime not null,
  expired datetime null default null,
  primary key (id, version),
  constraint tdz_contents_version_id__parent
    foreign key (id)
    references tdz_contents (id)
    on delete cascade
    on update cascade)",
  'create index tdz_contents_version_entry_idx on tdz_contents_version(entry asc)',
  'create index tdz_contents_version_updated_idx on tdz_contents_version(updated asc)',
  'create index tdz_contents_version_version_idx on tdz_contents_version(version asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: ~'";
    tdz::query($q);
} else {
    if(!isset($S['properties']['source'])) {
        $q = 'alter table tdz_contents_version add source varchar(200) null default null';
        if($driver=='mysql') $q .= ' after content_type';
        tdz::query($q);
    }
    if(!isset($S['properties']['attributes'])) {
        $q = 'alter table tdz_contents_version add attributes varchar(200) null default null';
        if($driver=='mysql') $q .= ' after source';
        tdz::query($q);
    }
}

if(!($S=$H->getTableSchema('tdz_permissions'))) {
    $q = array(
"create table tdz_permissions (
  id bigint(20) not null{$auto_increment},
  entry bigint(20) null default null,
  role varchar(100) not null,
  credentials text null default null,
  version bigint(20) null default null{$comment_versionable},
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_expired},
  primary key (id),
  constraint tdz_permissions__entry
    foreign key (entry)
    references tdz_entries (id))",
  'create index tdz_permissions_entry_idx on tdz_permissions(entry asc)',
  'create index tdz_permissions_role_idx on tdz_permissions(role asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_Permission'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_permissions_version'))) {
    $q = array(
"create table tdz_permissions_version (
  id bigint(20) not null default '0',
  entry bigint(20) null default null,
  role varchar(100) not null,
  credentials text null default null,
  version bigint(20) not null default '0',
  created datetime not null,
  updated datetime not null,
  expired datetime null default null,
  primary key (id, version),
  constraint tdz_permissions_version__parent
    foreign key (id)
    references tdz_permissions (id)
    on delete cascade
    on update cascade)"
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: ~'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_relations'))) {
    $q = array(
"create table tdz_relations (
  id bigint(20) not null{$auto_increment},
  parent bigint(20) null default null,
  entry bigint(20) not null,
  position bigint(20) null default '1'{$comment_sortable},
  version bigint(20) null default null,
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_expired},
  primary key (id),
  constraint __Child__
    foreign key (entry)
    references tdz_entries (id),
  constraint __Parent__
    foreign key (parent)
    references tdz_entries (id))",
  'create index tdz_relations_parent_idx on tdz_relations(parent asc)',
  'create index tdz_relations_position_idx on tdz_relations(position asc)',
  'create index tdz_relations_entry_idx on tdz_relations(entry asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_Relation'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_relations_version'))) {
    $q = array(
"create table tdz_relations_version (
  id bigint(20) not null default '0',
  parent bigint(20) null default null,
  entry bigint(20) not null,
  position bigint(20) null default '1',
  version bigint(20) not null default '0',
  created datetime not null,
  updated datetime not null,
  expired datetime null default null,
  primary key (id, version),
  constraint tdz_relations_version__parent
    foreign key (id)
    references tdz_relations (id)
    on delete cascade
    on update cascade)",
  'create index tdz_relations_version_entry_idx on tdz_relations_version(entry asc)',
  'create index tdz_relations_version_updated_idx on tdz_relations_version(updated asc)',
  'create index tdz_relations_version_version_idx on tdz_relations_version(version asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: ~'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_tags'))) {
    $q = array(
"create table tdz_tags (
  id bigint(20) not null{$auto_increment},
  entry bigint(20) null default null,
  tag varchar(100) not null,
  slug varchar(100) not null,
  version bigint(20) null default null{$comment_versionable},
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_expired},
  primary key (id),
  constraint fk_tdz_tags__entry
    foreign key (entry)
    references tdz_entries (id)
    on delete no action
    on update no action)",
  'create index tdz_tags_entry_idx on tdz_tags(entry asc)',
  'create index tdz_tags_slug_idx on tdz_tags(slug asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_Tag'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_tags_version'))) {
    $q = array(
"create table tdz_tags_version (
  id bigint(20) not null,
  entry bigint(20) null default null,
  tag varchar(100) null default null,
  slug varchar(100) null default null,
  version bigint(20) not null,
  created datetime not null,
  updated datetime not null,
  expired datetime null default null,
  primary key (id, version),
  constraint fk_tdz_tags_version__parent
    foreign key (id)
    references tdz_tags (id)
    on delete no action
    on update no action)",
  'create index tdz_tags_version_id_idx on tdz_tags_version(id asc)',
  'create index tdz_tags_version_entry_idx on tdz_tags_version(entry asc)',
  'create index tdz_tags_version_slug_idx on tdz_tags_version(slug asc)',
  'create index tdz_tags_version_updated_idx on tdz_tags_version(updated asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: ~'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_contents_display'))) {
    $q = array(
"create table tdz_contents_display (
  content bigint(20) not null,
  link varchar(200) not null,
  version bigint(20) null,
  display tinyint(1) not null default 0,
  created datetime not null,
  updated datetime not null,
  expired datetime null,
  primary key (content, link),
  constraint fk_tdz_contents_display__content
    foreign key (content)
    references tdz_contents (id)
    on delete cascade
    on update cascade)"
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_ContentDisplay'";
    tdz::query($q);
}
if($contents) {
    // upgrade from previous studio versions, migrate column tdz_contents.show_at|hide_at to this table
    $q = 'select distinct id as content, version, show_at, hide_at, created, updated, expired from tdz_contents where coalesce(show_at,\'\')<>\'\'';
    $r = tdz::query($q);
    $e = Tecnodesign_Studio_ContentDisplay::$schema['events'];
    Tecnodesign_Studio_ContentDisplay::$schema['events'] = array();
    if($r) {
        try {
            foreach($r as $i=>$c) {
                $b = array(
                    'content'=>$c['content'],
                    'version'=>$c['version'],
                    'created'=>$c['created'],
                    'updated'=>$c['updated'],
                    'expired'=>$c['expired'],
                );
                if($c['show_at']) {
                    $s = preg_split('/[\n\s\,]+/', $c['show_at'], null, PREG_SPLIT_NO_EMPTY);
                    $b['display'] = 1;
                    foreach($s as $l) {
                        $b['link'] = $l;
                        Tecnodesign_Studio_ContentDisplay::replace($b);
                        unset($l);
                    }
                    unset($s);
               }
                if($c['hide_at']) {
                    $s = preg_split('/[\n\s\,]+/', $c['hide_at'], null, PREG_SPLIT_NO_EMPTY);
                    $b['display'] = 0;
                    foreach($s as $l) {
                        $b['link'] = $l;
                        Tecnodesign_Studio_ContentDisplay::replace($b);
                        unset($l, $C);
                    }
                    unset($s);
               }
               unset($c, $b, $r[$i], $i);
            }
        } catch(Exception $E) {
            tdz::debug((string)$E, var_export($C, true));
        }
    }
}


if(!($S=$H->getTableSchema('tdz_contents_display_version'))) {
    $q = array(
"create table tdz_contents_display_version (
  content bigint(20) not null,
  link varchar(200) not null,
  version bigint(20) not null,
  display tinyint(1) not null default 0,
  created datetime not null,
  updated datetime not null,
  expired datetime null,
  primary key (content, link, version),
  constraint fk_tdz_contents_display__parent
    foreign key (content, version)
    references tdz_contents_version (id, version)
    on delete cascade
    on update cascade)",
  'create index fk_tdz_contents_display_version__parent on tdz_contents_display_version(content asc, version asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: ~'";
    tdz::query($q);
}
if($contents) {
    // upgrade from previous studio versions, migrate column tdz_contents.show_at|hide_at to this table
    $q = 'select distinct id as content, version, show_at, hide_at, created, updated, expired from tdz_contents_version where coalesce(show_at,\'\')<>\'\'';
    $r = tdz::query($q);
    $e = Tecnodesign_Studio_ContentDisplay::$schema->events;
    Tecnodesign_Studio_ContentDisplay::$schema->events = array();
    Tecnodesign_Studio_ContentDisplay::$schema->tableName .= '_version';
    Tecnodesign_Studio_ContentDisplay::$schema->properties['version']->primary = true;
    if($r) {
        try {
            foreach($r as $i=>$c) {
                $b = array(
                    'content'=>$c['content'],
                    'version'=>$c['version'],
                    'created'=>$c['created'],
                    'updated'=>$c['updated'],
                    'expired'=>$c['expired'],
                );
                if($c['show_at']) {
                    $s = preg_split('/[\n\s]+/', $c['show_at'], null, PREG_SPLIT_NO_EMPTY);
                    $b['display'] = 1;
                    foreach($s as $l) {
                        $b['link'] = $l;
                        Tecnodesign_Studio_ContentDisplay::replace($b);
                        unset($l);
                    }
                    unset($s);
               }
                if($c['hide_at']) {
                    $s = preg_split('/[\n\s]+/', $c['hide_at'], null, PREG_SPLIT_NO_EMPTY);
                    $b['display'] = 0;
                    foreach($s as $l) {
                        $b['link'] = $l;
                        Tecnodesign_Studio_ContentDisplay::replace($b);
                        unset($l, $C);
                    }
                    unset($s);
               }
               unset($c, $b, $r[$i], $i);
            }
        } catch(Exception $E) {
            tdz::debug((string)$E, var_export($C, true));
        }
    }
    Tecnodesign_Studio_ContentDisplay::$schema->events = $e;
    Tecnodesign_Studio_ContentDisplay::$schema->tableName = 'tdz_contents_display';
    Tecnodesign_Studio_ContentDisplay::$schema->properties['version']->primary = null;
}

if(!($S=$H->getTableSchema('tdz_groups'))) {
    $q = array(
"create table tdz_groups (
  id int(10) $unsigned not null{$auto_increment},
  name varchar(100) not null,
  priority int(10) not null,
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_expired},
  primary key (id)
)",
  'create index tdz_groups_priority_idx on tdz_groups(priority asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_Group'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_users'))) {
    $q = array(
"create table tdz_users (
  id int(10) $unsigned not null{$auto_increment},
  login varchar(100) not null,
  name varchar(200) null,
  password varchar(100) null,
  email varchar(100) null,
  details text null{$comment_serializable},
  accessed datetime null,
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_updated},
  primary key (id)
)",
  'create index login_idx on tdz_users(login asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_User'";
    tdz::query($q);
}

if(!($S=$H->getTableSchema('tdz_credentials'))) {
    $q = array(
"create table tdz_credentials (
  user int(10) $unsigned not null,
  groupid int(10) $unsigned not null,
  created datetime not null{$comment_created},
  updated datetime not null{$comment_updated},
  expired datetime null default null{$comment_expired},
  primary key (user,groupid))",
  'create index user_idx on tdz_credentials(user asc)',
  'create index groupid_idx on tdz_credentials(groupid asc)',
    );
    if($driver!='sqlite') $q[0] .= "comment = 'className: Tecnodesign_Studio_Credential'";
    tdz::query($q);
}


if(!($S=$H->getTableSchema('z_index_interfaces'))) {
    $H->create(Tecnodesign_Studio_IndexInterfaces::$schema);
}
if(!($S=$H->getTableSchema('z_index'))) {
    $H->create(Tecnodesign_Studio_Index::$schema);
}
if(!($S=$H->getTableSchema('z_index_properties'))) {
    $H->create(Tecnodesign_Studio_IndexProperties::$schema);
}
if(!($S=$H->getTableSchema('z_index_log'))) {
    $H->create(Tecnodesign_Studio_IndexLog::$schema);
}
