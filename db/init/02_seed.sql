use vegbuffet;

insert into Day (dayName, sortOrder) values
  ('Monday', 1), ('Tuesday', 2), ('Wednesday', 3), 
  ('Thursday', 4), ('Friday', 5), ('Saturday', 6), ('Sunday', 7)
on duplicate key update dayName = values(dayName), sortOrder = values(sortOrder);

insert into Admin (email, passwordHash, role, isActive, createdTime) values
  ('admin@vegbuffet.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SuperAdmin', 1, now())
on duplicate key update role = values(role), isActive = values(isActive);

insert into Dish (dishName, imageUrl) values
  ('Tofu Stir-Fry', '/images/mon-1.jpg'),
  ('Vegetable Curry', '/images/mon-1.jpg'),
  ('Lentil Soup', '/images/mon-1.jpg'),
  ('Chickpea Salad', '/images/mon-1.jpg'),
  ('Eggplant Parmesan', '/images/mon-1.jpg'),
  ('Vegan Pad Thai', '/images/mon-1.jpg'),
  ('Mushroom Risotto', '/images/mon-1.jpg'),
  ('Spinach and Feta Spanakopita', '/images/mon-1.jpg'),
  ('Vegetable Spring Rolls', '/images/mon-1.jpg'),
  ('Cauliflower Wings', '/images/mon-1.jpg'),
  ('Butternut Squash Soup', '/images/mon-1.jpg'),
  ('Vegetable Samosas', '/images/mon-1.jpg'),
  ('Vegan Mac and Cheese', '/images/mon-1.jpg'),
  ('Zucchini Noodles with Pesto', '/images/mon-1.jpg'),
  ('Quinoa Power Bowl', '/images/mon-1.jpg'),
  ('Vegan Sushi Rolls', '/images/mon-1.jpg'),
  ('Falafel Wrap', '/images/mon-1.jpg'),
  ('Sweet Potato Fries', '/images/mon-1.jpg'),
  ('Stuffed Bell Peppers', '/images/mon-1.jpg'),
  ('Mango Sticky Rice', '/images/mon-1.jpg')
on duplicate key update imageUrl = values(imageUrl);

insert ignore into DayMenuItem (dayId, dishId)
select day_tbl.dayId, dish_tbl.dishId from Day as day_tbl join Dish as dish_tbl 
on dish_tbl.dishName in ('Tofu Stir-Fry','Vegetable Curry', 'Vegetable Spring Rolls', 'Mango Sticky Rice') where day_tbl.dayName = 'Monday';

insert ignore into DayMenuItem (dayId, dishId)
select day_tbl.dayId, dish_tbl.dishId from Day as day_tbl join Dish as dish_tbl 
on dish_tbl.dishName in ('Vegan Pad Thai', 'Cauliflower Wings', 'Vegetable Spring Rolls', 'Sweet Potato Fries') where day_tbl.dayName = 'Tuesday';

insert ignore into DayMenuItem (dayId, dishId)
select day_tbl.dayId, dish_tbl.dishId from Day as day_tbl join Dish as dish_tbl 
on dish_tbl.dishName in ('Lentil Soup', 'Vegetable Samosas', 'Falafel Wrap', 'Quinoa Power Bowl') where day_tbl.dayName = 'Wednesday';

insert ignore into DayMenuItem (dayId, dishId)
select day_tbl.dayId, dish_tbl.dishId from Day as day_tbl join Dish as dish_tbl 
on dish_tbl.dishName in ('Eggplant Parmesan', 'Zucchini Noodles with Pesto', 'Vegan Sushi Rolls') where day_tbl.dayName = 'Thursday';

insert ignore into DayMenuItem (dayId, dishId)
select day_tbl.dayId, dish_tbl.dishId from Day as day_tbl join Dish as dish_tbl 
on dish_tbl.dishName in ('Mushroom Risotto', 'Spinach and Feta Spanakopita', 'Stuffed Bell Peppers') where day_tbl.dayName = 'Friday';

insert ignore into DayMenuItem (dayId, dishId)
select day_tbl.dayId, dish_tbl.dishId from Day as day_tbl join Dish as dish_tbl 
on dish_tbl.dishName in ('Vegan Mac and Cheese', 'Chickpea Salad', 'Butternut Squash Soup', 'Mango Sticky Rice') where day_tbl.dayName = 'Saturday';

insert ignore into DayMenuItem (dayId, dishId)
select day_tbl.dayId, dish_tbl.dishId from Day as day_tbl join Dish as dish_tbl 
on dish_tbl.dishName in ('Vegetable Curry', 'Vegan Pad Thai', 'Eggplant Parmesan', 'Sweet Potato Fries') where day_tbl.dayName = 'Sunday';

insert ignore into TakeoutSet (setName, description, price, isAvailable) VALUES 
('Vegan Delight Box', 'A delicious combo of Tofu Stir-fry, Spring Rolls, and Jasmine Rice.', 14.99, 1),
('Gluten-Free Harvest', 'Zucchini Noodles with Pesto, Chickpea Salad, and Lentil Soup.', 16.50, 1),
('Spicy Thai Combo', 'Vegan Pad Thai, Cauliflower Wings, and Mango Sticky Rice.', 15.00, 1);
