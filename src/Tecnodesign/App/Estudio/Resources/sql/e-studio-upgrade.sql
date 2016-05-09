drop trigger before_insert_entry;
drop trigger before_update_entry;
drop trigger before_insert_content;
drop trigger before_insert_tag;
drop trigger before_insert_relation;
drop trigger before_insert_permission;

delimiter $$
create trigger before_insert_entry
    after insert on tdz_entries_version for each row
    begin
        if @TRIGGER_DISABLED is null then
        replace into tdz_entries (id,title,summary,link,source,format,published,language,type,master,version,created,updated,expired)
            select id,title,summary,link,source,format,published,language,type,master,version,created,updated,expired
            from tdz_entries_version as e where e.id=new.id order by e.version desc limit 1;
        end if;    
    end$$

delimiter ;

delimiter $$
create trigger before_update_entry
    after update on tdz_entries_version for each row
    begin
        if @TRIGGER_DISABLED is null then
            replace into tdz_entries (id,title,summary,link,source,format,published,language,type,master,version,created,updated,expired)
                select id,title,summary,link,source,format,published,language,type,master,version,created,updated,expired
                from tdz_entries_version as e where e.id=new.id order by e.version desc limit 1;
        end if;    
    end$$

delimiter ;

delimiter $$
create trigger before_insert_content
    after insert on tdz_contents_version for each row
    begin
        if @TRIGGER_DISABLED is null then
            replace into tdz_contents (id, entry, slot, content_type, content, position, published, show_at, hide_at, version, created, updated, expired)
                select id, entry, slot, content_type, content, position, published, show_at, hide_at, version, created, updated, expired
                from tdz_contents_version as e where e.id=new.id order by e.version desc limit 1;
        end if;    
    end$$

delimiter ;

delimiter $$
create trigger before_insert_tag
    after insert on tdz_tags_version for each row
    begin
        if @TRIGGER_DISABLED is null then
            replace into tdz_tags (id, entry, tag, slug, version, created, updated, expired)
                select id, entry, tag, slug, version, created, updated, expired
                from tdz_tags_version as e where e.id=new.id order by e.version desc limit 1;
        end if;    
    end$$

delimiter ;

delimiter $$
create trigger before_insert_relation
    after insert on tdz_relations_version for each row
    begin
        if @TRIGGER_DISABLED is null then
            replace into tdz_relations (id, parent, entry, position, version, created, updated, expired)
                select id, parent, entry, position, version, created, updated, expired
                from tdz_relations_version as e where e.id=new.id order by e.version desc limit 1;
        end if;    
    end$$

delimiter ;

delimiter $$
create trigger before_insert_permission
    after insert on tdz_permissions_version for each row
    begin
        if @TRIGGER_DISABLED is null then
            replace into tdz_permissions (id, entry, role, credentials, version, created, updated, expired)
                select id, entry, role, credentials, version, created, updated, expired
                from tdz_permissions_version as e where e.id=new.id order by e.version desc limit 1;
        end if;    
    end$$

delimiter ;

alter table tdz_entries_version drop foreign key tdz_entries_version_id_tdz_entries_id;
alter table tdz_contents_version drop foreign key tdz_contents_version_id_tdz_contents_id;
alter table tdz_tags drop foreign key fk_tdz_tags__tdz_entries;
alter table tdz_contents drop foreign key tdz_contents_entry_tdz_entries_id;
alter table tdz_relations drop foreign key tdz_relations_entry_tdz_entries_id;
alter table tdz_relations drop foreign key tdz_relations_parent_tdz_entries_id;


create index created_idx on       tdz_entries_version (created desc) using btree;
create index created_idx on      tdz_contents_version (created desc) using btree;
create index created_idx on          tdz_tags_version (created desc) using btree;
create index created_idx on     tdz_relations_version (created desc) using btree;
create index created_idx on   tdz_permissions_version (created desc) using btree;
create index created_idx on       tdz_entries (created desc) using btree;
create index created_idx on      tdz_contents (created desc) using btree;
create index created_idx on          tdz_tags (created desc) using btree;
create index created_idx on     tdz_relations (created desc) using btree;
create index created_idx on   tdz_permissions (created desc) using btree;
