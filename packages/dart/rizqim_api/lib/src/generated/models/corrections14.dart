// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'corrections14.g.dart';

@JsonSerializable()
class Corrections14 {
  const Corrections14({required this.count, required this.amount});

  factory Corrections14.fromJson(Map<String, Object?> json) =>
      _$Corrections14FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Corrections14ToJson(this);
}
