/// `App\Enums\OrderChannel`: buyurtma qayerdan kelgan.
enum OrderChannel {
  telegram('telegram', 'Telegram'),
  mobile('mobile', 'Ilova'),
  posHall('pos_hall', 'Zal kassasi'),
  posTakeaway('pos_takeaway', 'Kassa'),
  aggregator('aggregator', 'Agregator');

  const OrderChannel(this.wire, this.label);

  /// API'dagi qiymat.
  final String wire;

  /// O'zbekcha nom (oshxona chiptasidagi kanal belgisi).
  final String label;

  static OrderChannel? fromWire(String? value) {
    for (final OrderChannel c in OrderChannel.values) {
      if (c.wire == value) return c;
    }
    return null;
  }
}
