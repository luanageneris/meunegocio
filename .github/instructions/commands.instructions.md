cd /home/lua/my_business && docker compose restart laravel.test && sleep 3
cd /home/lua/my_business && docker compose exec laravel.test npm run dev 2>&1 | head -20