# ai-landing

[![Проверки](https://github.com/molostvv/ai-landing/actions/workflows/checks.yml/badge.svg)](https://github.com/molostvv/ai-landing/actions/workflows/checks.yml)

Лендинг «ИИ-агенты для повседневных задач» — одностраничный сайт с формой заявки на бесплатную консультацию.

| Путь | Что это |
|---|---|
| [`landing/`](landing/) | Сайт: HTML, CSS, JS, SVG, обработчик формы `send.php`, политика конфиденциальности. Установка, плейсхолдеры и отчёт по ТЗ — в [`landing/README.md`](landing/README.md) |
| [`.github/workflows/checks.yml`](.github/workflows/checks.yml) | Проверки на каждый push: синтаксис PHP 8.3 и JS, валидатор HTML, поиск секретов (gitleaks) |
| [`ssh_helpers.py`](ssh_helpers.py) | Утилита для команд и загрузки файлов на сервер по SSH (доступы — из переменных окружения) |

Секреты в репозиторий не попадают: доступы к серверу и API хранятся локально, настройки формы — в `config.php` на сервере (образец — `landing/config.example.php`).
