-- Resume seed data migration
INSERT INTO education (institution, qualification, details, start_date, end_date, sort_order) VALUES
('Negros Oriental State University – Bayawan–Sta. Catalina Campus', 'Bachelor of Science in Information Technology (BSIT)', 'Currently pursuing a Bachelor of Science in Information Technology.', '2024-08-01', NULL, 1),
('Basay National High School', 'Secondary Education', 'Completed secondary education.', '2018-06-01', '2024-04-30', 2)
ON DUPLICATE KEY UPDATE institution=VALUES(institution);

INSERT INTO experience (title, organization, details, start_date, end_date, sort_order) VALUES
('ITS 300 — Web Development Coursework', 'Negros Oriental State University', 'Built portfolio and web development projects using HTML, CSS, JavaScript, PHP, and MySQL.', '2024-08-01', NULL, 1),
('Database Management Coursework', 'Negros Oriental State University', 'Practiced database design and SQL queries as part of the BSIT curriculum.', '2024-08-01', NULL, 2),
('Adobe Animate Workshops', 'Negros Oriental State University', 'Practiced squash-and-stretch, slow-in/slow-out, motion paths, and animation projects.', '2024-08-01', NULL, 3)
ON DUPLICATE KEY UPDATE title=VALUES(title);
