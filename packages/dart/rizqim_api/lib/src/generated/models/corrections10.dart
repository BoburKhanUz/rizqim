// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'corrections10.g.dart';

@JsonSerializable()
class Corrections10 {
  const Corrections10({required this.count, required this.amount});

  factory Corrections10.fromJson(Map<String, Object?> json) =>
      _$Corrections10FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Corrections10ToJson(this);
}
