# Пересылка избранных сообщений

[![phabel.io - PHP transpiler](https://phabel.io/badge)](https://phabel.io)

Created by <a href="https://github.com/HasanKulbuzhev" target="_blank" rel="noopener">Hasan Kulbuzhev</a>

# Что это?

Это скрипт, который позволяет пересылать все избранные сообщения с вашего аккаунта телеграмм в другой канал. Полезно, при смене аккаунта.

Сделано с помощью <a href="https://github.com/danog/MadelineProto" target="_blank" rel="noopener">MadelineProto</a>

# Начало работы
Клонируем проект:
```
git clone https://github.com/HasanKulbuzhev/saved_telegram_messages.git && cd saved_telegram_messages
```
Затем :
```
cp .env.example .env
```
в файле .env указываем канал, в котором будем пересылать сообщения. Пот типу :
```dotenv
CHANNEL_USERNAME=-1009999999999
```
либо :
```dotenv
CHANNEL_USERNAME=@username
```

Запускаем: 
```
php forward.php
```
 После этого скрипт попросит вас авторизоваться (это необходимо, т.к. избранные сообщения доступны только самому пользователю), после чего он начнёт пересылать сообщения в этот канал.


# P.s.
Телеграмм может ругаться, если увидит активную пересылку сообщений (обычно такое происходит после ~1000 пересылок. Если увидите ошибку FLOOD_WAIT_X (420), то значит телеграмм временно заблокировал для вас пересылку на n секунду (в примере на 420 секунд), в таком случае просто подождите это время и запустите снова. Во всех других случаях просто запустите скрипт снова, либо обратитесь ко мне. 
