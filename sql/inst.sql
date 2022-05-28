create table /*_*/inst(
  inst_id varchar(36),
  username varchar(64),
  passwd_hash varchar(255) not null,
  user_agent varchar(255) not null,
  app_version varchar(32) not null,
  update_count integer not null,
  created timestamp not null,
  modified timestamp not null,
  primary key (inst_id)
);
