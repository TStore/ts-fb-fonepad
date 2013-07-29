TStore - FB FonePad
===================

> <VirtualHost *:80>

>   DocumentRoot "c:/wwwroot/ts-fb-fonepad/web/"

>   ServerName ts-fb.dev

>   ServerAlias www.ts-fb.dev

>   SetEnv FACEBOOK_APP_ID 1402491543296992

>   SetEnv FACEBOOK_SECRET ################################

>   <Directory "c:/wwwroot/ts-fb-fonepad/web/">

>     AllowOverride All

>     Allow from All

>   </Directory>

> </VirtualHost>
