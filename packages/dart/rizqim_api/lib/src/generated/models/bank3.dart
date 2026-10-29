// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'bank3.g.dart';

@JsonSerializable()
class Bank3 {
  const Bank3({required this.rrn, required this.amount});

  factory Bank3.fromJson(Map<String, Object?> json) => _$Bank3FromJson(json);

  final String? rrn;
  final String amount;

  Map<String, Object?> toJson() => _$Bank3ToJson(this);
}
