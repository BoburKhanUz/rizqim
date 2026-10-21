/// Backend'ning `StaffOrderPresenter::ticket` javobiga o'xshash chipta (docs/API.md 9.1).
Map<String, Object?> ticketJson({
  String uuid = 'aaaaaaaa-0000-4000-8000-000000000001',
  String? shortCode = 'E101',
  String status = 'accepted',
  String type = 'delivery',
  int waitingSeconds = 120,
  List<Map<String, Object?>>? items,
  String? notes = "Piyozsiz, +99890*****67",
}) {
  final List<Map<String, Object?>> lines =
      items ??
      <Map<String, Object?>>[
        itemJson(id: 11, name: 'Osh', label: '1.5 kg', unit: 'kg', qty: 1.5),
        itemJson(
          id: 12,
          name: 'Choy',
          label: '2',
          modifiers: <Map<String, Object?>>[
            <String, Object?>{
              'name': 'Limon',
              'group_name': "Qo'shimcha",
              'quantity': 1,
              'price_delta': null,
            },
          ],
        ),
      ];
  return <String, Object?>{
    'uuid': uuid,
    'short_code': shortCode,
    'status': status,
    'type': type,
    'channel': 'telegram',
    'branch_id': 3,
    'hall_ref': null,
    'table_ref': null,
    'guests_count': null,
    'notes': notes,
    'items_total': lines.length,
    'items_ready': lines
        .where((Map<String, Object?> l) => l['kitchen_status'] == 'ready')
        .length,
    'items': lines,
    'created_at': '2026-10-20T11:55:00+05:00',
    'accepted_at': '2026-10-20T11:58:00+05:00',
    'preparing_at': null,
    'ready_at': null,
    'waiting_seconds': waitingSeconds,
  };
}

Map<String, Object?> itemJson({
  required int id,
  required String name,
  String label = '1',
  String unit = 'pcs',
  num qty = 1,
  String status = 'pending',
  String? variant,
  List<Map<String, Object?>> modifiers = const <Map<String, Object?>>[],
}) => <String, Object?>{
  'id': id,
  'product_name': name,
  'variant_name': variant,
  'quantity': qty,
  'unit': unit,
  'quantity_label': label,
  'modifiers': modifiers,
  'kitchen_status': status,
  'ready_at': status == 'ready' ? '2026-10-20T12:01:00+05:00' : null,
};
