// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'corrections17.g.dart';

@JsonSerializable()
class Corrections17 {
  const Corrections17({required this.count, required this.amount});

  factory Corrections17.fromJson(Map<String, Object?> json) =>
      _$Corrections17FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Corrections17ToJson(this);
}
