// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'fiscal.g.dart';

@JsonSerializable()
class Fiscal {
  const Fiscal({
    required this.enabled,
    required this.activeDriver,
    required this.smartKassaDriver,
  });

  factory Fiscal.fromJson(Map<String, Object?> json) => _$FiscalFromJson(json);

  final bool enabled;
  @JsonKey(name: 'active_driver')
  final String activeDriver;
  @JsonKey(name: 'smart_kassa_driver')
  final String smartKassaDriver;

  Map<String, Object?> toJson() => _$FiscalToJson(this);
}
