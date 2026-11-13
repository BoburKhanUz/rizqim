// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'display2.g.dart';

@JsonSerializable()
class Display2 {
  const Display2({
    required this.payload,
    required this.label,
    required this.provider,
  });

  factory Display2.fromJson(Map<String, Object?> json) =>
      _$Display2FromJson(json);

  final String payload;
  final String label;
  final String provider;

  Map<String, Object?> toJson() => _$Display2ToJson(this);
}
