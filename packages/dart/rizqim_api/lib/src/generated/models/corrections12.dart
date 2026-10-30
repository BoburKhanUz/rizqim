// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'corrections12.g.dart';

@JsonSerializable()
class Corrections12 {
  const Corrections12({required this.count, required this.amount});

  factory Corrections12.fromJson(Map<String, Object?> json) =>
      _$Corrections12FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Corrections12ToJson(this);
}
