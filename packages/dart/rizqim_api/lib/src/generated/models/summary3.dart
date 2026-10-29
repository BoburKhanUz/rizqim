// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period6.dart';
import 'sales5.dart';
import 'by_tender3.dart';
import 'cards3.dart';
import 'by_channel5.dart';
import 'by_waiter3.dart';
import 'discounts3.dart';
import 'voids3.dart';
import 'batch_writeoffs3.dart';
import 'refunds6.dart';
import 'deposits3.dart';
import 'credit6.dart';
import 'cash4.dart';
import 'movements3.dart';
import 'pending_receipts.dart';

part 'summary3.g.dart';

@JsonSerializable()
class Summary3 {
  const Summary3({
    required this.shiftUuid,
    required this.generatedAt,
    required this.period,
    required this.sales,
    required this.byTender,
    required this.cards,
    required this.byChannel,
    required this.byWaiter,
    required this.discounts,
    required this.voids,
    required this.batchWriteoffs,
    required this.refunds,
    required this.deposits,
    required this.credit,
    required this.cash,
    required this.movements,
    this.openOrders,
    this.pendingReceipts,
  });

  factory Summary3.fromJson(Map<String, Object?> json) =>
      _$Summary3FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String shiftUuid;
  @JsonKey(name: 'generated_at')
  final String generatedAt;
  final Period6 period;
  final Sales5 sales;
  @JsonKey(name: 'by_tender')
  final List<ByTender3> byTender;
  final List<Cards3> cards;
  @JsonKey(name: 'by_channel')
  final List<ByChannel5> byChannel;
  @JsonKey(name: 'by_waiter')
  final List<ByWaiter3> byWaiter;
  final Discounts3 discounts;
  final Voids3 voids;
  @JsonKey(name: 'batch_writeoffs')
  final BatchWriteoffs3 batchWriteoffs;
  final Refunds6 refunds;
  final Deposits3 deposits;
  final Credit6 credit;
  final Cash4 cash;
  final List<Movements3> movements;
  @JsonKey(name: 'open_orders')
  final List<String>? openOrders;
  @JsonKey(name: 'pending_receipts')
  final PendingReceipts? pendingReceipts;

  Map<String, Object?> toJson() => _$Summary3ToJson(this);
}
