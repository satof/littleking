drop database if exists littleking;
create database littleking;
use littleking;

drop table if exists members;
create table members(
  id int(11) auto_increment not null,
  name varchar(32) not null,
  password varchar(256),
  sort_order int(11),
  is_admin int(1),
  created_at datetime,
  updated_at datetime,
  primary key (id)
) default character set utf8;

drop table if exists schedule_dates;
create table schedule_dates(
  schedule_date date not null,
  comment varchar(256),
  created_at datetime,
  updated_at datetime,
  primary key (schedule_date)
) default character set utf8;

drop table if exists answers;
create table answers(
  member_id int(11) not null,
  schedule_date date not null,
  schedule_type_id int(4),
  comment varchar(256),
  created_at datetime,
  updated_at datetime,
  primary key (member_id, schedule_date)
) default character set utf8;

drop table if exists schedule_types;
create table schedule_types(
  id int(4) auto_increment not null,
  symbol varchar(8) not null,
  description varchar(64),
  sort_order int(4),
  created_at datetime,
  updated_at datetime,
  primary key (id)
) default character set utf8;

drop table if exists tokens;
create table tokens(
  token varchar(64) not null,
  member_id int(11) not null,
  created_at datetime,
  updated_at datetime,
  primary key (token)
) default character set utf8;

insert into schedule_types (symbol, description, sort_order, created_at, updated_at) values
('○', '終日参加', 1, now(), now()),
('□', 'AMのみ参加', 2, now(), now()),
('■', 'PMのみ参加', 3, now(), now()),
('●', '審判部役員等で終日別行動', 4, now(), now()),
('×', '不参加', 5, now(), now()),
('△', '未定', 6, now(), now());

insert into members (name, password, sort_order, is_admin, created_at, updated_at) values
('マキ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 1, 0, now(), now()),
('トク', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 2, 0, now(), now()),
('スギ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 3, 0, now(), now()),
('ホシ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 4, 0, now(), now()),
('オオ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 5, 0, now(), now()),
('ドバ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 6, 1, now(), now()),
('サワ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 7, 0, now(), now()),
('トミ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 8, 0, now(), now()),
('サト', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 9, 1, now(), now()),
('サイ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 10, 0, now(), now()),
('ゴト', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 11, 0, now(), now()),
('フセ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 12, 0, now(), now()),
('きむ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 13, 0, now(), now()),
('シブ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 14, 0, now(), now()),
('シマ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 15, 0, now(), now()),
('クラ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 16, 1, now(), now()),
('イシ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 17, 0, now(), now()),
('アサ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 18, 0, now(), now()),
('ムラ', '$2y$10$vM18tmVd3S4OHqJfU.ltNu.ZazlsGhchtrloy3QBsJRiv7Z3T6BWC', 19, 0, now(), now());

-- create user littleking@localhost IDENTIFIED BY "V4xZ2UNC";
-- GRANT ALL PRIVILEGES ON littleking.* TO littleking@localhost;
-- ALTER USER littleking@localhost IDENTIFIED WITH mysql_native_password BY "V4xZ2UNC";