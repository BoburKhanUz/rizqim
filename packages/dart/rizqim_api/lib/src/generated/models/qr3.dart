// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'qr3.g.dart';

@JsonSerializable()
class Qr3 {
  const Qr3({
    required this.payload,
    required this.label,
    required this.provider,
  });

  factory Qr3.fromJson(Map<String, Object?> json) => _$Qr3FromJson(json);

  final String payload;
  final String? label;
  final String? provider;

  Map<String, Object?> toJson() => _$Qr3ToJson(this);
}
