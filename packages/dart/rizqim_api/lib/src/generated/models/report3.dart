// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period8.dart';
import 'sales9.dart';
import 'by_tender6.dart';
import 'cards5.dart';
import 'qr_providers5.dart';
import 'by_channel7.dart';
import 'by_waiter5.dart';
import 'discounts5.dart';
import 'voids5.dart';
import 'batch_writeoffs5.dart';
import 'refunds9.dart';
import 'deposits5.dart';
import 'credit8.dart';
import 'tips15.dart';
import 'vouchers6.dart';
import 'banquets5.dart';
import 'cash6.dart';
import 'movements5.dart';

part 'report3.g.dart';

@JsonSerializable()
class Report3 {
  const Report3({
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
  });

  factory Report3.fromJson(Map<String, Object?> json) =>
      _$Report3FromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String shiftUuid;
  @JsonKey(name: 'generated_at')
  final String generatedAt;
  final Period8 period;
  final Sales9 sales;
  @JsonKey(name: 'by_tender')
  final List<ByTender6> byTender;
  final List<Cards5> cards;
  @JsonKey(name: 'qr_providers')
  final List<QrProviders5> qrProviders;
  @JsonKey(name: 'by_channel')
  final List<ByChannel7> byChannel;
  @JsonKey(name: 'by_waiter')
  final List<ByWaiter5> byWaiter;
  final Discounts5 discounts;
  final Voids5 voids;
  @JsonKey(name: 'batch_writeoffs')
  final BatchWriteoffs5 batchWriteoffs;
  final Refunds9 refunds;
  final Deposits5 deposits;
  final Credit8 credit;
  final Tips15 tips;
  final Vouchers6 vouchers;
  final Banquets5 banquets;
  final Cash6 cash;
  final List<Movements5> movements;

  Map<String, Object?> toJson() => _$Report3ToJson(this);
}
