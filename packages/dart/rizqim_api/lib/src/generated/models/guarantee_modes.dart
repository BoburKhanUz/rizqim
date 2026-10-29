// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'guarantee_modes.g.dart';

@JsonSerializable()
class GuaranteeModes {
  const GuaranteeModes({required this.value, required this.label});

  factory GuaranteeModes.fromJson(Map<String, Object?> json) =>
      _$GuaranteeModesFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$GuaranteeModesToJson(this);
}
