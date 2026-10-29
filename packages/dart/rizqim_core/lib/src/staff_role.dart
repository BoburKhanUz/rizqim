/// `users.role` (docs/AUTH.md, 6-bo'lim) va xodim ekranlaridagi o'zbekcha nomi.
enum StaffRole {
  owner('owner', 'Ega'),
  manager('manager', 'Menejer'),
  cashier('cashier', 'Kassir'),
  waiter('waiter', 'Ofitsiant'),
  cook('cook', 'Oshpaz'),
  hostess('hostess', 'Hostes'),
  courier('courier', 'Kuryer');

  const StaffRole(this.wire, this.label);

  /// API'dagi qiymat.
  final String wire;

  /// O'zbekcha nom.
  final String label;

  static StaffRole? fromWire(String? value) {
    for (final StaffRole r in StaffRole.values) {
      if (r.wire == value) return r;
    }
    return null;
  }

  /// Rol nomi; noma'lum rol bo'lsa xom qiymat.
  static String labelOf(String wire) => fromWire(wire)?.label ?? wire;
}
