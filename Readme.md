TStore - FB FonePad
===================

> &lt;VirtualHost *:80&rt;

>   DocumentRoot "c:/wwwroot/ts-fb-fonepad/web/"

>   ServerName ts-fb.dev

>   ServerAlias www.ts-fb.dev

>   SetEnv FACEBOOK_APP_ID 1402491543296992

>   SetEnv FACEBOOK_SECRET ################################

>   &lt;Directory "c:/wwwroot/ts-fb-fonepad/web/"&rt;

>     AllowOverride All

>     Allow from All

>   &lt;/Directory&rt;

> &lt;/VirtualHost&rt;
