// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_method12.dart';
import 'by_staff8.dart';
import 'corrections17.dart';

part 'summary12.g.dart';

@JsonSerializable()
class Summary12 {
  const Summary12({
    required this.count,
    required this.amount,
    required this.byMethod,
    required this.personal,
    required this.pool,
    required this.unassigned,
    required this.byStaff,
    required this.corrections,
  });

  factory Summary12.fromJson(Map<String, Object?> json) =>
      _$Summary12FromJson(json);

  final int count;
  final String amount;
  @JsonKey(name: 'by_method')
  final List<ByMethod12> byMethod;
  final String personal;
  final String pool;
  final String unassigned;
  @JsonKey(name: 'by_staff')
  final List<ByStaff8> byStaff;
  final Corrections17 corrections;

  Map<String, Object?> toJson() => _$Summary12ToJson(this);
}
