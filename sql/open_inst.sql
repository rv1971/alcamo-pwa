create table /*_*/open_inst(
  passwd_hash varchar(255) not null,
  username varchar(64) not null,
  created timestamp not null,
  constraint /*_*/open_inst_pk primary key (passwd_hash),
  constraint /*_*/open_inst_account_fk foreign key (username)
    references /*_*/account(username)
);
