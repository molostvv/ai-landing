# ai-landing

[![Проверки](https://github.com/molostvv/ai-landing/actions/workflows/checks.yml/badge.svg)](https://github.com/molostvv/ai-landing/actions/workflows/checks.yml)

Два сайта на одном сервере, каждый в своей папке:

- [`staff-agent/`](staff-agent/) — информационный сайт [staff-agent.ru](https://staff-agent.ru) про ИИ-агентов и цифровых сотрудников. Статьи в markdown, PHP-скрипт собирает статичные страницы. Полигон для системы SEO-агентов. Как писать статьи и собирать сайт — в [`staff-agent/README.md`](staff-agent/README.md).
- [`landing/`](landing/) — лендинг «ИИ-агенты для повседневных задач» с формой заявки. С 04.10.2026 открывается по адресу http://170.168.112.225/landing/; остальные адреса на IP перенаправляются на https://staff-agent.ru. Конфиг nginx для IP — [`staff-agent/deploy/nginx/ip-default.conf`](staff-agent/deploy/nginx/ip-default.conf). Установка, плейсхолдеры и отчёт по ТЗ — в [`landing/README.md`](landing/README.md).

| Путь | Что это |
|---|---|
| [`.github/workflows/checks.yml`](.github/workflows/checks.yml) | Проверки на каждый push. Лендинг: синтаксис PHP 8.3 и JS, валидатор HTML. staff-agent: боевая сборка со всеми её проверками, валидатор HTML. Для обоих — поиск секретов (gitleaks) |
| [`ssh_helpers.py`](ssh_helpers.py) | Утилита для команд и загрузки файлов на сервер по SSH (доступы — из переменных окружения) |

Секреты в репозиторий не попадают: доступы к серверу и API хранятся локально, настройки формы — в `config.php` на сервере (образец — `landing/config.example.php`).
