// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'display4.g.dart';

@JsonSerializable()
class Display4 {
  const Display4({
    required this.payload,
    required this.label,
    required this.provider,
  });

  factory Display4.fromJson(Map<String, Object?> json) =>
      _$Display4FromJson(json);

  final String payload;
  final String label;
  final String provider;

  Map<String, Object?> toJson() => _$Display4ToJson(this);
}
