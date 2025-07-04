# cmonlib - Modules of common use, some libs for webapp projects
Here are just misc. PHP/js modules and libs developed and used in my projects.
## Модули для работы с сервисом dadata через jQuery плагин suggestions
js/bankHelper.js - делает поле ввода с типом bankname авто-поисковым для заполнения БИК, корс-счета по введенному названию банка
Поля должны иметь один общий префикс в имени, отделенный от остальной части дефисом. Поле для БИК дложно иметь класс bankbic, поле для кор-счета - bankks
Пример
```HTML
<input type="text" name="my_banknaimenovanie" class="bankname" />
<input type="text" name="my_bank_korrschet" class="bankks" />
<input type="text" name="my_bank_bic" class="bankbic" />
```
