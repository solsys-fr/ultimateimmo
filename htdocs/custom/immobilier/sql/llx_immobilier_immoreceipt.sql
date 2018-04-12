-- Copyright (C) 2018 Philippe GRAND 	<philippe.grand@atoo-net.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see http://www.gnu.org/licenses/.


CREATE TABLE llx_immobilier_immoreceipt(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL, 
	ref varchar(128) NOT NULL, 
	label varchar(255), 
	rentamount double(24,8), 
	chargesamount double(24,8), 
	total_amount double(24,8) DEFAULT NULL, 
	balance double(24,8) DEFAULT NULL, 
	paiepartiel double(24,8) DEFAULT NULL, 
	echeance double(24,8) DEFAULT NULL, 
	vat double(24,8), 
	paye integer, 
	fk_soc integer, 
	fk_rent integer, 
	fk_property integer, 
	fk_renter integer, 
	fk_owner integer, 
	description text, 
	note_public text, 
	note_private text, 
	date_rent date NOT NULL, 
	date_start date NOT NULL, 
	date_end date NOT NULL, 
	date_creation datetime NOT NULL, 
	tms timestamp NOT NULL, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	import_key varchar(14), 
	model_pdf varchar(128) NOT NULL, 
	status integer NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;