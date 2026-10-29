// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method7.dart';
import 'by_staff5.dart';
import 'corrections12.dart';

part 'tips11.g.dart';

@JsonSerializable()
class Tips11 {
  const Tips11({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Tips11.fromJson(Map<String, Object?> json) => _$Tips11FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod7> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff5> byStaff;
  final Corrections12 corrections;

  Map<String, Object?> toJson() => _$Tips11ToJson(this);
}
