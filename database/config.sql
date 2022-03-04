INSERT INTO `config` (`id`, `item`, `value`, `class`, `default_value`, `type`) VALUES
(NULL, 'smtp_host', NULL, 'smtp', NULL, 'string'),
(NULL, 'smtp_username', NULL, 'smtp', NULL, 'string'),
(NULL, 'smtp_password', NULL, 'smtp', NULL, 'string'),
(NULL, 'smtp_port', NULL, 'smtp', NULL, 'int'),
(NULL, 'smtp_name', NULL, 'smtp', NULL, 'string'),
(NULL, 'smtp_sender', NULL, 'smtp', NULL, 'string'),
(NULL, 'telegram_account', NULL, 'telegram', NULL, 'string'),
(NULL, 'telegram_token', NULL, 'telegram', NULL, 'string'),
(NULL, 'email_notify', '0', 'switch', '0', 'bool'),
(NULL, 'telegram_notify', '0', 'switch', '0', 'bool'),
(NULL, 'allow_public_reg', '1', 'register', '1', 'bool'),
(NULL, 'reg_email_veriy', '0', 'register', '0', 'bool');
