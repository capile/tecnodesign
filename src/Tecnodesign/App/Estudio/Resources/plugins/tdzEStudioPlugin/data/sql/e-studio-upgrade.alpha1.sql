

-- tdz_contents
alter table tdz_contents add column created datetime not null default '0000-00-00 00:00:00' after created_at;
update tdz_contents set created=created_at;
alter table tdz_contents drop column created_at;

alter table tdz_contents_version add column created datetime null, add column updated datetime null;
update tdz_contents_version,tdz_contents set tdz_contents_version.created=tdz_contents.created, tdz_contents_version.updated=tdz_contents.updated
where tdz_contents_version.id=tdz_contents.id;
update tdz_contents_version set created=updated where version=1;

-- tdz_entries
alter table tdz_entries add column created datetime not null default '0000-00-00 00:00:00' after created_at;
update tdz_entries set created=created_at;
alter table tdz_entries drop column created_at;

alter table tdz_entries_version add column created datetime null, add column updated datetime null;
update tdz_entries_version,tdz_entries set tdz_entries_version.created=tdz_entries.created, tdz_entries_version.updated=tdz_entries.updated
where tdz_entries_version.id=tdz_entries.id;
update tdz_entries_version set created=updated where version=1;

-- tdz_permissions
alter table tdz_permissions add column created datetime not null default '0000-00-00 00:00:00' after created_at;
update tdz_permissions set created=created_at;
alter table tdz_permissions drop column created_at;

alter table tdz_permissions_version add column created datetime null, add column updated datetime null;
update tdz_permissions_version,tdz_permissions set tdz_permissions_version.created=tdz_permissions.created, tdz_permissions_version.updated=tdz_permissions.updated
where tdz_permissions_version.id=tdz_permissions.id;
update tdz_permissions_version set created=updated where version=1;

-- tdz_relations
alter table tdz_relations add column created datetime not null default '0000-00-00 00:00:00' after created_at;
update tdz_relations set created=created_at;
alter table tdz_relations drop column created_at;

alter table tdz_relations_version add column created datetime null, add column updated datetime null;
update tdz_relations_version,tdz_relations set tdz_relations_version.created=tdz_relations.created, tdz_relations_version.updated=tdz_relations.updated
where tdz_relations_version.id=tdz_relations.id;
update tdz_relations_version set created=updated where version=1;



-- expired
-- tdz_contents
alter table tdz_contents_version add column expired datetime null;
update tdz_contents_version,tdz_contents set tdz_contents_version.expired=tdz_contents.expired where tdz_contents_version.id=tdz_contents.id;

-- tdz_entries
alter table tdz_entries_version add column expired datetime null;
update tdz_entries_version,tdz_entries set tdz_entries_version.expired=tdz_entries.expired where tdz_entries_version.id=tdz_entries.id;


-- tdz_permissions
alter table tdz_permissions_version add column expired datetime null;
update tdz_permissions_version,tdz_permissions set tdz_permissions_version.expired=tdz_permissions.expired where tdz_permissions_version.id=tdz_permissions.id;

-- tdz_relations
alter table tdz_relations_version add column expired datetime null;
update tdz_relations_version,tdz_relations set tdz_relations_version.expired=tdz_relations.expired where tdz_relations_version.id=tdz_relations.id;


-- reset versioning system
delete from tdz_relations_version;
delete from tdz_permissions_version;
delete from tdz_contents_version;
delete from tdz_entries_version;
update tdz_relations set version=1;
update tdz_permissions set version=1;
update tdz_contents set version=1;
update tdz_entries set version=1;

update tdz_contents set published=now() where published is not null;
update tdz_entries set published=now() where published is not null;

insert into tdz_entries_version select * from tdz_entries;
insert into tdz_contents_version select * from tdz_contents;
insert into tdz_permissions_version select * from tdz_permissions;
insert into tdz_relations_version select * from tdz_relations;




