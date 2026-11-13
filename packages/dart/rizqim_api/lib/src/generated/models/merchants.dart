// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'merchants.g.dart';

@JsonSerializable()
class Merchants {
  const Merchants({
    required this.provider,
    required this.label,
    required this.configured,
    required this.driver,
  });

  factory Merchants.fromJson(Map<String, Object?> json) =>
      _$MerchantsFromJson(json);

  final String provider;
  final String label;
  final bool configured;
  final String driver;

  Map<String, Object?> toJson() => _$MerchantsToJson(this);
}
