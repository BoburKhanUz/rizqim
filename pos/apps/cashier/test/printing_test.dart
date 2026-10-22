import 'package:flutter_test/flutter_test.dart';
import 'package:pos_core/pos_core.dart';
import 'package:pos_data/pos_data.dart';
import 'package:pos_hardware/pos_hardware.dart';
import 'package:rizqim_cashier/src/printing/printing_service.dart';

void main() {
  PosPrinterInfo printer(
    int id,
    String connection, {
    String purpose = 'receipt',
    String? address,
    int? port,
    int paper = 80,
    int? station,
    String? encoding,
    bool image = false,
    bool active = true,
  }) => PosPrinterInfo(
    id: id,
    name: 'P$id',
    purpose: purpose,
    connection: connection,
    address: address,
    port: port,
    paperWidth: paper,
    stationId: station,
    encoding: encoding,
    printAsImage: image,
    isActive: active,
  );

  test('bootstrap printers become pos_hardware configs', () {
    final (PrinterConfig? lan, _) = toPrinterConfig(
      printer(1, 'lan', address: '192.168.1.50', encoding: 'cp1251', paper: 58),
    );
    expect(lan!.id, 'server-1');
    expect(lan.target, const LanPrinterTarget('192.168.1.50'));
    expect(lan.paper, PaperWidth.mm58);
    expect(lan.codePage, EscPosCodePage.cp1251);
    expect(lan.textMode, PrintTextMode.auto);

    final (PrinterConfig? sunmi, _) = toPrinterConfig(
      printer(2, 'builtin', image: true),
    );
    expect(sunmi!.target, const SunmiPrinterTarget());
    expect(sunmi.textMode, PrintTextMode.image);
    expect(sunmi.codePage, EscPosCodePage.cp866);

    final (PrinterConfig? usb, _) = toPrinterConfig(printer(3, 'usb'));
    expect(usb!.target, const WindowsPrinterTarget('P3'));

    final (PrinterConfig? bt, String? reason) = toPrinterConfig(
      printer(4, 'bluetooth', address: 'AA:BB'),
    );
    expect(bt, isNull);
    expect(reason, contains('Bluetooth'));

    final (PrinterConfig? noIp, String? why) = toPrinterConfig(
      printer(5, 'lan'),
    );
    expect(noIp, isNull);
    expect(why, contains('IP'));
  });

  test('receipt and kitchen printers by station', () {
    final CashierPrinting printing = CashierPrinting();
    var notified = 0;
    printing.addListener(() => notified++);
    printing.configure(<PosPrinterInfo>[
      printer(1, 'lan', address: '10.0.0.1', port: 9101),
      printer(2, 'lan', purpose: 'kitchen', address: '10.0.0.2', station: 1),
      printer(3, 'lan', purpose: 'kitchen', address: '10.0.0.3'),
      printer(4, 'bluetooth', purpose: 'kitchen', address: 'AA', station: 2),
      printer(
        5,
        'lan',
        purpose: 'kitchen',
        address: '10.0.0.5',
        station: 3,
        active: false,
      ),
    ]);
    expect(notified, 1);
    expect(
      printing.receiptPrinter!.target,
      const LanPrinterTarget('10.0.0.1', port: 9101),
    );
    expect(printing.kitchenPrinterFor(1)!.id, 'server-2');
    // Sexi printeri yo'q (yoki ishlamaydi) — sexsiz kitchen printeri
    expect(printing.kitchenPrinterFor(2)!.id, 'server-3');
    expect(printing.kitchenPrinterFor(null)!.id, 'server-3');
    expect(printing.configFor(2)!.name, 'P2');
    expect(printing.unsupported.single.info.id, 4);
    expect(printing.configs, hasLength(3));

    printing.clear();
    expect(printing.receiptPrinter, isNull);
    expect(printing.configs, isEmpty);
  });
}
