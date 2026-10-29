// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'bank4.g.dart';

@JsonSerializable()
class Bank4 {
  const Bank4({required this.rrn, required this.amount});

  factory Bank4.fromJson(Map<String, Object?> json) => _$Bank4FromJson(json);

  final String? rrn;
  final String amount;

  Map<String, Object?> toJson() => _$Bank4ToJson(this);
}
