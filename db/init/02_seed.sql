-- ============================================================
-- Seed data: days, default admin, sample dishes, day-menu
-- mappings, and pre-built takeout sets
-- ============================================================

-- Populate days of the week
insert into Day (dayName, sortOrder) values
  ('Monday', 1), ('Tuesday', 2), ('Wednesday', 3), 
  ('Thursday', 4), ('Friday', 5), ('Saturday', 6), ('Sunday', 7)
on duplicate key update dayName = values(dayName), sortOrder = values(sortOrder);

-- Default super admin (password: "password")
insert into Admin (email, passwordHash, role, isActive, createdTime) values
  ('admin@vegbuffet.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'SuperAdmin', 1, now())
on duplicate key update role = values(role), isActive = values(isActive);

-- Sample dish catalog
insert into Dish (dishName, description, imageUrl, isActive) values
  ('Tofu Stir-Fry', 'Tofu, bell pepper, broccoli, carrot, garlic, and soy-ginger glaze.', '/images/mon-1.jpg', 1),
  ('Vegetable Curry', 'Seasonal vegetables simmered in coconut curry sauce with warming spices.', '/images/mon-1.jpg', 1),
  ('Lentil Soup', 'Red lentils, onion, celery, tomato, herbs, and vegetable stock.', '/images/mon-1.jpg', 1),
  ('Chickpea Salad', 'Chickpeas, cucumber, tomato, parsley, lemon, and olive oil.', '/images/mon-1.jpg', 1),
  ('Eggplant Parmesan', 'Breaded eggplant, marinara, basil, and dairy-free cheese.', '/images/mon-1.jpg', 1),
  ('Vegan Pad Thai', 'Rice noodles, tofu, bean sprouts, peanuts, and tamarind sauce.', '/images/mon-1.jpg', 1),
  ('Mushroom Risotto', 'Creamy arborio rice with mushrooms, garlic, herbs, and vegetable stock.', '/images/mon-1.jpg', 1),
  ('Spinach and Feta Spanakopita', 'Crispy pastry filled with spinach, herbs, and dairy-free feta.', '/images/mon-1.jpg', 1),
  ('Vegetable Spring Rolls', 'Cabbage, carrot, mushroom, and glass noodles in a crisp wrapper.', '/images/mon-1.jpg', 1),
  ('Cauliflower Wings', 'Roasted cauliflower tossed in a spicy house sauce.', '/images/mon-1.jpg', 1),
  ('Butternut Squash Soup', 'Smooth roasted squash soup with onion, garlic, and coconut cream.', '/images/mon-1.jpg', 1),
  ('Vegetable Samosas', 'Spiced potato, peas, carrot, and herbs in flaky pastry.', '/images/mon-1.jpg', 1),
  ('Vegan Mac and Cheese', 'Pasta shells coated in a creamy cashew cheese sauce.', '/images/mon-1.jpg', 1),
  ('Zucchini Noodles with Pesto', 'Fresh zucchini ribbons with basil pesto, nuts, and cherry tomatoes.', '/images/mon-1.jpg', 1),
  ('Quinoa Power Bowl', 'Quinoa, roasted vegetables, leafy greens, and tahini dressing.', '/images/mon-1.jpg', 1),
  ('Vegan Sushi Rolls', 'Seasoned rice, cucumber, avocado, carrot, and seaweed.', '/images/mon-1.jpg', 1),
  ('Falafel Wrap', 'Falafel, lettuce, tomato, cucumber, and tahini in flatbread.', '/images/mon-1.jpg', 1),
  ('Sweet Potato Fries', 'Oven-roasted sweet potato fries with sea salt and herbs.', '/images/mon-1.jpg', 1),
  ('Stuffed Bell Peppers', 'Bell peppers filled with rice, beans, vegetables, and tomato sauce.', '/images/mon-1.jpg', 1),
  ('Mango Sticky Rice', 'Sweet sticky rice with ripe mango and coconut sauce.', '/images/mon-1.jpg', 1)
on duplicate key update
  description = values(description),
  imageUrl = values(imageUrl),
  isActive = values(isActive);

-- Assign dishes to each day of the week
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

-- Pre-built takeout sets + one "custom box" that lets customers pick dishes
insert into TakeoutSet (setName, description, price, imageUrl, isAvailable, sortOrder, allowsCustomSelection, selectionLimit) values
  ('Vegan Delight Box', 'A delicious combo of tofu stir-fry, spring rolls, and jasmine rice.', 14.99, '/images/mon-1.jpg', 1, 10, 0, 0),
  ('Gluten-Free Harvest', 'Zucchini noodles with pesto, chickpea salad, and lentil soup.', 16.50, '/images/mon-1.jpg', 1, 20, 0, 0),
  ('Spicy Thai Combo', 'Vegan Pad Thai, cauliflower wings, and mango sticky rice.', 15.00, '/images/mon-1.jpg', 1, 30, 0, 0),
  ('Custom Takeout Box', 'Choose up to 6 dishes from today''s weekly menu. If you list more than 6, staff will fulfill the first 6 items entered.', 18.00, '/images/mon-1.jpg', 1, 5, 1, 6)
on duplicate key update
  description = values(description),
  price = values(price),
  imageUrl = values(imageUrl),
  isAvailable = values(isAvailable),
  sortOrder = values(sortOrder),
  allowsCustomSelection = values(allowsCustomSelection),
  selectionLimit = values(selectionLimit);
