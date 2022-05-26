create table /*_*/open_inst(
  passwd_hash varchar(255) not null,
  username varchar(64) not null,
  created timestamp not null,
  primary key (passwd_hash)
);
