// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sales4.g.dart';

@JsonSerializable()
class Sales4 {
  const Sales4({required this.count, required this.amount});

  factory Sales4.fromJson(Map<String, Object?> json) => _$Sales4FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Sales4ToJson(this);
}
