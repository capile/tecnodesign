alter table tdz_entries add index published_idx (published desc);
alter table tdz_entries_version add index updated_idx (updated desc);
alter table tdz_entries_version add index entry_idx (id);
alter table tdz_entries_version add index version_idx (version desc);

alter table tdz_contents add index published_idx (published desc);
alter table tdz_contents_version add index updated_idx (updated desc);
alter table tdz_contents_version add index entry_idx (entry);
alter table tdz_contents_version add index content_idx (id);
alter table tdz_contents_version add index version_idx (version desc);