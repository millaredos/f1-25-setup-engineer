-- statement
INSERT INTO f125_game_presets (id, name, ala_del, ala_tra, dif_acc, dif_dec, cam_del, cam_tra, toe_del, toe_tra, muelle_del, muelle_tra, barra_del, barra_tra, altura_del, altura_tra, freno_rep, freno_pre, pres_di, pres_dd, pres_ti, pres_td) VALUES
(1, 'Máxima carga aerodinámica', 48.0, 50.0, 60.0, 30.0, -3.5, -2.0, 0.03, 0.15, 31.0, 9.0, 11.0, 3.0, 28.0, 56.0, 57.0, 97.0, 22.7, 22.7, 20.5, 20.5),
(2, 'Mayor carga aerodinámica', 38.0, 38.0, 60.0, 50.0, -3.5, -2.0, 0.04, 0.13, 37.0, 17.0, 15.0, 8.0, 24.0, 53.0, 57.0, 97.0, 24.2, 24.2, 20.9, 20.9),
(3, 'Equilibrado', 25.0, 25.0, 60.0, 50.0, -3.5, -2.0, 0.04, 0.15, 37.0, 16.0, 15.0, 8.0, 25.0, 52.0, 57.0, 97.0, 24.2, 24.2, 21.3, 21.3),
(4, 'Mayor velocidad punta', 12.0, 12.0, 60.0, 50.0, -3.5, -2.0, 0.04, 0.15, 37.0, 17.0, 15.0, 8.0, 24.0, 51.0, 57.0, 97.0, 24.3, 24.3, 21.5, 21.5),
(5, 'Máxima velocidad punta', 0.0, 0.0, 60.0, 50.0, -3.5, -2.0, 0.02, 0.12, 37.0, 17.0, 15.0, 7.0, 21.0, 50.0, 57.0, 97.0, 24.5, 24.5, 22.0, 22.0);

-- statement
INSERT INTO f125_game_circuits (id, round_order, location, name, preset_id) VALUES
('australia-albert-park', 1, 'Australia', 'Albert Park Circuit', 3),
('china-shanghai', 2, 'China', 'Shanghai International Circuit', NULL),
('japan-suzuka', 3, 'Japón', 'Suzuka International Racing Course', NULL),
('bahrain', 4, 'Baréin', 'Bahrain International Circuit', NULL),
('saudi-arabia-jeddah', 5, 'Arabia Saudita', 'Jeddah Corniche Circuit', NULL),
('miami', 6, 'Miami', 'Miami International Autodrome', NULL),
('canada-montreal', 7, 'Canadá', 'Circuit Gilles-Villeneuve', NULL),
('monaco', 8, 'Mónaco', 'Circuit de Monaco', NULL),
('catalunya-barcelona', 9, 'Catalunya', 'Circuit de Barcelona-Catalunya', NULL),
('austria-spielberg', 10, 'Austria', 'Red Bull Ring', NULL),
('great-britain-silverstone', 11, 'Gran Bretaña', 'Silverstone Circuit', NULL),
('belgium-spa', 12, 'Bélgica', 'Circuit de Spa-Francorchamps', NULL),
('hungary-budapest', 13, 'Hungría', 'Hungaroring', NULL),
('netherlands-zandvoort', 14, 'Países Bajos', 'Circuit Zandvoort', NULL),
('monza', 15, 'Monza', 'Autodromo Nazionale Monza', NULL),
('madrid', 16, 'Madrid', 'Circuito de Madring', NULL),
('azerbaijan-baku', 17, 'Azerbaiyán', 'Baku City Circuit', NULL),
('singapore', 18, 'Singapur', 'Marina Bay Street Circuit', NULL),
('texas-austin', 19, 'Texas', 'Circuit of the Americas', NULL),
('mexico', 20, 'México', 'Autódromo Hermanos Rodríguez', NULL),
('brazil-interlagos', 21, 'Brasil', 'Autódromo José Carlos Pace', NULL),
('las-vegas', 22, 'Las Vegas', 'Las Vegas Strip Circuit', NULL),
('qatar-lusail', 23, 'Catar', 'Lusail International Circuit', NULL),
('abu-dhabi', 24, 'Abu Dabi', 'Yas Marina Circuit', NULL);
