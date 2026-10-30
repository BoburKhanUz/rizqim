// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method9.dart';
import 'by_staff6.dart';
import 'corrections13.dart';

part 'tips12.g.dart';

@JsonSerializable()
class Tips12 {
  const Tips12({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Tips12.fromJson(Map<String, Object?> json) => _$Tips12FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod9> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff6> byStaff;
  final Corrections13 corrections;

  Map<String, Object?> toJson() => _$Tips12ToJson(this);
}
