// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period7.dart';
import 'sales7.dart';
import 'by_tender4.dart';
import 'cards4.dart';
import 'by_channel6.dart';
import 'by_waiter4.dart';
import 'discounts4.dart';
import 'voids4.dart';
import 'refunds7.dart';
import 'deposits4.dart';
import 'credit7.dart';
import 'cash5.dart';
import 'movements4.dart';
import 'pending_receipts2.dart';

part 'summary4.g.dart';

@JsonSerializable()
class Summary4 {
  const Summary4({
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
    required this.refunds,
    required this.deposits,
    required this.credit,
    required this.cash,
    required this.movements,
    this.openOrders,
    this.pendingReceipts,
  });

  factory Summary4.fromJson(Map<String, Object?> json) =>
      _$Summary4FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String shiftUuid;
  @JsonKey(name: 'generated_at')
  final String generatedAt;
  final Period7 period;
  final Sales7 sales;
  @JsonKey(name: 'by_tender')
  final List<ByTender4> byTender;
  final List<Cards4> cards;
  @JsonKey(name: 'by_channel')
  final List<ByChannel6> byChannel;
  @JsonKey(name: 'by_waiter')
  final List<ByWaiter4> byWaiter;
  final Discounts4 discounts;
  final Voids4 voids;
  final Refunds7 refunds;
  final Deposits4 deposits;
  final Credit7 credit;
  final Cash5 cash;
  final List<Movements4> movements;
  @JsonKey(name: 'open_orders')
  final List<String>? openOrders;
  @JsonKey(name: 'pending_receipts')
  final PendingReceipts2? pendingReceipts;

  Map<String, Object?> toJson() => _$Summary4ToJson(this);
}
