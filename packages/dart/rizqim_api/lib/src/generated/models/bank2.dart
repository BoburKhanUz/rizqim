// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'bank2.g.dart';

@JsonSerializable()
class Bank2 {
  const Bank2({required this.rrn, required this.amount});

  factory Bank2.fromJson(Map<String, Object?> json) => _$Bank2FromJson(json);

  final String? rrn;
  final String amount;

  Map<String, Object?> toJson() => _$Bank2ToJson(this);
}
