// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'display5.g.dart';

@JsonSerializable()
class Display5 {
  const Display5({
    required this.payload,
    required this.label,
    required this.provider,
  });

  factory Display5.fromJson(Map<String, Object?> json) =>
      _$Display5FromJson(json);

  final String payload;
  final String label;
  final String provider;

  Map<String, Object?> toJson() => _$Display5ToJson(this);
}
