create database if not exists vegbuffet
  character set utf8mb4
  collate utf8mb4_unicode_ci;

use vegbuffet;

create table if not exists Admin (
  adminId int not null auto_increment,
  email varchar(255) not null,
  passwordHash varchar(255) not null,
  role varchar(32) not null default 'Staff',
  isActive boolean not null default 1,
  createdTime datetime not null,
  primary key (adminId),
  unique key uq_admin_email (email)
) engine=InnoDB;

create table if not exists Customer (
  customerId int not null auto_increment,
  email varchar(255) not null,
  passwordHash varchar(255) not null,
  fullName varchar(255) not null,
  phone varchar(64) null,
  createdTime datetime not null,
  primary key (customerId),
  unique key uq_customer_email (email)
) engine=InnoDB;

create table if not exists Day (
  dayId int not null auto_increment,
  dayName varchar(32) not null,
  sortOrder int not null,
  primary key (dayId),
  unique key uq_day_name (dayName),
  unique key uq_day_sort (sortOrder)
) engine=InnoDB;

create table if not exists Dish (
  dishId int not null auto_increment,
  dishName varchar(255) not null,
  imageUrl varchar(2048) null,
  primary key (dishId),
  unique key uq_dish_name (dishName)
) engine=InnoDB;

create table if not exists DayMenuItem (
  dayMenuItemId int not null auto_increment,
  dayId int not null,
  dishId int not null,
  primary key (dayMenuItemId),
  unique key uq_day_dish (dayId, dishId),
  key idx_daymenu_day (dayId),
  key idx_daymenu_dish (dishId),
  constraint fk_daymenu_day
    foreign key (dayId) references Day(dayId)
    on delete cascade
    on update cascade,
  constraint fk_daymenu_dish
    foreign key (dishId) references Dish(dishId)
    on delete restrict
    on update cascade
) engine=InnoDB;

create table if not exists TakeoutSet (
  setId int not null auto_increment,
  setName varchar(255) not null,
  description varchar(1024) not null,
  price decimal(10,2) not null,
  isAvailable boolean not null,
  primary key (setId),
  unique key uq_set_name (setName),
  key idx_set_available (isAvailable)
) engine=InnoDB;

create table if not exists `Order` (
  orderId int not null auto_increment,
  dailyOrderNumber int not null default 0,
  customerId int null,
  customerName varchar(255) not null,
  customerPhone varchar(64) not null,
  pickupTime datetime not null,
  status varchar(32) not null,
  totalAmount decimal(10,2) not null default 0.00,
  createdTime datetime not null,
  primary key (orderId),
  key idx_order_customer (customerId),
  key idx_order_daily (dailyOrderNumber),
  key idx_order_status (status),
  key idx_order_created (createdTime),
  key idx_order_pickup (pickupTime)
) engine=InnoDB;

create table if not exists OrderItem (
  orderItemId int not null auto_increment,
  orderId int not null,
  setId int not null,
  quantity int not null,
  primary key (orderItemId),
  key idx_orderitem_order (orderId),
  key idx_orderitem_set (setId),
  constraint fk_orderitem_order
    foreign key (orderId) references `Order`(orderId)
    on delete cascade
    on update cascade,
  constraint fk_orderitem_set
    foreign key (setId) references TakeoutSet(setId)
    on delete restrict
    on update cascade
) engine=InnoDB;

create table if not exists Payment (
  paymentId int not null auto_increment,
  orderId int not null,
  paymentStatus varchar(64) not null,
  referenceId varchar(255) not null,
  primary key (paymentId),
  unique key uq_payment_reference (referenceId),
  unique key uq_payment_order (orderId),
  constraint fk_payment_order
    foreign key (orderId) references `Order`(orderId)
    on delete cascade
    on update cascade
) engine=InnoDB;

