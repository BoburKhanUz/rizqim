// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method.dart';
import 'by_staff.dart';
import 'corrections.dart';

part 'totals.g.dart';

@JsonSerializable()
class Totals {
  const Totals({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Totals.fromJson(Map<String, Object?> json) => _$TotalsFromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff> byStaff;
  final Corrections corrections;

  Map<String, Object?> toJson() => _$TotalsToJson(this);
}
