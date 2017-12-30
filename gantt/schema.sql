CREATE TABLE `gantt_links` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT,
	`source` int(11) NOT NULL,
	`target` int(11) NOT NULL,
	`type` varchar(1) NOT NULL
);
CREATE TABLE `gantt_tasks` (
	`id` INTEGER PRIMARY KEY AUTOINCREMENT,
	`text` varchar(255) NOT NULL,
	`start_date` datetime NOT NULL,
	`duration` int(11) NOT NULL,
	`progress` float NOT NULL,
	`parent` int(11) NOT NULL,
	`sortorder` int(11) NOT NULL
);

INSERT INTO `gantt_tasks` VALUES ('1', 'Project #1', '2017-04-01 00:00:00', '5', '0.8', '0', '1');
INSERT INTO `gantt_tasks` VALUES ('2', 'Task #1', '2017-04-06 00:00:00', '4', '0.5', '1', '2');
INSERT INTO `gantt_tasks` VALUES ('3', 'Task #2', '2017-04-05 00:00:00', '6', '0.7', '1', '3');
INSERT INTO `gantt_tasks` VALUES ('4', 'Task #3', '2017-04-07 00:00:00', '2', '0', '1', '4');
INSERT INTO `gantt_tasks` VALUES ('5', 'Task #1.1', '2017-04-05 00:00:00', '5', '0.34', '2', '5');
INSERT INTO `gantt_tasks` VALUES ('6', 'Task #1.2', '2017-04-11 13:22:17', '4', '0.5', '2', '6');
INSERT INTO `gantt_tasks` VALUES ('7', 'Task #2.1', '2017-04-07 00:00:00', '5', '0.2', '3', '7');
INSERT INTO `gantt_tasks` VALUES ('8', 'Task #2.2', '2017-04-06 00:00:00', '4', '0.9', '3', '8');

