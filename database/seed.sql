-- ============================================================
-- HABARI HUB  –  Seed / Demo Data
-- Run AFTER schema.sql
-- ============================================================
SET time_zone = "+00:00";

-- Default site settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_name',           'Habari Hub',                              'general'),
('site_tagline',        'Kenya\'s Pulse – News, Politics & Docs',  'general'),
('site_description',    'Habari Hub delivers independent Kenyan news, political commentary, and documentary stories.',  'general'),
('site_email',          'info@habarihub.co.ke',                    'general'),
('site_phone',          '+254 700 000 000',                        'general'),
('site_address',        'Nairobi, Kenya',                          'general'),
('twitter_url',         'https://twitter.com/habarihub',           'social'),
('facebook_url',        'https://facebook.com/habarihub',          'social'),
('youtube_url',         'https://youtube.com/@habarihub',          'social'),
('instagram_url',       'https://instagram.com/habarihub',         'social'),
('footer_about',        'Independent journalism from the heart of Kenya.',  'footer'),
('seo_default_title',   'Habari Hub – Kenyan News & Politics',     'seo'),
('seo_default_desc',    'Breaking news, political analysis, and documentary stories from across Kenya.',  'seo'),
('og_image',            '/assets/images/og-default.jpg',           'seo'),
('articles_per_page',   '12',                                      'general'),
('allow_comments',      '0',                                       'general');

-- Categories
INSERT INTO `categories` (`name`, `slug`, `description`, `color`, `sort_order`) VALUES
('Breaking News',   'breaking-news',   'Urgent, developing stories.',                    '#EF4444', 1),
('Politics',        'politics',        'Kenyan political news and analysis.',             '#C62828', 2),
('Latest News',     'latest-news',     'Most recently published stories.',               '#B71C1C', 3),
('Trending',        'trending',        'Stories gaining national attention.',            '#FF5722', 4),
('Documentary',     'documentary',     'Long-form investigative and documentary content.','#7B1FA2', 5),
('Business',        'business',        'Economy, markets, and enterprise news.',         '#1565C0', 6),
('Society',         'society',         'Culture, health, and social affairs.',           '#2E7D32', 7),
('Opinion',         'opinion',         'Editorials and opinion columns.',                '#F57F17', 8);

-- Default admin user  (username: admin | password: Admin@2024  –  change immediately in production)
INSERT INTO `users` (`username`, `email`, `password_hash`, `display_name`, `role`) VALUES
('admin', 'admin@habarihub.co.ke',
 '$2y$12$igaC6d0Ovw87koU6uIY7NuFT/GyyCmem3V6MriV9E2ihghaMRBQvK',
 'Habari Hub Admin', 'admin');

-- NOTE: Replace the password hash above using:
--   php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost'=>12]);"
-- Then UPDATE users SET password_hash='<new_hash>' WHERE username='admin';

-- Sample articles (linked to admin user id=1, various categories)
INSERT INTO `articles`
  (`title`, `slug`, `excerpt`, `body`, `featured_image`, `author_id`, `category_id`,
   `content_type`, `status`, `is_featured`, `is_trending`, `is_breaking`, `trending_rank`, `published_at`)
VALUES
(
  'Kenya Charts New Economic Path Amid Global Headwinds',
  'kenya-charts-new-economic-path-global-headwinds',
   'Government unveils a comprehensive economic stimulus package targeting SMEs, infrastructure, and youth employment across all 47 counties.',
   '<p>The National Treasury has unveiled a bold economic stimulus framework aimed at cushioning Kenyan households and businesses from the effects of rising global commodity prices and currency fluctuations.</p><p>The package, estimated at Ksh 120 billion, will channel funds into small and medium enterprises, road infrastructure, digital economy initiatives, and targeted cash transfers for the most vulnerable households.</p><p>Cabinet Secretary for National Treasury stated that the government remains committed to fiscal discipline while prioritising growth-enhancing expenditure. "We are making difficult but necessary choices to position Kenya for long-term prosperity," he noted.</p>',
   '/api/uploads/images/sample-economy.jpg',
   1, 6, 'article', 'published', 1, 1, 1, 1,
  DATE_SUB(NOW(), INTERVAL 2 HOUR)
),
(
  'Senate Debates Controversial Revenue Sharing Formula',
  'senate-debates-revenue-sharing-formula',
   'Senators from various regions clash over the proposed formula, with devolution advocates warning of adverse effects on smaller counties.',
   '<p>A heated session at the Senate chamber saw legislators trade accusations over the proposed revenue sharing formula that critics argue disadvantages less populous counties.</p><p>The Chairperson of the Finance and Budget Committee called for calm deliberations, insisting that the formula is anchored in constitutional principles and Supreme Court guidance.</p>',
   '/api/uploads/images/sample-politics.jpg',
   1, 2, 'article', 'published', 0, 1, 0, 2,
  DATE_SUB(NOW(), INTERVAL 5 HOUR)
),
(
  'Inside Kenya\'s Silicon Savannah: The Next Tech Unicorns',
  'inside-kenya-silicon-savannah-next-tech-unicorns',
   'A documentary exploration of the Nairobi startup ecosystem and the entrepreneurs building world-class solutions from East Africa.',
   '<p>From the gleaming towers of Westlands to co-working spaces in Kilimani, a new generation of Kenyan technologists is building companies that are attracting international venture capital and reshaping how Africa does business.</p>',
   '/api/uploads/images/sample-documentary.jpg',
   1, 5, 'documentary', 'published', 1, 0, 0, 0,
  DATE_SUB(NOW(), INTERVAL 1 DAY)
),
(
  'Counties Struggle as Delayed Exchequer Releases Bite',
  'counties-struggle-delayed-exchequer-releases',
   'County governments are facing acute cash-flow challenges after months of delayed disbursements from the national government.',
   '<p>Health facilities have run out of essential medicines, and county workers in several regions have gone weeks without pay as the national government continues to delay disbursements to devolved units.</p>',
   '/api/uploads/images/sample-counties.jpg',
   1, 2, 'article', 'published', 0, 1, 0, 3,
  DATE_SUB(NOW(), INTERVAL 8 HOUR)
),
(
  'Opinion: The Promise and Peril of Kenya\'s Housing Agenda',
  'opinion-promise-peril-kenya-housing-agenda',
   'Our columnist examines whether the affordable housing initiative can deliver on its ambitious targets without displacing urban poor communities.',
   '<p>The government\'s affordable housing programme has generated significant debate among urban planners, civil society organisations, and ordinary Kenyans who find themselves caught between the promise of decent shelter and the fear of displacement.</p>',
   '/api/uploads/images/sample-opinion.jpg',
   1, 8, 'article', 'published', 0, 0, 0, 0,
  DATE_SUB(NOW(), INTERVAL 12 HOUR)
);
