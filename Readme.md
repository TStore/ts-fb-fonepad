TStore - FB FonePad
===================

```apache
<VirtualHost *:80>
  DocumentRoot "/directory/path/web/"
  ServerName ts-fb.dev
  ServerAlias www.ts-fb.dev
  SetEnv FACEBOOK_APP_ID ################
  SetEnv FACEBOOK_SECRET ################################
  <Directory "/directory/path/web/">
    AllowOverride All
    Allow from All
  </Directory>
</VirtualHost>
```