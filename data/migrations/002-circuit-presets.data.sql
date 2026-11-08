-- statement
UPDATE f125_game_circuits SET preset_id = 3 WHERE id IN ('china-shanghai', 'japan-suzuka', 'bahrain', 'great-britain-silverstone', 'brazil-interlagos', 'abu-dhabi') AND preset_id IS NULL;

-- statement
UPDATE f125_game_circuits SET preset_id = 4 WHERE id IN ('saudi-arabia-jeddah', 'miami', 'canada-montreal', 'austria-spielberg', 'belgium-spa', 'azerbaijan-baku', 'las-vegas') AND preset_id IS NULL;

-- statement
UPDATE f125_game_circuits SET preset_id = 1 WHERE id IN ('monaco', 'madrid') AND preset_id IS NULL;

-- statement
UPDATE f125_game_circuits SET preset_id = 2 WHERE id IN ('catalunya-barcelona', 'hungary-budapest', 'netherlands-zandvoort', 'singapore', 'texas-austin', 'mexico', 'qatar-lusail') AND preset_id IS NULL;

-- statement
UPDATE f125_game_circuits SET preset_id = 5 WHERE id = 'monza' AND preset_id IS NULL;

-- statement
INSERT INTO f125_game_circuits (id, round_order, location, name, preset_id) VALUES ('imola', 25, 'Imola', 'Imola', 3);
