// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'distributions.dart';

part 'by_day.g.dart';

@JsonSerializable()
class ByDay {
  const ByDay({
    required this.businessDate,
    required this.amount,
    required this.personal,
    required this.pool,
    required this.cash,
    required this.card,
    required this.qr,
    required this.distributions,
    required this.undistributed,
  });

  factory ByDay.fromJson(Map<String, Object?> json) => _$ByDayFromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  final String amount;
  final String personal;
  final String pool;
  final String cash;
  final String card;
  final String qr;
  final List<Distributions> distributions;
  final String undistributed;

  Map<String, Object?> toJson() => _$ByDayToJson(this);
}
