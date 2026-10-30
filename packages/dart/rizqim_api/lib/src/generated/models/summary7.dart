// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method12.dart';
import 'by_staff8.dart';
import 'corrections15.dart';

part 'summary7.g.dart';

@JsonSerializable()
class Summary7 {
  const Summary7({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Summary7.fromJson(Map<String, Object?> json) =>
      _$Summary7FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod12> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff8> byStaff;
  final Corrections15 corrections;

  Map<String, Object?> toJson() => _$Summary7ToJson(this);
}
