// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sales2.g.dart';

@JsonSerializable()
class Sales2 {
  const Sales2({required this.count, required this.amount});

  factory Sales2.fromJson(Map<String, Object?> json) => _$Sales2FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Sales2ToJson(this);
}
