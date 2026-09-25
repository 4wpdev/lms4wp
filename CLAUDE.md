# CLAUDE.md

Контекст для роботи Claude Code в цьому репозиторії.

## Про проєкт

Автор: Anatoliy Dovgun, Full-stack WordPress розробник (4wp.dev), GitHub: 4wpdev.
Екосистема Gutenberg-плагінів: 4wp-account, 4wp-notifications, 4wp-responsive,
4wp-seo, 4wp-weather, 4wp-drive та ін. Також проєкт ФК "Волинь" (fcvolyn.com.ua).

Цей репозиторій — частина двох взаємопов'язаних продуктів:

1. **LMS4WP** (github.com/4wpdev/lms4wp) — WordPress-плагін для побудови
   LMS-платформ. CPT Course/Lesson/Quiz, Repository/Service pattern,
   MCP-bridge, AI provider abstraction, WooCommerce-інтеграція.
   Статус: MVP у розробці, приватний репозиторій на момент розробки.
   Наступний етап — подача на ревью в WordPress.org (wordpress.org/plugins).
   НЕ фінальна архітектура — використовувати як референс лише там, де
   ідеологічно і архітектурно підходить.

2. **Курс "Agentic Engineering with a WordPress Twist"** — публікується
   як перший безкоштовний курс на самій LMS4WP. Контент курсу = документований
   build-log реального процесу розробки LMS4WP через агентні техніки (dogfooding).
   Службовий підзаголовок/SEO-опис (не title): "for WordPress Developers".

3. **4WP Drive** (wordpress.org/plugins/4wp-drive) — плагін імпорту документів
   у WP-чернетки через Inbox (front-matter + body → post). Наразі live-джерело —
   Google Drive; GitHub-джерело в розробці. Цей репозиторій (`4wp-drive-incoming`)
   — тестова тека для імпорту матеріалів курсу через git.

## Цілі проєкту

1. **Продукт для ком'юніті**: дати WP-розробникам entry/middle рівня
   (аудиторія — ~7K підписників LinkedIn) легкий, практичний вхід в
   Agentic Engineering саме в контексті WordPress-проєктів.
2. **Позиціонування**: підтвердити експертизу через реальний, публічний
   pet-проєкт — не теорія, а робочий інструмент + менторська позиція.

## Структура курсу (7 модулів)

1. Фундамент (LLM basics, Messages API, provider abstraction, structured
   output) — на реальному PHP-стеку: Neuron AI / php-agents як base library
2. Tool Use / Function Calling — реєстрація WordPress Ability (нативний
   core-механізм WP 7.0) + MCP Adapter як точка входу, агентна логіка поверх
3. Пам'ять та контекст — RAG, Hybrid Search (FTS + Vector) у WP MySQL,
   progress tracking
4. Планування та оркестрація — multi-agent, orchestrator-workers
5. Guardrails та надійність — WP capabilities/roles застосовані до агента-юзера
6. Продакшн — деплой, логування, cost-контроль, SSE-стрімінг
7. Фінальний проєкт — власний MCP-tool + агент-юзер у LMS4WP

Кожен модуль = теорія + реальний артефакт з процесу розробки (код, промпти,
MCP-конфіги). Аналогії до звичного WP-мислення (hooks, CPT, capabilities)
дозволені для мотивації, але технічний хребет курсу — реальні бібліотеки
й стандарти (Neuron AI, php-agents, MCP, WP Abilities API), не вигадані
аналогії.

## WordPress 7.0 core AI-інфраструктура — фундамент, не конкурент

WordPress 7.0 має вбудований canonical AI plugin. Реальні core-API, на які
спираємось (не винаходимо з нуля):
- **Connectors page** — підключення AI-провайдера
- **Request Logging** — моніторинг AI-активності на сайті
- **Connector Approvals** — контроль доступу плагінів до AI-провайдера
- **MCP Adapter** — конект зовнішніх AI-асистентів (Claude, ChatGPT) до сайту
- **WordPress Ability** (registration) — робить функціонал плагіна видимим
  для AI-tools
- **WordPress AI Client** — API для AI-промптів з власного плагіна/теми

Офіційний курс **"AI-Powered WordPress"** (learn.wordpress.org) описує саме
ці механізми, але з погляду **адміністрування** (site owners, editors, адміни
в Модулях 1-3; розробка з'являється лише в Модулі 4 як базовий виклик AI
Client). Станом на зараз курс — стаб без опублікованих уроків.

**Наше розмежування:** ми НЕ дублюємо адміністративну парадигму. Наш курс —
з погляду **інженерингу/розробки** з першого модуля: multi-agent, оркестрація,
guardrails, побудова власного MCP-сервера, агент, що діє як WP-юзер (а не
просто зовнішній асистент, що конектиться до сайту). Core AI-інфраструктура
WP 7.0 — наш фундамент і точка входу, не тема курсу сама по собі.

## Конкурентне розмежування #2 — WordPress VIP Learn

**"AI WordPress Developer Tools and Workflow"** (VIP Learn, enterprise) —
найближчий за суттю курс з усіх знайдених: реально про розробку, не
адміністрування. Структура: AI Foundations → Claude-specific tooling
(термінал, Desktop, CLAUDE.md, MCP, VS Code, Chrome, Skills, власні
MCP-сервери) → Enterprise workflow (GitHub Issue→PR, agentic-workflow-template
з Pew Research Center).

Наша відмінність:
1. **Vendor-agnostic vs vendor-specific** — вони прив'язані до конкретного
   інструментарію Claude; ми — концептуальний agentic engineering (Claude як
   приклад реалізації, не залежність)
2. **Community-first vs closed enterprise** — вони закриті, для VIP-клієнтів;
   ми безкоштовні, для community (~7K LinkedIn)
3. **Реальний production-проєкт vs навчальний demo** — вони вчать на
   demo-плагіні (`vip-learn-ai-workflows-demo`); ми документуємо реальну
   розробку LMS4WP, що йде в продакшн

Схожість структури модулів (Foundations → Tools → Enterprise/Production) —
не привід міняти нашу структуру, а підтвердження, що вона правильна:
незалежно до неї прийшли і ми, і enterprise WP-команда.

## Front-matter формат для файлів курсу (під 4wp-drive Inbox)

```yaml
---
title: "Назва матеріалу"
slug: "url-slug"
type: post | lesson | draft-note
category: "Модуль N"
status: draft
---
```

Тіло файлу — Markdown, звичайний контент.

## Репозиторій lms4wp — статус доступу

Наразі приватний (активна розробка). Публікація на WordPress.org —
окремий подальший етап (проходження плагін-review процесу WP.org:
відповідність guidelines, security review, readme.txt стандарт тощо).
Не публікувати посилання/деталі репо публічно до готовності.

## Як працювати в цьому репо

- Не хардкодити секрети/токени в файлах — лише через `.env` (не в git) або
  секрети GitHub Actions
- Комітити дрібними, змістовними комітами (build-in-public — історія комітів
  сама по собі контент)
- LMS4WP-код — референс, не догма: покращувати архітектуру там, де вона
  ще "лише образ бажаного", не копіювати сліпо
- Відповіді/контент за замовчуванням — українською, коротко і по суті
- Назва курсу завжди: "Agentic Engineering with a WordPress Twist"
