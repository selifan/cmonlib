# Тест 2 - Короткая схема тестирую mermaid схемы
Доки про mermaid: https://github.com/mermaid-js/mermaid/tree/develop/docs

```mermaid
flowchart TB
 A[LLM -> Markdown] --> B[Парсер markdown-it]
 B --> C[Заменить mermaid на placeholder]
 C --> D[DOMPurify -> вставить в DOM]
 D --> E[mermaid.mermaidAPI.render -> SVG]
 E --> F[Показ пользователю]
```

## Минимальный HTML
```html
<i>Тут кусок HTML кода</i>
<h2> Заголовок типа 2 </h2>
```
Ну вот как-то так!