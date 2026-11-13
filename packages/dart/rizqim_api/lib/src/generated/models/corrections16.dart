// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'corrections16.g.dart';

@JsonSerializable()
class Corrections16 {
  const Corrections16({required this.count, required this.amount});

  factory Corrections16.fromJson(Map<String, Object?> json) =>
      _$Corrections16FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Corrections16ToJson(this);
}
