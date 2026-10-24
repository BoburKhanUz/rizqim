import 'package:pos_data/pos_data.dart';
import 'package:pos_data/testing.dart';
import 'package:rizqim_api/rizqim_api.dart';
import 'package:test/test.dart';

import 'support/harness.dart';

void main() {
  group('PosSession', () {
    test('phases: unpaired → locked → signedIn → ready → locked', () async {
      final Harness h = Harness();
      final PosSession s = h.pos.session;
      final List<PosSessionPhase> seen = <PosSessionPhase>[];
      s.changes.listen(seen.add);
      expect(s.phase, PosSessionPhase.unpaired);

      await h.client.auth.pairTerminal('ABCD-EFGH');
      expect(s.phase, PosSessionPhase.locked);
      expect(s.branchName, 'Chilonzor');
      expect(s.branchId, 1);

      await h.client.auth.signInStaff(userId: 3, pin: '4827');
      expect(s.phase, PosSessionPhase.signedIn);
      expect(s.staffName, 'Kamola Karimova');
      expect(s.staffRole, 'cashier');
      expect(s.can(PosPermissions.takePayment), isTrue);
      expect(s.needsApproval(PosPermissions.ordersVoidItem), isTrue);
      expect(s.needsApproval(PosPermissions.devicesManage), isFalse);
      expect(s.canUnpair, isFalse);
      expect(s.idleTimeout(), const Duration(minutes: 5));

      h.state.screenLockMinutes = 0;
      final PosBootstrap boot = await s.loadBootstrap();
      expect(s.phase, PosSessionPhase.ready);
      expect(s.bootstrap, same(boot));
      expect(s.idleTimeout(), isNull);
      expect(s.shift, isNull);

      s.updateShift(const PosShiftSummary(uuid: 's1', status: 'open'));
      expect(s.shift!.uuid, 's1');

      await s.lock(reason: 'idle');
      expect(s.phase, PosSessionPhase.locked);
      expect(s.bootstrap, isNull);
      expect(s.shift, isNull);
      await Future<void>.delayed(Duration.zero);
      expect(
        seen,
        containsAllInOrder(<PosSessionPhase>[
          PosSessionPhase.locked,
          PosSessionPhase.signedIn,
          PosSessionPhase.ready,
          PosSessionPhase.locked,
        ]),
      );
    });

    test('a manager can unpair; unpair revokes on the server', () async {
      final Harness h = Harness();
      await h.signIn(userId: 2);
      expect(h.pos.session.canUnpair, isTrue);
      expect(await h.pos.session.unpair(), isTrue);
      expect(h.adapter.to('DELETE', '/v1/terminal'), hasLength(1));
      expect(h.pos.session.phase, PosSessionPhase.unpaired);
      expect(h.state.paired, isFalse);
    });

    test('the shift from bootstrap', () async {
      final Harness h = Harness();
      h.state.openShift();
      await h.signIn();
      await h.pos.session.loadBootstrap();
      expect(h.pos.session.shift!.isOpen, isTrue);
      expect(h.pos.session.shift!.openingCash.tiyin, 10000000);
    });
  });

  group('PosCatalogRepository', () {
    test('cached by menu_version: same version → no request, new version → refetch', () async {
      final Harness h = Harness();
      await h.signIn();
      final PosCatalogRepository repo = h.pos.catalog;
      final PosCatalog first = await repo.load(
        PosChannel.hall,
        menuVersion: FakePosState.menuVersion,
      );
      expect(repo.fetches, 1);
      expect(h.last.uri.queryParameters, <String, String>{
        'channel': 'pos_hall',
      });

      final PosCatalog again = await repo.load(
        PosChannel.hall,
        menuVersion: FakePosState.menuVersion,
      );
      expect(again, same(first));
      expect(repo.fetches, 1);

      await repo.load(PosChannel.hall, menuVersion: 'newer0000001');
      expect(repo.fetches, 2);
      await repo.load(PosChannel.hall, force: true);
      expect(repo.fetches, 3);

      await repo.load(PosChannel.takeaway);
      expect(repo.fetches, 4);
      expect(repo.cached(PosChannel.takeaway)!.channel, PosChannel.takeaway);
    });

    test(
      'a stored catalog of the same version is used after a restart',
      () async {
        final MemoryPosCatalogCache cache = MemoryPosCatalogCache();
        final Harness a = Harness();
        await a.signIn();
        final PosCatalogRepository first = PosCatalogRepository(
          a.pos.http,
          cache: cache,
        );
        await first.load(
          PosChannel.hall,
          menuVersion: FakePosState.menuVersion,
        );

        final Harness b = Harness();
        await b.signIn();
        final PosCatalogRepository second = PosCatalogRepository(
          b.pos.http,
          cache: cache,
        );
        final PosCatalog c = await second.load(
          PosChannel.hall,
          menuVersion: FakePosState.menuVersion,
        );
        expect(second.fetches, 0);
        expect(c.products, hasLength(4));
        expect(
          (await second.loadStored(PosChannel.hall))!.menuVersion,
          FakePosState.menuVersion,
        );

        // Boshqa versiya — serverdan
        await second.load(PosChannel.hall, menuVersion: 'other0000000');
        expect(second.fetches, 1);
      },
    );

    test('refreshAvailability updates stop-list and kazan without refetching the menu', () async {
      final Harness h = Harness();
      await h.signIn();
      final PosCatalog c = await h.pos.catalog.load(PosChannel.hall);
      final List<PosCatalog> changes = <PosCatalog>[];
      h.pos.catalog.changes.listen(changes.add);
      final PosVariant tea = c.products.first.singleVariant!;
      expect(c.isVariantAvailable(tea), isTrue);

      (h.state.availability['stopped']! as Map<String, Object?>)['variants'] =
          <int>[tea.id];
      await h.pos.catalog.refreshAvailability(storeId: 1);
      expect(h.last.uri.path, '/api/v1/catalog/availability');
      expect(h.last.uri.queryParameters, <String, String>{
        'store_id': '1',
        'branch_id': '1',
      });
      final PosCatalog updated = h.pos.catalog.cached(PosChannel.hall)!;
      expect(updated.unavailableReasonOf(tea), PosUnavailableReason.stopped);
      expect(h.pos.catalog.fetches, 1);
      await Future<void>.delayed(Duration.zero);
      expect(changes, isNotEmpty);
    });
  });

  test('connectivity follows responses', () async {
    final Harness h = Harness();
    await h.signIn();
    final List<bool> seen = <bool>[];
    h.pos.connectivity.changes.listen(
      (PosConnectivityState s) => seen.add(s.online),
    );
    h.adapter.offline = true;
    await expectLater(h.pos.floor.load(), throwsA(isA<PosNetworkException>()));
    h.adapter.offline = false;
    await h.pos.floor.load();
    await Future<void>.delayed(Duration.zero);
    expect(seen, containsAllInOrder(<bool>[false, true]));
    expect(h.pos.connectivity.state.lastContactAt, h.now);
  });

  test('auth errors from a dead staff token sign the staff out', () async {
    final Harness h = Harness();
    await h.signIn();
    h.adapter.respond = (_) => const FakeResponse(401, <String, Object?>{
      'message': 'Unauthenticated.',
    });
    final PosException e = await h.pos.floor.load().then<PosException>(
      (_) => fail('expected 401'),
      onError: (Object e) => e as PosException,
    );
    expect(e.statusCode, 401);
    expect(e.message, 'Sessiya tugadi. PIN bilan qayta kiring.');
    expect(h.client.auth.staff, isNull);
    expect(h.pos.session.phase, PosSessionPhase.locked);
    expect(h.client.auth.terminal, isA<PairedTerminal>());
  });
}
