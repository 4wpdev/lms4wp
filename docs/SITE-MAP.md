# Карта сайту і середовищ

Жива схема: доповнюється сутностями послідовно, за вказівкою автора.
Посилання в описах — на прод; уся робота — на локалі.

## Середовища

| Середовище | URL | Примітка |
|---|---|---|
| Прод | https://4wp.dev/ | лише для посилань |
| Локаль | https://4wpdev.local/ | робоче середовище; база — остання з проду |

## 1. Потік контенту

```mermaid
flowchart LR
  subgraph SRC["Джерела контенту (локально)"]
    FAB["Фабрика<br/>~/brag-test"]
    RAG["4wp-rag<br/>(пізніше)"]:::later
    ACH["anatoliy-content-hub<br/>лише ідеї"]:::aside
  end

  HUB[("4wp-publish-hub<br/>джерело істини")]
  FAB --> HUB
  RAG -.-> HUB
  ACH -. ідеї .-> HUB

  subgraph WP["WordPress · 4wpdev.local"]
    DRIVE["4wp-drive<br/>імпорт"]
    subgraph CONTENT["Контент"]
      PAGES["Pages"]
      POSTS["Posts"]
      VIDEOS["Videos"]
      COURSE["Курси LMS4WP"]
      FAQ["FAQ · база знань"]
    end
    DRIVE --> CONTENT
  end

  HUB --> DRIVE
  COURSE -- "матеріали" --> POSTS
  COURSE -- "матеріали" --> VIDEOS
  COURSE -- "FAQ автоматично" --> FAQ


  classDef later stroke-dasharray: 5 5,opacity:0.6
  classDef aside opacity:0.5
```

## 2. Шлях користувача (курс)

```mermaid
flowchart TD
  IN["Вхід: посилання / відео / рекомендація"] --> CP["Сторінка курсу"]
  CP --> PREV["Ознайомлення<br/>(обсяг — налаштування курсу)"]
  CP --> START["Розпочати курс<br/>(без авторизації)"]
  START --> CH["Чаптери / модулі"]
  CH --> GATE{"Авторизований?<br/>4wp-account"}
  GATE -- ні --> AUTH["Авторизація"] --> CH2
  GATE -- так --> CH2["Проходження далі"]
  CH2 --> QA["Запитання-відповіді"]
  CH2 --> TERM["Емулятори терміналу / IDE<br/>4wp-advanced-code"]
  CH2 --> DONE["Завершення курсу"]
  WOO["WooCommerce-логіка<br/>(закладена, поки прихована)"]:::later -.-> START

  classDef later stroke-dasharray: 5 5,opacity:0.6
```

Поза MVP: AI-оцінювання, реальні репозиторії.

## Плагіни родини 4wp-*

| Плагін | Роль |
|---|---|
| LMS4WP | курси, чаптери, проходження |
| 4wp-account | авторизація користувача |
| 4wp-drive | імпорт контенту з хабу |
| 4wp-advanced-code | сніпети, емулятори терміналу й IDE |
| 4wp-faq | FAQ, база знань |
| 4wp-notifications | (уточнити роль у курсі) |
| 4wp-seo-helper | SEO |
| 4wp-social | (уточнити) |

## Сутності

| Сутність | Тип | Джерело |
|---|---|---|
| Pages | core `page` | |
| Posts | core `post` | хаб / матеріали з курсу |
| Videos | тема `4wpdev` (уточнити) | матеріали з курсу |
| Курс | LMS4WP | хаб: `4wp-publish-hub/LMS4WP/courses/<slug>/` |
| FAQ | 4wp-faq | автоматично з курсу |

Приклад курсу в хабі: `courses/git-basics/` — `struktura-kursu.md` + `modul-1…7`.

## Налаштування курсу

Що відкрито гостю (ознайомлення, скільки чаптерів без авторизації) — визначається в самому курсі.
