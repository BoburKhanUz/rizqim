// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method5.dart';
import 'by_staff4.dart';
import 'corrections11.dart';

part 'tips10.g.dart';

@JsonSerializable()
class Tips10 {
  const Tips10({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Tips10.fromJson(Map<String, Object?> json) => _$Tips10FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod5> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff4> byStaff;
  final Corrections11 corrections;

  Map<String, Object?> toJson() => _$Tips10ToJson(this);
}
