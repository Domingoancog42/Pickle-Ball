ALTER TABLE booking_addon_products MODIFY category ENUM('equipment','food','snack') NOT NULL;

INSERT IGNORE INTO booking_addon_products(product_key,name,category,unit_price,image_path) VALUES
 ('c2','C2','food',40,'assets/images/addons/c2.jpg'),
 ('coca-cola','Coca-Cola','food',40,'assets/images/addons/coca-cola.jpg'),
 ('sprite','Sprite','food',40,'assets/images/addons/sprite.jpg'),
 ('royal','Royal','food',40,'assets/images/addons/royal.jpg'),
 ('pepsi','Pepsi','food',40,'assets/images/addons/pepsi.jpg'),
 ('gatorade','Gatorade','food',60,'assets/images/addons/gatorade.jpg'),
 ('pocari-sweat','Pocari Sweat','food',60,'assets/images/addons/pocari-sweat.jpg'),
 ('piattos','Piattos','snack',25,'assets/images/addons/piattos.jpg'),
 ('nova','Nova','snack',25,'assets/images/addons/nova.jpg'),
 ('v-cut','V-cut','snack',25,'assets/images/addons/v-cut.png'),
 ('chippy','Chippy','snack',25,'assets/images/addons/chippy.png'),
 ('clover-chips','Clover Chips','snack',25,'assets/images/addons/clover-chips.png'),
 ('oishi-prawn-crackers','Oishi Prawn Crackers','snack',25,'assets/images/addons/oishi-prawn-crackers.jpg'),
 ('mang-juan','Mang Juan','snack',25,'assets/images/addons/mang-juan.jpg'),
 ('martys-cracklin','Marty''s Cracklin''','snack',25,'assets/images/addons/martys-cracklin.jpg'),
 ('boy-bawang','Boy Bawang','snack',25,'assets/images/addons/boy-bawang.png'),
 ('ding-dong','Ding Dong','snack',25,'assets/images/addons/ding-dong.png'),
 ('nagaraya','Nagaraya','snack',25,'assets/images/addons/nagaraya.jpg'),
 ('chiz-curls','Chiz Curls','snack',25,'assets/images/addons/chiz-curls.jpg');

UPDATE booking_addon_products SET is_active=0 WHERE product_key='soft-drinks';
