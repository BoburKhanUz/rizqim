// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'qr2.g.dart';

@JsonSerializable()
class Qr2 {
  const Qr2({
    required this.payload,
    required this.label,
    required this.provider,
  });

  factory Qr2.fromJson(Map<String, Object?> json) => _$Qr2FromJson(json);

  final String payload;
  final String? label;
  final String? provider;

  Map<String, Object?> toJson() => _$Qr2ToJson(this);
}
