// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'print3.g.dart';

@JsonSerializable()
class Print3 {
  const Print3({
    required this.store,
    required this.code,
    required this.qr,
    required this.value,
    required this.validFrom,
    required this.validUntil,
  });

  factory Print3.fromJson(Map<String, Object?> json) => _$Print3FromJson(json);

  final String? store;
  final String code;
  final String qr;
  final String value;
  @JsonKey(name: 'valid_from')
  final String validFrom;
  @JsonKey(name: 'valid_until')
  final String? validUntil;

  Map<String, Object?> toJson() => _$Print3ToJson(this);
}
