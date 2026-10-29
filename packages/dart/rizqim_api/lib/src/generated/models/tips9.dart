// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method3.dart';
import 'by_staff3.dart';
import 'corrections10.dart';

part 'tips9.g.dart';

@JsonSerializable()
class Tips9 {
  const Tips9({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Tips9.fromJson(Map<String, Object?> json) => _$Tips9FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod3> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff3> byStaff;
  final Corrections10 corrections;

  Map<String, Object?> toJson() => _$Tips9ToJson(this);
}
