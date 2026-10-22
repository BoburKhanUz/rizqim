/// Zal xaritasidagi joy turi (backend `dining_tables.kind`).
enum FloorTableKind {
  table('table', 'Stol'),
  room('room', 'Xona'),
  tapchan('tapchan', 'Tapchan');

  const FloorTableKind(this.wire, this.label);

  final String wire;
  final String label;

  static FloorTableKind fromWire(String? value) {
    for (final FloorTableKind k in values) {
      if (k.wire == value) return k;
    }
    return FloorTableKind.table;
  }
}

/// Xona yoki stol holati (research R1): bo'sh, band, bron kutilmoqda, schyot chiqdi, uzoq ochiq.
enum FloorTableStatus {
  free("Bo'sh"),
  occupied('Band'),

  /// Bron bor, mehmonlar hali kelmagan.
  booked('Bron'),

  /// Schyot chop etildi, to'lov kutilmoqda.
  billPrinted('Schyot chiqdi'),

  /// Belgilangan soatdan uzoq ochiq (egaga eslatma).
  overdue('Uzoq ochiq'),

  /// Vaqtincha yopiq (mavsumiy zona, ta'mir).
  inactive('Yopiq');

  const FloorTableStatus(this.label);

  final String label;
}
