# Extend sys_file WITH marker FOR fake-files
CREATE TABLE sys_file (
	tx_fakefal_fake tinyint(1) unsigned DEFAULT '0' NOT NULL
);