// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period4.dart';
import 'sales.dart';
import 'by_tender.dart';
import 'cards.dart';
import 'by_channel3.dart';
import 'by_waiter.dart';
import 'discounts.dart';
import 'voids.dart';
import 'batch_writeoffs.dart';
import 'refunds4.dart';
import 'deposits.dart';
import 'credit4.dart';
import 'tips9.dart';
import 'cash2.dart';
import 'movements.dart';

part 'report.g.dart';

@JsonSerializable()
class Report {
  const Report({
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
    required this.tips,
    required this.cash,
    required this.movements,
  });

  factory Report.fromJson(Map<String, Object?> json) => _$ReportFromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String shiftUuid;
  @JsonKey(name: 'generated_at')
  final String generatedAt;
  final Period4 period;
  final Sales sales;
  @JsonKey(name: 'by_tender')
  final List<ByTender> byTender;
  final List<Cards> cards;
  @JsonKey(name: 'by_channel')
  final List<ByChannel3> byChannel;
  @JsonKey(name: 'by_waiter')
  final List<ByWaiter> byWaiter;
  final Discounts discounts;
  final Voids voids;
  @JsonKey(name: 'batch_writeoffs')
  final BatchWriteoffs batchWriteoffs;
  final Refunds4 refunds;
  final Deposits deposits;
  final Credit4 credit;
  final Tips9 tips;
  final Cash2 cash;
  final List<Movements> movements;

  Map<String, Object?> toJson() => _$ReportToJson(this);
}
