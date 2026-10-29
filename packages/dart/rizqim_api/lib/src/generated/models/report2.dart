// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period5.dart';
import 'sales3.dart';
import 'by_tender2.dart';
import 'cards2.dart';
import 'by_channel4.dart';
import 'by_waiter2.dart';
import 'discounts2.dart';
import 'voids2.dart';
import 'batch_writeoffs2.dart';
import 'refunds5.dart';
import 'deposits2.dart';
import 'credit5.dart';
import 'cash3.dart';
import 'movements2.dart';

part 'report2.g.dart';

@JsonSerializable()
class Report2 {
  const Report2({
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
  });

  factory Report2.fromJson(Map<String, Object?> json) =>
      _$Report2FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String shiftUuid;
  @JsonKey(name: 'generated_at')
  final String generatedAt;
  final Period5 period;
  final Sales3 sales;
  @JsonKey(name: 'by_tender')
  final List<ByTender2> byTender;
  final List<Cards2> cards;
  @JsonKey(name: 'by_channel')
  final List<ByChannel4> byChannel;
  @JsonKey(name: 'by_waiter')
  final List<ByWaiter2> byWaiter;
  final Discounts2 discounts;
  final Voids2 voids;
  @JsonKey(name: 'batch_writeoffs')
  final BatchWriteoffs2 batchWriteoffs;
  final Refunds5 refunds;
  final Deposits2 deposits;
  final Credit5 credit;
  final Cash3 cash;
  final List<Movements2> movements;

  Map<String, Object?> toJson() => _$Report2ToJson(this);
}
