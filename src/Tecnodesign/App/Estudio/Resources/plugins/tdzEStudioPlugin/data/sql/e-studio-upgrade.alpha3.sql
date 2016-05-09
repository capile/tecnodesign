use tecnodz;
drop table if exists tdz_entries_published;
drop view if exists tdz_entries_published;
create DEFINER=esr@localhost SQL SECURITY INVOKER
view tdz_entries_published as
select e.id as id,e.title as title,e.summary as summary,e.link as link,e.source as source,e.format as format,e.published as published,e.language as language,e.type as type,e.master as master,e.version as version,e.created as created,e.updated as updated,e.expired as expired from (tdz_entries_version e join tdz_entries o on(((o.id = e.id) and (e.updated >= o.published) and isnull(e.expired)))) group by e.id order by e.id,e.version desc;


drop table if exists tdz_contents_published;
drop view if exists tdz_contents_published;
create DEFINER=esr@localhost SQL SECURITY INVOKER
view tdz_contents_published as select c.id as id,c.entry as entry,c.slot as slot,c.content_type as content_type,c.content as content,c.position as position,c.published as published,c.show_at as show_at,c.hide_at as hide_at,c.version as version,c.created as created,c.updated as updated,c.expired as expired from (tdz_contents_version c join tdz_contents o on(((o.id = c.id) and (c.updated >= o.published) and isnull(c.expired)))) group by c.id order by c.id,c.version desc;

