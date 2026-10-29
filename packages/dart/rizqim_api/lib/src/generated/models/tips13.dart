// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method11.dart';
import 'by_staff7.dart';
import 'corrections14.dart';

part 'tips13.g.dart';

@JsonSerializable()
class Tips13 {
  const Tips13({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Tips13.fromJson(Map<String, Object?> json) => _$Tips13FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod11> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff7> byStaff;
  final Corrections14 corrections;

  Map<String, Object?> toJson() => _$Tips13ToJson(this);
}
