import { useLanguageStore } from '../store/languageStore';

// The words of the customer's order, profile, complaint and notification screens that store/languageStore.ts has no
// key for yet. One entry per phrase, in all three languages (the language store has the shared ones: statuses,
// payment methods, "Yopish", "Qayta urinish"). Complaints and Notifications also serve the owner and the manager.

type Text = { uz: string; ru: string; en: string };

const COPY = {
  // Orders
  ordersSubtitle: { uz: 'Buyurtmalar tarixi va holati', ru: 'История и статус заказов', en: 'Order history and status' },
  ordersActive: { uz: 'Faol buyurtmalar', ru: 'Активные заказы', en: 'Active orders' },
  ordersHistory: { uz: 'Tarix', ru: 'История', en: 'History' },
  ordersHistoryLabel: { uz: 'Buyurtmalar tarixi', ru: 'История заказов', en: 'Order history' },
  ordersEmptyTitle: { uz: "Buyurtmalar yo'q", ru: 'Заказов пока нет', en: 'No orders yet' },
  ordersEmptyText: {
    uz: "Hali hech qanday buyurtma bermadingiz. Mazali taomlarimizni tatib ko'ring!",
    ru: 'Вы ещё не сделали ни одного заказа. Самое время заказать что-нибудь вкусное!',
    en: "You haven't placed any orders yet. Time to order something tasty!",
  },
  ordersToCatalog: { uz: 'Katalogga qaytish', ru: 'В каталог', en: 'Go to catalog' },
  orderDetails: { uz: 'Batafsil', ru: 'Подробнее', en: 'Details' },
  orderCollapse: { uz: "Yig'ish", ru: 'Свернуть', en: 'Collapse' },
  orderItemFallback: { uz: 'Mahsulot', ru: 'Товар', en: 'Item' },
  orderPaymentMethod: { uz: "To'lov usuli", ru: 'Способ оплаты', en: 'Payment method' },
  orderPaymentStatus: { uz: "To'lov holati", ru: 'Статус оплаты', en: 'Payment status' },
  orderCancelReason: { uz: 'Bekor qilish sababi', ru: 'Причина отмены', en: 'Reason' },
  orderRejectReason: { uz: 'Rad etish sababi', ru: 'Причина отказа', en: 'Reason' },
  orderComplain: { uz: 'Shikoyat qilish', ru: 'Пожаловаться', en: 'Report a problem' },
  orderCancel: { uz: 'Bekor qilish', ru: 'Отменить заказ', en: 'Cancel order' },
  orderRate: { uz: 'Baholash', ru: 'Оценить', en: 'Rate' },
  rateTitle: { uz: 'Restoranni baholang', ru: 'Оцените ресторан', en: 'Rate the restaurant' },
  rateStars: { uz: '{{n}} yulduz', ru: '{{n}} из 5', en: '{{n}} of 5 stars' },
  rateComment: { uz: 'Fikringiz (ixtiyoriy)', ru: 'Ваш отзыв (необязательно)', en: 'Your feedback (optional)' },
  rateCommentPlaceholder: { uz: 'Fikringizni shu yerda qoldiring...', ru: 'Напишите ваш отзыв здесь...', en: 'Leave your feedback here...' },
  rateSend: { uz: 'Yuborish', ru: 'Отправить', en: 'Send' },
  rateSending: { uz: 'Yuborilmoqda...', ru: 'Отправка...', en: 'Sending...' },
  rateThanks: { uz: 'Baho yuborildi! Rahmat.', ru: 'Оценка отправлена! Спасибо.', en: 'Rating sent! Thank you.' },
  genericError: { uz: 'Xatolik yuz berdi.', ru: 'Произошла ошибка.', en: 'Something went wrong.' },

  // Profile
  profileTitle: { uz: 'Mening profilim', ru: 'Мой профиль', en: 'My profile' },
  profileGuest: { uz: 'Mijoz', ru: 'Клиент', en: 'Customer' },
  profilePremium: { uz: 'Doimiy mijoz', ru: 'Постоянный клиент', en: 'Regular customer' },
  profileEdit: { uz: 'Profilni tahrirlash', ru: 'Редактировать профиль', en: 'Edit profile' },
  profileInfo: { uz: "Profil ma'lumotlari", ru: 'Данные профиля', en: 'Profile details' },
  profileName: { uz: 'Ism-familiya', ru: 'Имя и фамилия', en: 'Full name' },
  profilePhone: { uz: 'Telefon raqam', ru: 'Номер телефона', en: 'Phone number' },
  profilePhoneHint: { uz: 'Yangi raqam SMS kod orqali tasdiqlanadi', ru: 'Новый номер подтверждается кодом из SMS', en: 'A new number is confirmed with an SMS code' },
  profilePhoneInvalid: {
    uz: "Telefon raqami +998 bilan boshlanib, 9 ta raqamdan iborat bo'lishi kerak.",
    ru: 'Номер должен начинаться с +998 и содержать 9 цифр.',
    en: 'The number must start with +998 and have 9 digits.',
  },
  profilePhoneCleared: {
    uz: "Tasdiqlangan telefon raqamini o'chirib bo'lmaydi.",
    ru: 'Подтверждённый номер телефона нельзя удалить.',
    en: 'A verified phone number cannot be removed.',
  },
  profileSaveError: { uz: 'Profilni saqlashda xatolik yuz berdi', ru: 'Не удалось сохранить профиль', en: 'Could not save the profile' },
  save: { uz: 'Saqlash', ru: 'Сохранить', en: 'Save' },
  saving: { uz: 'Saqlanmoqda...', ru: 'Сохранение...', en: 'Saving...' },
  cancel: { uz: 'Bekor qilish', ru: 'Отмена', en: 'Cancel' },
  profileCashback: { uz: 'Keshbek balans', ru: 'Кешбэк', en: 'Cashback balance' },
  profileOrdersLabel: { uz: 'Yetkazilgan', ru: 'Доставлено', en: 'Delivered' },
  profileOrdersCount: { uz: '{{n}} ta buyurtma', ru: 'Заказов: {{n}}', en: '{{n}} orders' },
  addressesTitle: { uz: 'Manzillarim', ru: 'Мои адреса', en: 'My addresses' },
  addressNew: { uz: 'Yangi manzil', ru: 'Новый адрес', en: 'New address' },
  addressNewTitle: { uz: 'Yangi manzil kiritish', ru: 'Новый адрес', en: 'New address' },
  addressEditTitle: { uz: 'Manzilni tahrirlash', ru: 'Редактирование адреса', en: 'Edit address' },
  addressTitlePlaceholder: { uz: 'Sarlavha (masalan: Uyim, Ofis)', ru: 'Название (например: Дом, Офис)', en: 'Title (e.g. Home, Office)' },
  addressRegion: { uz: 'Viloyatni tanlang', ru: 'Выберите область', en: 'Choose a region' },
  addressDistrict: { uz: 'Tumanni tanlang', ru: 'Выберите район', en: 'Choose a district' },
  addressApartment: { uz: 'Xonadon/Ofis raqami', ru: 'Квартира / офис', en: 'Apartment / office' },
  addressCourierNote: { uz: 'Kuryerga eslatma', ru: 'Комментарий курьеру', en: 'Note for the courier' },
  addressSave: { uz: 'Manzilni saqlash', ru: 'Сохранить адрес', en: 'Save address' },
  addressSaveChanges: { uz: "O'zgarishlarni saqlash", ru: 'Сохранить изменения', en: 'Save changes' },
  addressPinHint: { uz: 'Saqlash uchun xaritada joylashuvni belgilang', ru: 'Чтобы сохранить, отметьте место на карте', en: 'Mark the location on the map to save' },
  addressNone: { uz: "Sizda hali saqlangan manzillar yo'q", ru: 'У вас пока нет сохранённых адресов', en: 'You have no saved addresses yet' },
  addressNoPin: {
    uz: 'Xaritada belgilanmagan: buyurtma berish uchun tahrirlab, joylashuvni belgilang',
    ru: 'Нет точки на карте: чтобы заказывать, отредактируйте адрес и отметьте место',
    en: 'No map point: edit the address and mark the location to order',
  },
  addressSelect: { uz: 'Manzilni tanlash', ru: 'Выбрать адрес', en: 'Select address' },
  addressEdit: { uz: 'Manzilni tahrirlash', ru: 'Редактировать адрес', en: 'Edit address' },
  addressDelete: { uz: "Manzilni o'chirish", ru: 'Удалить адрес', en: 'Delete address' },
  addressDeleteConfirm: { uz: "Manzil o'chirilsinmi?", ru: 'Удалить адрес?', en: 'Delete this address?' },
  addressDeleteAction: { uz: "O'chirish", ru: 'Удалить', en: 'Delete' },
  inviteTitle: { uz: "Do'stlarni taklif qiling", ru: 'Пригласите друзей', en: 'Invite friends' },
  inviteText: {
    uz: "Do'stingizni taklif qiling va uning birinchi muvaffaqiyatli buyurtmasidan so'ng hisobingizga {{amount}} keshbek oling!",
    ru: 'Пригласите друга и получите {{amount}} кешбэка после его первого успешного заказа!',
    en: 'Invite a friend and get {{amount}} cashback after their first successful order!',
  },
  inviteShare: { uz: 'Ulashish', ru: 'Поделиться', en: 'Share' },
  inviteMessage: {
    uz: "Rizqim dan ajoyib taomlarga buyurtma bering va keshbeklarga ega bo'ling!",
    ru: 'Заказывайте вкусную еду в Rizqim и получайте кешбэк!',
    en: 'Order great food from Rizqim and earn cashback!',
  },
  supportRow: { uz: 'Shikoyatlar va yordam', ru: 'Жалобы и поддержка', en: 'Complaints and support' },

  // Complaints
  complaintsTitleCustomer: { uz: 'Mening shikoyatlarim', ru: 'Мои жалобы', en: 'My complaints' },
  complaintsTitleStaff: { uz: "Shikoyat va so'rovlar", ru: 'Жалобы и споры', en: 'Complaints and disputes' },
  complaintsSubtitleCustomer: { uz: "Sifat va xizmat yuzasidan e'tiroz bildirish", ru: 'Претензии по качеству и обслуживанию', en: 'Report a problem with quality or service' },
  complaintsSubtitleStaff: { uz: "Mijozlar tomonidan qoldirilgan e'tirozlar", ru: 'Претензии, оставленные клиентами', en: 'Complaints left by customers' },
  complaintSend: { uz: 'Shikoyat yuborish', ru: 'Отправить жалобу', en: 'Send a complaint' },
  complaintOrder: { uz: 'Buyurtmani tanlang', ru: 'Выберите заказ', en: 'Choose an order' },
  complaintOrderPlaceholder: { uz: 'Buyurtma tanlash', ru: 'Выбрать заказ', en: 'Choose an order' },
  complaintType: { uz: 'Muammo turi', ru: 'Тип проблемы', en: 'Type of problem' },
  complaintDescription: { uz: 'Batafsil tushuntiring', ru: 'Опишите подробнее', en: 'Describe it' },
  complaintPlaceholder: { uz: "Muammo haqida batafsil ma'lumot qoldiring...", ru: 'Опишите проблему подробнее...', en: 'Tell us more about the problem...' },
  complaintSubmit: { uz: 'Yuborish', ru: 'Отправить', en: 'Send' },
  complaintSent: { uz: 'Shikoyat muvaffaqiyatli yuborildi!', ru: 'Жалоба успешно отправлена!', en: 'Complaint sent!' },
  complaintPickOrder: { uz: 'Iltimos, buyurtmani tanlang.', ru: 'Пожалуйста, выберите заказ.', en: 'Please choose an order.' },
  complaintEnterText: { uz: 'Iltimos, tavsif kiriting.', ru: 'Пожалуйста, введите описание.', en: 'Please describe the problem.' },
  complaintListCustomer: { uz: 'Yuborilgan shikoyatlar tarixi', ru: 'История жалоб', en: 'Your complaints' },
  complaintListStaff: { uz: 'Kelgan shikoyatlar', ru: 'Полученные жалобы', en: 'Received complaints' },
  complaintsEmptyTitle: { uz: "Shikoyatlar yo'q", ru: 'Жалоб нет', en: 'No complaints' },
  complaintsEmptyCustomer: {
    uz: "Muammo bo'lsa, yuqoridagi shakl orqali yozing: javobni shu yerda ko'rasiz.",
    ru: 'Если возникла проблема, напишите через форму выше: ответ появится здесь.',
    en: 'If something went wrong, use the form above: the reply will show up here.',
  },
  complaintsEmptyStaff: { uz: "Mijozlar shikoyati hozircha yo'q", ru: 'Жалоб от клиентов пока нет', en: 'No complaints from customers yet' },
  complaintReplyBy: { uz: 'Restoran javobi', ru: 'Ответ ресторана', en: 'Restaurant reply' },
  complaintReplyPlaceholder: { uz: 'Mijozga javob yozing...', ru: 'Напишите ответ клиенту...', en: 'Write a reply to the customer...' },
  complaintReplySend: { uz: 'Javob yuborish', ru: 'Отправить ответ', en: 'Send reply' },
  complaintReplySaved: { uz: 'Javob muvaffaqiyatli saqlandi!', ru: 'Ответ успешно сохранён!', en: 'Reply saved!' },
  complaintWaiting: { uz: 'Javob kutilmoqda...', ru: 'Ожидается ответ...', en: 'Waiting for a reply...' },
  complaintEscalate: { uz: 'Moderatorga yuborish', ru: 'Передать модератору', en: 'Send to a moderator' },
  complaintEscalateAsk: {
    uz: 'Shikoyatni SuperAdmin moderatorga yuborishni xohlaysizmi?',
    ru: 'Передать жалобу модератору SuperAdmin?',
    en: 'Send this complaint to a SuperAdmin moderator?',
  },
  complaintEscalated: { uz: 'Shikoyat SuperAdmin moderatoriga yuborildi!', ru: 'Жалоба отправлена модератору SuperAdmin!', en: 'The complaint was sent to a SuperAdmin moderator!' },
  complaintStatus_pending: { uz: 'Kutilmoqda', ru: 'Ожидает', en: 'Pending' },
  complaintStatus_owner_replied: { uz: 'Oshxona javob berdi', ru: 'Ресторан ответил', en: 'Restaurant replied' },
  complaintStatus_in_review: { uz: "Ko'rib chiqilmoqda", ru: 'На рассмотрении', en: 'In review' },
  complaintStatus_resolved: { uz: 'Hal qilindi', ru: 'Решена', en: 'Resolved' },
  complaintStatus_closed: { uz: 'Yopilgan', ru: 'Закрыта', en: 'Closed' },
  complaintType_WRONG_ITEM: { uz: "Noto'g'ri mahsulot", ru: 'Неверный товар', en: 'Wrong item' },
  complaintType_ORDER_NOT_RECEIVED: { uz: 'Buyurtma kelmadi', ru: 'Заказ не доставлен', en: 'Order not received' },
  complaintType_QUALITY_ISSUE: { uz: 'Sifat muammosi', ru: 'Проблема с качеством', en: 'Quality issue' },
  complaintType_COURIER_ISSUE: { uz: 'Kuryer muammosi', ru: 'Проблема с курьером', en: 'Courier issue' },
  complaintType_PAYMENT_ISSUE: { uz: "To'lov muammosi", ru: 'Проблема с оплатой', en: 'Payment issue' },
  complaintType_OTHER: { uz: 'Boshqa', ru: 'Другое', en: 'Other' },

  // Notifications
  notificationsTitle: { uz: 'Bildirishnomalar', ru: 'Уведомления', en: 'Notifications' },
  notificationsCount: { uz: '{{n}} ta xabar', ru: 'Сообщений: {{n}}', en: '{{n}} messages' },
  notificationsEmptyTitle: { uz: "Hozircha xabarlar yo'q", ru: 'Уведомлений пока нет', en: 'No notifications yet' },
  notificationsEmptyCustomer: {
    uz: "Buyurtmalaringiz holati haqidagi xabarlar shu yerda ko'rsatiladi.",
    ru: 'Здесь будут отображаться статусы ваших заказов.',
    en: 'Updates on your orders will show up here.',
  },
  notificationsEmptyStaff: {
    uz: "Yangi buyurtmalar va muhim xabarlar shu yerda ko'rsatiladi.",
    ru: 'Здесь будут новые заказы и важные сообщения.',
    en: 'New orders and important messages will show up here.',
  },
} satisfies Record<string, Text>;

export type CopyKey = keyof typeof COPY;

type Translate = (key: CopyKey, replacements?: Record<string, string | number>) => string;

// `const c = useCopy(); c('rateTitle')`: the phrase in the user's language (Uzbek when the language is unknown).
export function useCopy(): Translate {
  const language = useLanguageStore((state) => state.language);
  return (key, replacements) => {
    const entry: Text | undefined = COPY[key]; // undefined for a key built from API data this app does not know
    if (!entry) return String(key);
    let text = entry[language] ?? entry.uz;
    if (replacements) {
      for (const [name, value] of Object.entries(replacements)) text = text.replace(`{{${name}}}`, String(value));
    }
    return text;
  };
}
