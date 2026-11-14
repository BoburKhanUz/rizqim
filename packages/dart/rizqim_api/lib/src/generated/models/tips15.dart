// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method11.dart';
import 'by_staff7.dart';
import 'corrections16.dart';

part 'tips15.g.dart';

@JsonSerializable()
class Tips15 {
  const Tips15({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Tips15.fromJson(Map<String, Object?> json) => _$Tips15FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod11> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff7> byStaff;
  final Corrections16 corrections;

  Map<String, Object?> toJson() => _$Tips15ToJson(this);
}
