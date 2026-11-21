Serverga xos qo'shimcha SAYTLAR: `conf.d/*.caddy` fayllari Caddyfile oxirida `import conf.d/*.caddy` bilan, eng yuqori
darajada o'qiladi va git'ga qo'shilmaydi. Shuning uchun bu yerda faqat to'liq sayt bloklari bo'lsin (`domen { ... }`):
matcher (`@nom ...`) yoki `handle` yozilsa Caddy konfiguratsiyani rad etadi.
Namuna: `aliases.caddy.example` (rizqim.com, rizq.im yo'naltirish).
admin.<domen> uchun IP ro'yxati bu yerda EMAS: `infra/edge/admin/allow.caddy.example` (docs/SERVER_SETUP.md).
