import 'package:flutter_test/flutter_test.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:rizqim_kds/src/board/board_view.dart';
import 'package:rizqim_kds/src/board/skewer_groups.dart';
import 'package:rizqim_kds/src/settings/kds_settings.dart';
import 'package:rizqim_kds/src/stations/station_selection.dart';

const KitchenStation mangal = KitchenStation(
  id: 1,
  name: 'Mangal',
  kind: StationKind.mangal,
);
const KitchenStation osh = KitchenStation(
  id: 2,
  name: 'Osh',
  kind: StationKind.osh,
);
const KitchenStation salat = KitchenStation(
  id: 3,
  name: 'Salat',
  kind: StationKind.saladBar,
);
const List<KitchenStation> stations = <KitchenStation>[mangal, osh, salat];

final DateTime t0 = DateTime(2026, 10, 22, 12);

KitchenLine line(
  int id,
  String name, {
  int? station,
  String status = 'sent',
  String fire = 'now',
  String qty = '1',
  int round = 1,
  DateTime? sentAt,
  DateTime? dueAt,
  DateTime? voidedAt,
  String unit = 'piece',
  String? variant,
  int? product,
  int? category,
  String? note,
  List<KitchenModifier> modifiers = const <KitchenModifier>[],
  DateTime? readyAt,
}) => KitchenLine(
  id: id,
  productName: name,
  variantName: variant,
  quantity: num.tryParse(qty.split(' ').first) ?? 1,
  unit: unit,
  quantityLabel: qty,
  status: status,
  roundNo: round,
  fireMode: fire,
  stationId: station,
  sentAt: status == 'pending' && fire == 'hold' ? null : (sentAt ?? t0),
  dueAt: dueAt,
  voidedAt: voidedAt,
  productId: product,
  categoryId: category,
  note: note,
  modifiers: modifiers,
  readyAt: readyAt,
);

/// Server clock = [t0] at receipt; the order was accepted 5 minutes before.
KitchenTicket ticket(
  List<KitchenLine> lines, {
  String uuid = 'a',
  DateTime? receivedAt,
}) => KitchenTicket(
  uuid: uuid,
  status: 'accepted',
  type: 'dine_in',
  channel: 'pos_hall',
  lines: lines,
  waitingSeconds: 300,
  receivedAt: receivedAt ?? t0,
  acceptedAt: t0.subtract(const Duration(minutes: 5)),
  createdAt: t0.subtract(const Duration(minutes: 6)),
);

BoardView build(
  List<KitchenTicket> tickets, {
  StationSelection selection = const StationSelection(),
  bool filter = false,
  DateTime? now,
  KdsSettings settings = const KdsSettings(),
  Map<int, DateTime> voidSeen = const <int, DateTime>{},
  KitchenStationRoutes routes = const KitchenStationRoutes(),
}) => buildBoardView(
  tickets: tickets,
  selection: selection,
  filter: filter,
  stations: stations,
  settings: settings,
  now: now ?? t0,
  voidSeen: voidSeen,
  voidWindow: const Duration(minutes: 2),
  routes: routes,
);

void main() {
  group('station filter', () {
    final KitchenTicket mixed = ticket(<KitchenLine>[
      line(1, "Qo'y", station: 1),
      line(2, 'Osh', station: 2),
      line(3, 'Salat', station: 3),
      line(4, 'Choy'),
    ]);

    List<String> names(BoardView v) => <String>[
      for (final BoardTicket b in v.active)
        for (final KitchenLine l in b.ticket.lines) l.productName,
    ];

    test('no filter shows every line', () {
      expect(names(build(<KitchenTicket>[mixed])), hasLength(4));
    });

    test('chosen stations only; lines without a station need "Umumiy"', () {
      const StationSelection two = StationSelection(
        chosen: true,
        stationIds: <int>{1, 2},
      );
      expect(
        names(build(<KitchenTicket>[mixed], selection: two, filter: true)),
        <String>["Qo'y", 'Osh'],
      );
      final StationSelection withGeneral = two.copyWith(general: true);
      expect(
        names(
          build(<KitchenTicket>[mixed], selection: withGeneral, filter: true),
        ),
        <String>["Qo'y", 'Osh', 'Choy'],
      );
    });

    test('a ticket without any chosen line is not on the board', () {
      const StationSelection salad = StationSelection(
        chosen: true,
        stationIds: <int>{3},
      );
      final KitchenTicket mangalOnly = ticket(<KitchenLine>[
        line(1, "Qo'y", station: 1),
      ], uuid: 'm');
      final BoardView v = build(
        <KitchenTicket>[mangalOnly, mixed],
        selection: salad,
        filter: true,
      );
      expect(v.active.map((BoardTicket b) => b.ticket.uuid), <String>['a']);
    });

    test('selection validity follows the known stations', () {
      const StationSelection gone = StationSelection(
        chosen: true,
        stationIds: <int>{9},
      );
      expect(gone.validFor(stations), isFalse);
      expect(gone.copyWith(general: true).validFor(stations), isTrue);
      expect(const StationSelection().validFor(stations), isFalse);
    });

    test('selection survives the store round trip', () {
      const StationSelection s = StationSelection(
        chosen: true,
        stationIds: <int>{2, 1},
        general: true,
        mode: KitchenViewMode.mangal,
      );
      expect(StationSelection.fromMap(s.toMap()), s);
      expect(
        StationSelection.fromMap(<String, Object?>{
          'stations_ids': 'x,,3',
          'view_mode': 'weird',
        }).stationIds,
        <int>{3},
      );
    });

    test('mangal view: auto only when every chosen station is a mangal', () {
      const StationSelection onlyMangal = StationSelection(
        chosen: true,
        stationIds: <int>{1},
      );
      expect(isMangalView(onlyMangal, stations), isTrue);
      expect(
        isMangalView(onlyMangal.copyWith(stationIds: <int>{1, 2}), stations),
        isFalse,
      );
      expect(
        isMangalView(onlyMangal.copyWith(general: true), stations),
        isFalse,
      );
      expect(
        isMangalView(
          onlyMangal.copyWith(mode: KitchenViewMode.standard),
          stations,
        ),
        isFalse,
      );
      expect(
        isMangalView(
          const StationSelection(mode: KitchenViewMode.mangal),
          null,
        ),
        isTrue,
      );
    });
  });

  group('pre-order queue (fake clock)', () {
    KitchenTicket held(Duration dueIn, {int station = 2}) =>
        ticket(<KitchenLine>[
          line(
            7,
            'Toy oshi',
            station: station,
            status: 'pending',
            fire: 'hold',
            dueAt: t0.add(dueIn),
          ),
        ]);

    test(
      'osh waits until 120 min before the due time, then joins the board',
      () {
        final KitchenTicket t = held(const Duration(hours: 3));
        BoardView v = build(<KitchenTicket>[t]);
        expect(v.active, isEmpty);
        expect(v.queue.single.line.id, 7);
        expect(v.queue.single.lead, const Duration(minutes: 120));
        expect(v.queue.single.dueIn(t0), const Duration(hours: 3));
        // the screen redraws exactly when the lead time comes
        expect(v.nextBoundary, t0.add(const Duration(hours: 1)));

        v = build(<KitchenTicket>[t], now: t0.add(const Duration(minutes: 59)));
        expect(v.queue, hasLength(1));
        expect(v.active, isEmpty);
        v = build(<KitchenTicket>[t], now: t0.add(const Duration(minutes: 61)));
        expect(v.queue, isEmpty);
        expect(v.active.single.ticket.lines.single.productName, 'Toy oshi');
      },
    );

    test('mangal uses 30 min; the lead times come from the settings', () {
      final KitchenTicket t = held(const Duration(minutes: 50), station: 1);
      expect(build(<KitchenTicket>[t]).queue, hasLength(1));
      expect(
        build(<KitchenTicket>[
          t,
        ], now: t0.add(const Duration(minutes: 21))).active,
        hasLength(1),
      );
      final KdsSettings longer = const KdsSettings().copyWith(
        leadMangal: const Duration(minutes: 60),
      );
      expect(build(<KitchenTicket>[t], settings: longer).queue, isEmpty);
      expect(build(<KitchenTicket>[t], settings: longer).active, hasLength(1));
    });

    test('a line without a known station uses the "other" lead (30 min)', () {
      final KitchenTicket t = ticket(<KitchenLine>[
        line(
          7,
          'Choy',
          status: 'pending',
          fire: 'hold',
          dueAt: t0.add(const Duration(minutes: 40)),
        ),
      ]);
      expect(
        build(<KitchenTicket>[t]).queue.single.lead,
        const Duration(minutes: 30),
      );
    });

    test(
      'ordered by due time; a sent line is on the board whatever its due time',
      () {
        final KitchenTicket t = ticket(<KitchenLine>[
          line(
            1,
            'C',
            station: 2,
            status: 'pending',
            fire: 'hold',
            dueAt: t0.add(const Duration(hours: 5)),
          ),
          line(
            2,
            'A',
            station: 2,
            status: 'pending',
            fire: 'hold',
            dueAt: t0.add(const Duration(hours: 3)),
          ),
          line(
            3,
            'B',
            station: 2,
            status: 'sent',
            fire: 'hold',
            dueAt: t0.add(const Duration(hours: 4)),
          ),
        ]);
        final BoardView v = build(<KitchenTicket>[t]);
        expect(v.queue.map((QueuedLine q) => q.line.productName), <String>[
          'A',
          'C',
        ]);
        expect(v.active.single.ticket.lines.single.productName, 'B');
      },
    );

    test('a skewed tablet clock does not move the countdown (server time from the ticket)', () {
      // the device clock is 3 hours ahead of the server
      final DateTime device = t0.add(const Duration(hours: 3));
      final KitchenTicket t = ticket(<KitchenLine>[
        line(
          7,
          'Toy oshi',
          station: 2,
          status: 'pending',
          fire: 'hold',
          dueAt: t0.add(const Duration(hours: 3)),
        ),
      ], receivedAt: device);
      final QueuedLine q = build(<KitchenTicket>[t], now: device).queue.single;
      expect(q.dueIn(device), const Duration(hours: 3));
      expect(
        q.dueIn(device.add(const Duration(minutes: 30))),
        const Duration(hours: 2, minutes: 30),
      );
    });
  });

  group('a held line without a station (station routes)', () {
    // osh category 20 -> osh (2); the lamb dish 41 -> mangal (1), whatever its category
    const KitchenStationRoutes routes = KitchenStationRoutes(
      products: <int, int>{41: 1},
      categories: <int, int>{20: 2},
    );
    KitchenTicket preorder(Duration dueIn) => ticket(<KitchenLine>[
      line(
        7,
        'Toy oshi',
        category: 20,
        status: 'pending',
        fire: 'hold',
        dueAt: t0.add(dueIn),
      ),
      line(
        8,
        "Qo'y kabob",
        product: 41,
        category: 20,
        status: 'pending',
        fire: 'hold',
        dueAt: t0.add(dueIn),
      ),
    ]);
    const StationSelection oshOnly = StationSelection(
      chosen: true,
      stationIds: <int>{2},
    );
    const StationSelection general = StationSelection(
      chosen: true,
      general: true,
    );

    test('is on the screen of the station its dish or category routes to', () {
      final KitchenTicket t = preorder(const Duration(minutes: 20));
      final BoardView osh = build(
        <KitchenTicket>[t],
        selection: oshOnly,
        filter: true,
        routes: routes,
      );
      expect(osh.active.single.ticket.lines.single.productName, 'Toy oshi');
      expect(osh.active.single.ticket.lines.single.stationId, 2);
      expect(
        build(
          <KitchenTicket>[t],
          selection: general,
          filter: true,
          routes: routes,
        ).active,
        isEmpty,
        reason: 'routed lines are not "Umumiy"',
      );
      // without routes it is a line without a station: "Umumiy" only
      expect(
        build(<KitchenTicket>[t], selection: oshOnly, filter: true).active,
        isEmpty,
      );
    });

    test('its lead time is the routed station kind: 120 min for osh, 30 for mangal', () {
      final BoardView v = build(<KitchenTicket>[
        preorder(const Duration(minutes: 100)),
      ], routes: routes);
      expect(v.active.single.ticket.lines.single.productName, 'Toy oshi');
      final QueuedLine q = v.queue.single;
      expect(q.line.productName, "Qo'y kabob");
      expect(q.lead, const Duration(minutes: 30));
      expect(q.station?.name, 'Mangal');
      // unrouted, the osh would wait for the "other" lead (30 min)
      expect(
        build(<KitchenTicket>[preorder(const Duration(minutes: 100))]).queue,
        hasLength(2),
      );
    });

    test(
      'a sent line or one with its own station keeps the server station',
      () {
        final KitchenTicket t = ticket(<KitchenLine>[
          line(
            1,
            'Osh',
            category: 20,
            station: 3,
            status: 'pending',
            fire: 'hold',
            dueAt: t0,
          ),
          line(2, 'Osh', category: 20, fire: 'hold'),
        ]);
        final List<KitchenLine> lines = build(<KitchenTicket>[
          t,
        ], routes: routes).active.single.ticket.lines;
        expect(lines.map((KitchenLine l) => l.stationId), <int?>[3, null]);
      },
    );
  });

  group('void', () {
    test(
      'a voided line shows for the window after it was first seen, then goes',
      () {
        final KitchenTicket t = ticket(<KitchenLine>[
          line(1, "Qo'y", station: 1, status: 'void'),
          line(2, 'Tovuq', station: 1),
        ]);
        final Map<int, DateTime> seen = <int, DateTime>{1: t0};
        BoardView v = build(<KitchenTicket>[t], voidSeen: seen);
        expect(v.active.single.ticket.lines, hasLength(2));
        expect(v.nextBoundary, t0.add(const Duration(minutes: 2)));
        v = build(
          <KitchenTicket>[t],
          voidSeen: seen,
          now: t0.add(const Duration(minutes: 2)),
        );
        expect(v.active.single.ticket.lines.single.productName, 'Tovuq');
        // an old void (never seen as fresh) is not shown
        expect(
          build(<KitchenTicket>[t])
              .active
              .single
              .ticket
              .lines
              .single
              .productName,
          'Tovuq',
        );
      },
    );

    test('a ticket with only expired voids disappears; a voided ticket carries the badge flag', () {
      final KitchenTicket t = ticket(<KitchenLine>[
        line(1, "Qo'y", station: 1, status: 'void'),
      ]);
      expect(
        build(<KitchenTicket>[t], voidSeen: <int, DateTime>{1: t0}).active,
        hasLength(1),
      );
      expect(
        build(
          <KitchenTicket>[t],
          voidSeen: <int, DateTime>{1: t0},
          now: t0.add(const Duration(minutes: 3)),
        ).active,
        isEmpty,
      );
      final BoardView gone = buildBoardView(
        tickets: const <KitchenTicket>[],
        selection: const StationSelection(),
        filter: false,
        stations: stations,
        settings: const KdsSettings(),
        now: t0,
        voidSeen: const <int, DateTime>{},
        voidWindow: const Duration(minutes: 2),
        voidedTickets: <VoidedTicket>[VoidedTicket(t, t0)],
      );
      expect(gone.active.single.voided, isTrue);
      expect(gone.active.single.canMarkOrderReady, isFalse);
    });
  });

  group('served', () {
    test('a ticket with every line served leaves the board, a partly served one stays', () {
      final KitchenTicket allServed = ticket(<KitchenLine>[
        line(1, "Qo'y", station: 1, status: 'served'),
        line(2, 'Osh', station: 2, status: 'served'),
      ], uuid: 'done');
      final KitchenTicket partly = ticket(<KitchenLine>[
        line(3, "Qo'y", station: 1, status: 'served'),
        line(4, 'Osh', station: 2, status: 'ready'),
      ], uuid: 'part');
      final BoardView v = build(<KitchenTicket>[allServed, partly]);
      expect(v.active.map((BoardTicket b) => b.ticket.uuid), <String>['part']);
      expect(v.active.single.ticket.readyCount, 2);
    });

    test(
      'the station filter decides what counts as served for this screen',
      () {
        final KitchenTicket t = ticket(<KitchenLine>[
          line(1, "Qo'y", station: 1, status: 'served'),
          line(2, 'Osh', station: 2),
        ]);
        const StationSelection mangalOnly = StationSelection(
          chosen: true,
          stationIds: <int>{1},
        );
        expect(
          build(<KitchenTicket>[t], selection: mangalOnly, filter: true).active,
          isEmpty,
        );
        expect(build(<KitchenTicket>[t]).active, hasLength(1));
      },
    );
  });

  group('wait of a POS ticket', () {
    test('counts from the oldest line still cooking, not from the room session start', () {
      final KitchenTicket t = ticket(<KitchenLine>[
        line(
          1,
          'Eski',
          station: 1,
          status: 'ready',
          sentAt: t0.subtract(const Duration(hours: 2)),
        ),
        line(
          2,
          'Yangi',
          station: 1,
          sentAt: t0.subtract(const Duration(minutes: 4)),
        ),
      ]);
      final BoardTicket b = build(<KitchenTicket>[t]).active.single;
      expect(b.posOrder, isTrue);
      expect(b.canMarkOrderReady, isFalse);
      expect(b.waiting(t0), const Duration(minutes: 4));
      expect(
        b.waiting(t0.add(const Duration(minutes: 1))),
        const Duration(minutes: 5),
      );
    });

    test('a ticket with only pre-orders on the board waits from their due time, not the room session', () {
      final KitchenTicket t = ticket(<KitchenLine>[
        line(
          7,
          'Toy oshi',
          station: 2,
          status: 'pending',
          fire: 'hold',
          dueAt: t0.add(const Duration(minutes: 30)),
        ),
      ]);
      final BoardTicket b = build(<KitchenTicket>[t]).active.single;
      expect(b.waiting(t0), Duration.zero);
      expect(
        b.waiting(t0.add(const Duration(minutes: 40))),
        const Duration(minutes: 10),
      );
    });

    test('an all-ready ticket waits from its last ready line, not the room session', () {
      final KitchenTicket t = ticket(<KitchenLine>[
        line(
          1,
          'Osh',
          station: 2,
          status: 'ready',
          sentAt: t0.subtract(const Duration(hours: 2)),
          readyAt: t0.subtract(const Duration(minutes: 6)),
        ),
        line(
          2,
          'Non',
          station: 2,
          status: 'ready',
          sentAt: t0.subtract(const Duration(hours: 2)),
          readyAt: t0.subtract(const Duration(minutes: 2)),
        ),
      ]);
      final BoardTicket b = build(<KitchenTicket>[t]).active.single;
      expect(b.waiting(t0), const Duration(minutes: 2));
    });

    test(
      'an online ticket keeps the server rule and the whole-order button',
      () {
        final KitchenTicket online = KitchenTicket(
          uuid: 'o',
          status: 'accepted',
          type: 'delivery',
          channel: 'telegram',
          lines: <KitchenLine>[
            const KitchenLine(
              id: 1,
              productName: 'Somsa',
              quantityLabel: '4',
              status: 'pending',
            ),
          ],
          waitingSeconds: 120,
          receivedAt: t0,
          acceptedAt: t0.subtract(const Duration(minutes: 2)),
        );
        final BoardTicket b = build(<KitchenTicket>[online]).active.single;
        expect(b.posOrder, isFalse);
        expect(b.canMarkOrderReady, isTrue);
        expect(b.waiting(t0), const Duration(minutes: 2));
      },
    );
  });

  group('mangal grouping', () {
    test('same dish in a round is one row with the skewers added up', () {
      final List<SkewerGroup> g = groupSkewers(<KitchenLine>[
        line(1, "Qo'y shashlik", station: 1, qty: '4'),
        line(2, 'Tovuq shashlik', station: 1, qty: '4'),
        line(3, "Qo'y shashlik", station: 1, qty: '2'),
        line(4, "Qo'y shashlik", station: 1, qty: '5', round: 2),
      ]);
      expect(
        g.map((SkewerGroup x) => '${x.title}/${x.round}/${x.count}'),
        <String>[
          "Qo'y shashlik/1/6",
          'Tovuq shashlik/1/4',
          "Qo'y shashlik/2/5",
        ],
      );
      expect(pendingSkewers(g), 15);
    });

    test('done and voided rows are not counted; a half-done row counts what is left', () {
      final List<SkewerGroup> g = groupSkewers(<KitchenLine>[
        line(1, "Qo'y", station: 1, qty: '4', status: 'ready'),
        line(2, "Qo'y", station: 1, qty: '3'),
        line(3, 'Tovuq', station: 1, qty: '2', status: 'void'),
        line(4, 'Jigar', station: 1, qty: '6', status: 'served'),
      ]);
      expect(g[0].count, 3);
      expect(g[0].done, isFalse);
      expect(g[0].bumpable.map((KitchenLine l) => l.id), <int>[2]);
      expect(g[1].voided, isTrue);
      expect(g[2].done, isTrue);
      expect(pendingSkewers(g), 3);
    });

    test('a note or other modifiers keep a row apart; the same ones (in any order) merge', () {
      const KitchenModifier onion = KitchenModifier(name: 'Piyoz');
      const KitchenModifier spicy = KitchenModifier(
        name: 'Achchiq',
        groupName: 'Ta\'m',
      );
      final List<SkewerGroup> g = groupSkewers(<KitchenLine>[
        line(1, "Qo'y", station: 1, qty: '4'),
        line(2, "Qo'y", station: 1, qty: '2', note: 'yaxshi pishgan'),
        line(
          3,
          "Qo'y",
          station: 1,
          qty: '3',
          modifiers: <KitchenModifier>[onion, spicy],
        ),
        line(
          4,
          "Qo'y",
          station: 1,
          qty: '1',
          modifiers: <KitchenModifier>[spicy, onion],
        ),
        line(5, "Qo'y", station: 1, qty: '5', note: ' yaxshi pishgan '),
      ]);
      expect(
        g.map(
          (SkewerGroup x) => x.lines.map((KitchenLine l) => l.id).join('+'),
        ),
        <String>['1', '2+5', '3+4'],
      );
      expect(g[1].count, 7);
      expect(g[0].bumpable.map((KitchenLine l) => l.id), <int>[1]);
    });

    test('variants stay apart, weighed dishes are never merged', () {
      final List<SkewerGroup> g = groupSkewers(<KitchenLine>[
        line(1, 'Kabob', station: 1, qty: '2', variant: 'katta'),
        line(2, 'Kabob', station: 1, qty: '2', variant: 'kichik'),
        line(3, 'Qozon kabob', station: 1, qty: '1.5 kg', unit: 'kg'),
        line(4, 'Qozon kabob', station: 1, qty: '2 kg', unit: 'kg'),
      ]);
      expect(g, hasLength(4));
      expect(g[2].piece, isFalse);
      expect(g[2].quantityLabel, '1.5 kg');
    });

    test('a held row without a due time is flagged and not bumpable; with one it can be ready early', () {
      // a held line has no station yet (the server writes it when the line is sent)
      final List<SkewerGroup> g = groupSkewers(<KitchenLine>[
        line(1, "Qo'y", status: 'pending', fire: 'hold'),
      ]);
      expect(g.single.held, isTrue);
      expect(g.single.bumpable, isEmpty);
      // P3-26: a pre-order with its due time may be marked ready before it is sent
      final List<SkewerGroup> due = groupSkewers(<KitchenLine>[
        line(1, "Qo'y", status: 'pending', fire: 'hold', dueAt: t0),
      ]);
      expect(due.single.held, isFalse);
      expect(due.single.bumpable, hasLength(1));
    });
  });

  group('settings', () {
    test(
      'lead times: defaults 120 / 30 / 30, by station kind, saved and restored',
      () {
        const KdsSettings s = KdsSettings();
        expect(s.leadFor(StationKind.osh), const Duration(minutes: 120));
        expect(s.leadFor(StationKind.mangal), const Duration(minutes: 30));
        expect(s.leadFor(StationKind.tea), const Duration(minutes: 30));
        expect(s.leadFor(null), const Duration(minutes: 30));

        final KdsSettings changed = s.copyWith(
          leadOsh: const Duration(minutes: 180),
          leadMangal: const Duration(minutes: 20),
          leadOther: const Duration(minutes: 45),
        );
        expect(KdsSettings.fromMap(changed.toMap()), changed);
        // bad values fall back to defaults
        expect(
          KdsSettings.fromMap(<String, Object?>{
            'lead_osh_minutes': 0,
            'lead_mangal_minutes': 'x',
          }).leadOsh,
          const Duration(minutes: 120),
        );
      },
    );
  });
}
