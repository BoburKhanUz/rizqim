// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'qr.g.dart';

@JsonSerializable()
class Qr {
  const Qr({this.payload, this.label, this.provider});

  factory Qr.fromJson(Map<String, Object?> json) => _$QrFromJson(json);

  final String? payload;
  final String? label;
  final String? provider;

  Map<String, Object?> toJson() => _$QrToJson(this);
}
