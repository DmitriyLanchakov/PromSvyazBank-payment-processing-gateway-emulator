# PromSvyazBank payment processing gateway emulator
## Symfony
### ./app/config/parameters.yml
```yml
Parameters:
    psb_key: key
    psb_terminal_id: terminal_id
    psb_merchant_id: merchant_id
    psb_merchant_name: merchant_name
    psb_merchant_email: merchant_email
    psb_url: 'http://psb.loc/'
```

## PHP-FPM
### /etc/php5/fpm/php-fpm.conf
```
listen = 127.0.0.1:9000
```

```bash
service php-fpm restart
```

## Nginx
### /etc/nginx/vhosts.d/psb.conf
```lua
server {
    server_name psb.loc;
    root /srv/www/htdocs/psb;

    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~.php$ {
        fastcgi_pass 127.0.0.1:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_index index.php;
    }
}
```

```bash
service nginx restart
```

## Hosts

```bash
echo "127.0.0.1 psb.loc" >> /etc/hosts
```

```bash
nscd -i hosts
```
