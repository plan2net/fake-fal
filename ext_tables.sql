# Extend sys_file WITH marker FOR fake-files
CREATE TABLE sys_file (
	tx_fakefal_fake tinyint(1) unsigned DEFAULT '0' NOT NULL
);

# Extend sys_file_storage WITH field FOR original driver-type
CREATE TABLE sys_file_storage (
	driver_original tinytext
);