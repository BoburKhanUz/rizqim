// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period6.dart';
import 'sales5.dart';
import 'by_tender4.dart';
import 'cards3.dart';
import 'qr_providers3.dart';
import 'by_channel5.dart';
import 'by_waiter3.dart';
import 'discounts3.dart';
import 'voids3.dart';
import 'batch_writeoffs3.dart';
import 'refunds7.dart';
import 'deposits3.dart';
import 'credit6.dart';
import 'tips13.dart';
import 'vouchers4.dart';
import 'banquets3.dart';
import 'cash4.dart';
import 'movements3.dart';
import 'pending_receipts.dart';

part 'summary10.g.dart';

@JsonSerializable()
class Summary10 {
  const Summary10({
    required this.shiftUuid,
    required this.generatedAt,
    required this.period,
    required this.sales,
    required this.byTender,
    required this.cards,
    required this.qrProviders,
    required this.byChannel,
    required this.byWaiter,
    required this.discounts,
    required this.voids,
    required this.batchWriteoffs,
    required this.refunds,
    required this.deposits,
    required this.credit,
    required this.tips,
    required this.vouchers,
    required this.banquets,
    required this.cash,
    required this.movements,
    this.openOrders,
    this.pendingReceipts,
  });

  factory Summary10.fromJson(Map<String, Object?> json) =>
      _$Summary10FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String shiftUuid;
  @JsonKey(name: 'generated_at')
  final String generatedAt;
  final Period6 period;
  final Sales5 sales;
  @JsonKey(name: 'by_tender')
  final List<ByTender4> byTender;
  final List<Cards3> cards;
  @JsonKey(name: 'qr_providers')
  final List<QrProviders3> qrProviders;
  @JsonKey(name: 'by_channel')
  final List<ByChannel5> byChannel;
  @JsonKey(name: 'by_waiter')
  final List<ByWaiter3> byWaiter;
  final Discounts3 discounts;
  final Voids3 voids;
  @JsonKey(name: 'batch_writeoffs')
  final BatchWriteoffs3 batchWriteoffs;
  final Refunds7 refunds;
  final Deposits3 deposits;
  final Credit6 credit;
  final Tips13 tips;
  final Vouchers4 vouchers;
  final Banquets3 banquets;
  final Cash4 cash;
  final List<Movements3> movements;
  @JsonKey(name: 'open_orders')
  final List<String>? openOrders;
  @JsonKey(name: 'pending_receipts')
  final PendingReceipts? pendingReceipts;

  Map<String, Object?> toJson() => _$Summary10ToJson(this);
}
